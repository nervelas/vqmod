<?php
// Proceso de trabajo para pruebas de concurrencia: php book_worker.php <config> <evento> <inicioUTC> <n> <barrera> [asientos]
[, $cfg, $eventId, $start, $n, $barrier] = $argv;
putenv('AP_CONFIG=' . $cfg);
$_SERVER['SCRIPT_NAME'] = '/index.php';
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require __DIR__ . '/stubs.php';
\App\Core\Clock::set(\App\Core\Tz::ts('2026-10-05 14:00:00'));
while (!is_file($barrier)) {
    usleep(1000);
}
try {
    $r = \App\Services\BookingService::create([
        'event_id' => (int) $eventId, 'duration' => 30, 'start' => $start, 'name' => 'Concurrente ' . $n,
        'email' => 'conc' . $n . '@example.test', 'phone' => '55551234', 'consent' => 1, 'timezone' => 'America/Guatemala',
    ]);
    echo 'OK';
} catch (\App\Services\BookingException $e) {
    echo 'BX:' . $e->errorCode;
} catch (\Throwable $e) {
    echo 'ERR:' . get_class($e) . ':' . $e->getMessage();
}
