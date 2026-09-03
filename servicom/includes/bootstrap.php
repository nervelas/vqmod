<?php
declare(strict_types=1);

/**
 * Arranque de la aplicacion: configuracion, sesion segura, autoload y utilidades.
 */

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('Se requiere PHP 8.0 o superior. Version actual: ' . PHP_VERSION);
}

define('APP_ROOT', dirname(__DIR__));

$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    if (is_dir(APP_ROOT . '/install')) {
        header('Location: ' . ((defined('BASE_PATH') ? BASE_PATH : '') . '/install/'));
        exit;
    }
    http_response_code(500);
    exit('Falta config/config.php. Copie config/config.sample.php y complete los datos.');
}
require $configFile;

foreach ([['SITE_URL', 'http://localhost'], ['BASE_PATH', ''], ['APP_DEBUG', false],
          ['SESSION_NAME', 'servicom_sess'], ['DB_DRIVER', 'mysql'], ['DB_PORT', '3306'],
          ['DB_CHARSET', 'utf8mb4'], ['DB_FILE', ''], ['APP_KEY', 'servicom'],
          ['MAIL_TO', ''], ['MAIL_FROM', '']] as [$const, $default]) {
    if (!defined($const)) {
        define($const, $default);
    }
}

// --- Errores -----------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
if (!is_dir(APP_ROOT . '/storage')) {
    @mkdir(APP_ROOT . '/storage', 0755, true);
}
ini_set('error_log', APP_ROOT . '/storage/error.log');

// --- Zona horaria ------------------------------------------------------------
date_default_timezone_set('America/Guatemala');
setlocale(LC_ALL, 'es_GT.UTF-8', 'es_ES.UTF-8', 'es_GT', 'es');

// --- Autoload de clases ------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/icons.php';

// --- Sesion segura -----------------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionPath = APP_ROOT . '/storage/sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0700, true);
    }
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }

    $https = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_PATH === '' ? '/' : BASE_PATH . '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', '7200');
    session_start();
}

// --- Cabeceras de seguridad --------------------------------------------------
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), interest-cohort=()');
    header('X-XSS-Protection: 1; mode=block');
}

// --- Conexion a base de datos ------------------------------------------------
try {
    Database::pdo();
} catch (Throwable $ex) {
    http_response_code(503);
    error_log('[Servicom] Error de base de datos: ' . $ex->getMessage());
    if (APP_DEBUG) {
        exit('Error de base de datos: ' . e($ex->getMessage()));
    }
    exit('El sitio no esta disponible temporalmente. Intente de nuevo en unos minutos.');
}
