<?php
declare(strict_types=1);

namespace App\Core;

final class Logger
{
    public static function write(string $level, string $message, array $context = []): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );
        @file_put_contents($dir . '/app-' . date('Y-m') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function error(string $m, array $c = []): void { self::write('error', $m, $c); }
    public static function info(string $m, array $c = []): void { self::write('info', $m, $c); }
    public static function warn(string $m, array $c = []): void { self::write('warning', $m, $c); }
}
