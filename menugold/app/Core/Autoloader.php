<?php
/**
 * Autoload PSR-4 propio. Sin composer en el servidor.
 */
final class Autoloader
{
    /** @var array<string,string> prefijo => directorio base */
    private static $map = array();

    public static function register()
    {
        spl_autoload_register(array(__CLASS__, 'load'), true, true);
    }

    public static function addNamespace($prefix, $baseDir)
    {
        self::$map[trim($prefix, '\\') . '\\'] = rtrim($baseDir, '/\\') . '/';
    }

    public static function load($class)
    {
        foreach (self::$map as $prefix => $baseDir) {
            $len = strlen($prefix);
            if (strncmp($class, $prefix, $len) !== 0) {
                continue;
            }
            $relative = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
                return true;
            }
        }
        return false;
    }
}
