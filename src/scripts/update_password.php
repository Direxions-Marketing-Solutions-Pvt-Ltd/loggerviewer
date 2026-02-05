<?php
/**
 * CLI Tool to update user password with correct peppering.
 * Usage: php src/scripts/update_password.php <username> <new_password>
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../autoload.php';

use App\Database;
use App\User;

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

if ($argc < 3) {
    echo "Usage: php " . $argv[0] . " <username> <new_password>\n";
    exit(1);
}

$username = $argv[1];
$newPassword = $argv[2];

$db = new Database(DB_PATH);
$userManager = new User($db);

$user = $db->get_row("SELECT id FROM users WHERE username = ?", [$username]);

if (!$user) {
    echo "Error: User '$username' not found.\n";
    exit(1);
}

if ($userManager->update((int)$user->id, ['password' => $newPassword])) {
    echo "Success: Password updated for user '$username'.\n";
} else {
    echo "Error: Failed to update password.\n";
    exit(1);
}
