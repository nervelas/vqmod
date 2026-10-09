<?php
declare(strict_types=1);
/** Cron diario: php /ruta/portal/tools/cron.php  (borra vistas previas vencidas, avisa renovaciones, reanuda construcciones). */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('S5_ROOT', dirname(__DIR__));
require S5_ROOT . '/app/bootstrap.php';
if (!\S5\Core\Config::installed()) { fwrite(STDERR, "No instalado\n"); exit(1); }
$r = \S5\Services\Cron::run('cron');
echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
