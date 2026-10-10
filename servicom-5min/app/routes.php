<?php
declare(strict_types=1);
/** Tabla de rutas. $router, $path y $method vienen de index.php */

use S5\Controllers\AdminController;
use S5\Controllers\ApiController;
use S5\Controllers\FileController;
use S5\Controllers\PortalController;

// --- Portal público
$router->get('/', fn() => PortalController::home());
$router->get('/crear', fn() => PortalController::wizard());
$router->get('/guia-presentacion', fn() => PortalController::guide());
$router->get('/guia-presentacion/plantilla', fn() => PortalController::guideTemplate());
$router->get('/continuar/{token}', fn($p) => PortalController::wizard($p));
$router->get('/vista-previa/{token}', fn($p) => PortalController::status($p));
$router->get('/pago/{token}', fn($p) => PortalController::pay($p));
$router->get('/vp/{key}/{action}', fn($p) => PortalController::previewLink($p));
$router->get('/f/{token}/{id}', fn($p) => FileController::client($p));

// --- API
$router->post('/api/borrador', fn() => ApiController::start());
$router->get('/api/borrador/{token}', fn($p) => ApiController::get($p));
$router->get('/api/borrador/{token}/archivo/{id}', fn($p) => FileController::clientThumb($p));
$router->post('/api/borrador/{token}/guardar', fn($p) => ApiController::save($p));
$router->post('/api/borrador/{token}/subir', fn($p) => ApiController::upload($p));
$router->delete('/api/borrador/{token}/archivo/{id}', fn($p) => ApiController::deleteFile($p));
$router->post('/api/borrador/{token}/analizar', fn($p) => ApiController::analyze($p));
$router->get('/api/borrador/{token}/analisis', fn($p) => ApiController::analysisStatus($p));
$router->post('/api/borrador/{token}/omitir-presentacion', fn($p) => ApiController::skipPresentation($p));
$router->post('/api/borrador/{token}/confirmar-presentacion', fn($p) => ApiController::confirmPresentation($p));
$router->post('/api/borrador/{token}/auto-confirmar', fn($p) => ApiController::autoConfirm($p));
$router->post('/api/borrador/{token}/crear', fn($p) => ApiController::create($p));
$router->get('/api/borrador/{token}/construccion', fn($p) => ApiController::progress($p));
$router->post('/api/borrador/{token}/regenerar', fn($p) => ApiController::regenerate($p));
$router->post('/api/borrador/{token}/pagar', fn($p) => ApiController::pay($p));

// --- Panel del dueño
AdminController::routes($router);
