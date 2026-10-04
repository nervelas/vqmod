<?php
declare(strict_types=1);

// Proceso hijo para pruebas de concurrencia de B1: php sb1_race.php <config> <inicio_microtime> <accion> <json>
[, $cfg, $start, $action, $json] = $argv;
putenv('AP_CONFIG=' . $cfg);
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = '127.0.0.1:8190';
require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Db;
use App\Services\PricingService;
use App\Services\WaitlistService;

$p = json_decode($json, true);
while (microtime(true) < (float) $start) {
    usleep(200);
}
try {
    switch ($action) {
        case 'consume':
            Db::tx(static function () use ($p): void {
                PricingService::consume($p['quote'], (int) $p['booking_id']);
            });
            echo "OK\n";
            break;
        case 'offer':
            WaitlistService::onSlotFreed($p['booking']);
            echo "OK\n";
            break;
        default:
            echo "ERR accion\n";
    }
} catch (\Throwable $e) {
    echo 'ERR ' . $e->getMessage() . "\n";
}
