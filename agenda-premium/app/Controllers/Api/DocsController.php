<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Services\ApiDocs;

/** GET /api-docs: documentación pública de la API, generada desde el registro ApiDocs. */
final class DocsController extends Controller
{
    public function index(Request $req, array $p): Response
    {
        $groups = [];
        foreach (ApiDocs::endpoints() as $e) {
            $id = 'grupo-' . Str::slug($e['group']);
            $groups[$id] ??= ['id' => $id, 'title' => $e['group'], 'endpoints' => []];
            $groups[$id]['endpoints'][] = $e;
        }
        return $this->view('api/docs', [
            'title' => 'Documentación de la API',
            'description' => 'Referencia de la API REST v1: autenticación, límites, errores, endpoints y verificación de firmas de webhooks.',
            'styles' => ['css/api-docs.css'],
            'bodyClass' => 'api-docs-page',
            'groups' => array_values($groups),
            'baseUrl' => $req->baseUrl(),
            'apiBase' => $req->baseUrl() . ApiDocs::BASE,
            'rate' => max(1, Settings::int('api_rate_per_minute', 60)),
            'errorCodes' => ApiDocs::errorCodes(),
            'webhookEvents' => ApiDocs::webhookEvents(),
            'webhookExample' => ApiDocs::webhookExample(),
        ], 'layouts/public');
    }
}
