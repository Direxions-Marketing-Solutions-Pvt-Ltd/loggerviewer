<?php
/**
 * Cron script to collect log statistics for visual analytics.
 * Recommended schedule: Run every hour via crontab.
 * Usage: php src/scripts/collect_stats.php
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../autoload.php';

date_default_timezone_set('UTC');

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
            if ($file['type'] === 'text') {
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
            }
        }
    }

    // Check PHP Logs
    if (!empty($project->php_path)) {
        $reader = new LogReader($project->php_path, $project->php_format);
        foreach ($reader->getLogFiles() as $file) {
            if ($file['type'] === 'text') {
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
            }
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

echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
