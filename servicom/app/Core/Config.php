<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static array $data = [];
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        $file = CONFIG_PATH . '/config.php';
        self::$data = is_file($file) ? (array) require $file : [];
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        return self::$data[$key] ?? $default;
    }

    public static function installed(): bool
    {
        return is_file(CONFIG_PATH . '/config.php') && is_file(BASE_PATH . '/install/.lock');
    }

    public static function all(): array
    {
        self::load();
        return self::$data;
    }
}
