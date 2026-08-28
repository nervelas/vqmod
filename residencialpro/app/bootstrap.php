<?php
declare(strict_types=1);

/**
 * ResidencialPro — arranque de la aplicación.
 */

if (!defined('RUTA_BASE')) {
    define('RUTA_BASE', dirname(__DIR__));
}
define('RPRO_VERSION', '1.0.2');

// --- Errores: nunca visibles al usuario, siempre en el registro ---------
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', RUTA_BASE . '/storage/logs/php-' . date('Y-m') . '.log');

require_once RUTA_BASE . '/app/Core/Autoloader.php';

use App\Core\Ajustes;
use App\Core\Auth;
use App\Core\Autoloader;
use App\Core\Config;
use App\Core\DB;
use App\Core\Log;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\Sesion;
use App\Core\Vista;

Autoloader::registrar('App', RUTA_BASE . '/app');
Autoloader::registrar('Vendor\\Pdf', RUTA_BASE . '/vendor/pdf');
Autoloader::registrar('Vendor\\Qr', RUTA_BASE . '/vendor/qr');
Autoloader::registrar('Vendor\\Xlsx', RUTA_BASE . '/vendor/xlsx');
Autoloader::registrar('Vendor\\Mailer', RUTA_BASE . '/vendor/mailer');
Autoloader::registrar('Vendor\\Push', RUTA_BASE . '/vendor/push');

require_once RUTA_BASE . '/app/helpers.php';

// --- Configuración -----------------------------------------------------
$archivoConfig = RUTA_BASE . '/config/config.php';
if (!is_file($archivoConfig)) {
    if (!str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/install')
        && !defined('RPRO_INSTALADOR')) {
        header('Location: ' . rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/') . '/install/');
        exit;
    }
    Config::cargar(['app' => ['nombre' => 'ResidencialPro'], 'db' => []]);
} else {
    Config::cargar(require $archivoConfig);
}

date_default_timezone_set((string) Config::get('app.zona', 'America/Guatemala'));
setlocale(LC_TIME, 'es_GT.UTF-8', 'es_ES.UTF-8', 'es_ES', 'spanish');

// --- Manejo de errores no capturados -----------------------------------
set_exception_handler(static function (\Throwable $e): void {
    Log::error(get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    if (Config::get('app.depurar', false) === true) {
        http_response_code(500);
        echo '<pre style="padding:20px;font:13px/1.5 monospace;background:#1b2019;color:#f3ede0">'
           . e($e->getMessage()) . "\n\n" . e($e->getTraceAsString()) . '</pre>';
        exit;
    }
    Respuesta::abortar(500, 'Ocurrió un error inesperado. El equipo técnico ya fue notificado.');
});

set_error_handler(static function (int $nivel, string $mensaje, string $archivo = '', int $linea = 0): bool {
    if ((error_reporting() & $nivel) === 0) {
        return false;
    }
    Log::error("PHP({$nivel}): {$mensaje} en {$archivo}:{$linea}");
    return true;
});

// --- Sesión y cabeceras ------------------------------------------------
Sesion::iniciar();
Respuesta::cabecerasSeguridad();

// --- Datos compartidos con las vistas ----------------------------------
if (DB::disponible()) {
    Vista::compartir('ajustes', Ajustes::todos());
}
Vista::compartir('usuario', Auth::usuario());
Vista::compartir('uriActual', Peticion::uri());
