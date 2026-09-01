<?php
declare(strict_types=1);

/** Sesión, CSRF, cabeceras, autenticación y límites de uso. */
final class Security
{
    public const LOGIN_MAX_ATTEMPTS = 8;
    public const LOGIN_WINDOW = 900; // 15 minutos

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $sessionDir = PF_STORAGE . '/sessions';
        if (is_dir($sessionDir) && is_writable($sessionDir)) {
            session_save_path($sessionDir);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', '86400');
        $params = [
            'lifetime' => 0,
            'path' => Support::baseUrl() . '/',
            'httponly' => true,
            'secure' => Support::isHttps(),
            'samesite' => 'Lax',
        ];
        session_set_cookie_params($params);
        session_name('PIXELFORGE');
        if (!@session_start()) {
            Logger::write('session', 'No se pudo iniciar la sesión; se reintenta con la ruta por defecto');
            session_save_path(sys_get_temp_dir());
            @session_start();
        }
    }

    public static function headers(bool $allowFonts = true): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), interest-cohort=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        $csp = "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; "
            . "img-src 'self' data: blob:; script-src 'self'; object-src 'none'; ";
        $csp .= $allowFonts
            ? "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; "
            : "style-src 'self' 'unsafe-inline'; font-src 'self'; ";
        $csp .= "connect-src 'self'";
        header('Content-Security-Policy: ' . $csp);
        if (Support::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    // --- CSRF ---------------------------------------------------------------
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            try {
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
            } catch (Throwable $e) {
                $_SESSION['csrf'] = hash('sha256', uniqid('pf', true));
            }
        }
        return (string) $_SESSION['csrf'];
    }

    public static function checkCsrf(?string $token): bool
    {
        $expected = (string) ($_SESSION['csrf'] ?? '');
        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }

    /** Aborta la petición si el token no es válido. */
    public static function requireCsrf(?string $token): void
    {
        if (!self::checkCsrf($token)) {
            Logger::write('security', 'Token CSRF inválido desde ' . Support::clientIp());
            if (Support::wantsJson()) {
                Support::jsonError('Tu sesión caducó. Recarga la página e inténtalo de nuevo.', 419);
            }
            http_response_code(419);
            Support::redirect(Support::baseUrl() . '/index.php?error=csrf');
        }
    }

    // --- Autenticación ------------------------------------------------------
    public static function isLoggedIn(): bool
    {
        if (empty($_SESSION['auth'])) {
            return false;
        }
        $last = (int) ($_SESSION['auth_time'] ?? 0);
        if ($last > 0 && (time() - $last) > 86400 * 14) {
            self::logout();
            return false;
        }
        return true;
    }

    public static function login(): void
    {
        session_regenerate_id(true);
        $_SESSION['auth'] = true;
        $_SESSION['auth_time'] = time();
        unset($_SESSION['csrf']);
        self::csrfToken();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) ($params['secure'] ?? false), true);
        }
        session_destroy();
    }

    public static function requireAuth(): void
    {
        if (self::isLoggedIn()) {
            return;
        }
        if (Support::wantsJson()) {
            Support::jsonError('Debes iniciar sesión de nuevo.', 401);
        }
        Support::redirect(Support::baseUrl() . '/index.php');
    }

    // --- Límites ------------------------------------------------------------
    public static function loginBlocked(Store $store): bool
    {
        return $store->rateCount('login:' . Support::clientIp(), self::LOGIN_WINDOW) >= self::LOGIN_MAX_ATTEMPTS;
    }

    public static function loginFailed(Store $store): void
    {
        $store->rateHit('login:' . Support::clientIp(), self::LOGIN_WINDOW);
    }

    public static function loginSucceeded(Store $store): void
    {
        $store->rateReset('login:' . Support::clientIp());
    }

    /** true si aún hay cupo para generar. */
    public static function allowGeneration(Store $store, int $perHour): bool
    {
        if ($perHour <= 0) {
            return true;
        }
        return $store->rateHit('gen:' . Support::clientIp(), 3600) <= $perHour;
    }

    // --- Validación de entradas --------------------------------------------
    public static function str(string $key, string $default = '', int $maxLen = 4000, string $source = 'post'): string
    {
        $bag = $source === 'get' ? $_GET : $_POST;
        $value = $bag[$key] ?? $default;
        if (!is_string($value)) {
            return $default;
        }
        $value = str_replace(["\0", "\r"], '', $value);
        $value = trim($value);
        if (function_exists('mb_substr')) {
            $value = mb_substr($value, 0, $maxLen, 'UTF-8');
        } else {
            $value = substr($value, 0, $maxLen);
        }
        return $value;
    }

    public static function int(string $key, int $default, int $min, int $max, string $source = 'post'): int
    {
        $bag = $source === 'get' ? $_GET : $_POST;
        $raw = $bag[$key] ?? null;
        if ($raw === null || $raw === '' || !is_scalar($raw)) {
            return $default;
        }
        $value = (int) $raw;
        return max($min, min($max, $value));
    }

    public static function id(string $key, string $source = 'post'): string
    {
        $bag = $source === 'get' ? $_GET : $_POST;
        $value = $bag[$key] ?? '';
        if (!is_string($value)) {
            return '';
        }
        return preg_match('/^[a-f0-9\-]{6,64}$/i', $value) === 1 ? $value : '';
    }
}
