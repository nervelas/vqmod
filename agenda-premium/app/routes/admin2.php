<?php
declare(strict_types=1);

use App\Controllers\Admin\EmbedController;
use App\Controllers\Admin\EventsController;
use App\Controllers\Admin\ExternalCalendarsController;
use App\Controllers\Admin\HolidaysController;
use App\Controllers\Admin\HostsController;
use App\Controllers\Admin\ResourcesController;
use App\Controllers\Admin\SchedulesController;
use App\Controllers\Admin\TeamsController;
use App\Controllers\Admin\TimeOffController;
use App\Controllers\Admin\UsersController;
use App\Core\Router;

return static function (Router $r): void {
    // Tipos de evento
    $ev = ['auth' => true, 'area' => 'events'];
    $r->get('/admin/eventos', [EventsController::class, 'index'], $ev);
    $r->post('/admin/eventos/orden', [EventsController::class, 'order'], $ev);
    $r->get('/admin/eventos/nuevo', [EventsController::class, 'create'], $ev);
    $r->post('/admin/eventos/nuevo', [EventsController::class, 'store'], $ev);
    $r->get('/admin/eventos/{id:\d+}/editar', [EventsController::class, 'edit'], $ev);
    $r->post('/admin/eventos/{id:\d+}/editar', [EventsController::class, 'update'], $ev);
    $r->post('/admin/eventos/{id:\d+}/duplicar', [EventsController::class, 'duplicate'], $ev);
    $r->post('/admin/eventos/{id:\d+}/enlace-unico', [EventsController::class, 'singleUse'], $ev);
    $r->post('/admin/eventos/{id:\d+}/pausar', [EventsController::class, 'toggle'], $ev);
    $r->post('/admin/eventos/{id:\d+}/eliminar', [EventsController::class, 'delete'], $ev);

    // Insertar y compartir
    $r->get('/admin/insertar', [EmbedController::class, 'index'], ['auth' => true, 'area' => 'embed']);

    // Anfitriones
    $team = ['auth' => true, 'area' => 'team'];
    $r->get('/admin/anfitriones', [HostsController::class, 'index'], $team);
    $r->get('/admin/anfitriones/nuevo', [HostsController::class, 'create'], $team);
    $r->post('/admin/anfitriones/nuevo', [HostsController::class, 'store'], $team);
    $r->get('/admin/anfitriones/{id:\d+}/editar', [HostsController::class, 'edit'], $team);
    $r->post('/admin/anfitriones/{id:\d+}/editar', [HostsController::class, 'update'], $team);
    $r->post('/admin/anfitriones/{id:\d+}/token', [HostsController::class, 'regenerateToken'], $team);
    $r->post('/admin/anfitriones/orden', [HostsController::class, 'order'], $team);

    // Equipos
    $r->get('/admin/equipos', [TeamsController::class, 'index'], $team);
    $r->get('/admin/equipos/nuevo', [TeamsController::class, 'create'], $team);
    $r->post('/admin/equipos/nuevo', [TeamsController::class, 'store'], $team);
    $r->get('/admin/equipos/{id:\d+}/editar', [TeamsController::class, 'edit'], $team);
    $r->post('/admin/equipos/{id:\d+}/editar', [TeamsController::class, 'update'], $team);
    $r->post('/admin/equipos/{id:\d+}/eliminar', [TeamsController::class, 'delete'], $team);

    // Usuarios y roles (solo administración)
    $us = ['auth' => true, 'area' => 'team', 'roles' => ['admin']];
    $r->get('/admin/usuarios', [UsersController::class, 'index'], $us);
    $r->post('/admin/usuarios/invitar', [UsersController::class, 'invite'], $us);
    $r->post('/admin/usuarios/{id:\d+}/rol', [UsersController::class, 'changeRole'], $us);
    $r->post('/admin/usuarios/{id:\d+}/activo', [UsersController::class, 'toggleActive'], $us);
    $r->post('/admin/usuarios/{id:\d+}/enlace', [UsersController::class, 'resetLink'], $us);

    // Horarios y ausencias (el anfitrión solo gestiona lo suyo)
    $av = ['auth' => true, 'area' => 'availability'];
    $r->get('/admin/horarios', [SchedulesController::class, 'index'], $av);
    $r->get('/admin/horarios/nuevo', [SchedulesController::class, 'create'], $av);
    $r->post('/admin/horarios/nuevo', [SchedulesController::class, 'store'], $av);
    $r->get('/admin/horarios/{id:\d+}/editar', [SchedulesController::class, 'edit'], $av);
    $r->post('/admin/horarios/{id:\d+}/editar', [SchedulesController::class, 'update'], $av);
    $r->post('/admin/horarios/{id:\d+}/predeterminado', [SchedulesController::class, 'makeDefault'], $av);
    $r->post('/admin/horarios/{id:\d+}/eliminar', [SchedulesController::class, 'delete'], $av);

    $r->get('/admin/ausencias', [TimeOffController::class, 'index'], $av);
    $r->post('/admin/ausencias/guardar', [TimeOffController::class, 'save'], $av);
    $r->post('/admin/ausencias/{id:\d+}/eliminar', [TimeOffController::class, 'delete'], $av);

    // Feriados y recursos (administración)
    $sc = ['auth' => true, 'area' => 'schedules', 'roles' => ['admin']];
    $r->get('/admin/feriados', [HolidaysController::class, 'index'], $sc);
    $r->post('/admin/feriados/guardar', [HolidaysController::class, 'save'], $sc);
    $r->post('/admin/feriados/{id:\d+}/activo', [HolidaysController::class, 'toggle'], $sc);
    $r->post('/admin/feriados/{id:\d+}/eliminar', [HolidaysController::class, 'delete'], $sc);
    $r->post('/admin/feriados/restablecer', [HolidaysController::class, 'reset'], $sc);
    $r->post('/admin/feriados/interruptor', [HolidaysController::class, 'master'], $sc);

    $r->get('/admin/recursos', [ResourcesController::class, 'index'], $sc);
    $r->post('/admin/recursos/guardar', [ResourcesController::class, 'save'], $sc);
    $r->post('/admin/recursos/{id:\d+}/eliminar', [ResourcesController::class, 'delete'], $sc);

    // Calendarios externos (el anfitrión solo los suyos)
    $cal = ['auth' => true, 'area' => 'calendars'];
    $r->get('/admin/calendarios', [ExternalCalendarsController::class, 'index'], $cal);
    $r->post('/admin/calendarios/guardar', [ExternalCalendarsController::class, 'save'], $cal);
    $r->post('/admin/calendarios/probar', [ExternalCalendarsController::class, 'test'], $cal);
    $r->post('/admin/calendarios/{id:\d+}/sincronizar', [ExternalCalendarsController::class, 'sync'], $cal);
    $r->post('/admin/calendarios/{id:\d+}/activo', [ExternalCalendarsController::class, 'toggle'], $cal);
    $r->post('/admin/calendarios/{id:\d+}/eliminar', [ExternalCalendarsController::class, 'delete'], $cal);
};
