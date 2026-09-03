<?php
declare(strict_types=1);

namespace App\Core;

final class App
{
    private static ?string $base = null;

    /** Ruta base de la instalación (vacía en raíz, "/carpeta" en subcarpeta). */
    public static function basePath(): string
    {
        if (self::$base !== null) {
            return self::$base;
        }
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $dir = rtrim(str_replace('/index.php', '', $script), '/');
        // Si el front-controller vive en /install/ o /cron/, subimos un nivel.
        foreach (['/install', '/cron', '/panel'] as $sub) {
            if (str_ends_with($dir, $sub)) {
                $dir = substr($dir, 0, -strlen($sub));
            }
        }
        return self::$base = ($dir === '/' ? '' : $dir);
    }

    public static function setBasePath(string $p): void
    {
        self::$base = rtrim($p, '/');
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }
        $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $proto === 'https';
    }

    public static function host(): string
    {
        $h = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
        return strtolower((string) preg_replace('/[^A-Za-z0-9\.\-:]/', '', $h));
    }

    public static function origin(): string
    {
        $cfg = (string) Config::get('site_url', '');
        if ($cfg !== '') {
            return rtrim($cfg, '/');
        }
        return (self::isHttps() ? 'https://' : 'http://') . self::host();
    }

    public static function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $dir = STORAGE_PATH . '/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        session_name('cotizapro_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => self::basePath() . '/',
            'domain'   => '',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '3600');
        session_start();

        // Expiración por inactividad: 30 minutos.
        $limit = 1800;
        if (isset($_SESSION['_last']) && (time() - (int) $_SESSION['_last']) > $limit) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['_expired'] = true;
        }
        $_SESSION['_last'] = time();
    }
}
