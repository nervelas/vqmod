<?php
declare(strict_types=1);

namespace Aurea\Core;

/** Cifrado de secretos guardados en la BD (contraseña SMTP, tokens) con AES-256-GCM y la clave de config. */
final class Secret
{
    private static function key(): string
    {
        return hash('sha256', 'aurea-secret|' . (string)config('app_key', ''), true);
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') { return ''; }
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return 'v1:' . base64_encode($iv . $tag . (string)$ct);
    }

    public static function decrypt(string $stored): string
    {
        if (strpos($stored, 'v1:') !== 0) { return ''; }
        $raw = base64_decode(substr($stored, 3), true);
        if ($raw === false || strlen($raw) < 29) { return ''; }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? '' : $plain;
    }
}
