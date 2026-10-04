<?php
declare(strict_types=1);

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\BookingsController;
use App\Controllers\Admin\CalendarController;
use App\Controllers\Admin\ClientsController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\ProfileController;
use App\Controllers\Admin\SearchController;
use App\Core\Router;

return static function (Router $r): void {
    $tok = '{token:[a-f0-9]{32}}';

    // --- Acceso (sin sesión de usuario; el CSRF va ligado a la sesión anónima) ---
    $r->get('/admin/login', [AuthController::class, 'loginForm'], ['session' => true]);
    $r->post('/admin/login', [AuthController::class, 'login']);
    $r->get('/admin/2fa', [AuthController::class, 'twoFactorForm'], ['session' => true]);
    $r->post('/admin/2fa', [AuthController::class, 'twoFactor']);
    $r->post('/admin/salir', [AuthController::class, 'logout'], ['auth' => true]);
    $r->get('/admin/olvide', [AuthController::class, 'forgotForm'], ['session' => true]);
    $r->post('/admin/olvide', [AuthController::class, 'forgot']);
    $r->get('/admin/restablecer/' . $tok, [AuthController::class, 'resetForm'], ['session' => true]);
    $r->post('/admin/restablecer/' . $tok, [AuthController::class, 'reset']);
    $r->get('/admin/invitacion/' . $tok, [AuthController::class, 'inviteForm'], ['session' => true]);
    $r->post('/admin/invitacion/' . $tok, [AuthController::class, 'invite']);

    // --- Inicio ---
    $r->get('/admin', [DashboardController::class, 'index'], ['auth' => true, 'area' => 'dashboard']);

    // --- Calendario ---
    $r->get('/admin/calendario', [CalendarController::class, 'index'], ['auth' => true, 'area' => 'calendar']);
    $r->get('/admin/calendario/datos', [CalendarController::class, 'data'], ['auth' => true, 'area' => 'calendar']);

    // --- Citas ---
    $b = ['auth' => true, 'area' => 'bookings'];
    $r->get('/admin/citas', [BookingsController::class, 'index'], $b);
    $r->get('/admin/citas/exportar', [BookingsController::class, 'export'], $b);
    $r->get('/admin/citas/nueva', [BookingsController::class, 'createForm'], $b);
    $r->post('/admin/citas/nueva', [BookingsController::class, 'create'], $b);
    $r->get('/admin/slots', [BookingsController::class, 'slots'], $b);
    $r->get('/admin/citas/{id:\d+}', [BookingsController::class, 'show'], $b);
    $r->get('/admin/citas/{id:\d+}/ics', [BookingsController::class, 'ics'], $b);
    $r->post('/admin/citas/{id:\d+}/estado', [BookingsController::class, 'status'], $b);
    $r->post('/admin/citas/{id:\d+}/mover', [BookingsController::class, 'move'], $b);
    $r->post('/admin/citas/{id:\d+}/cancelar', [BookingsController::class, 'cancel'], $b);
    $r->post('/admin/citas/{id:\d+}/nota', [BookingsController::class, 'note'], $b);

    // --- Clientes ---
    $c = ['auth' => true, 'area' => 'clients'];
    $r->get('/admin/clientes', [ClientsController::class, 'index'], $c);
    $r->get('/admin/clientes/exportar', [ClientsController::class, 'export'], $c);
    $r->get('/admin/clientes/nuevo', [ClientsController::class, 'createForm'], $c);
    $r->post('/admin/clientes/nuevo', [ClientsController::class, 'create'], $c);
    $r->get('/admin/clientes/importar', [ClientsController::class, 'importForm'], $c);
    $r->post('/admin/clientes/importar', [ClientsController::class, 'importPreview'], $c);
    $r->post('/admin/clientes/importar/confirmar', [ClientsController::class, 'importConfirm'], $c);
    $r->get('/admin/clientes/duplicados', [ClientsController::class, 'duplicates'], $c);
    $r->get('/admin/clientes/fusionar', [ClientsController::class, 'mergeForm'], $c);
    $r->post('/admin/clientes/fusionar', [ClientsController::class, 'merge'], $c);
    $r->get('/admin/clientes/{id:\d+}', [ClientsController::class, 'show'], $c);
    $r->get('/admin/clientes/{id:\d+}/editar', [ClientsController::class, 'editForm'], $c);
    $r->post('/admin/clientes/{id:\d+}/editar', [ClientsController::class, 'update'], $c);
    $r->post('/admin/clientes/{id:\d+}/notas', [ClientsController::class, 'addNote'], $c);
    $r->post('/admin/clientes/{id:\d+}/notas/{nid:\d+}/eliminar', [ClientsController::class, 'deleteNote'], $c);
    $r->post('/admin/clientes/{id:\d+}/etiquetas', [ClientsController::class, 'tags'], $c);
    $r->post('/admin/clientes/{id:\d+}/archivo', [ClientsController::class, 'upload'], $c);
    $r->post('/admin/clientes/{id:\d+}/archivo/{fid:\d+}/eliminar', [ClientsController::class, 'deleteFile'], $c);
    $r->post('/admin/clientes/{id:\d+}/bloqueo', [ClientsController::class, 'block'], $c);
    $r->get('/admin/clientes/{id:\d+}/datos', [ClientsController::class, 'exportPerson'], $c + ['roles' => ['admin']]);
    $r->post('/admin/clientes/{id:\d+}/eliminar', [ClientsController::class, 'erase'], $c + ['roles' => ['admin']]);

    // --- Perfil ---
    $pf = ['auth' => true, 'area' => 'profile'];
    $r->get('/admin/perfil', [ProfileController::class, 'index'], $pf);
    $r->post('/admin/perfil', [ProfileController::class, 'update'], $pf);
    $r->post('/admin/perfil/clave', [ProfileController::class, 'password'], $pf);
    $r->post('/admin/perfil/2fa/iniciar', [ProfileController::class, 'twoFactorStart'], $pf);
    $r->post('/admin/perfil/2fa/activar', [ProfileController::class, 'twoFactorEnable'], $pf);
    $r->post('/admin/perfil/2fa/desactivar', [ProfileController::class, 'twoFactorDisable'], $pf);
    $r->post('/admin/perfil/tema', [ProfileController::class, 'theme'], $pf);

    // --- Búsqueda global y paleta de comandos ---
    $r->get('/admin/buscar', [SearchController::class, 'search'], ['auth' => true, 'area' => 'search']);
};
