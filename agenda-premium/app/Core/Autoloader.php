<?php
declare(strict_types=1);

namespace App\Core;

/** Autoloader PSR-4 propio: App\Foo\Bar -> app/Foo/Bar.php */
final class Autoloader
{
    public static function register(string $baseDir): void
    {
        spl_autoload_register(static function (string $class) use ($baseDir): void {
            if (strncmp($class, 'App\\', 4) !== 0) {
                return;
            }
            $file = $baseDir . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }
}
