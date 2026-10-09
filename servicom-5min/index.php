<?php
declare(strict_types=1);
/** Front controller del portal "Tu web en 5 minutos". */
define('S5_ROOT', __DIR__);
require S5_ROOT . '/app/bootstrap.php';

use S5\Core\Config;
use S5\Core\Http;
use S5\Core\Router;
use S5\Core\Security;
use S5\Core\Session;

if (!Config::installed()) {
    if (is_file(S5_ROOT . '/install.php')) {
        header('Location: /install.php');
        exit;
    }
    http_response_code(503);
    exit('El sistema no está instalado.');
}

Security::headers();
Session::start();

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$path = '/' . trim(preg_replace('#/+#', '/', $path) ?? '/', '/');
if ($path === '') {
    $path = '/';
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$router = new Router();
require S5_ROOT . '/app/routes.php';

// Cron perezoso (respaldo del cron diario): como máximo una vez por hora, tras responder.
register_shutdown_function(static function (): void {
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        @litespeed_finish_request();
    }
    try {
        \S5\Services\Cron::lazy();
    } catch (\Throwable $e) {
        \S5\Core\Log::error('lazy cron: ' . $e->getMessage());
    }
});

if (!$router->dispatch($method, $path)) {
    http_response_code(404);
    \S5\Controllers\PortalController::notFound();
}
