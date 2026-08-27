<?php
/**
 * EduPortal — Sistema Integral de Gestion para Colegios
 * Front controller unico. Todas las peticiones pasan por aqui.
 */
declare(strict_types=1);

define('BASE_PATH', __DIR__);
define('EDUPORTAL_VERSION', '1.0.0');

if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    http_response_code(500);
    exit('EduPortal requiere PHP 8.0 o superior. Version actual: ' . PHP_VERSION);
}

require BASE_PATH . '/app/Core/Autoloader.php';
$loader = new App\Core\Autoloader();
$loader->addNamespace('App', BASE_PATH . '/app');
$loader->addNamespace('Vendor', BASE_PATH . '/vendor');
$loader->register();

require BASE_PATH . '/app/Helpers/functions.php';

$app = new App\Core\App();
$app->boot();

$r = $app->router();
require BASE_PATH . '/app/rutas.php';

$app->run();
