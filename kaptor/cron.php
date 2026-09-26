<?php
/**
 * Kaptor - Tarea programada.
 *
 * Empuja las campañas en marcha sin necesidad de tener el navegador abierto.
 * Es la forma recomendada de enviar: el ritmo lo marcan los límites por hora,
 * así que la campaña avanza sola durante días si hace falta.
 *
 * Configúralo en cPanel → Avanzado → Trabajos de cron, cada 5 minutos:
 *
 *   /usr/bin/php /home/USUARIO/public_html/cron.php CLAVE
 *
 * o, si tu hosting solo permite URLs:
 *
 *   curl -s "https://tudominio.com/cron.php?clave=CLAVE"
 *
 * La clave está en Ajustes → Campañas.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

@set_time_limit(300);
@ignore_user_abort(true);

$esConsola = PHP_SAPI === 'cli';
$clave = $esConsola
    ? (string) ($argv[1] ?? '')
    : (string) ($_GET['clave'] ?? '');

$esperada = Ajustes::obtener('cron_clave', '');

if ($esperada === '' || !hash_equals($esperada, $clave)) {
    if (!$esConsola) { http_response_code(403); }
    exit("Clave del cron incorrecta.\n");
}

if (!$esConsola) { header('Content-Type: text/plain; charset=utf-8'); }

// --- Limpieza de mantenimiento (una vez al día es suficiente) --------------
$retencion = Ajustes::entero('retencion_dias', 90, 0, 3650);
if ($retencion > 0 && random_int(1, 20) === 1) {
    BD::ejecutar('DELETE FROM `cr_escaneos` WHERE `inicio` < (NOW() - INTERVAL ' . $retencion . ' DAY)');
    BD::ejecutar('DELETE c FROM `cr_correos` c LEFT JOIN `cr_escaneos` e ON e.`id` = c.`escaneo_id` WHERE e.`id` IS NULL');
    BD::ejecutar('DELETE t FROM `cr_telefonos` t LEFT JOIN `cr_escaneos` e ON e.`id` = t.`escaneo_id` WHERE e.`id` IS NULL');
    BD::ejecutar('DELETE q FROM `cr_cola` q LEFT JOIN `cr_escaneos` e ON e.`id` = q.`escaneo_id` WHERE e.`id` IS NULL');
    echo "Mantenimiento: historial anterior a {$retencion} días eliminado.\n";
}

// Escaneos que se quedaron colgados
BD::ejecutar(
    'UPDATE `cr_escaneos` SET `estado` = \'cancelado\', `fin` = NOW()
     WHERE `estado` = \'ejecutando\' AND `inicio` < (NOW() - INTERVAL 30 MINUTE)'
);

// --- Campañas en marcha ----------------------------------------------------
if (!Ajustes::activo('campanas_activas', true)) {
    exit("El módulo de campañas está desactivado.\n");
}

$campanas = Campana::enMarcha();
if (!$campanas) {
    exit("No hay campañas en marcha.\n");
}

$presupuesto = max(10, (int) floor(240 / max(1, count($campanas))));

foreach ($campanas as $campana) {
    $id = (int) $campana['id'];
    try {
        $p = Campana::paso($id, $presupuesto);
        printf(
            "Campaña #%d «%s»: %d enviados ahora · %d/%d en total · %d pendientes · estado %s%s\n",
            $id,
            $campana['nombre'],
            (int) ($p['enviados_ahora'] ?? 0),
            (int) ($p['enviados'] ?? 0),
            (int) ($p['total'] ?? 0),
            (int) ($p['pendientes'] ?? 0),
            (string) ($p['estado'] ?? '?'),
            !empty($p['aviso']) ? ' · ' . $p['aviso'] : ''
        );
    } catch (Throwable $e) {
        error_log('Kaptor / cron campaña ' . $id . ': ' . $e->getMessage());
        printf("Campaña #%d: error %s\n", $id, $e->getMessage());
    }
}
