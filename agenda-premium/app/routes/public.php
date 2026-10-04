<?php
declare(strict_types=1);

use App\Controllers\Pub\BookingController;
use App\Controllers\Pub\HomeController;
use App\Controllers\Pub\ManageController;
use App\Controllers\Pub\PollController;
use App\Controllers\Pub\ReviewController;
use App\Controllers\Pub\RoutingController;
use App\Controllers\Pub\WaitlistController;
use App\Core\Router;

return static function (Router $r): void {
    // Nota: el enrutador no admite llaves anidadas ({32}); la longitud de 32 se verifica en cada controlador.
    $tok = '{token:[a-f0-9]+}';

    // Inicio, equipo y perfiles
    $r->get('/', [HomeController::class, 'index']);
    $r->get('/equipo', [HomeController::class, 'team']);
    $r->get('/h/{slug:[a-z0-9\-]+}', [HomeController::class, 'host']);
    $r->get('/privacidad', [HomeController::class, 'privacy']);
    $r->get('/terminos', [HomeController::class, 'terms']);

    // Reserva
    $r->get('/e/{slug:[a-z0-9\-]+}', [BookingController::class, 'page'], ['embed' => true]);
    $r->get('/_/slots', [BookingController::class, 'slots']);
    $r->post('/_/book', [BookingController::class, 'book'], ['csrf' => 'public', 'scope' => 'book']);
    $r->post('/_/track', [BookingController::class, 'track'], ['csrf' => 'public', 'scope' => 'book']);
    $r->post('/_/coupon', [BookingController::class, 'coupon'], ['csrf' => 'public', 'scope' => 'book']);
    $r->post('/_/upload', [BookingController::class, 'upload'], ['csrf' => 'public', 'scope' => 'book']);
    $r->post('/_/waitlist', [BookingController::class, 'waitlist'], ['csrf' => 'public', 'scope' => 'book']);

    // Gestión de la cita por el invitado
    $r->get('/reserva/' . $tok, [ManageController::class, 'show']);
    $r->post('/reserva/' . $tok . '/cancelar', [ManageController::class, 'cancel'], ['csrf' => 'public', 'scope' => 'manage']);
    $r->post('/reserva/' . $tok . '/reprogramar', [ManageController::class, 'reschedule'], ['csrf' => 'public', 'scope' => 'manage']);
    $r->post('/reserva/' . $tok . '/comprobante', [ManageController::class, 'proof'], ['csrf' => 'public', 'scope' => 'manage']);
    $r->get('/reserva/' . $tok . '/evento.ics', [ManageController::class, 'ics']);
    $r->get('/reserva/' . $tok . '/recibo', [ManageController::class, 'receipt']);

    // Enrutamiento, encuestas, lista de espera y reseñas
    $r->get('/enrutar/{slug:[a-z0-9\-]+}', [RoutingController::class, 'show']);
    $r->post('/enrutar/{slug:[a-z0-9\-]+}', [RoutingController::class, 'submit'], ['csrf' => 'public', 'scope' => 'book']);
    $r->get('/encuesta/' . $tok, [PollController::class, 'show']);
    $r->post('/encuesta/' . $tok, [PollController::class, 'vote'], ['csrf' => 'public', 'scope' => 'book']);
    $r->get('/espera/' . $tok, [WaitlistController::class, 'show']);
    $r->post('/espera/' . $tok . '/aceptar', [WaitlistController::class, 'accept'], ['csrf' => 'public', 'scope' => 'book']);
    $r->get('/resena/' . $tok, [ReviewController::class, 'show']);
    $r->post('/resena/' . $tok, [ReviewController::class, 'submit'], ['csrf' => 'public', 'scope' => 'book']);
};
