<?php
/**
 * Tareas programadas de EduPortal.
 * Configure en cPanel un cron cada 15 minutos que invoque:
 * curl -s "https://TUDOMINIO/cron/run.php?token=SU_TOKEN"
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$esCli = PHP_SAPI === 'cli';

require BASE_PATH . '/app/Core/Autoloader.php';
$loader = new App\Core\Autoloader();
$loader->addNamespace('App', BASE_PATH . '/app');
$loader->addNamespace('Vendor', BASE_PATH . '/vendor');
$loader->register();
require BASE_PATH . '/app/Helpers/functions.php';

$cfgFile = BASE_PATH . '/config/config.php';
if (!is_file($cfgFile)) {
    http_response_code(503);
    exit("EduPortal no esta instalado.\n");
}
App\Core\Config::load(require $cfgFile);
date_default_timezone_set((string)App\Core\Config::get('timezone', 'America/Guatemala'));
App\Core\Database::connect((array)App\Core\Config::get('db', []));
App\Core\Settings::load();

$tokenEsperado = (string)App\Core\Settings::get('cron_token', '');
$token = $esCli
    ? (string)($argv[1] ?? '')
    : (string)($_GET['token'] ?? '');

if ($tokenEsperado === '' || !hash_equals($tokenEsperado, $token)) {
    http_response_code(403);
    App\Core\Logger::warn('Intento de cron con token invalido', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli']);
    exit("Token invalido.\n");
}

if (!$esCli) {
    header('Content-Type: text/plain; charset=utf-8');
}
set_time_limit(300);
$inicio = microtime(true);
$salida = [];

try {
    $moras = App\Models\Cobranza::actualizarMoras();
    $salida[] = "Moras actualizadas: {$moras}";

    $rec = App\Servicios\Recordatorios::procesar();
    $salida[] = "Recordatorios enviados: {$rec['enviados']} (omitidos: {$rec['omitidos']})";

    App\Core\RateLimit::purge();
    $salida[] = 'Intentos de acceso antiguos depurados';

    App\Core\Database::run(
        'DELETE FROM password_resets WHERE expira_en < :d',
        ['d' => date('Y-m-d H:i:s', time() - 86400)]
    );
    App\Core\Database::run(
        'DELETE FROM notificaciones WHERE leido_en IS NOT NULL AND creado_en < :d',
        ['d' => date('Y-m-d H:i:s', time() - 60 * 86400)]
    );

    // Respaldo automatico semanal (domingos, una vez al dia)
    if (App\Core\Settings::bool('backup_semanal', true) && (int)date('w') === 0) {
        $ultimo = App\Core\Database::value(
            'SELECT creado_en FROM cron_log WHERE tarea = :t ORDER BY id DESC LIMIT 1',
            ['t' => 'respaldo'],
            null
        );
        if ($ultimo === null || strtotime((string)$ultimo) < strtotime('today')) {
            $archivo = App\Servicios\Respaldo::generar();
            $salida[] = $archivo ? 'Respaldo semanal: ' . basename($archivo) : 'Respaldo semanal fallido';
            App\Core\Database::run(
                'INSERT INTO cron_log (tarea, detalle) VALUES (:t, :d)',
                ['t' => 'respaldo', 'd' => $archivo ? basename($archivo) : 'fallido']
            );
        }
    }

    $ms = (int)round((microtime(true) - $inicio) * 1000);
    App\Core\Database::run(
        'INSERT INTO cron_log (tarea, detalle) VALUES (:t, :d)',
        ['t' => 'run', 'd' => implode(' | ', $salida) . " ({$ms} ms)"]
    );
    echo implode("\n", $salida), "\nOK ({$ms} ms)\n";
} catch (Throwable $e) {
    App\Core\Logger::error('Fallo en el cron', ['e' => $e->getMessage()]);
    http_response_code(500);
    echo "Error al ejecutar las tareas programadas.\n";
}
