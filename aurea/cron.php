<?php
declare(strict_types=1);

/**
 * Tareas programadas de AUREA (recordatorios, cola de mensajes, lista de espera, pendientes, limpieza).
 *   CLI:  php /ruta/cron.php            (cada minuto o cada 5 minutos)
 *   URL:  https://tu-dominio/cron.php?token=TU_TOKEN   (con wget/curl si el hosting no permite CLI)
 * El token está en Panel > Sistema.
 */
if (PHP_VERSION_ID < 80000) { exit("Se requiere PHP 8.0+\n"); }
require __DIR__ . '/app/bootstrap.php';

use Aurea\Core\Db;
use Aurea\Core\Settings;
use Aurea\Services\CronService;

$cli = PHP_SAPI === 'cli';
if (!is_file(AUREA_ROOT . '/config/config.php')) { http_response_code(503); exit($cli ? "No instalado\n" : 'No instalado'); }
Db::connect((array)config('db', []));
date_default_timezone_set((string)Settings::get('timezone', 'America/Guatemala'));

if (!$cli) {
    $tok = (string)Settings::get('cron_token', '');
    $given = (string)($_GET['token'] ?? '');
    if ($tok === '' || !hash_equals($tok, $given)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Token inválido');
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
}
try {
    $force = $cli && in_array('--force', $argv ?? [], true);
    $res = CronService::run($force);
    echo 'OK ' . date('Y-m-d H:i:s') . ' ' . json_encode($res, JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    \Aurea\Core\Logger::exception($e);
    http_response_code(500);
    echo "ERROR\n";
    exit(1);
}
