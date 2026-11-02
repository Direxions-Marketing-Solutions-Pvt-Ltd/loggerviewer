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
                // Brute force mitigation
                usleep(rand(500000, 1500000)); // 0.5s to 1.5s delay
                error_log("Login failed for user '$username': Password mismatch.");
            }
        } else {
            // OWASP: Slow down even if user not found to prevent timing attacks
            usleep(rand(500000, 1500000));
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

    public static function hasAccess(int $projectId): bool
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

    private function attemptKey(string $identifier): string
    {
        return 'lv_attempts_' . md5($identifier);
    }

    public function isLockedOut(string $identifier): bool
    {
        $key = $this->attemptKey($identifier);
        $data = $_SESSION[$key] ?? null;
        if (!is_array($data)) {
            return false;
        }
        if (!empty($data['lock_until']) && time() < (int)$data['lock_until']) {
            return true;
        }
        if (time() - (int)($data['first'] ?? time()) > 900) {
            unset($_SESSION[$key]);
        }
        return false;
    }

    public function recordFailure(string $identifier): void
    {
        $key = $this->attemptKey($identifier);
        $data = $_SESSION[$key] ?? ['count' => 0, 'first' => time(), 'lock_until' => 0];
        if (time() - (int)($data['first'] ?? time()) > 900) {
            $data = ['count' => 0, 'first' => time(), 'lock_until' => 0];
        }
        $data['count'] = (int)($data['count'] ?? 0) + 1;
        if ($data['count'] >= 5) {
            $data['lock_until'] = time() + 900;
        }
        $_SESSION[$key] = $data;
    }

    public function clearAttempts(string $identifier): void
    {
        unset($_SESSION[$this->attemptKey($identifier)]);
    }

}