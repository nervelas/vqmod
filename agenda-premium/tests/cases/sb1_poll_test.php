<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
require __DIR__ . '/../lib/Sb1.php';
T::boot('sb1_poll', ['profession' => 'medico']);

use App\Core\Clock;
use App\Core\Db;
use App\Services\AvailabilityService;
use App\Services\EventRepository;
use App\Services\PollService;

Db::exec('UPDATE custom_fields SET required = 0');
$event = EventRepository::find(1);
$slots = AvailabilityService::slots($event, (int) $event['default_duration'], gmdate('Y-m-d H:i:s', Clock::now() + 3 * 86400), gmdate('Y-m-d H:i:s', Clock::now() + 9 * 86400), ['nocache' => true]);
$opts = [$slots[0]['start'], $slots[2]['start'], $slots[4]['start']];
$d = ['title' => 'Reunión de padres', 'description' => 'Elijan su horario', 'host_id' => 1, 'event_type_id' => 1, 'duration' => (int) $event['default_duration'], 'timezone' => 'America/Guatemala'];

T::section('Crear');
T::throws(fn () => PollService::create($d, [$opts[0]]), 'mínimo 2 opciones', \InvalidArgumentException::class, '2 horarios');
T::throws(fn () => PollService::create($d, [$opts[0], $opts[0]]), 'opciones repetidas cuentan como una', \InvalidArgumentException::class);
$many = [];
for ($i = 1; $i <= 21; $i++) {
    $many[] = gmdate('Y-m-d H:i:s', Clock::now() + $i * 86400);
}
T::throws(fn () => PollService::create($d, $many), 'máximo 20 opciones', \InvalidArgumentException::class, '20');
T::throws(fn () => PollService::create($d, [$opts[0], gmdate('Y-m-d H:i:s', Clock::now() - 3600)]), 'opciones en el pasado', \InvalidArgumentException::class, 'futuro');
T::throws(fn () => PollService::create($d, [$opts[0], 'mañana']), 'opción con formato inválido', \InvalidArgumentException::class);
T::throws(fn () => PollService::create(['title' => ''] + $d, $opts), 'título obligatorio', \InvalidArgumentException::class);
T::throws(fn () => PollService::create(['host_id' => 999] + $d, $opts), 'anfitrión inexistente', \InvalidArgumentException::class);
$pid = PollService::create($d, [$opts[2], $opts[0], $opts[1]]);
$poll = PollService::getById($pid);
T::ok(preg_match('/^[a-f0-9]{32}$/', $poll['token']) === 1 && count($poll['options']) === 3 && $poll['options'][0]['starts_at'] === $opts[0], 'token de 128 bits y opciones ordenadas por hora');
T::eq(Tz_ts($poll['options'][0]['ends_at']) - Tz_ts($poll['options'][0]['starts_at']), (int) $event['default_duration'] * 60, 'fin = inicio + duración');
$token = $poll['token'];
$o = array_column($poll['options'], 'id');

function Tz_ts(string $u): int
{
    return \App\Core\Tz::ts($u);
}

T::section('Votar');
T::throws(fn () => PollService::vote($token, 'Ana', 'no-correo', [$o[0] => 'yes']), 'correo inválido', \InvalidArgumentException::class, 'correo');
T::throws(fn () => PollService::vote($token, '', 'ana@example.test', [$o[0] => 'yes']), 'nombre vacío', \InvalidArgumentException::class);
T::throws(fn () => PollService::vote($token, 'Ana', 'ana@example.test', []), 'sin respuestas', \InvalidArgumentException::class);
T::throws(fn () => PollService::vote($token, 'Ana', 'ana@example.test', [$o[0] => 'talvez']), 'valor de voto inválido', \InvalidArgumentException::class);
T::throws(fn () => PollService::vote($token, 'Ana', 'ana@example.test', [999999 => 'yes']), 'opción de otra encuesta', \InvalidArgumentException::class);
T::throws(fn () => PollService::vote(str_repeat('0', 32), 'Ana', 'ana@example.test', [$o[0] => 'yes']), 'encuesta inexistente', \InvalidArgumentException::class, 'No encontramos');
PollService::vote($token, 'Ana', 'ana@example.test', [$o[0] => 'yes', $o[1] => 'no', $o[2] => 'maybe']);
PollService::vote($token, 'Beto', 'beto@example.test', [$o[0] => 'maybe', $o[1] => 'yes', $o[2] => 'yes']);
PollService::vote($token, 'Carla', 'CARLA@example.test', [$o[0] => 'no', $o[1] => 'yes', $o[2] => 'yes']);
$g = PollService::get($token);
T::ok($g['voter_count'] === 3 && !isset($g['voters'][0]['email']), 'tres votantes; el correo no se expone en la vista pública');
T::ok(isset(PollService::get($token, true)['voters'][0]['email']), 'el panel sí ve los correos');
$byId = array_column($g['options'], null, 'id');
T::eq([1, 1, 1], [$byId[$o[0]]['yes'], $byId[$o[0]]['maybe'], $byId[$o[0]]['no']], 'totales de la opción 1 (sí/quizá/no)');
T::eq([2, 1, 0], [$byId[$o[2]]['yes'], $byId[$o[2]]['maybe'], $byId[$o[2]]['no']], 'totales de la opción 3');
T::eq($o[2], $g['best_option_id'], 'mejor opción: empate en "sí" con la 2, gana la que tiene más "quizá"');
PollService::vote($token, 'Ana Lucía', 'ANA@example.test', [$o[2] => 'yes', $o[1] => 'yes']);
$g = PollService::get($token);
T::eq(3, $g['voter_count'], 'votar de nuevo con el mismo correo actualiza, no duplica');
T::eq(3, (int) Db::val("SELECT COUNT(*) FROM poll_votes WHERE option_id = ? AND vote = 'yes'", [$o[1]]), 'el voto actualizado cuenta');
T::eq(9, (int) Db::val('SELECT COUNT(*) FROM poll_votes'), 'un voto por opción y correo (3 personas × 3 opciones)');
T::eq($o[1], $g['best_option_id'], 'tras el cambio de voto hay empate total (3 sí y 0 quizá) y gana la más temprana');
T::eq(null, PollService::get('zzz'), 'token mal formado → null');

T::section('Cierre y plazo');
$p2 = PollService::create($d + ['deadline_at' => gmdate('Y-m-d H:i:s', Clock::now() + 3600)], $opts);
$t2 = PollService::getById($p2)['token'];
$o2 = array_column(PollService::getById($p2)['options'], 'id');
PollService::vote($t2, 'Ana', 'ana@example.test', [$o2[0] => 'yes']);
Clock::set(Clock::now() + 7200);
T::throws(fn () => PollService::vote($t2, 'Beto', 'beto@example.test', [$o2[0] => 'yes']), 'después de la fecha límite', \InvalidArgumentException::class, 'plazo');
T::ok(PollService::get($t2)['is_open'] === false, 'is_open falso tras el plazo');
Clock::set(null);
$p3 = PollService::create($d, $opts);
$t3 = PollService::getById($p3)['token'];
PollService::close($p3);
T::throws(fn () => PollService::vote($t3, 'Ana', 'ana@example.test', [PollService::getById($p3)['options'][0]['id'] => 'yes']), 'encuesta cerrada', \InvalidArgumentException::class, 'cerrada');
T::throws(fn () => PollService::create($d + ['deadline_at' => '2020-01-01 00:00:00'], $opts), 'plazo en el pasado', \InvalidArgumentException::class);

T::section('Finalizar');
T::throws(fn () => PollService::finalize($pid, 999999, ['label' => 'Admin']), 'opción ajena', \InvalidArgumentException::class);
T::throws(fn () => PollService::finalize($p3, PollService::getById($p3)['options'][0]['id'], ['label' => 'Admin']), 'sin votantes sí/quizá', \InvalidArgumentException::class, 'Nadie');
$res = PollService::finalize($pid, $o[1], ['type' => 'user', 'label' => 'Admin Prueba', 'user_id' => 1]);
$bk = $res['booking'];
T::ok($bk['created_via'] === 'poll' && $bk['starts_at'] === $poll['options'][1]['starts_at'] && (int) $bk['host_id'] === 1 && (int) $bk['event_type_id'] === 1, 'cita creada vía poll con el horario elegido');
T::ok($bk['guest_email'] === 'beto@example.test' || $bk['guest_email'] === 'ana@example.test', 'el invitado principal es el primer votante sí/quizá (' . $bk['guest_email'] . ')');
T::eq(2, (int) Db::val('SELECT COUNT(*) FROM booking_attendees WHERE booking_id = ?', [(int) $bk['id']]), 'los demás votantes quedan como invitados adicionales (2)');
T::eq(1, (int) Db::val("SELECT COUNT(*) FROM bookings WHERE created_via = 'poll'"), 'se crea UNA sola cita');
$fin = Db::one('SELECT * FROM polls WHERE id = ?', [$pid]);
T::ok($fin['status'] === 'finalized' && (int) $fin['final_option_id'] === $o[1] && (int) $fin['final_booking_id'] === (int) $bk['id'], 'encuesta finalizada con opción y cita guardadas');
T::eq(3, $res['notified'], 'tres votantes notificados');
$mails = Db::all("SELECT * FROM email_queue WHERE subject LIKE 'Confirmado: Reunión de padres%'");
T::eq(3, count($mails), 'tres correos de confirmación encolados');
T::ok(str_contains((string) $mails[0]['attachments'], 'BEGIN:VCALENDAR') || str_contains((string) $mails[0]['attachments'], 'cita.ics'), 'los correos llevan el .ics adjunto');
T::throws(fn () => PollService::finalize($pid, $o[1], ['label' => 'Admin']), 'no se puede finalizar dos veces', \InvalidArgumentException::class, 'ya se finalizó');
T::eq(1, (int) Db::val("SELECT COUNT(*) FROM bookings WHERE created_via = 'poll'"), 'sigue habiendo una sola cita');
T::throws(fn () => PollService::vote($token, 'Tarde', 'tarde@example.test', [$o[0] => 'yes']), 'una encuesta finalizada no recibe votos', \InvalidArgumentException::class);
T::done();
