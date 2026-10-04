<?php
declare(strict_types=1);

use App\Controllers\Api\DocsController;
use App\Controllers\Api\FallbackController;
use App\Core\Router;
use App\Services\ApiDocs;

return static function (Router $r): void {
    $r->get('/api-docs', [DocsController::class, 'index']);
    ApiDocs::routes($r);
    // Cualquier otra ruta o método bajo /api/v1: 404/405 en el mismo formato JSON (va al final para que gane lo documentado)
    foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
        $r->add($method, '/api/v1/{rest:.*}', [FallbackController::class, 'notFound'], ['api' => 'read']);
    }
};
