<?php
namespace MenuGold\Core;

/** Acceso a la configuración cargada desde /config/config.php */
final class Config
{
    /** @var array */
    private static $items = array();

    public static function load(array $items)
    {
        self::$items = $items;
    }

    public static function get($key, $default = null)
    {
        $parts = explode('.', $key);
        $node = self::$items;
        foreach ($parts as $p) {
            if (!is_array($node) || !array_key_exists($p, $node)) {
                return $default;
            }
            $node = $node[$p];
        }
        return $node;
    }

    public static function set($key, $value)
    {
        self::$items[$key] = $value;
    }

    public static function all()
    {
        return self::$items;
    }
}
