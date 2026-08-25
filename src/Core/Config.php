<?php
declare(strict_types=1);

namespace Fel\Core;

/**
 * Configuracion global cargada desde config/config.php.
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $datos = [];
    private static bool $cargada = false;

    public static function cargar(?string $ruta = null): void
    {
        $ruta ??= dirname(__DIR__, 2) . '/config/config.php';

        if (!is_file($ruta)) {
            throw new \RuntimeException(
                "No existe el archivo de configuracion: {$ruta}. " .
                'Copie config/config.example.php a config/config.php y complete sus datos.'
            );
        }

        $datos = require $ruta;
        if (!is_array($datos)) {
            throw new \RuntimeException('config/config.php debe retornar un array.');
        }

        self::$datos = $datos;
        self::$cargada = true;
    }

    /** @param array<string,mixed> $datos */
    public static function establecer(array $datos): void
    {
        self::$datos = $datos;
        self::$cargada = true;
    }

    /**
     * Lee una clave con notacion de puntos: Config::get('emisor.nit').
     */
    public static function get(string $clave, mixed $porDefecto = null): mixed
    {
        if (!self::$cargada) {
            // get() es tolerante: permite usar utilidades (validadores, catalogos)
            // sin haber configurado la instalacion todavia. requerido() sigue siendo estricto.
            try {
                self::cargar();
            } catch (\RuntimeException) {
                self::$datos  = [];
                self::$cargada = true;
            }
        }

        $actual = self::$datos;
        foreach (explode('.', $clave) as $parte) {
            if (!is_array($actual) || !array_key_exists($parte, $actual)) {
                return $porDefecto;
            }
            $actual = $actual[$parte];
        }

        return $actual;
    }

    public static function requerido(string $clave): mixed
    {
        $valor = self::get($clave);
        if ($valor === null || $valor === '') {
            throw new \RuntimeException("Falta la configuracion obligatoria: {$clave}");
        }

        return $valor;
    }

    /** @return array<string,mixed> */
    public static function todo(): array
    {
        if (!self::$cargada) {
            self::cargar();
        }

        return self::$datos;
    }
}
