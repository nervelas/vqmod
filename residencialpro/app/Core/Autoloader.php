<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Autoload PSR-4 propio. Sin composer en el servidor.
 */
final class Autoloader
{
    /** @var array<string,string> prefijo => directorio base */
    private static array $mapa = [];

    public static function registrar(string $prefijo, string $baseDir): void
    {
        self::$mapa[trim($prefijo, '\\') . '\\'] = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
        static $registrado = false;
        if (!$registrado) {
            spl_autoload_register([self::class, 'cargar']);
            $registrado = true;
        }
    }

    public static function cargar(string $clase): void
    {
        foreach (self::$mapa as $prefijo => $base) {
            if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
                continue;
            }
            $relativa = substr($clase, strlen($prefijo));
            $archivo  = $base . str_replace('\\', DIRECTORY_SEPARATOR, $relativa) . '.php';
            if (is_file($archivo)) {
                require_once $archivo;
                return;
            }
        }
    }
}
