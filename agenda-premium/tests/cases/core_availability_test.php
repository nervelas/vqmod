<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
T::boot('avail');
require __DIR__ . '/../lib/stubs.php';

use App\Core\Clock;
use App\Core\Db;
use App\Core\Tz;
use App\Services\AvailabilityService as A;
use App\Services\BookingException;
use App\Services\BookingService as B;
use App\Services\EventRepository as E;

$hostId = (int) Db::val('SELECT id FROM hosts LIMIT 1');
Clock::set(Tz::ts('2026-10-05 14:00:00')); // lunes 08:00 en Guatemala

function mk(array $o): array
{
    global $hostId;
    $id = E::save($o + ['name' => 'Evento ' . bin2hex(random_bytes(2)), 'hosts' => [$hostId], 'min_notice_minutes' => 0, 'max_advance_days' => 90, 'cancel_hours' => 0]);
    return E::find($id);
}
function day(array $ev, string $date, int $dur = 30, string $tz = 'America/Guatemala'): array
{
    return A::slotsByDay($ev, $dur, $date, $date, $tz)[$date] ?? [];
}
function book(array $ev, string $startUtc, array $extra = []): array
{
    return B::create($extra + ['event_id' => $ev['id'], 'duration' => (int) $ev['default_duration'], 'start' => $startUtc, 'name' => 'Cliente Prueba', 'email' => 'c' . random_int(1, 999999) . '@example.test', 'phone' => '55551234', 'consent' => 1, 'timezone' => 'America/Guatemala']);
}

T::section('Slots básicos y feriados');
$ev = mk(['name' => 'Consulta', 'duration_options' => '30,60', 'default_duration' => 30]);
$slots = day($ev, '2026-10-12');
T::eq(16, count($slots), 'lunes laborable: 8 + 8 horarios de 30 min');
T::eq('09:00', $slots[0]['local_time'], 'primer horario 9:00 GT');
T::eq(0, count(day($ev, '2026-10-20')), '20 de octubre (feriado) sin horarios');
T::eq(0, count(day($ev, '2026-10-11')), 'domingo cerrado');
T::eq(6, count(day($ev, '2026-10-17')), 'sábado 9–12');
T::eq(0, count(day($ev, '2026-12-25')), 'Navidad sin horarios');
$d24 = day($ev, '2026-12-24');
T::eq(6, count($d24), '24 de diciembre solo hasta las 12:00');
T::eq(0, count(A::slotsByDay($ev, 45, '2026-10-12', '2026-10-12', 'America/Guatemala')), 'duración no permitida');

T::section('Aviso mínimo y anticipación máxima');
$ev2 = mk(['min_notice_minutes' => 180, 'max_advance_days' => 3]);
$today = day($ev2, '2026-10-05');
T::eq('11:00', $today[0]['local_time'] ?? '', 'con 3 h de aviso desde 08:00, el primero es 11:00');
T::eq(0, count(day($ev2, '2026-10-12')), 'más allá de la anticipación máxima');

T::section('Reserva, buffers y doble reserva');
$ev3 = mk(['buffer_after' => 15, 'buffer_before' => 0]);
$r = book($ev3, '2026-10-12 15:00:00'); // 9:00 GT
T::eq('confirmed', $r['status'], 'reserva confirmada');
$after = array_column(day($ev3, '2026-10-12'), 'local_time');
T::ok(!in_array('09:00', $after, true) && !in_array('09:30', $after, true) && in_array('10:00', $after, true), 'el buffer posterior bloquea 9:30');
T::throws(fn () => book($ev3, '2026-10-12 15:00:00'), 'doble reserva rechazada', BookingException::class);
T::throws(fn () => book($ev3, '2026-10-12 15:30:00'), 'solape por buffer rechazado', BookingException::class);
T::ok((bool) book($ev3, '2026-10-12 16:00:00'), 'horario libre se puede reservar');
$other = mk(['name' => 'Otro evento mismo anfitrión']);
T::throws(fn () => book($other, '2026-10-12 15:00:00'), 'otro evento del mismo anfitrión también choca', BookingException::class);
T::throws(fn () => book($other, '2026-10-12 12:00:00'), 'fuera del horario laboral rechazado', BookingException::class);
T::throws(fn () => B::create(['event_id' => $ev3['id'], 'duration' => 30, 'start' => '2026-10-13 15:00:00', 'name' => 'Xavier Prueba', 'email' => 'x@example.test', 'phone' => '55551234', 'timezone' => 'America/Guatemala']), 'sin consentimiento rechazado', BookingException::class, 'consentimiento');

T::section('Cancelar libera el horario');
B::cancel((int) $r['booking']['id'], 'Prueba', ['type' => 'user', 'label' => 'Admin']);
T::ok(in_array('09:00', array_column(day($ev3, '2026-10-12'), 'local_time'), true), 'horario libre otra vez');

T::section('Límite diario');
$evL = mk(['daily_limit' => 2]);
book($evL, '2026-10-13 15:00:00');
book($evL, '2026-10-13 16:00:00');
T::eq(0, count(day($evL, '2026-10-13')), 'tras 2 reservas el día queda cerrado');
T::throws(fn () => book($evL, '2026-10-13 17:00:00'), 'tercera reserva rechazada por límite', BookingException::class);

T::section('Zonas horarias del invitado');
$evT = mk([]);
$ny = A::slotsByDay($evT, 30, '2026-10-26', '2026-10-26', 'America/New_York')['2026-10-26'] ?? [];
T::eq('11:00', $ny[0]['local_time'] ?? '', 'NY en octubre (EDT, UTC-4): 9:00 GT = 11:00');
$ny2 = A::slotsByDay($evT, 30, '2026-11-02', '2026-11-02', 'America/New_York')['2026-11-02'] ?? [];
T::eq('10:00', $ny2[0]['local_time'] ?? '', 'NY en noviembre (EST, UTC-5): 9:00 GT = 10:00');
$mad = A::slotsByDay($evT, 30, '2026-10-26', '2026-10-26', 'Europe/Madrid')['2026-10-26'] ?? [];
T::eq('16:00', $mad[0]['local_time'] ?? '', 'Madrid tras fin del horario de verano (UTC+1): 9:00 GT = 16:00');
$mad2 = A::slotsByDay($evT, 30, '2026-10-19', '2026-10-19', 'Europe/Madrid')['2026-10-19'] ?? [];
T::eq('17:00', $mad2[0]['local_time'] ?? '', 'Madrid antes del cambio (UTC+2): 9:00 GT = 17:00');

T::section('Ausencias y excepciones');
Db::insert('time_off', ['host_id' => $hostId, 'starts_at' => '2026-10-14 15:00:00', 'ends_at' => '2026-10-14 17:00:00', 'created_at' => Clock::utc()]);
$evA = mk([]);
$t = array_column(day($evA, '2026-10-14'), 'local_time');
T::ok(!in_array('09:00', $t, true) && !in_array('10:30', $t, true) && in_array('11:00', $t, true), 'ausencia de 9:00 a 11:00 bloquea');
$sid = (int) Db::val('SELECT id FROM schedules LIMIT 1');
Db::insert('schedule_overrides', ['schedule_id' => $sid, 'date' => '2026-10-20', 'is_open' => 1, 'start_time' => '10:00:00', 'end_time' => '11:00:00']);
\App\Core\Cache::bumpAvailability();
T::eq(2, count(day($evA, '2026-10-20')), 'excepción abre un feriado puntualmente');
Db::insert('schedule_overrides', ['schedule_id' => $sid, 'date' => '2026-10-15', 'is_open' => 0]);
\App\Core\Cache::bumpAvailability();
T::eq(0, count(day($evA, '2026-10-15')), 'excepción cierra un día laboral');

T::section('Próximo horario');
$nx = A::next($evA, 30);
T::ok($nx !== null && $nx['start'] >= '2026-10-05 14:00:00', 'next() devuelve un horario futuro');

T::section('Grupal');
$g = mk(['kind' => 'group', 'capacity' => 3, 'allow_guests' => 1, 'max_guests' => 3]);
$s = '2026-10-16 15:00:00';
$rg = book($g, $s, ['guests' => [['name' => 'Acompañante', 'email' => 'a@example.test']]]);
T::eq(2, (int) $rg['booking']['seats'], 'el invitado + acompañante ocupan 2 cupos');
$slot = array_values(array_filter(day($g, '2026-10-16'), fn ($x) => $x['start'] === $s))[0] ?? null;
T::eq(1, $slot['seats_left'] ?? -1, 'queda 1 cupo');
T::throws(fn () => book($g, $s, ['guests' => [['name' => 'A'], ['name' => 'B']], 'status' => 'confirmed']), 'excede cupos (3 asientos pedidos con 1 libre)', BookingException::class);
T::ok((bool) book($g, $s), 'el último cupo se reserva');
T::throws(fn () => book($g, $s), 'sin cupos', BookingException::class);

T::section('Colectivo y round robin');
$h2 = Db::insert('hosts', ['name' => 'Segundo', 'slug' => 'segundo', 'timezone' => 'America/Guatemala', 'ics_token' => bin2hex(random_bytes(16)), 'schedule_id' => (int) Db::val('SELECT schedule_id FROM hosts WHERE id = ?', [$hostId]), 'created_at' => Clock::utc()]);
$h3 = Db::insert('hosts', ['name' => 'Tercero', 'slug' => 'tercero', 'timezone' => 'America/Guatemala', 'ics_token' => bin2hex(random_bytes(16)), 'schedule_id' => (int) Db::val('SELECT schedule_id FROM hosts WHERE id = ?', [$hostId]), 'created_at' => Clock::utc()]);
$col = mk(['kind' => 'collective', 'hosts' => [$hostId, $h2]]);
$before = count(day($col, '2026-10-21'));
Db::insert('time_off', ['host_id' => $h2, 'starts_at' => '2026-10-21 15:00:00', 'ends_at' => '2026-10-21 16:00:00', 'created_at' => Clock::utc()]);
\App\Core\Cache::bumpAvailability();
T::eq($before - 2, count(day($col, '2026-10-21')), 'colectivo: si uno está ausente, esas horas desaparecen');
$rc = book($col, '2026-10-21 17:00:00');
T::eq(2, count(\App\Repositories\BookingRepository::hostIds((int) $rc['booking']['id'])), 'colectivo ocupa a todos');
$rr = mk(['kind' => 'round_robin', 'hosts' => [$hostId => ['weight' => 1], $h2 => ['weight' => 1], $h3 => ['weight' => 1]], 'rr_mode' => 'equitable']);
$cnt = [$hostId => 0, $h2 => 0, $h3 => 0];
$days = ['2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13'];
$n = 0;
foreach ($days as $dd) {
    foreach (day($rr, $dd) as $sl) {
        if ($n >= 99) {
            break 2;
        }
        $bk = B::create(['event_id' => $rr['id'], 'duration' => 30, 'start' => $sl['start'], 'name' => 'RR ' . $n, 'email' => 'rr' . $n . '@example.test', 'phone' => '55551234', 'consent' => 1, 'timezone' => 'America/Guatemala']);
        $cnt[(int) $bk['booking']['host_id']]++;
        $n++;
    }
}
T::ok($n >= 90, "se simularon $n reservas round robin");
T::ok(max($cnt) - min($cnt) <= 1, 'reparto equitativo: ' . json_encode(array_values($cnt)));
$rw = mk(['kind' => 'round_robin', 'hosts' => [$hostId => ['weight' => 1], $h2 => ['weight' => 2], $h3 => ['weight' => 3]], 'rr_mode' => 'weighted']);
$cw = [$hostId => 0, $h2 => 0, $h3 => 0];
$n = 0;
foreach (['2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20', '2026-11-23', '2026-11-24', '2026-11-25', '2026-11-26'] as $dd) {
    foreach (day($rw, $dd) as $sl) {
        if ($n >= 60) {
            break 2;
        }
        $bk = B::create(['event_id' => $rw['id'], 'duration' => 30, 'start' => $sl['start'], 'name' => 'RW ' . $n, 'email' => 'rw' . $n . '@example.test', 'phone' => '55551234', 'consent' => 1, 'timezone' => 'America/Guatemala']);
        $cw[(int) $bk['booking']['host_id']]++;
        $n++;
    }
}
T::ok(abs($cw[$hostId] - 10) <= 1 && abs($cw[$h2] - 20) <= 1 && abs($cw[$h3] - 30) <= 1, 'reparto ponderado 1:2:3 → ' . json_encode(array_values($cw)));
$rp = mk(['kind' => 'round_robin', 'hosts' => [$hostId => ['priority' => 2], $h2 => ['priority' => 1]], 'rr_mode' => 'priority']);
$bk = book($rp, day($rp, '2026-12-01')[0]['start']);
T::eq($h2, (int) $bk['booking']['host_id'], 'prioridad: gana el de prioridad 1');
// nunca asigna a alguien ocupado
$rr2 = mk(['kind' => 'round_robin', 'hosts' => [$hostId, $h2]]);
Db::insert('time_off', ['host_id' => $h2, 'starts_at' => '2026-12-02 15:00:00', 'ends_at' => '2026-12-02 16:00:00', 'created_at' => Clock::utc()]);
for ($i = 0; $i < 4; $i++) {
    $bk = book($rr2, '2026-12-02 15:00:00', ['name' => 'Z' . $i]);
    T::eq($hostId, (int) $bk['booking']['host_id'], 'RR no asigna a quien está ausente');
    break;
}

T::section('Recurso');
$rid = Db::insert('resources', ['name' => 'Sala 1', 'capacity' => 1, 'active' => 1]);
Db::insert('hosts', ['name' => 'Cuarto', 'slug' => 'cuarto', 'timezone' => 'America/Guatemala', 'ics_token' => bin2hex(random_bytes(16)), 'schedule_id' => $sid, 'created_at' => Clock::utc()]);
$h4 = (int) Db::lastId();
$ra = mk(['resources' => [$rid], 'name' => 'Con sala A']);
$rb = mk(['resources' => [$rid], 'name' => 'Con sala B', 'hosts' => [$h4]]);
book($ra, '2026-12-03 15:00:00');
T::throws(fn () => book($rb, '2026-12-03 15:00:00'), 'la sala no se puede reservar dos veces a la vez', BookingException::class);
T::ok(!in_array('09:00', array_column(day($rb, '2026-12-03'), 'local_time'), true), 'el horario con sala ocupada no se ofrece');

T::section('Serie');
$se = mk(['series_sessions' => 3, 'series_interval_days' => 7]);
$sr = book($se, '2026-11-30 15:00:00');
T::eq(3, count($sr['bookings']), 'serie crea 3 sesiones');
T::eq('2026-12-07 15:00:00', $sr['bookings'][1]['starts_at'], 'segunda sesión una semana después');
Db::insert('time_off', ['host_id' => $hostId, 'starts_at' => '2026-12-21 17:00:00', 'ends_at' => '2026-12-21 18:00:00', 'created_at' => Clock::utc()]);
\App\Core\Cache::bumpAvailability();
$se2 = mk(['series_sessions' => 3, 'series_interval_days' => 7]);
T::throws(fn () => book($se2, '2026-12-14 17:00:00'), 'serie con una sesión imposible se rechaza completa', BookingException::class);
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM bookings WHERE event_type_id = ?", [$se2['id']]), 'no quedó ninguna sesión parcial');

T::section('Enlace de un solo uso');
$su = E::find(E::singleUseCopy((int) $ev['id']));
T::eq(null, E::unavailableReason($su), 'enlace nuevo disponible');
book($su, '2026-12-08 15:00:00');
$su = E::find((int) $su['id']);
T::ok(E::unavailableReason($su) !== null, 'tras reservar ya no se puede reutilizar');
T::throws(fn () => book($su, '2026-12-08 16:00:00'), 'segundo uso rechazado', BookingException::class);

T::section('Reprogramar, estados y política');
$ev5 = mk(['cancel_hours' => 24]);
$x = book($ev5, '2026-12-10 15:00:00');
$id = (int) $x['booking']['id'];
$mv = B::reschedule($id, '2026-12-10 17:00:00', ['type' => 'guest', 'label' => 'Cliente']);
T::eq('2026-12-10 17:00:00', $mv['starts_at'], 'reprogramada');
T::ok(in_array('09:00', array_column(day($ev5, '2026-12-10'), 'local_time'), true), 'el horario anterior queda libre');
Clock::set(Tz::ts('2026-12-10 10:00:00'));
T::throws(fn () => B::cancel($id, 'tarde', ['type' => 'guest', 'label' => 'Cliente']), 'cancelación del invitado fuera de política rechazada', BookingException::class);
B::cancel($id, 'por el negocio', ['type' => 'user', 'label' => 'Admin']);
T::eq('cancelled', (string) Db::val('SELECT status FROM bookings WHERE id = ?', [$id]), 'el administrador sí puede cancelar');
Clock::set(Tz::ts('2026-10-05 14:00:00'));
$y = book($ev5, '2026-12-11 15:00:00');
B::setStatus((int) $y['booking']['id'], 'no_show', ['type' => 'user', 'label' => 'Admin']);
T::eq(1, (int) Db::val('SELECT noshow_count FROM clients WHERE id = ?', [$y['booking']['client_id']]), 'no asistió suma al contador');

T::section('Aprobación manual');
$ap = mk(['approval' => 1]);
$z = book($ap, '2026-12-16 15:00:00');
T::eq('pending', $z['status'], 'queda pendiente');
T::throws(fn () => book($ap, '2026-12-16 15:00:00'), 'la pendiente también bloquea el horario', BookingException::class);
B::setStatus((int) $z['booking']['id'], 'confirmed', ['type' => 'user', 'label' => 'Admin']);
T::eq('confirmed', (string) Db::val('SELECT status FROM bookings WHERE id = ?', [$z['booking']['id']]), 'aprobada');

T::section('Validación y seguridad de datos');
T::throws(fn () => book($ev5, '2026-12-15 15:00:00', ['email' => 'no-es-correo']), 'correo inválido', BookingException::class);
T::throws(fn () => book($ev5, '2026-12-15 15:00:00', ['phone' => '123']), 'teléfono inválido', BookingException::class);
$xss = book($ev5, '2026-12-15 15:00:00', ['name' => "<script>alert(1)</script>'; DROP TABLE bookings;--", 'notes' => '<img src=x onerror=alert(1)>']);
T::ok((int) Db::val('SELECT COUNT(*) FROM bookings') > 5, 'la tabla sigue intacta tras inyección SQL en el nombre');
T::ok(strpos(e($xss['booking']['guest_name']), '<script>') === false, 'el nombre se escapa al mostrarse');
T::done();
