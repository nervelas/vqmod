<?php
declare(strict_types=1);

namespace App\Core;

final class Log
{
    public static function escribir(string $nivel, string $mensaje): void
    {
        $dir = RUTA_BASE . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $linea = sprintf(
            "[%s] %s: %s%s",
            date('Y-m-d H:i:s'),
            strtoupper($nivel),
            str_replace(["\r", "\n"], ' ', $mensaje),
            PHP_EOL
        );
        @file_put_contents($dir . '/app-' . date('Y-m') . '.log', $linea, FILE_APPEND | LOCK_EX);
    }

    public static function error(string $m): void { self::escribir('error', $m); }
    public static function info(string $m): void  { self::escribir('info', $m); }
    public static function aviso(string $m): void { self::escribir('aviso', $m); }
}
