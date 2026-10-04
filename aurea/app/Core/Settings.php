<?php
declare(strict_types=1);

namespace Aurea\Core;

/** Configuración del negocio (tabla settings) con caché simple en archivo. */
final class Settings
{
    private static ?array $data = null;

    private static function cacheFile(): string
    {
        return AUREA_ROOT . '/storage/cache/settings.json';
    }

    public static function all(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }
        $f = self::cacheFile();
        if (is_file($f)) {
            // JSON (no include): evita que OPcache sirva una versión vieja de la configuración
            $d = json_decode((string)@file_get_contents($f), true);
            if (is_array($d)) {
                return self::$data = $d;
            }
        }
        $rows = Db::all('SELECT skey, svalue FROM settings');
        $d = [];
        foreach ($rows as $r) {
            $d[$r['skey']] = $r['svalue'];
        }
        @file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return self::$data = $d;
    }

    public static function get(string $key, $default = '')
    {
        $a = self::all();
        return array_key_exists($key, $a) && $a[$key] !== null ? $a[$key] : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int)self::get($key, $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return (bool)(int)self::get($key, $default ? 1 : 0);
    }

    public static function set(string $key, ?string $value): void
    {
        Db::exec('INSERT INTO settings (skey, svalue, updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE svalue=VALUES(svalue), updated_at=VALUES(updated_at)',
            [$key, $value, date('Y-m-d H:i:s')]);
        self::flush();
    }

    public static function setMany(array $kv): void
    {
        foreach ($kv as $k => $v) {
            Db::exec('INSERT INTO settings (skey, svalue, updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE svalue=VALUES(svalue), updated_at=VALUES(updated_at)',
                [(string)$k, $v === null ? null : (string)$v, date('Y-m-d H:i:s')]);
        }
        self::flush();
    }

    public static function flush(): void
    {
        self::$data = null;
        @unlink(self::cacheFile());
    }
}
