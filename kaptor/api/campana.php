<?php
/**
 * Kaptor - API del motor de campañas (solo administradores).
 *
 * Acciones: paso (envía un lote), estado (consulta el progreso),
 * iniciar, pausar y cancelar.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

@set_time_limit(120);

$datos  = cr_cuerpo_json();
$accion = (string) ($datos['accion'] ?? '');

Seguridad::exigirCsrf((string) ($datos['csrf'] ?? ''), true);

if (!Auth::esAdmin()) {
    cr_json(['ok' => false, 'error' => 'Necesitas permisos de administrador.'], 403);
}
if (!Ajustes::activo('campanas_activas', true)) {
    cr_json(['ok' => false, 'error' => 'El módulo de campañas está desactivado en los ajustes.'], 403);
}

$id = (int) ($datos['campana_id'] ?? 0);
if ($id <= 0) { cr_json(['ok' => false, 'error' => 'Campaña no válida.'], 400); }

$campana = Campana::obtener($id);
if (!$campana) { cr_json(['ok' => false, 'error' => 'La campaña no existe.'], 404); }

try {
    switch ($accion) {
        case 'iniciar':
            $r = Campana::iniciar($id);
            if (!$r['ok']) { cr_json($r, 400); }
            cr_json(Campana::paso($id, 5));

        case 'paso':
            cr_json(Campana::paso($id, Ajustes::entero('paso_segundos', 20, 5, 60)));

        case 'pausar':
            Campana::pausar($id);
            cr_json(['ok' => true, 'estado' => 'pausada']);

        case 'cancelar':
            Campana::cancelar($id);
            cr_json(['ok' => true, 'estado' => 'cancelada']);

        case 'estado':
            $c = Campana::obtener($id);
            cr_json([
                'ok'         => true,
                'estado'     => (string) $c['estado'],
                'pendientes' => Campana::pendientes($id),
            ] + Campana::estadisticas($id));

        default:
            cr_json(['ok' => false, 'error' => 'Acción no reconocida.'], 400);
    }
} catch (Throwable $e) {
    error_log('Kaptor / campaña: ' . $e->getMessage());
    cr_json(['ok' => false, 'error' => 'Error durante el envío: ' . $e->getMessage()], 500);
}
