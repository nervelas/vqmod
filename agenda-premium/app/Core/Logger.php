<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/** Registro de errores en storage/logs/app.log (nunca se muestra al usuario). */
final class Logger
{
    public static function path(): string
    {
        return APP_ROOT . '/storage/logs/app.log';
    }

    public static function error(string $msg, ?Throwable $e = null): void
    {
        $line = '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . $msg;
        if ($e !== null) {
            $line .= ' | ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . str_replace(APP_ROOT, '', $e->getFile()) . ':' . $e->getLine();
        }
        self::write($line);
    }

    public static function info(string $msg): void
    {
        self::write('[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . $msg);
    }

    private static function write(string $line): void
    {
        $file = self::path();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (is_file($file) && filesize($file) > 2 * 1024 * 1024) {
            @rename($file, $file . '.1');
        }
        @file_put_contents($file, str_replace(["\r", "\n"], ' ', $line) . "\n", FILE_APPEND | LOCK_EX);
    }

    /** Últimas N líneas (para el panel). */
    public static function tail(int $n = 100): array
    {
        $file = self::path();
        if (!is_file($file)) {
            return [];
        }
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        return array_slice($lines, -$n);
    }
}
