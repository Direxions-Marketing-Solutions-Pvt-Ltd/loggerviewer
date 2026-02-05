<?php
/**
 * Installer script for Logger View
 * Run this script to initialize the database and basic configurations.
 */

require_once __DIR__ . '/src/ConfigManager.php';

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

echo "=== Logger View Installation ===\n\n";

// 1. Ask for AUTH_SECRET
$authSecret = '';
while (true) {
    echo "Enter a 32-character AUTH_SECRET (for encryption & peppering): ";
    $authSecret = trim(fgets(STDIN));
    if (strlen($authSecret) === 32) {
        break;
    }
    echo "Error: Secret must be exactly 32 characters long. Current length: " . strlen($authSecret) . "\n";
}

// 2. Setup .env
echo "Initializing .env configuration...\n";
$envData = [
    'AUTH_SECRET' => $authSecret,
    'DB_PATH' => 'data/database.sqlite'
];

if (!\App\ConfigManager::updateEnv($envData)) {
    die("Error: Could not create or write to .env file.\n");
}

// 3. Reload config to get the new DB_PATH
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Database.php';

$dbPath = DB_PATH; // This is the absolute path defined in config.php
$dbDir = dirname($dbPath);

if (!is_dir($dbDir)) {
    echo "Creating database directory: $dbDir...\n";
    if (!mkdir($dbDir, 0755, true)) {
        die("Error: Could not create directory $dbDir\n");
    }
}

// 4. Initialize SQLite Database
echo "Initializing database at $dbPath...\n";
try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sqlFile = __DIR__ . '/schema.sql';
    if (!file_exists($sqlFile)) {
        die("Error: schema.sql not found at $sqlFile\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // We need to update the default admin password hash in the SQL based on the NEW secret
    // because schema.sql has a hardcoded hash for the default secret.
    $pepperedAdmin = password_hash(hash_hmac('sha256', 'admin123', $authSecret), PASSWORD_DEFAULT);
    
    // Simple replacement of the hardcoded hash in the SQL content
    // We look for the VALUES ('admin', '...') part
    $sql = preg_replace(
        "/VALUES \('admin', '.*?',/",
        "VALUES ('admin', '$pepperedAdmin',",
        $sql
    );

    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
    
    echo "\nSUCCESS: Logger View installed successfully!\n";
    echo "Default Admin: admin / admin123\n";
    echo "Security: AUTH_SECRET saved and passwords peppered.\n\n";
    echo "IMPORTANT: Please delete install.php after verification.\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
