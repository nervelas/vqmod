<?php
declare(strict_types=1);

namespace App\Core;

final class Settings
{
    private static array $cache = [];
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded || !Database::isConnected()) {
            return;
        }
        try {
            foreach (Database::all('SELECT clave, valor FROM settings') as $r) {
                self::$cache[$r['clave']] = $r['valor'];
            }
            self::$loaded = true;
        } catch (\Throwable $e) {
            Logger::error('No se pudieron cargar las configuraciones', ['e' => $e->getMessage()]);
        }
    }

    public static function get(string $key, mixed $default = ''): mixed
    {
        self::load();
        $v = self::$cache[$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key, null);
        return is_numeric($v) ? (int)$v : $default;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $v = self::get($key, null);
        return is_numeric($v) ? (float)$v : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key, null);
        if ($v === null) {
            return $default;
        }
        return in_array((string)$v, ['1', 'true', 'si', 'on'], true);
    }

    public static function set(string $key, mixed $value, string $grupo = 'general'): void
    {
        $driver = Config::get('db.driver', 'mysql');
        if ($driver === 'sqlite') {
            Database::run(
                'INSERT INTO settings (clave, valor, grupo) VALUES (:c, :v, :g)
                 ON CONFLICT(clave) DO UPDATE SET valor = :v2',
                ['c' => $key, 'v' => (string)$value, 'g' => $grupo, 'v2' => (string)$value]
            );
        } else {
            Database::run(
                'INSERT INTO settings (clave, valor, grupo) VALUES (:c, :v, :g)
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
                ['c' => $key, 'v' => (string)$value, 'g' => $grupo]
            );
        }
        self::$cache[$key] = (string)$value;
    }

    public static function all(): array
    {
        self::load();
        return self::$cache;
    }

    public static function flush(): void
    {
        self::$cache = [];
        self::$loaded = false;
    }
}
