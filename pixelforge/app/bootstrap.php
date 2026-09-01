<?php
/**
 * PixelForge - arranque de la aplicación.
 * Define rutas, prepara almacenamiento, sesión segura y manejo de errores.
 */

declare(strict_types=1);

define('PF_VERSION', '1.0.0');
define('PF_ROOT', dirname(__DIR__));
define('PF_APP', PF_ROOT . '/app');
define('PF_STORAGE', PF_ROOT . '/storage');
define('PF_VIEWS', PF_ROOT . '/views');

// --- Errores: nunca en pantalla, siempre al log ---------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

require_once PF_APP . '/Logger.php';
require_once PF_APP . '/Support.php';
require_once PF_APP . '/Crypto.php';
require_once PF_APP . '/Store.php';
require_once PF_APP . '/StoreSqlite.php';
require_once PF_APP . '/StoreJson.php';
require_once PF_APP . '/Settings.php';
require_once PF_APP . '/Security.php';
require_once PF_APP . '/Imaging.php';
require_once PF_APP . '/Http.php';
require_once PF_APP . '/Providers.php';
require_once PF_APP . '/Forja.php';
require_once PF_APP . '/Zipper.php';

Logger::init(PF_STORAGE . '/logs/app.log');
ini_set('error_log', PF_STORAGE . '/logs/php.log');

set_error_handler(function (int $no, string $str, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $no)) {
        return true;
    }
    Logger::write('php', sprintf('[%d] %s en %s:%d', $no, $str, $file, $line));
    return true; // nada llega a la pantalla
});

set_exception_handler(function (Throwable $e): void {
    Logger::exception($e);
    Support::fatalScreen();
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        Logger::write('fatal', sprintf('%s en %s:%d', $err['message'], $err['file'], $err['line']));
        Support::fatalScreen();
    }
});

// --- Auto-instalación de carpetas ----------------------------------------
Support::prepareStorage();

// --- Sesión segura (carpeta propia: hostings compartidos) -----------------
Security::startSession();

// --- Servicios ------------------------------------------------------------
$GLOBALS['pf_store'] = Store::make();
$GLOBALS['pf_settings'] = new Settings($GLOBALS['pf_store']);

/** Acceso rápido al almacén. */
function pf_store(): Store
{
    return $GLOBALS['pf_store'];
}

/** Acceso rápido a los ajustes. */
function pf_settings(): Settings
{
    return $GLOBALS['pf_settings'];
}
