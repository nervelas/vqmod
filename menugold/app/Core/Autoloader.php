<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Autoloader PSR-4 propio (sin composer).
 *
 *   MenuGold\...     -> /app/...
 *   MenuGold\Vendor\ -> /vendor/...
 */
final class Autoloader
{
    /** @var array<string,string> prefijo => directorio base */
    private static array $prefixes = [];

    public static function register(): void
    {
        self::addNamespace('MenuGold\\Vendor\\', MG_ROOT . '/vendor');
        self::addNamespace('MenuGold\\', MG_ROOT . '/app');
        spl_autoload_register([self::class, 'load'], true, false);
    }

    public static function addNamespace(string $prefix, string $baseDir): void
    {
        self::$prefixes[$prefix] = rtrim($baseDir, '/\\') . '/';
        // Prefijos mas largos primero para que Vendor gane sobre el general
        uksort(self::$prefixes, static fn($a, $b) => strlen($b) <=> strlen($a));
    }

    public static function load(string $class): void
    {
        foreach (self::$prefixes as $prefix => $baseDir) {
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                continue;
            }
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
}
