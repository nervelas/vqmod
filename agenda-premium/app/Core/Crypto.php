<?php
declare(strict_types=1);

namespace App\Core;

final class Crypto
{
    public static function hmac(string $data, string $key): string
    {
        return hash_hmac('sha256', $data, $key);
    }

    public static function appKey(): string
    {
        $k = (string) Config::get('app_key', '');
        if ($k === '') {
            throw new \RuntimeException('Falta app_key en la configuración.');
        }
        return $k;
    }

    /** Firma de un valor con la clave de la aplicación (enlaces con caducidad, etc.). */
    public static function sign(string $payload): string
    {
        return self::hmac($payload, self::appKey());
    }

    public static function equals(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }

    public static function hashPassword(string $plain): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            try {
                $h = password_hash($plain, PASSWORD_ARGON2ID);
                if (is_string($h) && $h !== '') {
                    return $h;
                }
            } catch (\Throwable $e) {
                // continúa con BCRYPT
            }
        }
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function passwordStrongEnough(string $p): ?string
    {
        if (strlen($p) < 10) {
            return 'La contraseña debe tener al menos 10 caracteres.';
        }
        if (!preg_match('/[a-z]/', $p) || !preg_match('/[A-Z]/', $p) || !preg_match('/\d/', $p)) {
            return 'Usa mayúsculas, minúsculas y números en la contraseña.';
        }
        return null;
    }

    /** Cifrado simétrico para secretos guardados en settings (tokens de API). Requiere openssl. */
    public static function encrypt(string $plain): string
    {
        $key = hash('sha256', self::appKey(), true);
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) {
            throw new \RuntimeException('No se pudo cifrar.');
        }
        return 'v1:' . base64_encode($iv . $tag . $ct);
    }

    public static function decrypt(string $blob): ?string
    {
        if (strncmp($blob, 'v1:', 3) !== 0) {
            return null;
        }
        $raw = base64_decode(substr($blob, 3), true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $key = hash('sha256', self::appKey(), true);
        $pt = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $pt === false ? null : $pt;
    }
}
