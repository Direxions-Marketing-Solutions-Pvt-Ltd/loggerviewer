<?php

declare(strict_types=1);

namespace App;

class Auth
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) {
            // OWASP: Secure Session Headers
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']), // Only secure if HTTPS
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            session_start();
        }
    }

    public static function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrfToken(?string $token): bool
    {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public function getUserByUsername(string $username)
    {
        return $this->db->get_row("SELECT * FROM users WHERE username = ?", [$username]);
    }

    public function login(string $username, string $password): bool
    {
        $user = $this->getUserByUsername($username);

        if ($user && $user->auth_type === 'password') {
            $peppered = hash_hmac('sha256', $password, AUTH_SECRET);
            if (password_verify($peppered, $user->password)) {
                $this->setSession($user);
                return true;
            } else {
                error_log("Login failed for user '$username': Password mismatch.");
            }
        }
        return false;
    }

    public function loginWithOtp(int $userId, string $otp): bool
    {
        $userManager = new \App\User($this->db);
        if ($userManager->verifyOtp($userId, $otp)) {
            $user = $userManager->getById($userId);
            $this->setSession($user);
            return true;
        }
        return false;
    }

    private function setSession($user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;
        $_SESSION['role'] = $user->role;

        // Force password change if default admin password is still in use
        if ($user->username === 'admin' && password_verify(hash_hmac('sha256', 'admin123', AUTH_SECRET), $user->password)) {
            $_SESSION['force_password_change'] = true;
        } else {
            unset($_SESSION['force_password_change']);
        }
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function isAdmin(): bool
    {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public static function getCurrentUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public function hasAccess(int $projectId): bool
    {
        if (self::isAdmin()) {
            return true;
        }

        $userId = self::getCurrentUserId();
        if (!$userId) {
            return false;
        }

        $db = new Database(DB_PATH);
        $access = $db->get_row(
            "SELECT 1 FROM project_access WHERE user_id = ? AND project_id = ?",
            [$userId, $projectId]
        );

        return $access !== false;
    }

    private function ensureAttemptsTable(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key TEXT UNIQUE NOT NULL,
            count INTEGER NOT NULL DEFAULT 0,
            first_at INTEGER NOT NULL,
            lock_until INTEGER NOT NULL DEFAULT 0
        )");
    }

    private function attemptKey(string $identifier): string
    {
        return md5($identifier);
    }

    public function isLockedOut(string $identifier): bool
    {
        $this->ensureAttemptsTable();
        $key = $this->attemptKey($identifier);
        $row = $this->db->get_row("SELECT * FROM login_attempts WHERE key = ?", [$key]);
        if (!$row) {
            return false;
        }
        $now = time();
        if (!empty($row->lock_until) && $now < (int)$row->lock_until) {
            return true;
        }
        if ($now - (int)$row->first_at > 900) {
            $this->db->delete('login_attempts', ['key' => $key]);
        }
        return false;
    }

    public function recordFailure(string $identifier): void
    {
        $this->ensureAttemptsTable();
        $key = $this->attemptKey($identifier);
        $now = time();
        $row = $this->db->get_row("SELECT * FROM login_attempts WHERE key = ?", [$key]);

        if (!$row || $now - (int)$row->first_at > 900) {
            $this->db->insert('login_attempts', [
                'key' => $key,
                'count' => 1,
                'first_at' => $now,
                'lock_until' => 0
            ], true);
            return;
        }

        $count = (int)$row->count + 1;
        $lockUntil = ($count >= 5) ? ($now + 900) : 0;
        $this->db->update('login_attempts', [
            'count' => $count,
            'lock_until' => $lockUntil
        ], ['key' => $key]);
    }

    public function clearAttempts(string $identifier): void
    {
        $this->ensureAttemptsTable();
        $this->db->delete('login_attempts', ['key' => $this->attemptKey($identifier)]);
    }

}