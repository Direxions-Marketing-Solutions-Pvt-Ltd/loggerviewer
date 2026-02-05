<?php

declare(strict_types=1);

namespace App;

class User
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getAll(): array
    {
        return $this->db->get_results("SELECT * FROM users ORDER BY username ASC");
    }

    public function getById(int $id)
    {
        return $this->db->get_row("SELECT * FROM users WHERE id = ?", [$id]);
    }

    public function create(string $username, string $password, string $role, string $email = '', string $authType = 'password'): int|bool
    {
        // Check if username exists
        $exists = $this->db->get_row("SELECT id FROM users WHERE username = ?", [$username]);
        if ($exists) {
            return false;
        }

        return $this->db->insert('users', [
            'username' => $username,
            'password' => password_hash(hash_hmac('sha256', $password, AUTH_SECRET), PASSWORD_DEFAULT),
            'role' => $role,
            'email' => $email,
            'auth_type' => $authType
        ]);
    }

    public function generateOtp(int $userId): string|bool
    {
        $otp = (string)random_int(100000, 999999);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $updated = $this->db->update('users', [
            'otp_code' => $otp,
            'otp_expires_at' => $expiresAt
        ], ['id' => $userId]);

        return $updated ? $otp : false;
    }

    public function verifyOtp(int $userId, string $otp): bool
    {
        $user = $this->db->get_row(
            "SELECT * FROM users WHERE id = ? AND otp_code = ? AND otp_expires_at > CURRENT_TIMESTAMP",
            [$userId, $otp]
        );

        if ($user) {
            // Clear OTP after success
            $this->db->update('users', [
                'otp_code' => null,
                'otp_expires_at' => null
            ], ['id' => $userId]);
            return true;
        }

        return false;
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        if (isset($data['username'])) {
            $fields['username'] = $data['username'];
        }
        if (isset($data['password']) && !empty($data['password'])) {
            $fields['password'] = password_hash(hash_hmac('sha256', $data['password'], AUTH_SECRET), PASSWORD_DEFAULT);
        }
        if (isset($data['role'])) {
            $fields['role'] = $data['role'];
        }
        if (isset($data['email'])) {
            $fields['email'] = $data['email'];
        }
        if (isset($data['auth_type'])) {
            $fields['auth_type'] = $data['auth_type'];
        }

        if (empty($fields)) {
            return false;
        }

        return $this->db->update('users', $fields, ['id' => $id]);
    }

    public function delete(int $id): bool
    {
        // Prevent deleting self
        if ($id == ($_SESSION['user_id'] ?? 0)) {
            return false;
        }

        // Delete project access records
        $this->db->delete('project_access', ['user_id' => $id]);
        return $this->db->delete('users', ['id' => $id]);
    }
}
