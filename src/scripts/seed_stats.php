<?php
/**
 * Seeding script to generate historical statistics for testing visualizations.
 * Usage: php src/scripts/seed_stats.php
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../autoload.php';

date_default_timezone_set('UTC');

use App\Database;
use App\Project;

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

echo "--- Log Statistics Seeder ---\n";

$db = new Database(DB_PATH);
$projectManager = new Project($db);
$projects = $projectManager->getAll();

if (empty($projects)) {
    die("No projects found to seed.\n");
}

$sampleErrors = [
    "PHP Fatal error:  Uncaught Error: Call to undefined function json_decode()",
    "Nginx Error: 404 Not Found - /favicon.ico",
    "PHP Warning:  Division by zero in /var/www/html/math.php on line 42",
    "FastCGI sent in stderr: \"Primary script unknown\" while reading response header from upstream",
    "PHP Notice:  Undefined variable: userId in /var/www/html/user.php on line 12"
];

foreach ($projects as $project) {
    echo "Seeding stats for project: {$project->name}...\n";
    
    for ($i = 24; $i >= 0; $i--) {
        $timestamp = date('Y-m-d H:00:00', strtotime("-$i hours"));
        
        // Generate random but realistic counts
        $errorsCount = rand(5, 50);
        $warnsCount = rand(10, 100);
        $infosCount = rand(100, 1000);
        
        // Generate random top errors
        $topErrors = [];
        for ($j = 0; $j < 3; $j++) {
            $msg = $sampleErrors[array_rand($sampleErrors)];
            $topErrors[$msg] = rand(1, 15);
        }
        arsort($topErrors);

        try {
            $db->insert('stats', [
                'project_id' => $project->id,
                'timestamp' => $timestamp,
                'error_count' => $errorsCount,
                'warn_count' => $warnsCount,
                'info_count' => $infosCount,
                'top_errors' => json_encode($topErrors)
            ]);
            echo " - Seeded {$timestamp}\n";
        } catch (\Throwable $e) {
            // Already exists, skip or update
             $db->update('stats', [
                'error_count' => $errorsCount,
                'warn_count' => $warnsCount,
                'info_count' => $infosCount,
                'top_errors' => json_encode($topErrors)
            ], [
                'project_id' => $project->id,
                'timestamp' => $timestamp
            ]);
            echo " - Updated {$timestamp} (existing)\n";
        }
    }
}

echo "Seeding completed.\n";
