<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\ApiDocs;

/** Rutas /api/v1/... que no existen: 404 o 405 con el mismo formato JSON de toda la API. */
final class FallbackController extends ApiController
{
    protected function doNotFound(Request $req, array $p): Response
    {
        if (ApiDocs::knownPath($req->path)) {
            throw new ApiError('Este recurso no acepta el método ' . $req->method . '. Revisa la documentación en ' . abs_url('/api-docs') . '.', 'method_not_allowed', 405);
        }
        throw new ApiError('No existe ese recurso de la API. Revisa la documentación en ' . abs_url('/api-docs') . '.', 'not_found', 404);
    }
}
