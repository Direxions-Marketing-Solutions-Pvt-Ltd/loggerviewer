<?php
/**
 * Simple .env loader
 */
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) continue;
        $name = trim($parts[0]);
        $value = trim($parts[1]);
        
        // Strip quotes if present
        if (preg_match('/^"(.*)"$/s', $value, $matches) || preg_match("/^'(.*)'$/s", $value, $matches)) {
            $value = str_replace(['\"', "\'"], ['"', "'"], $matches[1]);
        }

        putenv(sprintf('%s=%s', $name, $value));
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) return $default;
    return $value;
}

loadEnv(__DIR__ . '/.env');

define('DB_PATH', __DIR__ . '/' . env('DB_PATH', 'data/database.sqlite'));
define('REDIS_HOST', env('REDIS_HOST', '127.0.0.1'));
define('REDIS_PORT', (int)env('REDIS_PORT', 6379));

// User Authentication Secret
define('AUTH_SECRET', env('AUTH_SECRET', 'replace_with_a_secure_token'));

// Application Title
define('APP_TITLE', env('APP_TITLE', 'Logger View'));

// SMTP Configuration
define('SMTP_HOST', env('SMTP_HOST'));
define('SMTP_PORT', (int)env('SMTP_PORT', 587));
define('SMTP_USER', env('SMTP_USER'));
define('SMTP_PASS', env('SMTP_PASS'));
define('SMTP_ENCRYPTION', env('SMTP_ENCRYPTION', 'tls')); // tls, starttls, or none
define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', 'no-reply@loggerview.local'));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'Logger View'));
define('DEFAULT_AUTH_TYPE', env('DEFAULT_AUTH_TYPE', 'otp'));

// AI Configuration
define('AI_ENABLED', env('AI_ENABLED', 'false') === 'true');
define('AI_API_URL', env('AI_API_URL', 'https://api.openai.com/v1/chat/completions'));
define('AI_API_KEY', env('AI_API_KEY', ''));
define('AI_MODEL', env('AI_MODEL', 'gpt-4-turbo'));
