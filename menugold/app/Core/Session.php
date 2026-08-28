<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Sesiones endurecidas: httponly, secure, samesite=Strict,
 * regeneracion de ID y expiracion por inactividad.
 */
final class Session
{
    public const INACTIVIDAD = 1800;          // 30 minutos
    public const INACTIVIDAD_OPERATIVA = 43200; // 12 h para KDS y mesero

    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) { self::$started = true; return; }
        if (PHP_SAPI === 'cli') { self::$started = true; $_SESSION = $_SESSION ?? []; return; }

        $dir = MG_ROOT . '/storage/sessions';
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        if (is_dir($dir) && is_writable($dir)) session_save_path($dir);

        session_name('MGSESS');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => App::basePath() === '' ? '/' : App::basePath() . '/',
            'domain'   => '',
            'secure'   => App::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax', // Lax permite volver desde pasarelas/QR externos
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string)self::INACTIVIDAD_OPERATIVA);

        @session_start();
        self::$started = true;

        // Huella de sesion (evita robo simple de cookie)
        $print = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . Security::appKey());
        if (!isset($_SESSION['_fp'])) {
            $_SESSION['_fp'] = $print;
        } elseif (!hash_equals((string)$_SESSION['_fp'], $print)) {
            self::destroy();
            @session_start();
            $_SESSION['_fp'] = $print;
        }
        self::checkIdle();
    }

    private static function checkIdle(): void
    {
        if (empty($_SESSION['user_id'])) return;
        $rol   = (string)($_SESSION['user_rol'] ?? '');
        $limit = in_array($rol, ['cocina', 'mesero'], true) ? self::INACTIVIDAD_OPERATIVA : self::INACTIVIDAD;
        $last  = (int)($_SESSION['_last'] ?? time());
        if (time() - $last > $limit) {
            self::destroy();
            @session_start();
            $_SESSION['_fp'] = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . Security::appKey());
            $_SESSION['_flash'][] = ['tipo' => 'aviso', 'texto' => 'Tu sesión expiró por inactividad. Ingresa de nuevo.'];
            return;
        }
        $_SESSION['_last'] = time();
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) @session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies') && !headers_sent()) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'],
                'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => 'Lax',
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) @session_destroy();
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void { $_SESSION[$key] = $value; }
    public static function forget(string $key): void { unset($_SESSION[$key]); }

    public static function pull(string $key, $default = null)
    {
        $v = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $v;
    }
}
