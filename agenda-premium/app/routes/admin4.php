<?php
declare(strict_types=1);

use App\Controllers\Admin\ActivityController;
use App\Controllers\Admin\BackupController;
use App\Controllers\Admin\CommunicationsController;
use App\Controllers\Admin\LegalController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\SystemStatusController;
use App\Controllers\Admin\WizardController;
use App\Core\Router;

/** Sistema: solo rol administrador (área "settings"). */
return static function (Router $r): void {
    $o = ['auth' => true, 'area' => 'settings', 'roles' => ['admin']];

    $r->get('/admin/ajustes', [SettingsController::class, 'index'], $o);
    $r->post('/admin/ajustes', [SettingsController::class, 'save'], $o);

    $r->get('/admin/legal', [LegalController::class, 'index'], $o);
    $r->post('/admin/legal', [LegalController::class, 'save'], $o);
    $r->post('/admin/legal/restaurar', [LegalController::class, 'restore'], $o);
    $r->post('/admin/legal/retencion', [LegalController::class, 'retention'], $o);
    $r->get('/admin/legal/consentimientos.csv', [LegalController::class, 'consentsCsv'], $o);

    $r->get('/admin/comunicaciones', [CommunicationsController::class, 'index'], $o);
    $r->post('/admin/comunicaciones', [CommunicationsController::class, 'save'], $o);
    $r->post('/admin/comunicaciones/probar-correo', [CommunicationsController::class, 'testMail'], $o);
    $r->post('/admin/comunicaciones/probar-conexion', [CommunicationsController::class, 'testConnection'], $o);
    $r->post('/admin/comunicaciones/probar-whatsapp', [CommunicationsController::class, 'testWhatsapp'], $o);
    $r->post('/admin/comunicaciones/cron-token', [CommunicationsController::class, 'regenerateCron'], $o);

    $r->get('/admin/sistema', [SystemStatusController::class, 'index'], $o);
    $r->post('/admin/sistema/cron', [SystemStatusController::class, 'runCron'], $o);
    $r->post('/admin/sistema/cache', [SystemStatusController::class, 'clearCache'], $o);
    $r->post('/admin/sistema/correo-prueba', [SystemStatusController::class, 'testMail'], $o);
    $r->get('/admin/sistema/registro', [SystemStatusController::class, 'downloadLog'], $o);
    $r->post('/admin/sistema/registro/vaciar', [SystemStatusController::class, 'clearLog'], $o);

    $r->get('/admin/actividad', [ActivityController::class, 'index'], $o);
    $r->get('/admin/actividad/exportar', [ActivityController::class, 'export'], $o);

    $name = '{name:[A-Za-z0-9._\-]+}';
    $r->get('/admin/respaldo', [BackupController::class, 'index'], $o);
    $r->post('/admin/respaldo/crear', [BackupController::class, 'create'], $o);
    $r->get('/admin/respaldo/descargar/' . $name, [BackupController::class, 'download'], $o);
    $r->post('/admin/respaldo/eliminar/' . $name, [BackupController::class, 'delete'], $o);
    $r->post('/admin/respaldo/conservar', [BackupController::class, 'keep'], $o);
    $r->get('/admin/respaldo/exportar', [BackupController::class, 'exportConfig'], $o);
    $r->post('/admin/respaldo/importar', [BackupController::class, 'importPreview'], $o);
    $r->post('/admin/respaldo/importar/aplicar', [BackupController::class, 'importApply'], $o);

    $r->get('/admin/asistente', [WizardController::class, 'index'], $o);
    $r->post('/admin/asistente/terminar', [WizardController::class, 'finish'], $o);
    $r->post('/admin/asistente/{n:[1-5]}', [WizardController::class, 'step'], $o);
};
