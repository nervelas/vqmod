<?php
declare(strict_types=1);

namespace App\Core;

final class Security
{
    private static ?string $nonce = null;

    /** Nonce por petición para los scripts en línea (CSP estricta). */
    public static function nonce(): string
    {
        return self::$nonce ??= rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    /** Cabeceras de seguridad para toda respuesta HTML. */
    public static function headers(): void
    {
        if (headers_sent()) {
            return;
        }
        $self  = "'self'";
        $nonce = "'nonce-" . self::nonce() . "'";
        $csp = [
            "default-src {$self}",
            "base-uri {$self}",
            "form-action {$self}",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src {$self} data: blob:",
            "font-src {$self}",
            "style-src {$self} 'unsafe-inline'",
            "script-src {$self} {$nonce}",
            "connect-src {$self}",
            "manifest-src {$self}",
            "worker-src {$self}",
            "media-src {$self}",
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), browsing-topics=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header_remove('X-Powered-By');
        if (App::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function hashPassword(string $plain): string
    {
        if (defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)) {
            return password_hash($plain, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost'   => 4,
                'threads'     => 2,
            ]);
        }
        // Respaldo para hostings sin libsodium/argon2 compilado.
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return $hash !== '' && password_verify($plain, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        if (defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)) {
            return password_needs_rehash($hash, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]);
        }
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /** Token HMAC largo, no adivinable, para links públicos /c/{token}. */
    public static function signedToken(string $payload): string
    {
        $key = (string) Config::get('app_key', '');
        $rnd = bin2hex(random_bytes(20));
        $sig = substr(hash_hmac('sha256', $payload . '|' . $rnd, $key), 0, 24);
        return $rnd . $sig;
    }

    public static function fingerprint(): string
    {
        return substr(hash('sha256', App::ip() . '|' . App::userAgent()), 0, 32);
    }

    public static function passwordScore(string $p): int
    {
        $s = 0;
        if (strlen($p) >= 8) { $s++; }
        if (strlen($p) >= 12) { $s++; }
        if (preg_match('/[A-Z]/', $p)) { $s++; }
        if (preg_match('/[a-z]/', $p)) { $s++; }
        if (preg_match('/[0-9]/', $p)) { $s++; }
        if (preg_match('/[^A-Za-z0-9]/', $p)) { $s++; }
        return $s;
    }

    public static function passwordOk(string $p): bool
    {
        return strlen($p) >= 8 && self::passwordScore($p) >= 4;
    }
}
