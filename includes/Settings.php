<?php
/**
 * Site settings + content accessor.
 * Values are stored in the `settings` table as key/value pairs and cached per request.
 */

declare(strict_types=1);

class Settings
{
    private static array $cache = [];
    private static bool $loaded = false;

    private static function load(): void
    {
        if (self::$loaded) { return; }
        self::$cache = [];
        foreach (Database::all('SELECT `key`, `value` FROM settings') as $row) {
            self::$cache[$row['key']] = $row['value'];
        }
        self::$loaded = true;
    }

    /** Get a setting value with an optional default. */
    public static function get(string $key, $default = ''): string
    {
        self::load();
        $v = self::$cache[$key] ?? null;
        return ($v === null || $v === '') ? (string)$default : (string)$v;
    }

    /** Get raw (may be empty string). */
    public static function raw(string $key, $default = ''): string
    {
        self::load();
        return (string)(self::$cache[$key] ?? $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        self::load();
        if (!array_key_exists($key, self::$cache)) { return $default; }
        return in_array(strtolower((string)self::$cache[$key]), ['1', 'true', 'on', 'yes'], true);
    }

    /** Set (upsert) a setting. */
    public static function set(string $key, string $value, string $group = 'general'): void
    {
        $exists = Database::scalar('SELECT COUNT(*) FROM settings WHERE `key` = ?', [$key]);
        if ($exists) {
            Database::update('settings', ['value' => $value], ['key' => $key]);
        } else {
            Database::insert('settings', ['key' => $key, 'value' => $value, 'group_name' => $group]);
        }
        self::$cache[$key] = $value;
    }

    public static function all(): array
    {
        self::load();
        return self::$cache;
    }
}
