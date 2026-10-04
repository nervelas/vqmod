<?php
declare(strict_types=1);

namespace App\Core;

/** Configuración técnica (config/config.php, generada por el instalador). */
final class Config
{
    private static array $data = [];
    private static bool $loaded = false;

    public static function load(string $file): void
    {
        self::$data = is_file($file) ? (array) (require $file) : [];
        self::$loaded = true;
    }

    public static function installed(): bool
    {
        return self::$loaded && !empty(self::$data['db']['name']);
    }

    /** Acceso con notación de puntos: Config::get('db.host') */
    public static function get(string $key, $default = null)
    {
        $cur = self::$data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                return $default;
            }
            $cur = $cur[$part];
        }
        return $cur;
    }

    public static function set(string $key, $value): void
    {
        $ref = &self::$data;
        foreach (explode('.', $key) as $part) {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
        $ref = $value;
    }
}
