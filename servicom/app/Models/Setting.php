<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** Ajustes globales de la plataforma (clave => valor). */
final class Setting
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $rows = DB::all('SELECT `key`, `value` FROM settings');
        $out = [];
        foreach ($rows as $r) {
            $out[$r['key']] = $r['value'];
        }
        return self::$cache = $out;
    }

    public static function get(string $key, string $default = ''): string
    {
        $all = self::all();
        return isset($all[$key]) && $all[$key] !== null ? (string) $all[$key] : $default;
    }

    public static function json(string $key, array $default = []): array
    {
        $v = self::get($key, '');
        if ($v === '') {
            return $default;
        }
        $d = json_decode($v, true);
        return is_array($d) ? $d : $default;
    }

    public static function set(string $key, string $value): void
    {
        DB::run('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$key, $value]);
        self::$cache = null;
    }

    public static function setMany(array $pairs): void
    {
        foreach ($pairs as $k => $v) {
            self::set((string) $k, (string) $v);
        }
    }
}
