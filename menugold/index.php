<?php
/**
 * MenuGold - Front Controller
 * Sistema de Menu Digital QR con Pedidos para Restaurantes
 *
 * Este es el unico punto de entrada publico de la aplicacion.
 */
declare(strict_types=1);

define('MG_START', microtime(true));
define('MG_ROOT', __DIR__);

// --- Version minima de PHP -------------------------------------------------
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    http_response_code(500);
    exit('MenuGold requiere PHP 8.0 o superior. Versión actual: ' . PHP_VERSION);
}

require MG_ROOT . '/app/Core/Autoloader.php';
\MenuGold\Core\Autoloader::register();

require MG_ROOT . '/app/Core/helpers.php';

// --- Sin configuracion => instalador --------------------------------------
if (!is_file(MG_ROOT . '/config/config.php')) {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (strpos($uri, '/install') === false) {
        header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/install/');
        exit;
    }
}

\MenuGold\Core\App::boot(MG_ROOT)->run();
