<?php
declare(strict_types=1);

/**
 * Arranque común (web y CLI). Define APP_ROOT y BASE_PATH, carga el autoloader PSR-4, las funciones auxiliares y la configuración.
 */
if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('Se requiere PHP 8.0 o superior.');
}

define('APP_ROOT', dirname(__DIR__));
define('AP_VERSION', '1.0.0');

if (!defined('BASE_PATH')) {
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = rtrim(dirname($script), '/');
    $dir = preg_replace('#/instalar$#', '', $dir) ?? $dir;
    define('BASE_PATH', $dir === '.' ? '' : $dir);
}

ini_set('display_errors', '0');
ini_set('log_errors', '0');
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');

require __DIR__ . '/Core/Autoloader.php';
\App\Core\Autoloader::register(__DIR__);
require __DIR__ . '/Core/Helpers.php';

\App\Core\Config::load((string) (getenv('AP_CONFIG') ?: APP_ROOT . '/config/config.php'));

set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    \App\Core\Logger::error('PHP[' . $no . '] ' . $str . ' @ ' . str_replace(APP_ROOT, '', $file) . ':' . $line);
    return true;
});

register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e !== null && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        \App\Core\Logger::error('Error fatal: ' . $e['message'] . ' @ ' . str_replace(APP_ROOT, '', $e['file']) . ':' . $e['line']);
    }
});
