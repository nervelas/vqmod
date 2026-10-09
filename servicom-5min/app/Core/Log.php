<?php
declare(strict_types=1);
namespace S5\Core;

final class Log
{
    public static function audit(string $accion, string $detalle = '', ?int $orderId = null): void
    {
        try {
            Db::insert('audit_log', [
                'created_at' => Db::now(),
                'actor' => self::actor(),
                'action' => mb_substr($accion, 0, 80),
                'detail' => mb_substr($detalle, 0, 2000),
                'order_id' => $orderId,
                'ip' => Http::ip(),
            ]);
        } catch (\Throwable $e) {
            self::error('audit falló: ' . $e->getMessage());
        }
    }

    private static function actor(): string
    {
        if (PHP_SAPI === 'cli') {
            return 'cli';
        }
        return isset($_SESSION['uid']) ? 'dueño#' . (int) $_SESSION['uid'] : 'publico';
    }

    public static function error(string $msg, array $ctx = []): void
    {
        $dir = S5_ROOT . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $file = $dir . '/app.log';
        if (is_file($file) && filesize($file) > 5242880) {
            @rename($file, $dir . '/app.log.1');
        }
        $line = '[' . gmdate('Y-m-d H:i:s') . '] ' . $msg;
        if ($ctx) {
            $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
        @file_put_contents($dir . '/app.log', $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
