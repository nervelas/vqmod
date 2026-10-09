<?php
declare(strict_types=1);
/** Proceso de segundo plano: php tools/worker.php analyze <orderId> */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('S5_ROOT', dirname(__DIR__));
require S5_ROOT . '/app/bootstrap.php';
$cmd = $argv[1] ?? '';
$id = (int) ($argv[2] ?? 0);
if ($cmd === 'analyze' && $id > 0) {
    \S5\Services\Analysis::run($id);
}
