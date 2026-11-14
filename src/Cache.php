<?php

declare(strict_types=1);

namespace App;

use Redis;
use RedisException;

class Cache
{
    private ?Redis $redis = null;
    private bool $available = false;

    public function __construct()
    {
        if (class_exists('Redis')) {
            try {
                $this->redis = new Redis();
                $this->redis->connect(REDIS_HOST, REDIS_PORT);
                $this->available = true;
            } catch (RedisException $e) {
                error_log("Redis connection failed: " . $e->getMessage());
                $this->available = false;
            }
        }
    }

    public function get(string $key)
    {
        if (!$this->available) {
            return false;
        }

        try {
            $value = $this->redis->get($key);
            if ($value === false) {
                return false;
            }
            $decoded = json_decode($value, true);
            return (json_last_error() === JSON_ERROR_NONE) ? $decoded : false;
        } catch (RedisException $e) {
            error_log("Redis get failed: " . $e->getMessage());
            return false;
        }
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        if (!$this->available) {
            return false;
        }

        try {
            return $this->redis->set($key, json_encode($value), $ttl);
        } catch (RedisException $e) {
            error_log("Redis set failed: " . $e->getMessage());
            return false;
        }
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }
}
