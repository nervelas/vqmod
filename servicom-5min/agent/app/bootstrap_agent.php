<?php
declare(strict_types=1);
if (PHP_VERSION_ID < 80000) { http_response_code(500); exit('Se requiere PHP 8.0 o superior.'); }
if (!defined('S5_ROOT')) { define('S5_ROOT', dirname(__DIR__)); }
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'S5\\', 3) !== 0) { return; }
    $f = S5_ROOT . '/app/' . str_replace('\\', '/', substr($class, 3)) . '.php';
    if (is_file($f)) { require $f; }
});
ini_set('display_errors', '0');
mb_internal_encoding('UTF-8');
