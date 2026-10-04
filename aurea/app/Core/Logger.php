<?php
declare(strict_types=1);

namespace Aurea\Core;

final class Logger
{
    public static function write(string $level, string $msg, array $ctx = []): void
    {
        $dir = AUREA_ROOT . '/storage/logs';
        if (!is_dir($dir)) {
            return;
        }
        $file = $dir . '/' . ($level === 'error' ? 'error' : 'app') . '.log';
        if (is_file($file) && filesize($file) > 2_000_000) {
            @rename($file, $file . '.1');
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . strtoupper($level) . ' ' . str_replace(["\r", "\n"], ' ', $msg);
        if ($ctx) {
            $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
        @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    public static function error(string $msg, array $ctx = []): void { self::write('error', $msg, $ctx); }
    public static function info(string $msg, array $ctx = []): void { self::write('info', $msg, $ctx); }

    public static function exception(\Throwable $e): void
    {
        $where = [];
        foreach (array_slice($e->getTrace(), 0, 6) as $f) { if (isset($f['file']) && strpos($f['file'], 'Db.php') === false) { $where[] = basename($f['file']) . ':' . ($f['line'] ?? 0); } }
        self::error(get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . ($where ? ' <- ' . implode(' <- ', array_slice($where, 0, 3)) : ''));
    }
}
