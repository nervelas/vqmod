<?php
/**
 * MenúGold · controlador frontal.
 * Todo el tráfico entra por aquí (ver .htaccess).
 */
declare(strict_types=1);

define('MG_ROOT', __DIR__);
define('MG_APP', __DIR__ . '/app');
define('MG_STORAGE', __DIR__ . '/storage');
define('MG_VERSION', '1.0.0');

require MG_APP . '/Core/Autoloader.php';
Autoloader::register();
Autoloader::addNamespace('MenuGold\\Core',        MG_APP . '/Core');
Autoloader::addNamespace('MenuGold\\Models',      MG_APP . '/Models');
Autoloader::addNamespace('MenuGold\\Controllers', MG_APP . '/Controllers');
Autoloader::addNamespace('MenuGold\\Support',     MG_APP . '/Support');

require MG_APP . '/Support/helpers.php';

use MenuGold\Core\App;
use MenuGold\Core\Request;
use MenuGold\Core\Response;
use MenuGold\Core\Router;
use MenuGold\Core\Security;
use MenuGold\Core\Url;

// Aún sin instalar: se envía al asistente.
if (!App::isInstalled()) {
    if (is_dir(MG_ROOT . '/install')) {
        header('Location: ' . Url::to('/install/'));
        exit;
    }
    http_response_code(503);
    exit('MenúGold no está configurado y la carpeta /install fue eliminada.');
}

$router = new Router();
require MG_ROOT . '/config/routes.php';

$app = new App($router);
$app->boot();

$request = Request::capture();

// Cabeceras de seguridad en toda respuesta HTML.
Security::sendHeaders(true);

$response = $app->run($request);
if ($response instanceof Response) {
    $response->send();
}
