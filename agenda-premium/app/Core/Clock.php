<?php
declare(strict_types=1);

namespace App\Core;

/** Reloj central. Todo el sistema pregunta la hora aquí (permite simular fechas en pruebas). */
final class Clock
{
    private static ?int $fixed = null;

    public static function now(): int
    {
        return self::$fixed ?? time();
    }

    public static function set(?int $ts): void
    {
        self::$fixed = $ts;
    }

    /** Fecha/hora UTC "Y-m-d H:i:s" */
    public static function utc(?int $ts = null): string
    {
        return gmdate('Y-m-d H:i:s', $ts ?? self::now());
    }
}
