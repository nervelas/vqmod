<?php
/**
 * Sincronización programada. Funciona por CLI y por URL protegida con token:
 *   php /ruta/public_html/cron/sync.php
 *   https://tudominio.com/cron/sync.php?token=TU_TOKEN
 */
require dirname(__DIR__) . '/src/bootstrap.php';
$cli = PHP_SAPI === 'cli';
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');
if (!is_installed()) { http_response_code(503); exit("No instalado.\n"); }
try {
    db(); boot_settings();
    if (!$cli) {
        $tok = (string) S('cron_token', cfg('cron_token', ''));
        $given = (string) ($_GET['token'] ?? '');
        if ($tok === '' || !hash_equals($tok, $given)) { http_response_code(403); exit("Token inválido.\n"); }
    }
    @set_time_limit(280);
    $r = run_sync($cli ? 'cron-cli' : 'cron-url');
    echo date('c') . ' [' . $r['status'] . '] ' . $r['message'] . "\n";
} catch (Throwable $e) {
    log_error('cron: ' . $e->getMessage());
    http_response_code(500); echo "Error interno; revisa storage/logs.\n";
}
