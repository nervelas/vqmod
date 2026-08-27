<?php
declare(strict_types=1);

namespace App\Core;

final class Sesion
{
    private static bool $iniciada = false;

    public static function iniciar(): void
    {
        if (self::$iniciada || session_status() === PHP_SESSION_ACTIVE) {
            self::$iniciada = true;
            return;
        }
        $ruta = RUTA_BASE . '/storage/tmp/sesiones';
        if (!is_dir($ruta)) {
            @mkdir($ruta, 0700, true);
        }
        if (is_dir($ruta) && is_writable($ruta)) {
            session_save_path($ruta);
        }
        $https = Peticion::esHttps();
        session_name('rpro_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => Url::basePath() === '' ? '/' : Url::basePath() . '/',
            'domain'   => '',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.gc_maxlifetime', '3600');
        session_start();
        self::$iniciada = true;

        $limite = (int) (Config::get('sesion.minutos', 30)) * 60;
        $rol    = $_SESSION['usuario']['rol'] ?? '';
        if ($rol === 'garita') {
            $limite = 60 * 60 * 12;
        }
        if (isset($_SESSION['_actividad']) && (time() - (int) $_SESSION['_actividad']) > $limite) {
            self::destruir();
            session_start();
            $_SESSION['_expirada'] = true;
        }
        $_SESSION['_actividad'] = time();
    }

    public static function get(string $clave, mixed $porDefecto = null): mixed
    {
        return $_SESSION[$clave] ?? $porDefecto;
    }

    public static function set(string $clave, mixed $valor): void
    {
        $_SESSION[$clave] = $valor;
    }

    public static function quitar(string $clave): void
    {
        unset($_SESSION[$clave]);
    }

    public static function regenerar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destruir(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }

    public static function flash(string $tipo, string $mensaje): void
    {
        $_SESSION['_flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    }

    public static function tomarFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($f) ? $f : [];
    }

    public static function guardarViejos(array $datos): void
    {
        unset($datos['csrf_token'], $datos['password'], $datos['clave'], $datos['clave2']);
        $_SESSION['_viejos'] = $datos;
    }

    public static function viejo(string $campo, string $porDefecto = ''): string
    {
        $v = $_SESSION['_viejos'][$campo] ?? null;
        return $v === null ? $porDefecto : (is_array($v) ? '' : (string) $v);
    }

    public static function limpiarViejos(): void
    {
        unset($_SESSION['_viejos']);
    }
}
