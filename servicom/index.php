<?php
declare(strict_types=1);

/**
 * CotizaPro B2B — punto de entrada único.
 * Todas las peticiones pasan por aquí (ver .htaccess).
 */

require __DIR__ . '/app/bootstrap.php';

use App\Core\App;
use App\Core\Config;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;

Config::load();

// Aún sin instalar: el instalador toma el control.
if (!Config::installed()) {
    $path = Request::path();
    if (!str_starts_with($path, '/install')) {
        header('Location: ' . url('/install/'), true, 302);
        exit;
    }
}

$router = new Router();
require __DIR__ . '/app/routes.php';

try {
    $router->dispatch(Request::method(), Request::path());
} catch (\Throwable $e) {
    ErrorHandler::handle($e);
}
