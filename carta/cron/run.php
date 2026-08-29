<?php
/**
 * MenúGold · tareas programadas.
 * cPanel → Cron Jobs, cada 10 minutos:
 *   *\/10 * * * * curl -s "https://TUDOMINIO/cron/run.php?token=XXXX"
 * También funciona desde la línea de comandos: php cron/run.php TOKEN
 */
declare(strict_types=1);

define('MG_ROOT', dirname(__DIR__));
define('MG_APP', MG_ROOT . '/app');
define('MG_STORAGE', MG_ROOT . '/storage');
define('MG_VERSION', '1.0.0');

require MG_APP . '/Core/Autoloader.php';
Autoloader::register();
Autoloader::addNamespace('MenuGold\\Core',   MG_APP . '/Core');
Autoloader::addNamespace('MenuGold\\Models', MG_APP . '/Models');
require MG_APP . '/Support/helpers.php';

use MenuGold\Core\Backup;
use MenuGold\Core\Config;
use MenuGold\Core\DB;
use MenuGold\Core\Logger;
use MenuGold\Core\RateLimiter;
use MenuGold\Models\Settings;

$cli = PHP_SAPI === 'cli';
if (!is_file(MG_ROOT . '/config/config.php')) {
    http_response_code(503);
    exit("MenúGold no está instalado.\n");
}
Config::load(require MG_ROOT . '/config/config.php');
date_default_timezone_set((string)Config::get('app.timezone', 'America/Guatemala'));

$expected = (string)Config::get('security.cron_token', '');
$given = $cli
    ? (isset($argv[1]) ? (string)$argv[1] : '')
    : (isset($_GET['token']) ? (string)$_GET['token'] : '');

if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(403);
    exit("Token inválido.\n");
}

if (!$cli) { header('Content-Type: text/plain; charset=UTF-8'); }

$done = array();
$start = microtime(true);

/* 1 · Limpieza del limitador de peticiones */
try {
    RateLimiter::prune();
    $done[] = 'rate_limits limpiados';
} catch (Throwable $e) { Logger::error('cron rate: ' . $e->getMessage()); }

/* 2 · Llamadas al mesero muy viejas se cierran solas */
try {
    $n = DB::run("UPDATE mg_service_calls SET status='done', resolved_at=NOW()
                  WHERE status='open' AND created_at < DATE_SUB(NOW(), INTERVAL 3 HOUR)")->rowCount();
    if ($n > 0) { $done[] = $n . ' llamadas cerradas por antigüedad'; }
} catch (Throwable $e) { Logger::error('cron calls: ' . $e->getMessage()); }

/* 3 · Mesas ocupadas sin pedidos activos vuelven a estar libres */
try {
    $n = DB::run("UPDATE mg_tables t SET t.status='free'
                  WHERE t.status <> 'free' AND NOT EXISTS (
                      SELECT 1 FROM mg_orders o
                      WHERE o.table_id = t.id AND o.status IN ('new','cooking','ready','served'))")->rowCount();
    if ($n > 0) { $done[] = $n . ' mesas liberadas'; }
} catch (Throwable $e) { Logger::error('cron tables: ' . $e->getMessage()); }

/* 4 · Bitácora: se conservan 90 días */
try {
    $n = DB::run("DELETE FROM mg_audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)")->rowCount();
    if ($n > 0) { $done[] = $n . ' entradas de bitácora purgadas'; }
} catch (Throwable $e) { Logger::error('cron audit: ' . $e->getMessage()); }

/* 5 · Respaldo semanal automático */
try {
    $last = (int)Settings::get('last_backup_ts', '0');
    if (time() - $last > 7 * 86400) {
        $file = Backup::create(8);
        Settings::set('last_backup_ts', (string)time());
        $done[] = 'respaldo creado: ' . basename($file);
    }
} catch (Throwable $e) { Logger::error('cron backup: ' . $e->getMessage()); }

/* 6 · Archivos de sesión y caché antiguos */
try {
    $removed = 0;
    foreach (array(MG_STORAGE . '/sessions', MG_STORAGE . '/cache/qr') as $dir) {
        if (!is_dir($dir)) { continue; }
        foreach ((array)@scandir($dir) as $f) {
            if ($f === '.' || $f === '..' || $f === '.htaccess') { continue; }
            $path = $dir . '/' . $f;
            if (is_file($path) && (time() - (int)@filemtime($path)) > 3 * 86400) {
                @unlink($path);
                $removed++;
            }
        }
    }
    if ($removed > 0) { $done[] = $removed . ' archivos temporales borrados'; }
} catch (Throwable $e) { Logger::error('cron tmp: ' . $e->getMessage()); }

/* 7 · Registros de más de tres meses */
try {
    foreach ((array)@glob(MG_STORAGE . '/logs/app-*.log') as $log) {
        if ((time() - (int)@filemtime($log)) > 90 * 86400) { @unlink($log); }
    }
} catch (Throwable $e) {}

Settings::set('last_cron_ts', (string)time());

$ms = round((microtime(true) - $start) * 1000);
echo "MenúGold cron · " . date('Y-m-d H:i:s') . " · " . $ms . " ms\n";
echo $done ? implode("\n", $done) . "\n" : "Sin tareas pendientes.\n";
