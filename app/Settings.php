<?php
/**
 * Key/value platform settings, cached per request.
 */
class Settings
{
    private static array $cache = [];
    private static bool  $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        foreach (Database::all("SELECT setting_key, setting_value FROM settings") as $row) {
            self::$cache[$row['setting_key']] = $row['setting_value'];
        }
        self::$loaded = true;
    }

    public static function get(string $key, $default = null)
    {
        self::load();
        return self::$cache[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key, $default ? '1' : '0');
        return $v === '1' || $v === 1 || $v === true;
    }

    public static function set(string $key, $value, string $group = 'general'): void
    {
        Database::q(
            "INSERT INTO settings (setting_key, setting_group, setting_value)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
            [$key, $group, (string)$value]
        );
        self::$cache[$key] = (string)$value;
    }

    public static function setMany(array $pairs, string $group = 'general'): void
    {
        foreach ($pairs as $k => $v) {
            self::set($k, $v, $group);
        }
    }
}
