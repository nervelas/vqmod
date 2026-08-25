<?php
declare(strict_types=1);

namespace Fel\Web;

/**
 * Mensajes de una sola lectura entre peticiones.
 */
final class Flash
{
    public static function exito(string $mensaje): void
    {
        self::agregar('exito', $mensaje);
    }

    public static function error(string $mensaje): void
    {
        self::agregar('error', $mensaje);
    }

    public static function aviso(string $mensaje): void
    {
        self::agregar('aviso', $mensaje);
    }

    private static function agregar(string $tipo, string $mensaje): void
    {
        $_SESSION['flash'][] = ['tipo' => $tipo, 'texto' => $mensaje];
    }

    /** @return list<array{tipo:string,texto:string}> */
    public static function consumir(): array
    {
        $mensajes = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        return $mensajes;
    }
}
