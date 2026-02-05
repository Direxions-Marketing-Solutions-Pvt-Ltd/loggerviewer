<?php

declare(strict_types=1);

namespace App;

class Mailer
{
    /**
     * Sends an email using PHP's native mail() function as a fallback.
     * In a production environment with fixed SMTP credentials, 
     * this should use a library like PHPMailer or SwiftMailer.
     */
    public static function send(string $to, string $subject, string $body): bool
    {
        if (empty(SMTP_HOST)) {
            $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
            $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            return mail($to, $subject, $body, $headers);
        }

        return self::sendSmtp($to, $subject, $body);
    }

    private static function sendSmtp(string $to, string $subject, string $body): bool
    {
        $host = SMTP_HOST;
        $port = SMTP_PORT;
        $user = SMTP_USER;
        $pass = \App\Encryption::decrypt(SMTP_PASS);
        $from = SMTP_FROM_EMAIL;
        $encryption = strtolower(SMTP_ENCRYPTION);

        $remote = $host;
        if ($encryption === 'ssl') {
            $remote = "ssl://" . $host;
        }

        $socket = @fsockopen($remote, $port, $errno, $errstr, 15);
        if (!$socket) {
            error_log("SMTP Connection Error: $errstr ($errno) - Host: $remote");
            return false;
        }

        $read = function($socket) {
            $res = "";
            while ($line = fgets($socket, 512)) {
                $res .= $line;
                if (substr($line, 3, 1) === " ") break;
            }
            return $res;
        };

        $send = function($socket, $cmd) use ($read) {
            fputs($socket, $cmd . "\r\n");
            $response = $read($socket);
            return $response;
        };

        $read($socket); // 220 Greeting
        $send($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));

        // STARTTLS support
        if ($encryption === 'starttls' || $encryption === 'tls') {
            $resp = $send($socket, "STARTTLS");
            if (strpos($resp, '220') === false) {
                error_log("SMTP STARTTLS Failed: $resp");
                fclose($socket);
                return false;
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("SMTP SSL/TLS encryption failed.");
                fclose($socket);
                return false;
            }
            // Repeat EHLO after TLS
            $send($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        }
        
        if (!empty($user)) {
            $resp = $send($socket, "AUTH LOGIN");
            if (strpos($resp, '334') === false) {
                error_log("SMTP AUTH LOGIN failed: $resp");
                fclose($socket);
                return false;
            }
            $send($socket, base64_encode($user));
            $resp = $send($socket, base64_encode($pass));
            if (strpos($resp, '235') === false) {
                error_log("SMTP Auth Failed: $resp");
                fclose($socket);
                return false;
            }
        }

        $send($socket, "MAIL FROM: <$from>");
        $send($socket, "RCPT TO: <$to>");
        $send($socket, "DATA");

        $headers = [
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "From: " . SMTP_FROM_NAME . " <$from>",
            "To: <$to>",
            "Subject: $subject",
            "Date: " . date('r'),
            "Message-ID: <" . md5((string)time()) . "@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . ">"
        ];

        fputs($socket, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
        $read($socket);

        $send($socket, "QUIT");
        fclose($socket);

        return true;
    }
}
