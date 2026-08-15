<?php
/**
 * Application bootstrap.
 * Loads configuration, database, helpers and starts a secure session.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('CONFIG_FILE', BASE_PATH . '/config/config.php');

// If not yet installed, send everyone to the installer (except the installer itself).
if (!file_exists(CONFIG_FILE)) {
    $self = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($self, '/install/') === false) {
        header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/install/');
        // Fallback absolute redirect
        if (!headers_sent()) {
            header('Location: /install/');
        }
        exit;
    }
    return;
}

$config = require CONFIG_FILE;
$GLOBALS['config'] = $config;

// Error display: never leak internals to visitors in production.
if (($config['app']['env'] ?? 'production') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

date_default_timezone_set($config['app']['timezone'] ?? 'America/Guatemala');

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Csrf.php';
require_once __DIR__ . '/Auth.php';

// Secure session configuration.
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') == 443)
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (session_status() === PHP_SESSION_NONE) {
    // On shared hosting the server's default session directory is sometimes not
    // writable, which breaks the admin login. Fall back to an app-local folder.
    $sessDir = BASE_PATH . '/storage/sessions';
    if (!is_dir($sessDir)) { @mkdir($sessDir, 0700, true); }
    if (is_dir($sessDir) && is_writable($sessDir)) {
        session_save_path($sessDir);
    }
    session_name($config['security']['session_name'] ?? 'fdv_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    @session_start();
}

// Boot the DB connection lazily; Settings uses it on demand.
try {
    Database::init($config['db']);
} catch (Throwable $e) {
    if (($config['app']['env'] ?? 'production') === 'development') {
        http_response_code(500);
        die('Database connection error: ' . e($e->getMessage()));
    }
    error_log('DB connection error: ' . $e->getMessage());
    http_response_code(500);
    die('El sitio no está disponible temporalmente. Intente más tarde.');
}
