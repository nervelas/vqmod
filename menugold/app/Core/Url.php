<?php
namespace MenuGold\Core;

/**
 * Rutas y URLs calculadas en tiempo de ejecución: la app funciona
 * igual en la raíz del dominio, en un subdirectorio o en un subdominio.
 */
final class Url
{
    /** @var string|null */
    private static $base = null;

    /** Ruta base (sin barra final). Cadena vacía si la app vive en la raíz. */
    public static function basePath()
    {
        if (self::$base === null) {
            $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
            $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
            if ($dir === '.' || $dir === '/') { $dir = ''; }
            self::$base = $dir;
        }
        return self::$base;
    }

    public static function setBasePath($p)
    {
        self::$base = rtrim($p, '/');
    }

    public static function scheme()
    {
        $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        return $https ? 'https' : 'http';
    }

    public static function host()
    {
        $h = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost');
        return preg_replace('/[^A-Za-z0-9\.\-:_]/', '', (string)$h);
    }

    /** URL absoluta de la raíz de la aplicación, sin barra final. */
    public static function root()
    {
        $cfg = Config::get('app.url');
        if ($cfg) { return rtrim($cfg, '/'); }
        return self::scheme() . '://' . self::host() . self::basePath();
    }

    /** URL relativa a la raíz de la app: to('/panel') => /subdir/panel */
    public static function to($path = '/')
    {
        $path = '/' . ltrim((string)$path, '/');
        return self::basePath() . ($path === '/' ? '/' : rtrim($path, '/'));
    }

    /** URL absoluta (para QR, correos, OpenGraph). */
    public static function abs($path = '/')
    {
        $path = '/' . ltrim((string)$path, '/');
        return self::root() . ($path === '/' ? '/' : rtrim($path, '/'));
    }

    /** Recurso estático con cache-busting por fecha de modificación. */
    public static function asset($path)
    {
        $path = ltrim((string)$path, '/');
        $file = MG_ROOT . '/' . $path;
        $v = is_file($file) ? substr((string)filemtime($file), -6) : '1';
        return self::basePath() . '/' . $path . '?v=' . $v;
    }

    public static function current()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        return self::scheme() . '://' . self::host() . $uri;
    }
}
