<?php
declare(strict_types=1);

/**
 * ResidencialPro — tareas programadas.
 *
 * Configure en cPanel → Trabajos cron, cada 15 minutos:
 *   * /15 * * * * curl -s "https://SUDOMINIO/cron/run.php?token=SU_TOKEN" >/dev/null 2>&1
 *
 * También funciona por línea de comandos:
 *   php /home/usuario/public_html/cron/run.php SU_TOKEN
 */

define('RUTA_BASE', dirname(__DIR__));
require_once RUTA_BASE . '/app/bootstrap.php';

use App\Controllers\CuotasControlador;
use App\Controllers\TableroControlador;
use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Config;
use App\Core\Correo;
use App\Core\DB;
use App\Core\Log;
use App\Core\Notificar;
use App\Core\Peticion;
use App\Models\Casa;
use App\Models\Cuota;

$esCli = PHP_SAPI === 'cli';
$token = $esCli ? (string) ($argv[1] ?? '') : Peticion::texto('token');
$esperado = (string) Config::get('cron.token', '');

if ($esperado === '' || !hash_equals($esperado, $token)) {
    if (!$esCli) {
        http_response_code(403);
    }
    Log::aviso('Cron: token inválido desde ' . ($esCli ? 'CLI' : Peticion::ip()));
    echo "Acceso denegado.\n";
    exit(1);
}

if (!$esCli) {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
}
@set_time_limit(300);

$inicio    = microtime(true);
$resultado = [];
$soloTarea = $esCli ? (string) ($argv[2] ?? '') : Peticion::texto('tarea');

/** ¿Ya se ejecutó esta tarea dentro del intervalo indicado? */
function yaCorrio(string $tarea, int $minutos): bool
{
    try {
        $ultima = DB::valor(
            'SELECT creado_en FROM cron_ejecuciones WHERE tarea = :t ORDER BY id DESC LIMIT 1',
            ['t' => $tarea]
        );
        return $ultima !== null && (time() - strtotime((string) $ultima)) < $minutos * 60;
    } catch (\Throwable) {
        return false;
    }
}

function registrar(string $tarea, string $texto): void
{
    try {
        DB::insertar('cron_ejecuciones', ['tarea' => $tarea, 'resultado' => mb_substr($texto, 0, 255)]);
    } catch (\Throwable $e) {
        Log::error('Cron registrar: ' . $e->getMessage());
    }
}

function tarea(string $nombre, int $cadaMinutos, callable $fn): void
{
    global $resultado, $soloTarea;
    if ($soloTarea !== '' && $soloTarea !== $nombre) {
        return;
    }
    if ($soloTarea === '' && yaCorrio($nombre, $cadaMinutos)) {
        $resultado[] = sprintf('· %-22s omitida (ejecutada hace poco)', $nombre);
        return;
    }
    try {
        $texto = (string) $fn();
        registrar($nombre, $texto);
        $resultado[] = sprintf('✓ %-22s %s', $nombre, $texto);
    } catch (\Throwable $e) {
        Log::error('Cron ' . $nombre . ': ' . $e->getMessage());
        $resultado[] = sprintf('✗ %-22s ERROR: %s', $nombre, $e->getMessage());
    }
}

// ---------------------------------------------------------------- 1. MORA
tarea('mora', 55, static function (): string {
    $n = Cuota::recalcularMora();
    return $n . ' cargo(s) con mora actualizada.';
});

// ------------------------------------------------- 2. CARGOS DEL PERÍODO
tarea('cargos_mes', 60 * 20, static function (): string {
    if (!Ajustes::esVerdadero('generacion_automatica', true)) {
        return 'Generación automática desactivada.';
    }
    if ((int) date('j') !== 1) {
        return 'Solo se generan el día 1 de cada mes.';
    }
    $r = Cuota::generarPeriodo(date('Y-m'));
    if ($r['creados'] > 0) {
        Notificar::rol(['admin'], 'Cargos del mes generados',
            $r['creados'] . ' cargos por ' . q($r['monto']), '/admin/cargos', 'recibo');
    }
    return $r['creados'] . ' cargo(s) por ' . number_format($r['monto'], 2) . '.';
});

// ------------------------------------------------------- 3. RECORDATORIOS
tarea('recordatorios', 60 * 6, static function (): string {
    $previo = (int) Ajustes::num('recordatorio_previo_dias', 5);
    $cada   = max(1, (int) Ajustes::num('recordatorio_cada_dias', 7));
    $enviados = 0;

    // a) Aviso previo al vencimiento.
    if ($previo > 0) {
        $casas = DB::todos(
            'SELECT DISTINCT casa_id FROM cargos
             WHERE estado IN ("pendiente","parcial")
               AND fecha_vence = DATE_ADD(CURDATE(), INTERVAL :d DAY)',
            ['d' => $previo]
        );
        foreach ($casas as $c) {
            if (!yaAvisado((int) $c['casa_id'], 'previo', 3)
                && CuotasControlador::enviarRecordatorio((int) $c['casa_id'], 'previo')) {
                $enviados++;
            }
        }
    }

    // b) El día del vencimiento.
    foreach (DB::todos(
        'SELECT DISTINCT casa_id FROM cargos
         WHERE estado IN ("pendiente","parcial") AND fecha_vence = CURDATE()'
    ) as $c) {
        if (!yaAvisado((int) $c['casa_id'], 'vencimiento', 1)
            && CuotasControlador::enviarRecordatorio((int) $c['casa_id'], 'vencimiento')) {
            $enviados++;
        }
    }

    // c) Insistencia periódica en mora.
    foreach (DB::todos(
        'SELECT DISTINCT casa_id FROM cargos
         WHERE estado IN ("pendiente","parcial") AND fecha_vence < CURDATE()'
    ) as $c) {
        if (!yaAvisado((int) $c['casa_id'], 'recordatorio', $cada)
            && CuotasControlador::enviarRecordatorio((int) $c['casa_id'], 'recordatorio')) {
            $enviados++;
        }
    }
    return $enviados . ' recordatorio(s) enviados.';
});

/** ¿Ya se avisó a esta casa dentro de los últimos N días? */
function yaAvisado(int $casaId, string $tipo, int $dias): bool
{
    $r = DB::valor(
        'SELECT id FROM cobranza_log
         WHERE casa_id = :c AND tipo = :t AND creado_en > DATE_SUB(NOW(), INTERVAL :d DAY) LIMIT 1',
        ['c' => $casaId, 't' => $tipo, 'd' => max(1, $dias)]
    );
    return $r !== null && $r !== false;
}

// -------------------------------------------------------- 4. ESCALAMIENTO
tarea('escalamiento', 60 * 12, static function (): string {
    $diasCarta = (int) Ajustes::num('carta_dias', 60);
    $diasCorte = (int) Ajustes::num('corte_dias', 90);
    $cartas = 0;
    $cortes = 0;
    $liberadas = 0;

    foreach (Cuota::morosidad() as $m) {
        $casaId = (int) $m['id'];
        $dias   = (int) $m['dias'];

        if ($diasCarta > 0 && $dias >= $diasCarta && !yaAvisado($casaId, 'carta', 30)) {
            DB::insertar('cobranza_log', [
                'casa_id' => $casaId,
                'tipo'    => 'carta',
                'canal'   => 'sistema',
                'detalle' => 'Escalamiento automático: corresponde carta de cobro (' . $dias . ' días).',
                'saldo'   => (float) $m['saldo'],
            ]);
            $cartas++;
        }
        if ($diasCorte > 0 && $dias >= $diasCorte && (int) $m['restringida'] === 0) {
            DB::actualizar('casas', ['restringida' => 1], 'id = :id', ['id' => $casaId]);
            Notificar::rol(['admin'], 'Vivienda con restricción de servicios',
                'Casa ' . $m['codigo'] . ' — ' . $dias . ' días de mora', '/admin/morosidad', 'alerta');
            $cortes++;
        }
    }

    // Liberar viviendas que ya se pusieron al día.
    foreach (DB::todos('SELECT id FROM casas WHERE restringida = 1') as $c) {
        if (Casa::diasMora((int) $c['id']) < max(1, $diasCorte)) {
            DB::actualizar('casas', ['restringida' => 0], 'id = :id', ['id' => (int) $c['id']]);
            $liberadas++;
        }
    }
    if ($cartas + $cortes > 0) {
        Auditoria::registrar('escalamiento_mora', null, null, $cartas . ' cartas, ' . $cortes . ' restricciones');
    }
    return $cartas . ' carta(s), ' . $cortes . ' restricción(es), ' . $liberadas . ' liberada(s).';
});

// ------------------------------------------------------ 5. COLA DE CORREO
tarea('cola_correo', 10, static function (): string {
    $n = Correo::procesarCola(25);
    return $n . ' correo(s) enviados desde la cola.';
});

// ------------------------------------------------------ 6. MANTENIMIENTO
tarea('mantenimiento', 60 * 12, static function (): string {
    $acciones = [];

    $n = DB::q(
        'UPDATE preregistros SET estado = "vencido"
         WHERE estado = "activo" AND recurrente = 0 AND valido_hasta < NOW()'
    )->rowCount();
    $acciones[] = $n . ' pase(s) vencidos';

    $n = DB::q('DELETE FROM intentos_acceso WHERE creado_en < DATE_SUB(NOW(), INTERVAL 3 DAY)')->rowCount();
    $acciones[] = $n . ' intento(s) de acceso purgados';

    $n = DB::q('DELETE FROM notificaciones WHERE leido_en IS NOT NULL AND creado_en < DATE_SUB(NOW(), INTERVAL 60 DAY)')->rowCount();
    $acciones[] = $n . ' notificación(es) antiguas';

    $n = DB::q('DELETE FROM password_resets WHERE expira_en < DATE_SUB(NOW(), INTERVAL 2 DAY)')->rowCount();
    $acciones[] = $n . ' token(s) de recuperación';

    $n = DB::q('DELETE FROM codigos_2fa WHERE expira_en < DATE_SUB(NOW(), INTERVAL 1 DAY)')->rowCount();
    $acciones[] = $n . ' código(s) de verificación';

    $n = DB::q(
        'UPDATE reservas SET estado = "completada"
         WHERE estado = "aprobada" AND fecha < CURDATE()'
    )->rowCount();
    $acciones[] = $n . ' reserva(s) completadas';

    $n = DB::q('DELETE FROM cron_ejecuciones WHERE creado_en < DATE_SUB(NOW(), INTERVAL 90 DAY)')->rowCount();
    unset($n);

    // Sesiones de archivo antiguas.
    foreach (glob(RUTA_BASE . '/storage/tmp/sesiones/sess_*') ?: [] as $f) {
        if (filemtime($f) < time() - 86400) {
            @unlink($f);
        }
    }
    return implode(', ', $acciones) . '.';
});

// ----------------------------------------------------- 7. RESPALDO SEMANAL
tarea('respaldo', 60 * 24 * 7, static function (): string {
    if (!Ajustes::esVerdadero('respaldo_automatico', true)) {
        return 'Respaldo automático desactivado.';
    }
    $archivo = TableroControlador::crearRespaldo();
    return 'Respaldo creado: ' . basename($archivo);
});

// -------------------------------------------------- 8. CIERRE DE VOTACIONES
tarea('votaciones', 60 * 3, static function (): string {
    $n = DB::q('UPDATE votaciones SET estado = "cerrada" WHERE estado = "abierta" AND fin < NOW()')->rowCount();
    return $n . ' votación(es) cerradas.';
});

$segundos = round(microtime(true) - $inicio, 2);
echo "ResidencialPro · tareas programadas\n";
echo str_repeat('-', 62) . "\n";
echo implode("\n", $resultado) . "\n";
echo str_repeat('-', 62) . "\n";
echo 'Completado en ' . $segundos . " s el " . date('d/m/Y H:i:s') . "\n";
