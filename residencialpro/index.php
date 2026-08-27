<?php
declare(strict_types=1);

/**
 * ResidencialPro — punto de entrada único.
 * Sistema integral de administración de condominios y residenciales.
 */

require_once __DIR__ . '/app/bootstrap.php';

use App\Core\Peticion;

/** @var App\Core\Router $router */
$router = require __DIR__ . '/app/routes.php';
$router->despachar(Peticion::metodo(), Peticion::uri());
