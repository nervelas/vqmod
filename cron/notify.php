<?php
/**
 * Cron entry point. Run hourly from cPanel:
 *    php /home/USER/public_html/cron/notify.php
 * or by URL (protected by push_cron_key):
 *    curl -s "https://midominio.com/cron/notify.php?key=YOURKEY"
 *
 * Sends, 24h (configurable) after a matchday's results are all uploaded:
 *   1) a notification with all results of that matchday,
 *   2) a notification with the next matchday's fixtures.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    // Web execution requires the configured key.
    $key = (string)Settings::get('push_cron_key', '');
    if ($key === '' || ($_GET['key'] ?? '') !== $key) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: application/json; charset=utf-8');
}

$result = Push::processDue();

if ($isCli) {
    echo date('c') . ' ' . json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
} else {
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
