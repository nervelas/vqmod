<?php
declare(strict_types=1);

namespace App\Core;

final class Url
{
    private static ?string $base = null;

    /** Ruta base cuando el sistema vive en un subdirectorio. '' o '/carpeta' */
    public static function basePath(): string
    {
        if (self::$base !== null) {
            return self::$base;
        }
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($dir === '.' || $dir === '/') {
            $dir = '';
        }
        // Si se ejecuta desde /install/ el base es el padre.
        if (str_ends_with($dir, '/install')) {
            $dir = substr($dir, 0, -8);
        }
        if (str_ends_with($dir, '/cron')) {
            $dir = substr($dir, 0, -5);
        }
        self::$base = $dir;
        return self::$base;
    }

    public static function raiz(): string
    {
        $esquema = Peticion::esHttps() ? 'https' : 'http';
        $host    = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $esquema . '://' . $host . self::basePath();
    }

    /** URL interna. a('/admin/cuotas') */
    public static function a(string $ruta = '/', array $query = []): string
    {
        $ruta = '/' . ltrim($ruta, '/');
        $u    = self::basePath() . ($ruta === '/' ? '/' : rtrim($ruta, '/'));
        if ($u === '') {
            $u = '/';
        }
        if ($query !== []) {
            $u .= '?' . http_build_query($query);
        }
        return $u;
    }

    public static function absoluta(string $ruta = '/'): string
    {
        $esquema = Peticion::esHttps() ? 'https' : 'http';
        $host    = (string) ($_SERVER['HTTP_HOST'] ?? Ajustes::get('sitio_host', 'localhost'));
        return $esquema . '://' . $host . self::a($ruta);
    }

    public static function activa(string $prefijo): bool
    {
        return str_starts_with(Peticion::uri(), $prefijo);
    }
}
