<?php
declare(strict_types=1);
/**
 * CotizaPro B2B — arranque de la aplicación.
 * Sin composer: autoload PSR-4 propio para el espacio de nombres App\.
 */

define('APP_START', microtime(true));
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('VENDOR_PATH', BASE_PATH . '/vendor');

// ---------------------------------------------------------------- autoload
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, 4) !== 0) {
        return;
    }
    $relative = substr($class, 4);
    $file = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/Core/helpers.php';

// ---------------------------------------------------------------- errores
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@mkdir(STORAGE_PATH . '/logs', 0755, true);
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

set_exception_handler(static function (\Throwable $e): void {
    \App\Core\ErrorHandler::handle($e);
});
set_error_handler(static function (int $no, string $str, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    throw new \ErrorException($str, 0, $no, $file, $line);
});
register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        \App\Core\ErrorHandler::fatal($e);
    }
});

date_default_timezone_set('America/Guatemala');
mb_internal_encoding('UTF-8');
