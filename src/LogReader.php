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
            $cached = $this->decompressGzCached($filePath);
            if ($cached !== null && file_exists($cached)) {
                $result = $this->readLogEnhanced($cached, $offset, $limit, $filter, $query);
                return $this->convertDatesToAppTz($result);
            }
            // Fallback if decompression failed
            $result = $this->readLogLegacy($filePath, $offset, $limit, $filter, $query);
            return $this->convertDatesToAppTz($result);
        }

        $result = $this->readLogEnhanced($filePath, $offset, $limit, $filter, $query);
        return $this->convertDatesToAppTz($result);
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

        $chunkSize = 65536; // 64KB chunks
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

                    $entry = [
                        'content' => $line,
                        'level' => $this->detectLevel($line, $parsed['fields']),
                        'parsed' => [
                            'type' => $matchedFormat,
                            'columns' => $parsed['fields']
                        ]
                    ];

                    // Handle Multiline Buffer (attach continuation lines for any format)
                    if (!empty($buffer)) {
                        $bufText = implode("\n", array_reverse($buffer));
                        $entry['content'] .= "\n" . $bufText;
                        $multilineKey = ($matchedFormat && isset($this->formats[$matchedFormat]['multiline']))
                            ? $this->formats[$matchedFormat]['multiline'] : null;
                        if ($multilineKey && isset($entry['parsed']['columns'][$multilineKey])) {
                            $entry['parsed']['columns'][$multilineKey] .= "\n" . $bufText;
                        } elseif (isset($entry['parsed']['columns']['message'])) {
                            $entry['parsed']['columns']['message'] .= "\n" . $bufText;
                        } elseif (isset($entry['parsed']['columns']['content'])) {
                            $entry['parsed']['columns']['content'] .= "\n" . $bufText;
                        }
                        $buffer = [];
                    }

                    // Recompute level from full (possibly multiline) content
                    $entry['level'] = $this->detectLevel($entry['content'], $parsed['fields']);
                    if ($filter !== 'all' && $entry['level'] !== $filter) {
                        continue;
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

        // Attach any trailing unparsed (multiline) buffer to the oldest parsed entry
        if (!empty($buffer) && !empty($lines)) {
            $multilineKey = ($matchedFormat && isset($this->formats[$matchedFormat]['multiline']))
                ? $this->formats[$matchedFormat]['multiline'] : null;
            $lastIdx = array_key_last($lines);
            $bufText = implode("\n", array_reverse($buffer));
            $lines[$lastIdx]['content'] .= "\n" . $bufText;
            if ($multilineKey && isset($lines[$lastIdx]['parsed']['columns'][$multilineKey])) {
                $lines[$lastIdx]['parsed']['columns'][$multilineKey] .= "\n" . $bufText;
            } elseif (isset($lines[$lastIdx]['parsed']['columns']['message'])) {
                $lines[$lastIdx]['parsed']['columns']['message'] .= "\n" . $bufText;
            } elseif (isset($lines[$lastIdx]['parsed']['columns']['content'])) {
                $lines[$lastIdx]['parsed']['columns']['content'] .= "\n" . $bufText;
            }
            // Recompute level from full content
            $lines[$lastIdx]['level'] = $this->detectLevel($lines[$lastIdx]['content'], $lines[$lastIdx]['parsed']['columns'] ?? []);
            $buffer = [];
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
        // WP-Admin style: 07-Feb-2026 16:46:02 UTC

        $normalized = str_replace('/', '-', $dateStr);
        
        // Handle Nginx Access format (remove : after day/month/year)
        if (preg_match('/^(\d{2}-[A-Za-z]{3}-\d{4}):(\d{2}:\d{2}:\d{2})/', $normalized, $m)) {
            $normalized = $m[1] . ' ' . $m[2];
        }

        // Parse in UTC regardless of app timezone (server logs are UTC)
        $prevTz = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $ts = strtotime($normalized);
        date_default_timezone_set($prevTz);
        if ($ts === false) return null;

        // If no timezone in string, assume UTC (since script/server is UTC)
        return $ts;
    }

    public function getHourlyStats(string $filename, int $sinceTimestamp): array
    {
        $filePath = $this->directory . '/' . basename($filename);
        if (!file_exists($filePath)) {
            return ['error' => 0, 'warn' => 0, 'info' => 0, 'samples' => []];
        }

        $isGz = str_ends_with($filePath, '.gz');

        if ($isGz) {
            $cached = $this->decompressGzCached($filePath);
            if ($cached !== null && file_exists($cached)) {
                $filePath = $cached;
                $isGz = false;
            }
        }

        // Compressed files: forward scan (no random seek)
        if ($isGz) {
            $stats = ['error' => 0, 'warn' => 0, 'info' => 0];
            $samples = [];
            $matchedFormat = null;
            $untilTimestamp = $sinceTimestamp + 3600;
            $gz = @gzopen($filePath, 'rb');
            if (!$gz) {
                return ['error' => 0, 'warn' => 0, 'info' => 0, 'samples' => []];
            }
            while (!gzeof($gz)) {
                $line = gzgets($gz, 4096);
                if ($line === false) break;
                $line = rtrim($line, "\n");
                if (trim($line) === '') continue;
                $parsed = $this->parseLineEnhanced($line, $matchedFormat);
                if ($parsed && isset($parsed['fields']['date'])) {
                    $ts = $this->parseDateToTimestamp($parsed['fields']['date']);
                    if (!$ts) continue;
                    if ($ts < $sinceTimestamp || $ts >= $untilTimestamp) continue;
                    $level = $this->detectLevel($line, $parsed['fields']);
                    if (isset($stats[$level])) {
                        $stats[$level]++;
                        if ($level === 'error' && count($samples) < 500) {
                            $samples[] = $parsed['fields']['message'] ?? $line;
                        }
                    }
                }
            }
            gzclose($gz);
            $stats['samples'] = $samples;
            return $stats;
        }

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return ['error' => 0, 'warn' => 0, 'info' => 0, 'samples' => []];
        }

        $stats = ['error' => 0, 'warn' => 0, 'info' => 0];
        $samples = [];
        $matchedFormat = null;
        $untilTimestamp = $sinceTimestamp + 3600;

        fseek($handle, 0, SEEK_END);
        $totalBytes = ftell($handle);
        $pos = $totalBytes;
        $chunkSize = 262144; // 256KB chunks for speed
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
                if (trim($line) === '') continue;

                $parsed = $this->parseLineEnhanced($line, $matchedFormat);
                if ($parsed && isset($parsed['fields']['date'])) {
                    $ts = $this->parseDateToTimestamp($parsed['fields']['date']);
                    if (!$ts) continue;

                    if ($ts < $sinceTimestamp) {
                        $stop = true;
                        break;
                    }

                    if ($ts >= $untilTimestamp) {
                        continue;
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
        if (preg_match('/errormsg|ORA-\d{4,5}/i', $line)) return 'error';
        if (preg_match('/\b(warning|warn|deprecated)\b/i', $line)) return 'warn';
        if (preg_match('/\b(info|information|notice|debug)\b/i', $line)) return 'info';
        
        return 'unknown';
    }

    /**
     * Legacy read method for compressed files (GZ) using shell commands
     */
    private function readLogLegacy(string $filePath, int $offset, int $limit, string $filter, string $query = ''): array
    {
        // Pure-PHP gz reader (no shell dependency)
        $rawLines = [];
        if (str_ends_with($filePath, '.gz')) {
            $gz = @gzopen($filePath, 'rb');
            if ($gz) {
                while (!gzeof($gz)) {
                    $line = gzgets($gz, 4096);
                    if ($line === false) break;
                    $rawLines[] = rtrim($line, "\n");
                }
                gzclose($gz);
            }
        } else {
            $handle = @fopen($filePath, 'rb');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $rawLines[] = rtrim($line, "\n");
                }
                fclose($handle);
            }
        }

        // Reverse to newest-first
        $rawLines = array_reverse($rawLines);

        // Filter
        $filtered = [];
        foreach ($rawLines as $line) {
            if ($line === '') continue;
            if ($filter !== 'all') {
                $lvl = $this->detectLevel($line, []);
                if ($lvl !== $filter) {
                    // fallback keyword check for gz path
                    $patterns = [
                        'error' => '/\b(error|critical|crit|fatal|exception|alert|emergency|emerg)\b/i',
                        'warn'  => '/\b(warning|warn|deprecated)\b/i',
                        'info'  => '/\b(info|information|notice|debug)\b/i',
                    ];
                    if (isset($patterns[$filter]) && !preg_match($patterns[$filter], $line)) {
                        continue;
                    }
                    if (!isset($patterns[$filter])) {
                        continue;
                    }
                }
            }
            if (!empty($query) && stripos($line, $query) === false) {
                continue;
            }
            $filtered[] = $line;
        }

        $slice = array_slice($filtered, $offset, $limit);

        $lines = [];
        $matchedFormat = null;
        $headers = [];

        foreach ($slice as $line) {
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
            'nextOffset' => $offset + count($slice),
            'hasMore' => ($offset + count($slice)) < count($filtered)
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

            $output = trim((string)shell_exec($cmd));
            if ($output !== '' && is_numeric($output)) {
                return (int)$output;
            }

            // PHP fallback when shell tools are unavailable
            return $this->countLinesPhp($filePath, $filter, $patterns);
        } catch (\Throwable $e) {
             return 0;
        }
    }

    private function countLinesPhp(string $filePath, string $filter, array $patterns): int
    {
        $count = 0;
        $handle = null;
        if (str_ends_with($filePath, '.gz')) {
            $handle = @gzopen($filePath, 'rb');
        } else {
            $handle = @fopen($filePath, 'rb');
        }
        if (!$handle) return 0;

        $read = str_ends_with($filePath, '.gz') ? 'gzgets' : 'fgets';
        while (!($read === 'gzgets' ? gzeof($handle) : feof($handle))) {
            $line = $read($handle);
            if ($line === false) break;
            if ($filter === 'all') {
                $count++;
            } elseif (isset($patterns[$filter])) {
                // simple keyword heuristic matching original patterns
                $lvl = $this->detectLevel($line, []);
                if ($lvl === $filter) $count++;
            }
        }
        if (str_ends_with($filePath, '.gz')) gzclose($handle); else fclose($handle);
        return $count;
    }

    private function decompressGzCached(string $gzPath): ?string
    {
        $mtime = @filemtime($gzPath) ?: 0;
        $size = @filesize($gzPath) ?: 0;
        $key = md5($gzPath . '|' . $mtime . '|' . $size);
        $tmpDir = sys_get_temp_dir();
        $target = $tmpDir . DIRECTORY_SEPARATOR . 'lv_gz_' . $key . '.log';

        if (file_exists($target) && filesize($target) > 0) {
            return $target;
        }

        // Decompress in chunks to keep memory low
        $in = @gzopen($gzPath, 'rb');
        if (!$in) {
            return null;
        }
        $out = @fopen($target, 'wb');
        if (!$out) {
            gzclose($in);
            return null;
        }
        while (!gzeof($in)) {
            $chunk = gzread($in, 1 << 20); // 1MB chunks
            if ($chunk === false) break;
            fwrite($out, $chunk);
        }
        fclose($out);
        gzclose($in);

        return file_exists($target) ? $target : null;
    }

    private function convertDatesToAppTz(array $result): array
    {
        $tz = defined('APP_TIMEZONE') ? APP_TIMEZONE : 'UTC';
        if ($tz === 'UTC' || empty($result['lines'])) {
            return $result;
        }
        foreach ($result['lines'] as &$entry) {
            if (isset($entry['parsed']['columns']['date'])) {
                $epoch = $this->parseDateToTimestamp((string)$entry['parsed']['columns']['date']);
                if ($epoch !== null) {
                    $prev = date_default_timezone_get();
                    date_default_timezone_set($tz);
                    $entry['parsed']['columns']['date'] = date('Y-m-d H:i:s', $epoch);
                    date_default_timezone_set($prev);
                }
            }
        }
        unset($entry);
        return $result;
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
