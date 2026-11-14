<?php
/**
 * Cron script to collect log statistics for visual analytics.
 * Recommended schedule: Run every hour via crontab.
 * Usage: php src/scripts/collect_stats.php
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../autoload.php';

date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'UTC');

use App\Database;
use App\Project;
use App\LogReader;

if (php_sapi_name() !== 'cli' && !isset($_GET['force'])) {
    die("This script must be run from the command line.\n");
}

echo "--- Log Statistics Collector ---\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";

$db = new Database(DB_PATH);
$projectManager = new Project($db);
$projects = $projectManager->getAll();

// Load previous collection state to skip unchanged log files
$stateFile = dirname(DB_PATH) . DIRECTORY_SEPARATOR . '.collect_state.json';
$state = [];
if (file_exists($stateFile)) {
    $decoded = json_decode((string)file_get_contents($stateFile), true);
    if (is_array($decoded)) {
        $state = $decoded;
    }
}
$newState = $state;

// We collect for the PREVIOUS hour, as that hour is now "complete"
$targetHourTs = strtotime(date('Y-m-d H:00:00')) - 3600;
$currentHour = date('Y-m-d H:00:00', $targetHourTs);

echo "Collecting stats for: {$currentHour}\n";

foreach ($projects as $project) {
    echo "Processing project: {$project->name} (ID: {$project->id})...\n";
    
    $stats = [
        'error' => 0,
        'warn' => 0,
        'info' => 0
    ];
    $errorMessages = [];

    // Check Webserver Logs
    if (!empty($project->webserver_path)) {
        $reader = new LogReader($project->webserver_path, $project->webserver_format);
        foreach ($reader->getLogFiles() as $file) {
            $fkey = $file['name'] . '@' . ($file['size'] ?? 0) . ':' . ($file['mtime'] ?? 0);
            if (isset($state[$fkey]) && $state[$fkey] === 'done') {
                echo " - Skipping unchanged webserver log: {$file['name']}\n";
                continue;
            }
            echo " - Scanning webserver log: {$file['name']}...";
                $hourly = $reader->getHourlyStats($file['name'], $targetHourTs);
                $stats['error'] += $hourly['error'];
                $stats['warn']  += $hourly['warn'];
                $stats['info']  += $hourly['info'];
                
                foreach ($hourly['samples'] as $msg) {
                    $msg = substr($msg, 0, 150);
                    $errorMessages[$msg] = ($errorMessages[$msg] ?? 0) + 1;
                }
                echo " Done (E:{$hourly['error']}, W:{$hourly['warn']}, I:{$hourly['info']})\n";
                $newState[$fkey] = 'done';
        }
    }

    // Check PHP Logs
    if (!empty($project->php_path)) {
        $reader = new LogReader($project->php_path, $project->php_format);
        foreach ($reader->getLogFiles() as $file) {
            $fkey = $file['name'] . '@' . ($file['size'] ?? 0) . ':' . ($file['mtime'] ?? 0);
            if (isset($state[$fkey]) && $state[$fkey] === 'done') {
                echo " - Skipping unchanged PHP log: {$file['name']}\n";
                continue;
            }
            echo " - Scanning PHP log: {$file['name']}...";
                $hourly = $reader->getHourlyStats($file['name'], $targetHourTs);
                $stats['error'] += $hourly['error'];
                $stats['warn']  += $hourly['warn'];
                $stats['info']  += $hourly['info'];

                foreach ($hourly['samples'] as $msg) {
                    $msg = substr($msg, 0, 150);
                    $errorMessages[$msg] = ($errorMessages[$msg] ?? 0) + 1;
                }
                echo " Done (E:{$hourly['error']}, W:{$hourly['warn']}, I:{$hourly['info']})\n";
                $newState[$fkey] = 'done';
        }
    }

    // Sort and pick top 5
    arsort($errorMessages);
    $topErrors = array_slice($errorMessages, 0, 5, true);
    $topErrorsJson = json_encode($topErrors);

    // Save to DB
    try {
        $inserted = $db->insert('stats', [
            'project_id' => $project->id,
            'timestamp' => $currentHour,
            'error_count' => $stats['error'],
            'warn_count' => $stats['warn'],
            'info_count' => $stats['info'],
            'top_errors' => $topErrorsJson
        ], true); // Use REPLACE mode for upsert

        if ($inserted !== false) {
            echo " - Stats saved for {$currentHour}\n";
        } else {
            echo " - FAILED to save stats for {$currentHour}. Error: " . $db->last_error . "\n";
        }
    } catch (\Throwable $e) {
        echo " - Critical Error during DB operation: " . $e->getMessage() . "\n";
    }
}

// Persist collection state
@file_put_contents($stateFile, json_encode($newState));

echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
