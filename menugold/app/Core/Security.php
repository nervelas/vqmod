<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Cabeceras de seguridad, firmas HMAC y utilidades criptograficas.
 */
final class Security
{
    private static ?string $nonce = null;

    public static function appKey(): string
    {
        $k = (string)App::config('app_key', '');
        return $k !== '' ? $k : 'menugold-clave-temporal-sin-instalar';
    }

    public static function nonce(): string
    {
        if (self::$nonce === null) self::$nonce = base64_encode(random_bytes(12));
        return self::$nonce;
    }

    public static function sendHeaders(): void
    {
        if (headers_sent() || PHP_SAPI === 'cli') return;
        header_remove('X-Powered-By');

        $esMenu = strncmp(App::uri(), '/r/', 3) === 0 || App::uri() === '/';
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: ' . ($esMenu ? 'SAMEORIGIN' : 'DENY'));
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(self), microphone=(), camera=(), payment=(), usb=()');
        header('X-Permitted-Cross-Domain-Policies: none');
        if (App::isSecure()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        $n = self::nonce();
        $csp = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            "frame-ancestors " . ($esMenu ? "'self' *" : "'none'"),
            "script-src 'self' 'nonce-{$n}'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "connect-src 'self'",
            "manifest-src 'self'",
            "worker-src 'self'",
            "media-src 'self' data:",
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));
    }

    /** Firma HMAC-SHA256 en base64url. */
    public static function sign(string $data, string $context = ''): string
    {
        return rtrim(strtr(base64_encode(
            hash_hmac('sha256', $context . '|' . $data, self::appKey(), true)
        ), '+/', '-_'), '=');
    }

    public static function verify(string $data, string $signature, string $context = ''): bool
    {
        return hash_equals(self::sign($data, $context), $signature);
    }

    /** Token firmado para el QR de una mesa: {id}.{firma} */
    public static function tableToken(int $restaurantId, int $tableId): string
    {
        $datos = $restaurantId . ':' . $tableId;
        return substr(self::sign($datos, 'mesa'), 0, 24);
    }

    public static function verifyTableToken(int $restaurantId, int $tableId, string $token): bool
    {
        return hash_equals(self::tableToken($restaurantId, $tableId), $token);
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function hashPassword(string $plain): string
    {
        if (defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)) {
            return password_hash($plain, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2,
            ]);
        }
        // Respaldo para hostings sin sodium/argon2
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function needsRehash(string $hash): bool
    {
        if (defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)) {
            return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2,
            ]);
        }
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /** Captcha matematico propio (sin servicios externos). */
    public static function captchaMake(): array
    {
        $a = random_int(2, 9);
        $b = random_int(1, 9);
        $op = random_int(0, 1) === 0 ? '+' : '-';
        if ($op === '-' && $b > $a) { [$a, $b] = [$b, $a]; }
        $res = $op === '+' ? $a + $b : $a - $b;
        $_SESSION['_captcha'] = ['r' => $res, 't' => time()];
        return ['pregunta' => "{$a} {$op} {$b}", 'a' => $a, 'b' => $b, 'op' => $op];
    }

    public static function captchaCheck($answer): bool
    {
        $c = $_SESSION['_captcha'] ?? null;
        unset($_SESSION['_captcha']);
        if (!$c || (time() - (int)$c['t']) > 900) return false;
        return is_numeric($answer) && (int)$answer === (int)$c['r'];
    }
}
