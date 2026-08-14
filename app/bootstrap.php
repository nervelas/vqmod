<?php
/**
 * Application bootstrap. Loaded by every entry point.
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');       // never leak errors to visitors
ini_set('log_errors', '1');

define('FL_ROOT', dirname(__DIR__));
define('FL_APP', __DIR__);

// ---- Autoload the small class set ------------------------------------------
spl_autoload_register(function (string $class): void {
    $file = FL_APP . '/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require FL_APP . '/helpers.php';

// ---- Configuration ---------------------------------------------------------
$configFile = FL_APP . '/config.php';
if (!is_file($configFile)) {
    // Not installed yet: send everything to the installer.
    if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/install/') === false) {
        redirect(base_url('install/'));
    }
    define('FL_NOT_INSTALLED', true);
    return;
}

$config = require $configFile;
if (!empty($config['app']['base_url'])) {
    define('FL_BASE_URL', $config['app']['base_url']);
}
define('FL_SALT', $config['app']['security_salt'] ?? '');

// ---- Database --------------------------------------------------------------
try {
    Database::connect($config['db']);
} catch (Throwable $e) {
    http_response_code(500);
    exit('No se pudo conectar a la base de datos.');
}

// ---- Session + security ----------------------------------------------------
Security::startSession();
Security::headers();

// Warm the settings cache.
Settings::load();
