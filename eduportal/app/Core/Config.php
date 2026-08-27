<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Configuracion inmutable cargada desde config/config.php
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $data = [];

    public static function load(array $data): void
    {
        self::$data = $data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $ref = self::$data;
        foreach ($parts as $p) {
            if (!is_array($ref) || !array_key_exists($p, $ref)) {
                return $default;
            }
            $ref = $ref[$p];
        }
        return $ref;
    }

    public static function all(): array
    {
        return self::$data;
    }
}
