<?php
declare(strict_types=1);

/** Log a archivo con rotación simple. Nunca escribe en pantalla. */
final class Logger
{
    private static string $file = '';

    public static function init(string $file): void
    {
        self::$file = $file;
    }

    public static function write(string $channel, string $message, array $context = []): void
    {
        if (self::$file === '') {
            return;
        }
        $dir = dirname(self::$file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (is_file(self::$file) && @filesize(self::$file) > 2097152) {
            @rename(self::$file, self::$file . '.1');
        }
        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($channel),
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );
        @file_put_contents(self::$file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function exception(Throwable $e): void
    {
        self::write('error', get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    }

    /** Últimas N líneas del log, para el panel. */
    public static function tail(int $lines = 120): array
    {
        if (self::$file === '' || !is_file(self::$file)) {
            return [];
        }
        $content = @file(self::$file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($content)) {
            return [];
        }
        return array_slice($content, -$lines);
    }

    public static function clear(): void
    {
        if (self::$file !== '' && is_file(self::$file)) {
            @file_put_contents(self::$file, '');
        }
    }
}
