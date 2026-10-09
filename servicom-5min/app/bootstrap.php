<?php
declare(strict_types=1);

if (!defined('S5_ROOT')) {
    define('S5_ROOT', dirname(__DIR__));
}
if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('Se requiere PHP 8.0 o superior.');
}

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'S5\\', 3) !== 0) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, 3));
    $f = S5_ROOT . '/app/' . $rel . '.php';
    if (is_file($f)) {
        require $f;
    }
});

require_once S5_ROOT . '/app/helpers.php';

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

// Errores: nunca se muestran al público; se registran.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$__logdir = S5_ROOT . '/storage/logs';
if (!is_dir($__logdir)) {
    @mkdir($__logdir, 0750, true);
}
ini_set('error_log', $__logdir . '/php-error.log');
error_reporting(E_ALL);

set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    S5\Core\Log::error("PHP[$no] $str @ " . basename($file) . ":$line");
    return true;
});

set_exception_handler(static function (\Throwable $e): void {
    S5\Core\Log::error('Excepción: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    $isApi = isset($_SERVER['REQUEST_URI']) && str_starts_with((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/api/');
    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Ocurrió un problema. Inténtelo de nuevo en unos minutos.'], JSON_UNESCAPED_UNICODE);
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>Error</title><body style="font-family:sans-serif;padding:2rem"><h1>Algo salió mal</h1><p>Estamos trabajando en ello. Inténtelo de nuevo en unos minutos.</p></body>';
    }
});
