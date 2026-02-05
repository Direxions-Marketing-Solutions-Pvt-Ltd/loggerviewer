<?php
namespace App;

class ConfigManager {
    /**
     * Update the .env file with new values.
     */
    public static function updateEnv(array $data) {
        $envPath = dirname(__DIR__) . '/.env';
        
        if (!file_exists($envPath)) {
            $examplePath = dirname(__DIR__) . '/.env.example';
            if (file_exists($examplePath)) {
                copy($examplePath, $envPath);
            } else {
                touch($envPath);
            }
        }

        if (!is_writable($envPath)) {
            return false;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES);
        $newLines = [];
        $processedKeys = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed) || strpos($trimmed, '#') === 0) {
                $newLines[] = $line;
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                $newLines[] = $line;
                continue;
            }

            $key = trim($parts[0]);
            if (array_key_exists($key, $data)) {
                $value = (string)$data[$key];
                // Quote if necessary
                if (strpos($value, ' ') !== false || strpos($value, '#') !== false || strpos($value, '"') !== false) {
                    $value = '"' . str_replace('"', '\"', $value) . '"';
                }
                $newLines[] = "$key=$value";
                $processedKeys[] = $key;
            } else {
                $newLines[] = $line;
            }
        }

        // Add new keys that weren't in the original file
        foreach ($data as $key => $value) {
            if (!in_array($key, $processedKeys)) {
                $value = (string)$value;
                if (strpos($value, ' ') !== false || strpos($value, '#') !== false || strpos($value, '"') !== false || empty($value)) {
                    if (empty($value)) {
                        $newLines[] = "$key=";
                    } else {
                        $value = '"' . str_replace('"', '\"', $value) . '"';
                        $newLines[] = "$key=$value";
                    }
                } else {
                    $newLines[] = "$key=$value";
                }
            }
        }

        return file_put_contents($envPath, implode("\n", $newLines) . "\n") !== false;
    }
}
