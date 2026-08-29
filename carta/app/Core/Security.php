<?php
namespace MenuGold\Core;

final class Security
{
    /** Hash Argon2id con degradación a bcrypt si el binario de PHP no lo trae. */
    public static function hashPassword($plain)
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($plain, PASSWORD_ARGON2ID, array(
                'memory_cost' => 65536,
                'time_cost'   => 4,
                'threads'     => 2,
            ));
        }
        return password_hash($plain, PASSWORD_BCRYPT, array('cost' => 12));
    }

    public static function verifyPassword($plain, $hash)
    {
        return is_string($hash) && $hash !== '' && password_verify($plain, $hash);
    }

    public static function needsRehash($hash)
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_needs_rehash($hash, PASSWORD_ARGON2ID, array('memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2));
        }
        return password_needs_rehash($hash, PASSWORD_BCRYPT, array('cost' => 12));
    }

    /** Firma HMAC-SHA256 truncada, para tokens de mesa en el QR. */
    public static function sign($payload)
    {
        return substr(hash_hmac('sha256', (string)$payload, self::key()), 0, 32);
    }

    public static function verifySignature($payload, $signature)
    {
        return is_string($signature) && hash_equals(self::sign($payload), $signature);
    }

    private static function key()
    {
        $k = Config::get('security.app_key', '');
        return $k !== '' ? $k : 'menugold-clave-no-configurada';
    }

    public static function randomToken($bytes = 24)
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    /** Código legible para pedidos: sin caracteres ambiguos. */
    public static function orderCode()
    {
        $alphabet = 'ACDEFGHJKLMNPQRTUVWXY3479';
        $out = '';
        for ($i = 0; $i < 6; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $out;
    }

    public static function clientIp()
    {
        $keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($keys as $k) {
            if (empty($_SERVER[$k])) { continue; }
            $val = explode(',', (string)$_SERVER[$k]);
            $ip = trim($val[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) { return $ip; }
        }
        return '0.0.0.0';
    }

    /** Cabeceras de seguridad aplicadas a toda respuesta HTML. */
    public static function sendHeaders($csp = true)
    {
        if (headers_sent()) { return; }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(self), microphone=(), camera=(self), payment=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        if (Url::scheme() === 'https') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        if ($csp) {
            $policy = array(
                "default-src 'self'",
                "base-uri 'self'",
                "object-src 'none'",
                "frame-ancestors 'self'",
                "form-action 'self'",
                "img-src 'self' data: blob:",
                "font-src 'self' data:",
                "style-src 'self' 'unsafe-inline'",
                "script-src 'self' 'unsafe-inline'",
                "connect-src 'self'",
                "manifest-src 'self'",
                "worker-src 'self'",
            );
            header('Content-Security-Policy: ' . implode('; ', $policy));
        }
    }

    /** Escape HTML por defecto en todas las vistas. */
    public static function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Limpia una cadena para usarla en atributos de URL internos. */
    public static function safeRedirect($target, $fallback = '/')
    {
        if (!is_string($target) || $target === '') { return Url::to($fallback); }
        if (preg_match('#^(https?:)?//#i', $target)) { return Url::to($fallback); }
        if (strpos($target, "\n") !== false || strpos($target, "\r") !== false) { return Url::to($fallback); }
        return Url::to($target);
    }
}
