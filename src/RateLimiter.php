<?php
declare(strict_types=1);

namespace App;

class RateLimiter
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key TEXT UNIQUE NOT NULL,
            count INTEGER NOT NULL DEFAULT 0,
            window_start INTEGER NOT NULL
        )");
    }

    /**
     * Returns true if the request is allowed, false if rate-limited.
     */
    public function allow(string $key, int $limit, int $windowSeconds): bool
    {
        $now = time();
        $row = $this->db->get_row("SELECT * FROM rate_limits WHERE key = ?", [$key]);

        if (!$row || ($now - (int)$row->window_start) >= $windowSeconds) {
            $this->db->insert('rate_limits', [
                'key' => $key,
                'count' => 1,
                'window_start' => $now
            ], true);
            return true;
        }

        if ((int)$row->count >= $limit) {
            return false;
        }

        $this->db->update('rate_limits', [
            'count' => (int)$row->count + 1
        ], ['key' => $key]);
        return true;
    }
}