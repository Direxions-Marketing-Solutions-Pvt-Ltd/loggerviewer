<?php
namespace App;

class Encryption {
    private static $method = 'aes-256-gcm';
    private static $legacyMethod = 'aes-256-cbc';
    private static $prefix = 'v2:';

    /**
     * Encrypt a string using AES-256-GCM (authenticated). Falls back to nothing; legacy
     * ciphertexts are still readable by decrypt().
     */
    public static function encrypt($data, $secret = null) {
        if (empty($data)) return '';
        $key = hash('sha256', $secret ?: AUTH_SECRET, true);
        $ivLen = 12;
        $iv = random_bytes($ivLen);
        $tag = '';
        $encrypted = openssl_encrypt($data, self::$method, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($encrypted === false) {
            return '';
        }
        return self::$prefix . base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Decrypt a string. Supports new GCM format (prefixed) and legacy CBC format.
     */
    public static function decrypt($data, $secret = null) {
        if (empty($data)) return '';
        $key = hash('sha256', $secret ?: AUTH_SECRET, true);

        // New authenticated format
        if (strpos($data, self::$prefix) === 0) {
            $raw = base64_decode(substr($data, strlen(self::$prefix)), true);
            if ($raw === false) return '';
            $ivLen = 12;
            $tagLen = 16;
            if (strlen($raw) < $ivLen + $tagLen) return '';
            $iv = substr($raw, 0, $ivLen);
            $tag = substr($raw, $ivLen, $tagLen);
            $ciphertext = substr($raw, $ivLen + $tagLen);
            $decrypted = openssl_decrypt($ciphertext, self::$method, $key, OPENSSL_RAW_DATA, $iv, $tag);
            return $decrypted === false ? '' : $decrypted;
        }

        // Legacy CBC format (iv prepended, base64-encoded)
        $raw = base64_decode($data, true);
        if ($raw === false) return '';
        $ivLen = openssl_cipher_iv_length(self::$legacyMethod);
        if (strlen($raw) < $ivLen) return '';
        $iv = substr($raw, 0, $ivLen);
        $ciphertext = substr($raw, $ivLen);
        $legacyKey = hash('sha256', $secret ?: AUTH_SECRET); // hex, as before
        $decrypted = openssl_decrypt($ciphertext, self::$legacyMethod, $legacyKey, 0, $iv);
        return $decrypted === false ? '' : $decrypted;
    }
}