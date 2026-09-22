<?php
/**
 * Kaptor - Arranque de la aplicación.
 *
 * Todos los puntos de entrada (index.php, admin/*, api/*) incluyen este archivo.
 * Se encarga de: constantes de ruta, configuración, errores, zona horaria,
 * sesión segura, autocarga de clases, conexión PDO y ajustes del sitio.
 */
declare(strict_types=1);

if (defined('CR_ARRANCADO')) { return; }
define('CR_ARRANCADO', true);
define('CR_VERSION', '6.0.1');
define('CR_PHP_MINIMO', '8.0.0');

// --- Rutas base --------------------------------------------------------------
define('CR_RAIZ',      dirname(__DIR__));
define('CR_INCLUDES',  CR_RAIZ . '/includes');
define('CR_CONFIG',    CR_RAIZ . '/config');
define('CR_STORAGE',   CR_RAIZ . '/storage');
define('CR_UPLOADS',   CR_STORAGE . '/uploads');

// --- Requisito mínimo de PHP -------------------------------------------------
if (version_compare(PHP_VERSION, CR_PHP_MINIMO, '<')) {
    http_response_code(500);
    exit('Kaptor necesita PHP ' . CR_PHP_MINIMO . ' o superior. Este servidor usa PHP ' . PHP_VERSION . '.');
}

// --- Configuración -----------------------------------------------------------
$cr_config = CR_CONFIG . '/config.php';
if (!is_file($cr_config)) {
    // Sin configuración => hay que instalar. Los scripts de API responden JSON.
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(503);
            exit(json_encode(['ok' => false, 'error' => 'Kaptor todavia no esta instalado.']));
        }
        header('Location: install.php');
        exit;
    }
    return;
}
require_once $cr_config;

// --- Errores -----------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', CR_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
if (!is_dir(CR_STORAGE . '/logs')) { @mkdir(CR_STORAGE . '/logs', 0755, true); }
ini_set('error_log', CR_STORAGE . '/logs/php-error.log');

// --- Zona horaria y codificación --------------------------------------------
date_default_timezone_set(defined('CR_ZONA_HORARIA') && CR_ZONA_HORARIA ? CR_ZONA_HORARIA : 'UTC');
if (function_exists('mb_internal_encoding')) { mb_internal_encoding('UTF-8'); }

// --- Autocarga de clases -----------------------------------------------------
spl_autoload_register(static function (string $clase): void {
    $archivo = CR_INCLUDES . '/' . str_replace('\\', '/', $clase) . '.php';
    if (is_file($archivo)) { require_once $archivo; }
});

require_once CR_INCLUDES . '/funciones.php';

// --- Sesión ------------------------------------------------------------------
/**
 * Inicia la sesión usando una carpeta propia dentro de storage/.
 * En muchos hosting compartidos el directorio de sesiones del sistema no es
 * escribible y el login falla en silencio; con carpeta propia siempre funciona.
 */
function cr_iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) { return; }

    $dir = CR_STORAGE . '/sessions';
    if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
    if (is_dir($dir) && is_writable($dir)) {
        // Protege la carpeta por si quedara dentro del area pública.
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        }
        session_save_path($dir);
    }

    $seguro = cr_es_https();
    session_name('kaptor');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $seguro,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    @session_start();

    // Regenera el identificador cada 30 minutos para mitigar la fijacion de sesión.
    if (!isset($_SESSION['_creada'])) {
        $_SESSION['_creada'] = time();
    } elseif (time() - (int) $_SESSION['_creada'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_creada'] = time();
    }
}

// --- Conexión y ajustes ------------------------------------------------------
cr_iniciar_sesion();
BD::conectar();
Ajustes::cargar();
Esquema::actualizar();
Seguridad::cabeceras();
