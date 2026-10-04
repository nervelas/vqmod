<?php
declare(strict_types=1);

/**
 * Tareas programadas. Dos formas de usarlo:
 *   Línea de comandos:  php /ruta/cron.php
 *   Por URL:            https://tu-sitio.com/cron.php?token=TU_TOKEN   (el token está en Ajustes > Sistema)
 * Programa cualquiera de las dos cada 5 minutos en el panel de tu hosting.
 */
define('AP_CRON', true);
require __DIR__ . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Request;
use App\Core\Settings;
use App\Services\CronService;
use App\Services\Mailer;

$cli = PHP_SAPI === 'cli';
$json = !$cli && ($_GET['format'] ?? 'json') !== 'text';

$reply = static function (int $status, array $data) use ($cli, $json): void {
    if ($cli) {
        fwrite($status === 200 ? STDOUT : STDERR, cron_text($data) . "\n");
        exit($status === 200 && ($data['ok'] ?? false) ? 0 : 1);
    }
    http_response_code($status);
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    header('Content-Type: ' . ($json ? 'application/json; charset=utf-8' : 'text/plain; charset=utf-8'));
    echo $json ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : cron_text($data);
    exit;
};

/** Resumen en texto plano para la consola o la opción ?format=text. */
function cron_text(array $d): string
{
    if (!empty($d['error']) && empty($d['tasks'])) {
        return 'Error: ' . $d['error'];
    }
    $lines = [($d['ok'] ?? false) ? 'Cron terminado correctamente.' : 'Cron terminado con avisos.'];
    foreach (($d['tasks'] ?? []) as $name => $r) {
        $lines[] = ' - ' . $name . ': ' . json_encode($r, JSON_UNESCAPED_UNICODE);
    }
    foreach (($d['errors'] ?? []) as $name => $msg) {
        $lines[] = ' ! ' . $name . ': ' . $msg;
    }
    return implode("\n", $lines);
}

if (!Config::installed()) {
    $reply(503, ['ok' => false, 'error' => 'El sistema aún no está instalado.']);
}

if (!$cli) {
    $stored = (string) Settings::get('cron_token', '');
    $given = $_GET['token'] ?? '';
    if ($stored === '' || !is_string($given) || !hash_equals($stored, $given)) {
        $reply(403, ['ok' => false, 'error' => 'Acceso denegado.']);
    }
    // La URL del cron la configura quien administra el sitio: sirve para conocer la dirección pública en los correos
    Mailer::rememberBaseUrl((new Request())->baseUrl());
    ignore_user_abort(true);
}

$result = CronService::run($cli ? 'cli' : 'url');
$reply($result['locked'] ? 409 : 200, $result);
