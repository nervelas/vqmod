<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
T::boot('conc');
require __DIR__ . '/../lib/stubs.php';

use App\Core\Clock;
use App\Core\Db;
use App\Core\Tz;
use App\Services\EventRepository as E;

Clock::set(Tz::ts('2026-10-05 14:00:00'));
$hostId = (int) Db::val('SELECT id FROM hosts LIMIT 1');
$cfg = getenv('AP_CONFIG');

function race(string $cfg, int $eventId, string $start, int $workers): array
{
    $barrier = sys_get_temp_dir() . '/ap_barrier_' . bin2hex(random_bytes(4));
    $procs = [];
    for ($i = 0; $i < $workers; $i++) {
        $cmd = 'php ' . escapeshellarg(__DIR__ . '/../lib/book_worker.php') . ' ' . escapeshellarg($cfg) . ' ' . $eventId . ' ' . escapeshellarg($start) . ' ' . $i . ' ' . escapeshellarg($barrier);
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $procs[] = [$p, $pipes];
    }
    usleep(900000);
    touch($barrier);
    $res = [];
    foreach ($procs as [$p, $pipes]) {
        $res[] = trim((string) stream_get_contents($pipes[1])) . trim((string) stream_get_contents($pipes[2]));
        proc_close($p);
    }
    @unlink($barrier);
    return array_count_values($res);
}

T::section('50 reservas simultáneas al mismo horario (individual)');
$ev = E::find(E::save(['name' => 'Concurrencia', 'hosts' => [$hostId], 'min_notice_minutes' => 0, 'cancel_hours' => 0]));
$r = race($cfg, (int) $ev['id'], '2026-10-12 15:00:00', 50);
T::eq(1, $r['OK'] ?? 0, 'exactamente una reserva exitosa ' . json_encode($r));
T::eq(1, (int) Db::val("SELECT COUNT(*) FROM bookings WHERE event_type_id = ? AND starts_at = '2026-10-12 15:00:00'", [$ev['id']]), 'una sola fila en la BD');
T::eq(0, count(array_filter(array_keys($r), fn ($k) => strpos((string) $k, 'ERR') === 0)), 'sin errores inesperados (interbloqueos reintentados)');

T::section('50 reservas simultáneas a una sesión grupal de 5 cupos');
$g = E::find(E::save(['name' => 'Grupo', 'kind' => 'group', 'capacity' => 5, 'hosts' => [$hostId], 'min_notice_minutes' => 0, 'cancel_hours' => 0]));
$r = race($cfg, (int) $g['id'], '2026-10-13 15:00:00', 50);
T::eq(5, $r['OK'] ?? 0, 'exactamente 5 exitosas ' . json_encode($r));
T::eq(5, (int) Db::val("SELECT COALESCE(SUM(seats),0) FROM bookings WHERE event_type_id = ?", [$g['id']]), 'nunca se exceden los cupos');

T::section('Round robin concurrente: nunca dos reservas al mismo anfitrión y hora');
$h2 = Db::insert('hosts', ['name' => 'Segundo', 'slug' => 'seg', 'timezone' => 'America/Guatemala', 'ics_token' => bin2hex(random_bytes(16)), 'schedule_id' => (int) Db::val('SELECT schedule_id FROM hosts WHERE id = ?', [$hostId]), 'created_at' => Clock::utc()]);
$rr = E::find(E::save(['name' => 'RR', 'kind' => 'round_robin', 'hosts' => [$hostId, $h2], 'min_notice_minutes' => 0, 'cancel_hours' => 0]));
$r = race($cfg, (int) $rr['id'], '2026-10-14 15:00:00', 20);
T::eq(2, $r['OK'] ?? 0, 'solo caben 2 (uno por anfitrión) ' . json_encode($r));
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM (SELECT host_id FROM bookings WHERE event_type_id = ? GROUP BY host_id HAVING COUNT(*) > 1) x", [$rr['id']]), 'ningún anfitrión duplicado');
T::done();
