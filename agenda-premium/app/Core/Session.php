<?php
declare(strict_types=1);

namespace App\Core;

/** Sesiones endurecidas. Solo se inician en el panel y flujos autenticados (las páginas públicas no usan cookies). */
final class Session
{
    private static bool $started = false;

    public static function start(Request $req): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }
        $dir = APP_ROOT . '/storage/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', '28800');
        session_name('apsid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (defined('BASE_PATH') && BASE_PATH !== '') ? BASE_PATH . '/' : '/',
            'secure' => $req->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::$started = true;
        $idle = (int) Settings::get('session_idle_minutes', 120) * 60;
        $last = (int) ($_SESSION['_last'] ?? 0);
        if ($last > 0 && Clock::now() - $last > $idle) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['_last'] = Clock::now();
    }

    public static function started(): bool
    {
        return self::$started;
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (self::$started) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (!self::$started) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], true);
        }
        session_destroy();
        self::$started = false;
    }

    public static function flash(string $type, string $msg): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
    }

    public static function pullFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }
}
