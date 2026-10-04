<?php
declare(strict_types=1);

use App\Controllers\Admin\AnalyticsController;
use App\Controllers\Admin\ApiKeysController;
use App\Controllers\Admin\CouponsController;
use App\Controllers\Admin\GiftCardsController;
use App\Controllers\Admin\MessagesController;
use App\Controllers\Admin\PackagesController;
use App\Controllers\Admin\PaymentsController;
use App\Controllers\Admin\PollsController;
use App\Controllers\Admin\ReviewsController;
use App\Controllers\Admin\RoutingController;
use App\Controllers\Admin\WaitlistController;
use App\Controllers\Admin\WebhooksController;
use App\Controllers\Admin\WorkflowsController;
use App\Core\Router;

return static function (Router $r): void {
    $id = '{id:\d+}';
    $o = static fn (string $area): array => ['auth' => true, 'area' => $area];

    // --- Mensajes de hoy ---
    $r->get('/admin/mensajes', [MessagesController::class, 'index'], $o('messages'));
    $r->post("/admin/mensajes/$id/enviado", [MessagesController::class, 'sent'], $o('messages'));
    $r->post("/admin/mensajes/$id/descartar", [MessagesController::class, 'dismiss'], $o('messages'));

    // --- Pagos ---
    $r->get('/admin/pagos', [PaymentsController::class, 'index'], $o('payments'));
    $r->post("/admin/citas/$id/pagos", [PaymentsController::class, 'record'], $o('payments'));
    $r->post("/admin/pagos/$id/verificar", [PaymentsController::class, 'verify'], $o('payments'));
    $r->post("/admin/pagos/$id/rechazar", [PaymentsController::class, 'reject'], $o('payments'));
    $r->post("/admin/pagos/$id/reembolso", [PaymentsController::class, 'refund'], $o('payments'));
    $r->get("/admin/pagos/$id/recibo", [PaymentsController::class, 'receipt'], $o('payments'));

    // --- Paquetes, cupones y certificados ---
    $r->get('/admin/paquetes', [PackagesController::class, 'index'], $o('payments'));
    $r->post('/admin/paquetes/guardar', [PackagesController::class, 'save'], $o('payments'));
    $r->post('/admin/paquetes/vender', [PackagesController::class, 'sell'], $o('payments'));
    $r->post("/admin/paquetes/$id/estado", [PackagesController::class, 'toggle'], $o('payments'));
    $r->post("/admin/paquetes/$id/eliminar", [PackagesController::class, 'delete'], $o('payments'));

    $r->get('/admin/cupones', [CouponsController::class, 'index'], $o('payments'));
    $r->post('/admin/cupones/guardar', [CouponsController::class, 'save'], $o('payments'));
    $r->post("/admin/cupones/$id/estado", [CouponsController::class, 'toggle'], $o('payments'));
    $r->post("/admin/cupones/$id/eliminar", [CouponsController::class, 'delete'], $o('payments'));

    $r->get('/admin/certificados', [GiftCardsController::class, 'index'], $o('payments'));
    $r->post('/admin/certificados/crear', [GiftCardsController::class, 'create'], $o('payments'));
    $r->post("/admin/certificados/$id/estado", [GiftCardsController::class, 'toggle'], $o('payments'));
    $r->get("/admin/certificados/$id/imprimir", [GiftCardsController::class, 'print'], $o('payments'));

    // --- Flujos y recordatorios ---
    $r->get('/admin/flujos', [WorkflowsController::class, 'index'], $o('workflows'));
    $r->get('/admin/flujos/nuevo', [WorkflowsController::class, 'form'], $o('workflows'));
    $r->get('/admin/flujos/ejecuciones', [WorkflowsController::class, 'runs'], $o('workflows'));
    $r->post("/admin/flujos/ejecuciones/$id/reintentar", [WorkflowsController::class, 'retry'], $o('workflows'));
    $r->post('/admin/flujos/vista-previa', [WorkflowsController::class, 'preview'], $o('workflows'));
    $r->post('/admin/flujos/guardar', [WorkflowsController::class, 'save'], $o('workflows'));
    $r->get("/admin/flujos/$id/editar", [WorkflowsController::class, 'form'], $o('workflows'));
    $r->post("/admin/flujos/$id/duplicar", [WorkflowsController::class, 'duplicate'], $o('workflows'));
    $r->post("/admin/flujos/$id/estado", [WorkflowsController::class, 'toggle'], $o('workflows'));
    $r->post("/admin/flujos/$id/eliminar", [WorkflowsController::class, 'delete'], $o('workflows'));
    $r->post("/admin/flujos/$id/prueba", [WorkflowsController::class, 'test'], $o('workflows'));

    // --- Enrutamiento ---
    $r->get('/admin/enrutamiento', [RoutingController::class, 'index'], $o('routing'));
    $r->get('/admin/enrutamiento/nuevo', [RoutingController::class, 'form'], $o('routing'));
    $r->post('/admin/enrutamiento/guardar', [RoutingController::class, 'save'], $o('routing'));
    $r->get("/admin/enrutamiento/$id/editar", [RoutingController::class, 'form'], $o('routing'));
    $r->get("/admin/enrutamiento/$id/bitacora", [RoutingController::class, 'logs'], $o('routing'));
    $r->post("/admin/enrutamiento/$id/estado", [RoutingController::class, 'toggle'], $o('routing'));
    $r->post("/admin/enrutamiento/$id/eliminar", [RoutingController::class, 'delete'], $o('routing'));

    // --- Encuestas de horarios ---
    $r->get('/admin/encuestas', [PollsController::class, 'index'], $o('polls'));
    $r->get('/admin/encuestas/nueva', [PollsController::class, 'form'], $o('polls'));
    $r->post('/admin/encuestas/crear', [PollsController::class, 'create'], $o('polls'));
    $r->get("/admin/encuestas/$id", [PollsController::class, 'show'], $o('polls'));
    $r->post("/admin/encuestas/$id/cerrar", [PollsController::class, 'close'], $o('polls'));
    $r->post("/admin/encuestas/$id/finalizar", [PollsController::class, 'finalize'], $o('polls'));
    $r->post("/admin/encuestas/$id/eliminar", [PollsController::class, 'delete'], $o('polls'));

    // --- Lista de espera ---
    $r->get('/admin/espera', [WaitlistController::class, 'index'], $o('waitlist'));
    $r->get("/admin/espera/$id/ofrecer", [WaitlistController::class, 'offerForm'], $o('waitlist'));
    $r->post("/admin/espera/$id/ofrecer", [WaitlistController::class, 'offer'], $o('waitlist'));
    $r->post("/admin/espera/$id/cancelar", [WaitlistController::class, 'cancel'], $o('waitlist'));

    // --- Webhooks y API ---
    $r->get('/admin/webhooks', [WebhooksController::class, 'index'], $o('api'));
    $r->get('/admin/webhooks/nuevo', [WebhooksController::class, 'form'], $o('api'));
    $r->post('/admin/webhooks/guardar', [WebhooksController::class, 'save'], $o('api'));
    $r->get("/admin/webhooks/$id/editar", [WebhooksController::class, 'form'], $o('api'));
    $r->post("/admin/webhooks/$id/secreto", [WebhooksController::class, 'secret'], $o('api'));
    $r->post("/admin/webhooks/$id/estado", [WebhooksController::class, 'toggle'], $o('api'));
    $r->post("/admin/webhooks/$id/eliminar", [WebhooksController::class, 'delete'], $o('api'));
    $r->post("/admin/webhooks/$id/prueba", [WebhooksController::class, 'test'], $o('api'));
    $r->post("/admin/webhooks/entregas/$id/reenviar", [WebhooksController::class, 'resend'], $o('api'));

    $r->get('/admin/api', [ApiKeysController::class, 'index'], $o('api'));
    $r->post('/admin/api/claves', [ApiKeysController::class, 'create'], $o('api'));
    $r->post("/admin/api/claves/$id/revocar", [ApiKeysController::class, 'revoke'], $o('api'));

    // --- Analítica y reseñas ---
    $r->get('/admin/analitica', [AnalyticsController::class, 'index'], $o('analytics'));
    $r->get('/admin/analitica/exportar', [AnalyticsController::class, 'export'], $o('analytics'));

    $r->get('/admin/resenas', [ReviewsController::class, 'index'], $o('reviews'));
    $r->post('/admin/resenas/solicitar', [ReviewsController::class, 'request'], $o('reviews'));
    $r->post("/admin/resenas/$id/aprobar", [ReviewsController::class, 'approve'], $o('reviews'));
    $r->post("/admin/resenas/$id/rechazar", [ReviewsController::class, 'reject'], $o('reviews'));
};
