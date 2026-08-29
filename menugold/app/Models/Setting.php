<?php
namespace MenuGold\Models;

use MenuGold\Core\DB;

/** Ajustes globales de la plataforma (tabla settings). */
final class Setting
{
    /** @var array|null */
    private static $cache = null;

    private static function load()
    {
        if (self::$cache === null) {
            self::$cache = array();
            foreach (DB::all('SELECT skey, svalue FROM settings') as $r) {
                self::$cache[$r['skey']] = $r['svalue'];
            }
        }
    }

    public static function get($key, $default = '')
    {
        self::load();
        return array_key_exists($key, self::$cache) && self::$cache[$key] !== null ? self::$cache[$key] : $default;
    }

    public static function put($key, $value)
    {
        DB::run('INSERT INTO settings (skey, svalue) VALUES (:k, :v) ON DUPLICATE KEY UPDATE svalue = :v2',
            array('k' => $key, 'v' => $value, 'v2' => $value));
        self::load();
        self::$cache[$key] = $value;
    }

    public static function all()
    {
        self::load();
        return self::$cache;
    }

    public static function forget()
    {
        self::$cache = null;
    }
}
