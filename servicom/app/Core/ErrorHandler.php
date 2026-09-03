<?php
declare(strict_types=1);

namespace App\Core;

final class ErrorHandler
{
    public static function log(string $message, array $context = []): void
    {
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
        if ($context) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        @file_put_contents($dir . '/app-' . date('Y-m') . '.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public static function handle(\Throwable $e): void
    {
        self::log('EXCEPTION ' . get_class($e) . ': ' . $e->getMessage(), [
            'file' => $e->getFile(), 'line' => $e->getLine(),
            'uri'  => $_SERVER['REQUEST_URI'] ?? 'cli',
        ]);
        self::log('TRACE: ' . $e->getTraceAsString());
        self::render(500);
    }

    public static function fatal(array $e): void
    {
        self::log('FATAL: ' . $e['message'], ['file' => $e['file'], 'line' => $e['line']]);
        if (!headers_sent()) {
            self::render(500);
        }
    }

    public static function render(int $code): void
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Error {$code}. Revise /storage/logs/.\n");
            exit(1);
        }
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: text/html; charset=utf-8');
        }
        $file = APP_PATH . '/Views/errors/' . $code . '.php';
        if (is_file($file)) {
            $title = $code === 404 ? 'No encontrado' : 'Error del servidor';
            include $file;
        } else {
            echo '<!doctype html><meta charset="utf-8"><title>Error</title><p>Ocurrió un error.</p>';
        }
        exit;
    }
}
