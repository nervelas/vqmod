<?php
declare(strict_types=1);
// Uso (como www-data): php tick.php <orderId> [presupuesto_s] — avanza la construcción desde la CLI (pruebas de interrupción)
define('S5_ROOT', '/tmp/s5test/portal');
require S5_ROOT . '/app/bootstrap.php';
$p = S5\Services\Pipeline::tick((int) $argv[1], (int) ($argv[2] ?? 20));
echo json_encode(['estado' => $p['estado'], 'progreso' => $p['progreso']]) . "\n";
