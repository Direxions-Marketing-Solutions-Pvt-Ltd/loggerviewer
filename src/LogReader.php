<?php

declare(strict_types=1);

namespace App;

class LogReader
{
    private string $directory;
    private string $filePattern = '*.{log,txt,gz}';
    private ?string $format;

    /**
     * Log Formats Configuration
     * Inspired by PimpMyLog, mapping regex to column names and types.
     */
    private array $formats = [
        'nginx_access' => [
            'name' => 'Nginx Access (NCSA)',
            'regex' => '/^(\S+)\s+-\s+\S+\s+\[(.*?)\]\s+"(\S+)\s+(.*?)\s+(\S+)"\s+(\d{3})\s+(\d+)(?:\s+"([^"]*)"\s+"([^"]*)")?/',
            'match' => [
                'ip' => 1,
                'date' => 2,
                'method' => 3,
                'request' => 4,
                'protocol' => 5,
                'status' => 6,
                'size' => 7,
                'referer' => 8,
                'ua' => 9
            ],
            'types' => [
                'status' => 'badge:http',
                'size' => 'size',
                'date' => 'date'
            ]
        ],
        'nginx_error' => [
            'name' => 'Nginx Error',
            'regex' => '/^(\d{4}\/\d{2}\/\d{2}\s+\d{2}:\d{2}:\d{2})\s+\[(.*?)\]\s+\d+#\d+:\s+(?:\*\d+\s+)?(.*?)(?:,\s+client:.*)?$/',
            'match' => [
                'date' => 1,
                'level' => 2,
                'message' => 3
            ],
            'types' => [
                'level' => 'badge:severity',
                'date' => 'date'
            ]
        ],
        'nginx_error_basic' => [
            'name' => 'Nginx Error (Basic)',
            'regex' => '/^(\d{4}\/\d{2}\/\d{2}\s+\d{2}:\d{2}:\d{2})\s+\[(.*?)\]\s+(.*)$/',
            'match' => [
                'date' => 1,
                'level' => 2,
                'message' => 3
            ],
            'types' => [
                'level' => 'badge:severity',
                'date' => 'date'
            ]
        ],
        'php_error' => [
            'name' => 'PHP Error Log',
            'regex' => '/^\[(.*?)\] PHP (.*?): (.*)$/',
            'match' => [
                'date' => 1,
                'level' => 2,
                'message' => 3
            ],
            'types' => [
                'level' => 'badge:severity',
                'date' => 'date'
            ],
            'multiline' => 'message'
        ],
        'generic_bracket' => [
            'name' => 'Generic (Bracket Date)',
            'regex' => '/^\[(.*?)\] (.*)$/',
            'match' => [
                'date' => 1,
                'message' => 2
            ],
            'types' => [
                'date' => 'date'
            ]
        ]
    ];

    public function __construct(string $path, ?string $format = null)
    {
        if (strpos($path, '*') !== false || strpos($path, '?') !== false || strpos($path, '{') !== false) {
            $this->directory = dirname($path);
            $this->filePattern = basename($path);
        } else {
            $this->directory = rtrim($path, '/');
        }

        if ($format) {
            $parsed = json_decode($format, true);
            if ($parsed && isset($parsed['regex'])) {
                $this->formats['custom'] = $parsed;
            } else if (!empty($format) && $format[0] === '/') {
                // If it's just a raw regex
                $this->formats['custom'] = [
                    'name' => 'Custom Format',
                    'regex' => $format,
                    'match' => ['content' => 0] // Default mapping if none provided
                ];
            }
        }
    }

    public function getLogFiles(): array
    {
        $searchPath = $this->directory . DIRECTORY_SEPARATOR . $this->filePattern;
        $files = glob($searchPath, GLOB_BRACE);
        
        if (!$files) {
            // Fallback to basic *.log if no files found and pattern was custom
            if ($this->filePattern !== '*.{log,txt,gz}') {
                 return [];
            }
            $files = glob($this->directory . '/*.log');
        }
        
        if (!$files) return [];

        $result = [];
        foreach ($files as $file) {
            if (!is_file($file) || !is_readable($file)) {
                continue;
            }

            $result[] = [
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'mtime' => filemtime($file),
                'type' => str_ends_with($file, '.gz') ? 'compressed' : 'text'
            ];
        }

        // Sort by modification time descending
        usort($result, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

        return $result;
    }

    public function readLog(string $filename, int $offset = 0, int $limit = 100, string $filter = 'all', string $query = ''): array
    {
        $filePath = $this->directory . '/' . basename($filename);
        if (!file_exists($filePath)) {
            return ['error' => 'File not found'];
        }

        $isGz = str_ends_with($filePath, '.gz');
        if ($isGz) {
            // Fallback to legacy behavior for compressed files for now
            return $this->readLogLegacy($filePath, $offset, $limit, $filter, $query);
        }

        return $this->readLogEnhanced($filePath, $offset, $limit, $filter, $query);
    }

    /**
     * Optimized log reading using buffered backward reading
     */
    private function readLogEnhanced(string $filePath, int $offset, int $limit, string $filter, string $query = ''): array
    {
        $start = microtime(true);
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return ['error' => 'Could not open file'];
        }

        $fileSize = filesize($filePath);
        $lines = [];
        $count = 0;
        $matchedFormat = null;
        $headers = [];
        $buffer = [];
        $fingerprint = '';

        // Determine starting position
        fseek($handle, 0, SEEK_END);
        $totalBytes = ftell($handle);
        $pos = $totalBytes - $offset;

        if ($offset === 0) {
            $lastLines = $this->getLinesFromBottom($filePath, 5);
            $fingerprint = sha1(implode("\n", $lastLines));
        }

        $chunkSize = 8192;
        $lineRemainder = '';

        while ($count < $limit && $pos > 0) {
            $readSize = min($pos, $chunkSize);
            $pos -= $readSize;
            fseek($handle, $pos);
            $chunk = fread($handle, $readSize) . $lineRemainder;
            
            $chunkLines = explode("\n", $chunk);
            $lineRemainder = array_shift($chunkLines); // The "first" line of chunk might be incomplete

            // Process lines in reverse order (bottom to top)
            for ($i = count($chunkLines) - 1; $i >= 0; $i--) {
                $line = $chunkLines[$i];
                if ($line === '') continue;

                // Search Query Filter
                if (!empty($query) && stripos($line, $query) === false) {
                    continue;
                }

                $parsed = $this->parseLineEnhanced($line, $matchedFormat);
                
                if ($parsed) {
                    // Populate headers on first match
                    if (empty($headers)) {
                        $matchedFormat = $parsed['format_key'];
                        foreach ($this->formats[$matchedFormat]['match'] as $col => $idx) {
                            $headers[$col] = ucfirst($col);
                        }
                    }

                    $level = $this->detectLevel($line, $parsed['fields']);
                    if ($filter !== 'all' && $level !== $filter) {
                        continue;
                    }

                    $entry = [
                        'content' => $line,
                        'level' => $level,
                        'parsed' => [
                            'type' => $matchedFormat,
                            'columns' => $parsed['fields']
                        ]
                    ];

                    // Handle Multiline Buffer
                    if (!empty($buffer)) {
                        $multilineKey = $this->formats[$matchedFormat]['multiline'] ?? null;
                        if ($multilineKey && isset($entry['parsed']['columns'][$multilineKey])) {
                            $entry['parsed']['columns'][$multilineKey] .= "\n" . implode("\n", array_reverse($buffer));
                            $entry['content'] .= "\n" . implode("\n", array_reverse($buffer));
                        }
                        $buffer = [];
                    }

                    $lines[] = $entry;
                    $count++;
                    if ($count >= $limit) break;
                } else {
                    $buffer[] = $line;
                }
            }
        }

        // Handle the very last remainder if we didn't hit the limit
        if ($count < $limit && $lineRemainder !== '') {
            $line = $lineRemainder;
            $parsed = $this->parseLineEnhanced($line, $matchedFormat);
            if ($parsed) {
                if (empty($headers)) {
                    $matchedFormat = $parsed['format_key'];
                    foreach ($this->formats[$matchedFormat]['match'] as $col => $idx) {
                        $headers[$col] = ucfirst($col);
                    }
                }
                $level = $this->detectLevel($line, $parsed['fields']);
                if ($filter === 'all' || $level === $filter) {
                    $lines[] = [
                        'content' => $line,
                        'level' => $level,
                        'parsed' => ['type' => $matchedFormat, 'columns' => $parsed['fields']]
                    ];
                }
            }
        }

        fclose($handle);

        return [
            'lines' => $lines,
            'headers' => !empty($headers) ? $headers : ['date' => 'Date', 'level' => 'Type', 'message' => 'Message'],
            'nextOffset' => $totalBytes - $pos,
            'hasMore' => $pos > 0,
            'stats' => [
                'duration' => (int)((microtime(true) - $start) * 1000),
                'filesize' => $fileSize,
                'fingerprint' => $fingerprint,
                'format' => $matchedFormat
            ]
        ];
    }

    private function parseLineEnhanced(string $line, ?string &$matchedFormatKey = null): ?array
    {
        // 1. Try custom format first if available
        if (isset($this->formats['custom'])) {
            if (preg_match($this->formats['custom']['regex'], $line, $matches)) {
                $matchedFormatKey = 'custom';
                return [
                    'format_key' => 'custom',
                    'fields' => $this->mapMatches('custom', $matches)
                ];
            }
        }

        // 2. If we already found a format in this session, try it first
        if ($matchedFormatKey && isset($this->formats[$matchedFormatKey])) {
            if (preg_match($this->formats[$matchedFormatKey]['regex'], $line, $matches)) {
                return [
                    'format_key' => $matchedFormatKey,
                    'fields' => $this->mapMatches($matchedFormatKey, $matches)
                ];
            }
        }

        // 3. Try all standard formats
        foreach ($this->formats as $key => $config) {
            if ($key === 'custom') continue;
            if (preg_match($config['regex'], $line, $matches)) {
                $matchedFormatKey = $key;
                return [
                    'format_key' => $key,
                    'fields' => $this->mapMatches($key, $matches)
                ];
            }
        }

        return null;
    }

    private function mapMatches(string $formatKey, array $matches): array
    {
        $config = $this->formats[$formatKey];
        $fields = [];
        foreach ($config['match'] as $name => $index) {
            $fields[$name] = $matches[$index] ?? '-';
        }
        return $fields;
    }

    private function parseDateToTimestamp(string $dateStr): ?int
    {
        // Formats:
        // Nginx Access: 07/Feb/2026:22:46:17 +0530
        // Nginx Error: 2026/02/07 00:30:17
        // PHP Error: 07-Feb-2026 21:26:49 UTC

        // Normalize Nginx Error format (replace / with -)
        $normalized = str_replace('/', '-', $dateStr);
        
        // Handle Nginx Access format (remove : after day/month/year)
        if (preg_match('/^(\d{2}-[A-Za-z]{3}-\d{4}):(\d{2}:\d{2}:\d{2})/', $normalized, $m)) {
            $normalized = $m[1] . ' ' . $m[2];
        }

        $ts = strtotime($normalized);
        return $ts === false ? null : $ts;
    }

    public function getHourlyStats(string $filename, int $sinceTimestamp): array
    {
        $filePath = $this->directory . '/' . basename($filename);
        if (!file_exists($filePath)) {
            return ['error' => 0, 'warn' => 0, 'info' => 0, 'samples' => []];
        }

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return ['error' => 0, 'warn' => 0, 'info' => 0, 'samples' => []];
        }

        $stats = ['error' => 0, 'warn' => 0, 'info' => 0];
        $samples = [];
        $matchedFormat = null;

        fseek($handle, 0, SEEK_END);
        $pos = ftell($handle);
        $chunkSize = 8192;
        $lineRemainder = '';
        $stop = false;

        while ($pos > 0 && !$stop) {
            $readSize = min($pos, $chunkSize);
            $pos -= $readSize;
            fseek($handle, $pos);
            $chunk = fread($handle, $readSize) . $lineRemainder;
            
            $chunkLines = explode("\n", $chunk);
            $lineRemainder = array_shift($chunkLines);

            for ($i = count($chunkLines) - 1; $i >= 0; $i--) {
                $line = $chunkLines[$i];
                if ($line === '') continue;

                $parsed = $this->parseLineEnhanced($line, $matchedFormat);
                if ($parsed && isset($parsed['fields']['date'])) {
                    $ts = $this->parseDateToTimestamp($parsed['fields']['date']);
                    if ($ts && $ts < $sinceTimestamp) {
                        $stop = true;
                        break;
                    }

                    $level = $this->detectLevel($line, $parsed['fields']);
                    if (isset($stats[$level])) {
                        $stats[$level]++;
                        if ($level === 'error' && count($samples) < 500) {
                            $samples[] = $parsed['fields']['message'] ?? $line;
                        }
                    }
                }
            }
        }
        
        // Final check for the lineRemainder
        if (!$stop && $lineRemainder !== '') {
             $parsed = $this->parseLineEnhanced($lineRemainder, $matchedFormat);
             if ($parsed && isset($parsed['fields']['date'])) {
                $ts = $this->parseDateToTimestamp($parsed['fields']['date']);
                if ($ts && $ts >= $sinceTimestamp) {
                    $level = $this->detectLevel($lineRemainder, $parsed['fields']);
                    if (isset($stats[$level])) {
                        $stats[$level]++;
                    }
                }
             }
        }

        fclose($handle);
        $stats['samples'] = $samples;
        return $stats;
    }

    private function detectLevel(string $line, array $fields = []): string
    {
        // 1. Check if level is already parsed
        if (isset($fields['level'])) {
            $lvl = strtolower($fields['level']);
            if (in_array($lvl, ['error', 'critical', 'crit', 'fatal', 'exception', 'alert', 'emergency', 'emerg'])) return 'error';
            if (in_array($lvl, ['warning', 'warn', 'deprecated'])) return 'warn';
            if (in_array($lvl, ['info', 'information', 'notice', 'debug'])) return 'info';
        }

        // 2. Structured Level Patterns (High Priority)
        if (preg_match('/\[(error|critical|crit|fatal|exception|alert|emergency|emerg)\]/i', $line)) return 'error';
        if (preg_match('/\[(warning|warn|deprecated)\]/i', $line)) return 'warn';
        if (preg_match('/\[(info|information|notice|debug)\]/i', $line)) return 'info';

        // PHP Prefix
        if (preg_match('/PHP (Fatal|Error|Parse|Exception)/i', $line)) return 'error';
        if (preg_match('/PHP (Warning|Deprecated)/i', $line)) return 'warn';
        if (preg_match('/PHP (Notice|Info)/i', $line)) return 'info';

        // 3. Status Code (for Access Logs)
        if (isset($fields['status'])) {
            $status = (int)$fields['status'];
            if ($status >= 500) return 'error';
            if ($status >= 400) return 'warn';
            return 'info';
        }
        
        // 4. Keyword Search (Low Priority)
        if (preg_match('/\b(error|critical|crit|fatal|exception|alert|emergency|emerg)\b/i', $line)) return 'error';
        if (preg_match('/\b(warning|warn|deprecated)\b/i', $line)) return 'warn';
        if (preg_match('/\b(info|information|notice|debug)\b/i', $line)) return 'info';
        
        return 'unknown';
    }

    /**
     * Legacy read method for compressed files (GZ) using shell commands
     */
    private function readLogLegacy(string $filePath, int $offset, int $limit, string $filter, string $query = ''): array
    {
        $escapedPath = escapeshellarg($filePath);
        $start = $offset + 1;
        $end = $offset + $limit;
        
        $shellPatterns = [
            'error' => "\\b(error|critical|crit|fatal|exception|alert|emergency|emerg)\\b|\" 5[0-9]{2} ",
            'warn'  => "\\b(warning|warn|deprecated)\\b|\" 4[0-9]{2} ",
            'info'  => "\\b(info|information|notice|debug)\\b|\\[[0-9]{2}/[A-Z][a-z]{2}/[0-9]{4}:"
        ];

        $cmd = "zcat $escapedPath | tac";
        if ($filter !== 'all' && isset($shellPatterns[$filter])) {
            $cmd .= " | grep -E -i " . escapeshellarg($shellPatterns[$filter]);
        }
        if (!empty($query)) {
            $cmd .= " | grep -i " . escapeshellarg($query);
        }
        $cmd .= " | sed -n '{$start},{$end}p'";
        
        $output = shell_exec($cmd);
        $rawLines = $output ? explode("\n", rtrim($output)) : [];
        
        $lines = [];
        $matchedFormat = null;
        $headers = [];

        foreach ($rawLines as $line) {
            if (empty($line)) continue;
            
            $parsed = $this->parseLineEnhanced($line, $matchedFormat);
            $entry = [
                'content' => rtrim($line),
                'level' => $this->detectLevel($line, $parsed ? $parsed['fields'] : []),
            ];

            if ($parsed) {
                $entry['parsed'] = ['type' => $parsed['format_key'], 'columns' => $parsed['fields']];
                if (!$headers) {
                    foreach ($this->formats[$parsed['format_key']]['match'] as $col => $idx) {
                        $headers[$col] = ucfirst($col);
                    }
                }
            }
            $lines[] = $entry;
        }

        return [
            'lines' => $lines,
            'headers' => $headers,
            'nextOffset' => $offset + count($rawLines), 
            'hasMore' => count($rawLines) >= $limit
        ];
    }

    public function getTotalLines(string $filename, string $filter = 'all'): int
    {
        $filePath = $this->directory . '/' . basename($filename);
        if (!file_exists($filePath)) {
            return 0;
        }

        $isGz = str_ends_with($filePath, '.gz');
        $escapedPath = escapeshellarg($filePath);

        $patterns = [
            'error' => "\\b(error|critical|crit|fatal|exception|alert|emergency|emerg)\\b|\" 5[0-9]{2} ",
            'warn'  => "\\b(warning|warn|deprecated)\\b|\" 4[0-9]{2} ",
            'info'  => "\\b(info|information|notice|debug)\\b|\\[[0-9]{2}/[A-Z][a-z]{2}/[0-9]{4}:"
        ];

        try {
            $filesize = filesize($filePath);
            if ($filter === 'all') {
                // If it's a large file, don't count lines every time for speed
                if (!$isGz && $filesize > 50000000) { // > 50MB
                    return -1; // Indicate "Too large to count"
                }
                $cmd = $isGz ? "zcat $escapedPath | wc -l" : "wc -l < $escapedPath";
            } elseif (isset($patterns[$filter])) {
                $pattern = $patterns[$filter];
                $cmd = $isGz 
                    ? "zgrep -E -c -i " . escapeshellarg($pattern) . " $escapedPath"
                    : "grep -E -c -i " . escapeshellarg($pattern) . " $escapedPath";
            } else {
                return 0;
            }

            $output = trim(shell_exec($cmd));
            return (int)$output;
        } catch (\Throwable $e) {
             return 0;
        }
    }

    private function getLinesFromBottom(string $file, int $count = 1): array
    {
        if (!file_exists($file)) return [];
        $handle = fopen($file, 'rb');
        if (!$handle) return [];

        $lines = [];
        $pos = -1;
        fseek($handle, 0, SEEK_END);
        $totalBytes = ftell($handle);

        for ($i = 0; $i < $count && abs($pos) <= $totalBytes; $i++) {
            $line = '';
            while (abs($pos) <= $totalBytes) {
                fseek($handle, $pos--, SEEK_END);
                $char = fgetc($handle);
                if ($char === "\n") {
                    if ($line === '') continue;
                    break;
                }
                $line = $char . $line;
            }
            if ($line !== '') $lines[] = $line;
        }
        fclose($handle);
        return $lines;
    }
}
