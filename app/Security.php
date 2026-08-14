<?php
/**
 * Session, CSRF and security-header helpers.
 */
class Security
{
    /** Start a hardened session. */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                 || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('FLSESSID');
        session_start();

        // Periodic session-id regeneration to mitigate fixation.
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        } elseif (time() - $_SESSION['_created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
    }

    /** Emit standard security headers. */
    public static function headers(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 1; mode=block');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        // A permissive but self-first CSP that still allows inline theming vars.
        header("Content-Security-Policy: default-src 'self'; "
             . "img-src 'self' data: blob:; "
             . "style-src 'self' 'unsafe-inline'; "
             . "script-src 'self' 'unsafe-inline'; "
             . "font-src 'self' data:; "
             . "object-src 'none'; base-uri 'self'; frame-ancestors 'self'");
    }

    // ---- CSRF ---------------------------------------------------------------

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::csrfToken()) . '">';
    }

    public static function checkCsrf(): bool
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return !empty($_SESSION['_csrf']) && is_string($token)
            && hash_equals($_SESSION['_csrf'], $token);
    }

    /** Enforce CSRF on POST; abort with 419 on failure. */
    public static function requireCsrf(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !self::checkCsrf()) {
            http_response_code(419);
            exit('Sesión expirada o token inválido. Recargue la página e intente de nuevo.');
        }
    }
}
