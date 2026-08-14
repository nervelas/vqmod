<?php
/**
 * CSRF protection using a per-session token.
 */

declare(strict_types=1);

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /** Hidden input field for forms. */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    /** Validate a submitted token (constant time). */
    public static function check(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    /** Enforce on POST requests; aborts on failure. */
    public static function verifyPost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { return; }
        if (!self::check($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            die('Sesión expirada o token inválido. Vuelva a cargar la página e intente de nuevo.');
        }
    }
}
