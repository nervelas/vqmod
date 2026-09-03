<?php
declare(strict_types=1);

/** Configuracion editable del sitio (clave / valor en base de datos). */
final class Settings
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    private static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::all('SELECT `key`, `value` FROM settings') as $row) {
                self::$cache[(string) $row['key']] = (string) $row['value'];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        $all = self::load();
        $val = $all[$key] ?? '';
        return $val === '' ? $default : $val;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $all = self::load();
        if (!array_key_exists($key, $all) || $all[$key] === '') {
            return $default;
        }
        return in_array(strtolower($all[$key]), ['1', 'true', 'si', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key, (string) $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function set(string $key, string $value, string $group = 'general'): void
    {
        $exists = Database::value('SELECT COUNT(*) FROM settings WHERE `key` = :k', ['k' => $key], 0);
        if ((int) $exists > 0) {
            Database::update('settings', ['value' => $value], '`key` = :k', ['k' => $key]);
        } else {
            Database::insert('settings', ['key' => $key, 'value' => $value, 'group_name' => $group]);
        }
        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }

    /** @param array<string,string> $values */
    public static function setMany(array $values, string $group = 'general'): void
    {
        foreach ($values as $k => $v) {
            self::set($k, (string) $v, $group);
        }
    }

    /** @return array<string,string> */
    public static function group(string $group): array
    {
        $out = [];
        foreach (Database::all('SELECT `key`, `value` FROM settings WHERE group_name = :g ORDER BY id', ['g' => $group]) as $r) {
            $out[(string) $r['key']] = (string) $r['value'];
        }
        return $out;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
