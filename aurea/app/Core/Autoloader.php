<?php
declare(strict_types=1);

namespace Aurea\Core;

/** Autoloader PSR-4 propio: Aurea\Foo\Bar => app/Foo/Bar.php */
final class Autoloader
{
    public static function register(string $baseDir): void
    {
        spl_autoload_register(static function (string $class) use ($baseDir): void {
            $prefix = 'Aurea\\';
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                return;
            }
            $file = $baseDir . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }
}
