<?php
declare(strict_types=1);
namespace S5\Core;

/** STUB de pruebas: acumula mensajes en memoria. */
class Log
{
    public static array $errores = [];
    public static array $auditoria = [];
    public static function audit(string $accion, string $detalle = '', ?int $orderId = null): void { self::$auditoria[] = [$accion, $detalle, $orderId]; }
    public static function error(string $msg, array $ctx = []): void { self::$errores[] = $msg; }
}
