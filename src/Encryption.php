<?php
namespace App;

class Encryption {
    private static $method = 'aes-256-cbc';

    /**
     * Encrypt a string using the AUTH_SECRET or a custom secret.
     */
    public static function encrypt($data, $secret = null) {
        if (empty($data)) return '';
        $key = hash('sha256', $secret ?: AUTH_SECRET);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$method));
        $encrypted = openssl_encrypt($data, self::$method, $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a string using the AUTH_SECRET or a custom secret.
     */
    public static function decrypt($data, $secret = null) {
        if (empty($data)) return '';
        $data = base64_decode($data);
        $key = hash('sha256', $secret ?: AUTH_SECRET);
        $ivLen = openssl_cipher_iv_length(self::$method);
        $iv = substr($data, 0, $ivLen);
        $encrypted = substr($data, $ivLen);
        return openssl_decrypt($encrypted, self::$method, $key, 0, $iv);
    }
}
