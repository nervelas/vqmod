<?php
/**
 * MenúGold · Tareas programadas
 *
 * Configúralo en cPanel → Trabajos cron:
 *   *\/10 * * * * curl -s "https://TUDOMINIO/cron/run.php?token=TU_TOKEN"
 *
 * Se encarga de:
 *   · Suspender restaurantes vencidos
 *   · Avisar por correo los vencimientos próximos
 *   · Cerrar pedidos abandonados
 *   · Limpiar sesiones, caché y límites de intentos
 *   · Respaldo automático semanal
 */
declare(strict_types=1);

define('MG_ROOT', dirname(__DIR__));
require MG_ROOT . '/app/Core/Autoloader.php';
\MenuGold\Core\Autoloader::register();
require MG_ROOT . '/app/Core/helpers.php';

use MenuGold\Core\App;
use MenuGold\Core\Backup;
use MenuGold\Core\DB;
use MenuGold\Core\Logger;
use MenuGold\Core\Mailer;
use MenuGold\Core\Setting;

if (!is_file(MG_ROOT . '/config/ajustes.json')) {
    http_response_code(503);
    exit("MenuGold no está instalado todavía.\n");
}

App::boot(MG_ROOT);

// --------------------------------------------------------------- acceso
$esCli = PHP_SAPI === 'cli';
$token = (string)($_GET['token'] ?? ($argv[1] ?? ''));
$esperado = (string)App::config('cron_token', '');

if (!$esCli) {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex');
    if ($esperado === '' || !hash_equals($esperado, $token)) {
        http_response_code(403);
        Logger::warn('Intento de cron con token invalido', ['ip' => client_ip()]);
        exit("Token inválido.\n");
    }
}

@set_time_limit(300);
$inicio = microtime(true);
$log = [];
$hoy = date('Y-m-d');

function tarea(string $nombre, callable $fn, array &$log): void
{
    try {
        $r = $fn();
        $log[] = '[OK]   ' . $nombre . ($r !== null && $r !== '' ? ': ' . $r : '');
    } catch (\Throwable $e) {
        $log[] = '[FALL] ' . $nombre . ': ' . $e->getMessage();
        Logger::error('Cron - ' . $nombre . ': ' . $e->getMessage());
    }
}

// =====================================================================
//  1. Restaurantes vencidos
// =====================================================================
tarea('Suspender vencidos', static function () use ($hoy) {
    $n = DB::ejecutar(
        "UPDATE restaurants SET estado='suspendido', actualizado=NOW()
         WHERE estado <> 'suspendido' AND vence_el IS NOT NULL AND vence_el < :h",
        ['h' => $hoy]
    );
    return $n > 0 ? $n . ' restaurante(s) suspendido(s)' : 'ninguno';
}, $log);

// =====================================================================
//  2. Avisos de vencimiento
// =====================================================================
tarea('Avisos de vencimiento', static function () use ($hoy) {
    $dias = max(0, (int)Setting::plat('aviso_vencimiento_dias', 7));
    if ($dias === 0) return 'desactivado';
    $limite = date('Y-m-d', strtotime("+{$dias} days"));

    $lista = DB::all(
        "SELECT r.id, r.nombre, r.slug, r.vence_el, u.email, u.nombre AS dueno
         FROM restaurants r
         LEFT JOIN users u ON u.restaurant_id = r.id AND u.rol = 'dueno' AND u.activo = 1
         WHERE r.estado = 'activo' AND r.vence_el IS NOT NULL
           AND r.vence_el BETWEEN :h AND :l
         GROUP BY r.id",
        ['h' => $hoy, 'l' => $limite]
    );

    $enviados = 0;
    foreach ($lista as $r) {
        if (empty($r['email'])) continue;
        $clave = 'aviso_venc_' . $r['vence_el'];
        if (Setting::get($clave, '', (int)$r['id']) === '1') continue;   // ya avisado

        $faltan = (int)ceil((strtotime((string)$r['vence_el']) - strtotime($hoy)) / 86400);
        $cuerpo = '<p>Hola ' . e((string)$r['dueno']) . ',</p>'
            . '<p>La suscripción de <strong>' . e((string)$r['nombre']) . '</strong> vence en <strong>'
            . $faltan . ' día(s)</strong> (' . dt((string)$r['vence_el'], 'd/m/Y') . ').</p>'
            . '<p>Renuévala para que tu menú siga recibiendo pedidos sin interrupción.</p>'
            . Mailer::boton('Contactar a soporte', App::url(''));
        if (Mailer::send((string)$r['email'], 'Tu suscripción vence pronto', $cuerpo, (int)$r['id'], (string)$r['dueno'])) {
            Setting::set($clave, '1', (int)$r['id']);
            $enviados++;
        }
    }
    return $enviados . ' aviso(s) enviado(s)';
}, $log);

// =====================================================================
//  3. Pedidos abandonados
// =====================================================================
tarea('Pedidos abandonados', static function () {
    // Pedidos "nuevos" de más de 6 horas que nadie tocó
    $n = DB::ejecutar(
        "UPDATE orders SET estado='anulado', motivo_anulacion='Anulado automáticamente por inactividad',
                actualizado=NOW()
         WHERE estado='nuevo' AND creado < DATE_SUB(NOW(), INTERVAL 6 HOUR)"
    );
    if ($n > 0) {
        DB::ejecutar("UPDATE order_items oi INNER JOIN orders o ON o.id = oi.order_id
                  SET oi.estado='anulado' WHERE o.estado='anulado' AND oi.estado <> 'anulado'");
    }
    // Mesas ocupadas sin pedidos abiertos
    $m = DB::ejecutar(
        "UPDATE tables t SET t.estado='libre', t.abierta_desde=NULL, t.mesero_id=NULL
         WHERE t.estado <> 'libre'
           AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.table_id = t.id
                           AND o.estado IN ('nuevo','preparando','listo','entregado'))
           AND (t.abierta_desde IS NULL OR t.abierta_desde < DATE_SUB(NOW(), INTERVAL 8 HOUR))"
    );
    // Llamadas al mesero muy antiguas
    DB::ejecutar("UPDATE waiter_calls SET estado='atendida', atendida_en=NOW()
              WHERE estado='pendiente' AND creado < DATE_SUB(NOW(), INTERVAL 3 HOUR)");
    return $n . ' pedido(s) anulado(s), ' . $m . ' mesa(s) liberada(s)';
}, $log);

// =====================================================================
//  4. Limpieza
// =====================================================================
tarea('Limpieza', static function () {
    DB::ejecutar('DELETE FROM rate_limits WHERE ventana_inicio < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    DB::ejecutar('DELETE FROM password_resets WHERE expira < DATE_SUB(NOW(), INTERVAL 2 DAY)');
    DB::ejecutar('DELETE FROM remember_tokens WHERE expira < NOW()');
    DB::ejecutar('DELETE FROM audit_log WHERE creado < DATE_SUB(NOW(), INTERVAL 18 MONTH)');

    $borrados = 0;
    foreach (['sessions' => 43200, 'cache' => 604800, 'tmp' => 3600] as $carpeta => $edad) {
        foreach (glob(MG_ROOT . '/storage/' . $carpeta . '/*') ?: [] as $f) {
            if (is_file($f) && filemtime($f) < time() - $edad && basename($f) !== '.htaccess') {
                @unlink($f);
                $borrados++;
            }
        }
    }
    // Registros de más de 90 días
    foreach (glob(MG_ROOT . '/storage/logs/*.log') ?: [] as $f) {
        if (filemtime($f) < time() - 7776000) { @unlink($f); $borrados++; }
    }
    return $borrados . ' archivo(s) temporales eliminados';
}, $log);

// =====================================================================
//  5. Respaldo semanal
// =====================================================================
tarea('Respaldo semanal', static function () {
    if (Setting::plat('backup_semanal', '1') !== '1') return 'desactivado';
    if ((int)date('w') !== 0) return 'solo los domingos';
    $ultimo = Setting::plat('backup_ultimo', '');
    if ($ultimo === date('Y-W')) return 'ya se hizo esta semana';
    $archivo = Backup::crear('semanal');
    Setting::setPlat('backup_ultimo', date('Y-W'));
    return basename($archivo) . ' (' . Backup::formatoPeso((int)filesize($archivo)) . ')';
}, $log);

// =====================================================================
//  6. Estadísticas de productos
// =====================================================================
tarea('Marcar novedades', static function () {
    // Quita la etiqueta "nuevo" a los platillos con más de 30 días
    $n = DB::ejecutar(
        "UPDATE products SET etiquetas = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', etiquetas, ','), ',nuevo,', ','))
         WHERE FIND_IN_SET('nuevo', etiquetas) AND creado < DATE_SUB(NOW(), INTERVAL 45 DAY)"
    );
    return $n . ' platillo(s) ya no son «nuevos»';
}, $log);

// =====================================================================
$duracion = round(microtime(true) - $inicio, 2);
$salida = "MenuGold · Tareas programadas\n"
    . date('Y-m-d H:i:s') . " · {$duracion}s\n"
    . str_repeat('-', 46) . "\n"
    . implode("\n", $log) . "\n";

Logger::info('Cron ejecutado en ' . $duracion . 's', ['tareas' => count($log)]);
echo $salida;
