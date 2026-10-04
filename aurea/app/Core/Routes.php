<?php
declare(strict_types=1);

namespace Aurea\Core;

use Aurea\Controllers as C;
use Aurea\Controllers\Admin as A;

final class Routes
{
    public static function register(Router $r): void
    {
        // Sitio público
        $r->get('/', [C\PublicController::class, 'home']);
        $r->get('/reservar', [C\PublicController::class, 'booking']);
        $r->get('/embed', [C\PublicController::class, 'booking']);
        $r->get('/equipo', [C\PublicController::class, 'team']);
        $r->get('/profesional/{slug:[a-z0-9\-]+}', [C\PublicController::class, 'professional']);
        $r->get('/privacidad', [C\PublicController::class, 'privacy']);
        $r->get('/terminos', [C\PublicController::class, 'terms']);
        $r->get('/sitemap.xml', [C\PublicController::class, 'sitemap']);
        $r->get('/robots.txt', [C\PublicController::class, 'robots']);
        $r->get('/ics/{token:[a-f0-9]{32}}.ics', [C\PublicController::class, 'icsFeed']);

        // API de reserva
        $r->get('/api/slots', [C\BookingApiController::class, 'slots']);
        $r->get('/api/days', [C\BookingApiController::class, 'days']);
        $r->post('/api/book', [C\BookingApiController::class, 'book']);
        $r->post('/api/coupon', [C\BookingApiController::class, 'coupon']);
        $r->post('/api/waitlist', [C\BookingApiController::class, 'waitlist']);

        // Gestión por token (sin login)
        $t = '{token:[a-f0-9]{32}}';
        $r->get('/cita/' . $t, [C\ManageController::class, 'show']);
        $r->get('/cita/' . $t . '/ics', [C\ManageController::class, 'ics']);
        $r->post('/cita/' . $t . '/confirmar', [C\ManageController::class, 'confirm']);
        $r->post('/cita/' . $t . '/cancelar', [C\ManageController::class, 'cancel']);
        $r->get('/cita/' . $t . '/reprogramar', [C\ManageController::class, 'rescheduleForm']);
        $r->post('/cita/' . $t . '/reprogramar', [C\ManageController::class, 'reschedule']);
        $r->post('/cita/' . $t . '/comprobante', [C\ManageController::class, 'receipt']);
        $r->get('/resena/' . $t, [C\ManageController::class, 'reviewForm']);
        $r->post('/resena/' . $t, [C\ManageController::class, 'reviewSave']);
        $r->get('/espera/' . $t, [C\ManageController::class, 'waitOffer']);
        $r->post('/espera/' . $t, [C\ManageController::class, 'waitAccept']);

        // Panel
        $r->get('/admin/login', [A\AuthController::class, 'loginForm']);
        $r->post('/admin/login', [A\AuthController::class, 'login']);
        $r->get('/admin/2fa', [A\AuthController::class, 'twoFactorForm']);
        $r->post('/admin/2fa', [A\AuthController::class, 'twoFactor']);
        $r->post('/admin/logout', [A\AuthController::class, 'logout']);
        $r->get('/admin/olvide', [A\AuthController::class, 'forgotForm']);
        $r->post('/admin/olvide', [A\AuthController::class, 'forgot']);
        $r->get('/admin/restablecer/' . $t, [A\AuthController::class, 'resetForm']);
        $r->post('/admin/restablecer/' . $t, [A\AuthController::class, 'reset']);
        $r->get('/admin/perfil', [A\AuthController::class, 'profile']);
        $r->post('/admin/perfil/clave', [A\AuthController::class, 'changePassword']);
        $r->post('/admin/perfil/2fa', [A\AuthController::class, 'toggle2fa']);

        $r->get('/admin', [A\DashboardController::class, 'index']);
        $r->get('/admin/onboarding', [A\DashboardController::class, 'onboarding']);
        $r->post('/admin/onboarding', [A\DashboardController::class, 'onboardingSave']);

        $r->get('/admin/agenda', [A\AgendaController::class, 'index']);
        $r->get('/admin/api/slots', [A\AgendaController::class, 'slots']);
        $r->get('/admin/citas', [A\AppointmentController::class, 'index']);
        $r->get('/admin/citas/nueva', [A\AppointmentController::class, 'create']);
        $r->post('/admin/citas', [A\AppointmentController::class, 'store']);
        $r->get('/admin/citas/{id:\d+}', [A\AppointmentController::class, 'show']);
        $r->post('/admin/citas/{id:\d+}/estado', [A\AppointmentController::class, 'status']);
        $r->post('/admin/citas/{id:\d+}/reprogramar', [A\AppointmentController::class, 'reschedule']);
        $r->post('/admin/citas/{id:\d+}/notas', [A\AppointmentController::class, 'notes']);
        $r->post('/admin/citas/{id:\d+}/pago', [A\AppointmentController::class, 'addPayment']);
        $r->post('/admin/pagos/{id:\d+}/estado', [A\AppointmentController::class, 'paymentStatus']);
        $r->get('/admin/citas/{id:\d+}/recibo', [A\AppointmentController::class, 'receipt']);
        $r->post('/admin/bloqueos', [A\AppointmentController::class, 'block']);
        $r->get('/admin/archivos/{id:\d+}', [A\ClientController::class, 'file']);

        $r->get('/admin/clientes', [A\ClientController::class, 'index']);
        $r->get('/admin/clientes/exportar', [A\ClientController::class, 'export']);
        $r->post('/admin/clientes/importar', [A\ClientController::class, 'import']);
        $r->get('/admin/clientes/nuevo', [A\ClientController::class, 'edit']);
        $r->get('/admin/clientes/{id:\d+}', [A\ClientController::class, 'show']);
        $r->get('/admin/clientes/{id:\d+}/editar', [A\ClientController::class, 'edit']);
        $r->post('/admin/clientes/guardar', [A\ClientController::class, 'save']);
        $r->post('/admin/clientes/{id:\d+}/nota', [A\ClientController::class, 'note']);
        $r->post('/admin/clientes/{id:\d+}/bloquear', [A\ClientController::class, 'block']);
        $r->post('/admin/clientes/{id:\d+}/archivo', [A\ClientController::class, 'upload']);
        $r->post('/admin/archivos/{id:\d+}/eliminar', [A\ClientController::class, 'deleteFile']);
        $r->post('/admin/clientes/{id:\d+}/paquete', [A\ClientController::class, 'assignPackage']);
        $r->get('/admin/clientes/{id:\d+}/datos', [A\ClientController::class, 'exportData']);
        $r->post('/admin/clientes/{id:\d+}/eliminar', [A\ClientController::class, 'delete']);

        // Catálogos (CRUD genérico)
        foreach (['servicios', 'categorias', 'sedes', 'feriados', 'ausencias', 'cupones', 'paquetes', 'formularios', 'plantillas', 'usuarios'] as $m) {
            $r->get('/admin/' . $m, [A\CatalogController::class, 'index']);
            $r->get('/admin/' . $m . '/nuevo', [A\CatalogController::class, 'form']);
            $r->get('/admin/' . $m . '/{id:\d+}', [A\CatalogController::class, 'form']);
            $r->post('/admin/' . $m . '/guardar', [A\CatalogController::class, 'save']);
            $r->post('/admin/' . $m . '/{id:\d+}/eliminar', [A\CatalogController::class, 'delete']);
        }
        $r->post('/admin/feriados/generar', [A\CatalogController::class, 'generateHolidays']);
        $r->post('/admin/plantillas/restaurar', [A\CatalogController::class, 'restoreTemplates']);

        $r->get('/admin/profesionales', [A\ProfessionalController::class, 'index']);
        $r->get('/admin/profesionales/nuevo', [A\ProfessionalController::class, 'form']);
        $r->get('/admin/profesionales/{id:\d+}', [A\ProfessionalController::class, 'form']);
        $r->post('/admin/profesionales/guardar', [A\ProfessionalController::class, 'save']);
        $r->post('/admin/profesionales/{id:\d+}/eliminar', [A\ProfessionalController::class, 'delete']);
        $r->post('/admin/profesionales/{id:\d+}/horario', [A\ProfessionalController::class, 'saveSchedule']);

        $r->get('/admin/pagos', [A\OpsController::class, 'payments']);
        $r->get('/admin/espera', [A\OpsController::class, 'waitlist']);
        $r->post('/admin/espera/{id:\d+}/accion', [A\OpsController::class, 'waitlistAction']);
        $r->get('/admin/resenas', [A\OpsController::class, 'reviews']);
        $r->post('/admin/resenas/{id:\d+}/accion', [A\OpsController::class, 'reviewAction']);
        $r->get('/admin/mensajes', [A\OpsController::class, 'messages']);
        $r->post('/admin/mensajes/{id:\d+}/accion', [A\OpsController::class, 'messageAction']);
        $r->get('/admin/reportes', [A\OpsController::class, 'reports']);
        $r->get('/admin/reportes/csv', [A\OpsController::class, 'reportCsv']);

        $r->get('/admin/ajustes', [A\SettingsController::class, 'index']);
        $r->post('/admin/ajustes', [A\SettingsController::class, 'save']);
        $r->post('/admin/ajustes/perfil-profesion', [A\SettingsController::class, 'applyPreset']);
        $r->get('/admin/compartir', [A\SettingsController::class, 'share']);
        $r->get('/admin/sistema', [A\SystemController::class, 'index']);
        $r->post('/admin/sistema/correo-prueba', [A\SystemController::class, 'testMail']);
        $r->post('/admin/sistema/cron', [A\SystemController::class, 'runCron']);
        $r->get('/admin/sistema/respaldo', [A\SystemController::class, 'backup']);
        $r->post('/admin/sistema/migrar', [A\SystemController::class, 'migrate']);
        $r->get('/admin/auditoria', [A\SystemController::class, 'auditLog']);
    }
}
