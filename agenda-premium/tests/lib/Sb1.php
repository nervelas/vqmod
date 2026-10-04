<?php
declare(strict_types=1);

// Ayudas de pruebas de Servicios B1 (no se incluye en el ZIP).
final class Sb1
{
    /** Lanza un proceso PHP por cada juego de parámetros, todos arrancan al mismo instante. Devuelve la salida (una línea) de cada uno. */
    public static function race(string $name, string $action, array $paramsList): array
    {
        $cfg = '/tmp/ap-t-' . $name . '.config.php';
        $start = microtime(true) + 1.5;
        $procs = [];
        foreach ($paramsList as $i => $params) {
            $cmd = ['php', __DIR__ . '/sb1_race.php', $cfg, sprintf('%.4f', $start), $action, json_encode($params)];
            $procs[$i] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $procs[$i] = [$procs[$i], $pipes];
        }
        $out = [];
        foreach ($procs as $i => [$proc, $pipes]) {
            $out[$i] = trim((string) stream_get_contents($pipes[1])) . trim((string) stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
        }
        return $out;
    }

    /** Cita real creada con BookingService (administración) en el primer horario libre a partir de $daysAhead días. */
    public static function booking(int $eventId, int $daysAhead = 2, array $extra = [], int $nth = 0): array
    {
        \App\Core\Db::exec('UPDATE custom_fields SET required = 0');
        $ev = \App\Services\EventRepository::find($eventId);
        $from = gmdate('Y-m-d H:i:s', \App\Core\Clock::now() + $daysAhead * 86400);
        $to = gmdate('Y-m-d H:i:s', \App\Core\Clock::now() + ($daysAhead + 6) * 86400);
        $slots = \App\Services\AvailabilityService::slots($ev, (int) $ev['default_duration'], $from, $to, ['nocache' => true]);
        $s = $slots[$nth];
        $out = \App\Services\BookingService::create($extra + [
            'event_id' => $eventId, 'duration' => (int) $ev['default_duration'], 'start' => $s['start'], 'timezone' => 'America/Guatemala',
            'name' => 'Cliente Prueba', 'email' => 'cliente' . random_int(1000, 99999) . '@example.test', 'phone' => '55551234',
            'consent' => true, 'created_via' => 'admin', 'force' => true,
        ]);
        return $out['booking'];
    }
}
