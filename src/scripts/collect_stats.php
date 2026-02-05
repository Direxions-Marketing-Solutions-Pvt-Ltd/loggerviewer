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

$currentHour = date('Y-m-d H:00:00');

foreach ($projects as $project) {
    echo "Processing project: {$project->name}...\n";
    
    $stats = [
        'error' => 0,
        'warn' => 0,
        'info' => 0
    ];

    // Check Webserver Logs
    $errorMessages = [];
    if (!empty($project->webserver_path)) {
        $reader = new LogReader($project->webserver_path, $project->webserver_format);
        foreach ($reader->getLogFiles() as $file) {
            // Include .log and .txt files, or any text file
            if ($file['type'] === 'text') {
                $stats['error'] += $reader->getTotalLines($file['name'], 'error');
                $stats['warn']  += $reader->getTotalLines($file['name'], 'warn');
                $stats['info']  += $reader->getTotalLines($file['name'], 'info');
                
                // Sample errors for frequency analysis
                $errors = $reader->readLog($file['name'], 0, 500, 'error');
                foreach ($errors['lines'] as $line) {
                    $msg = $line['parsed']['columns']['message'] ?? $line['content'];
                    // Strip variable parts like IDs or timestamps if possible, but keep it simple for now
                    $msg = substr($msg, 0, 150); 
                    $errorMessages[$msg] = ($errorMessages[$msg] ?? 0) + 1;
                }
            }
        }
    }

    // Check PHP Logs
    if (!empty($project->php_path)) {
        $reader = new LogReader($project->php_path, $project->php_format);
        foreach ($reader->getLogFiles() as $file) {
            if ($file['type'] === 'text') {
                $stats['error'] += $reader->getTotalLines($file['name'], 'error');
                $stats['warn']  += $reader->getTotalLines($file['name'], 'warn');
                $stats['info']  += $reader->getTotalLines($file['name'], 'info');

                $errors = $reader->readLog($file['name'], 0, 500, 'error');
                foreach ($errors['lines'] as $line) {
                    $msg = $line['parsed']['columns']['message'] ?? $line['content'];
                    $msg = substr($msg, 0, 150);
                    $errorMessages[$msg] = ($errorMessages[$msg] ?? 0) + 1;
                }
            }
        }
    }

    // Sort and pick top 5
    arsort($errorMessages);
    $topErrors = array_slice($errorMessages, 0, 5, true);
    $topErrorsJson = json_encode($topErrors);

    // Save to DB
    try {
        $db->insert('stats', [
            'project_id' => $project->id,
            'timestamp' => $currentHour,
            'error_count' => $stats['error'],
            'warn_count' => $stats['warn'],
            'info_count' => $stats['info'],
            'top_errors' => $topErrorsJson
        ]);
        echo " - Stats saved for {$currentHour}\n";
    } catch (\Throwable $e) {
        $db->update('stats', [
            'error_count' => $stats['error'],
            'warn_count' => $stats['warn'],
            'info_count' => $stats['info'],
            'top_errors' => $topErrorsJson
        ], [
            'project_id' => $project->id,
            'timestamp' => $currentHour
        ]);
        echo " - Stats updated for {$currentHour}\n";
    }
}

echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
