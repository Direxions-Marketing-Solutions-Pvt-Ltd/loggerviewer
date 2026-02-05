<?php
/**
 * CLI Tool to rotate AUTH_SECRET and re-encrypt sensitive credentials.
 * Usage: php src/scripts/update_secret.php
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../autoload.php';

use App\Encryption;
use App\ConfigManager;

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

echo "--- AUTH_SECRET Rotation Tool ---\n";
echo "This tool will update your AUTH_SECRET and re-encrypt SMTP_PASS and AI_API_KEY.\n";
echo "Existing passwords will be INVALIDATED.\n\n";

// 1. Get new secret
$newSecret = '';
while (true) {
    echo "Enter new 32-character AUTH_SECRET: ";
    $newSecret = trim(fgets(STDIN));
    if (strlen($newSecret) === 32) {
        break;
    }
    echo "Error: Secret must be exactly 32 characters long. Current length: " . strlen($newSecret) . "\n";
}

// 2. Decrypt existing values using CURRENT secret (from config.php)
$oldSmtpPass = Encryption::decrypt(SMTP_PASS);
$oldAiApiKey = Encryption::decrypt(AI_API_KEY);

echo "Decrypted current credentials using old secret...\n";

// 3. Define helper for re-encryption with new secret
function encryptWithNewSecret($data, $secret) {
    $method = 'aes-256-cbc';
    if (empty($data)) return '';
    $key = hash('sha256', $secret);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
    $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

// 4. Get new admin password
$newAdminPass = '';
while (true) {
    echo "Enter NEW password for 'admin' user: ";
    $newAdminPass = trim(fgets(STDIN));
    if (strlen($newAdminPass) >= 6) {
        break;
    }
    echo "Error: Password must be at least 6 characters.\n";
}

// 5. Update .env
$newSmtpPassEnc = encryptWithNewSecret($oldSmtpPass, $newSecret);
$newAiApiKeyEnc = encryptWithNewSecret($oldAiApiKey, $newSecret);

$updates = [
    'AUTH_SECRET' => $newSecret,
    'SMTP_PASS' => $newSmtpPassEnc,
    'AI_API_KEY' => $newAiApiKeyEnc
];

if (ConfigManager::updateEnv($updates)) {
    echo "SUCCESS: .env updated with new secret and re-encrypted credentials.\n";
    
    // 6. Update Admin Password in DB
    $db = new \App\Database(DB_PATH);
    $newHash = password_hash(hash_hmac('sha256', $newAdminPass, $newSecret), PASSWORD_DEFAULT);
    
    if ($db->update('users', ['password' => $newHash], ['username' => 'admin'])) {
        echo "SUCCESS: 'admin' user password updated with new pepper.\n";
    } else {
        echo "WARNING: Failed to update 'admin' password in database.\n";
    }
    
    echo "\nNOTE: All other user passwords are now INVALID and must be reset manually.\n";
} else {
    echo "ERROR: Failed to update .env file. Check permissions.\n";
    exit(1);
}
