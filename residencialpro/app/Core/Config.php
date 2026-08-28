<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static array $datos = [];

    public static function cargar(array $datos): void
    {
        self::$datos = $datos;
    }

    public static function get(string $clave, mixed $porDefecto = null): mixed
    {
        $partes = explode('.', $clave);
        $valor  = self::$datos;
        foreach ($partes as $p) {
            if (!is_array($valor) || !array_key_exists($p, $valor)) {
                return $porDefecto;
            }
            $valor = $valor[$p];
        }
        return $valor;
    }

    public static function todo(): array
    {
        return self::$datos;
    }
}
