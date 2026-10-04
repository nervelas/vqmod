<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
require __DIR__ . '/../lib/Sb1.php';
T::boot('sb1_waitlist', ['profession' => 'medico', 'demo' => true]);

use App\Core\Clock;
use App\Core\Db;
use App\Services\EventRepository;
use App\Services\WaitlistService;

$event = EventRepository::find(1);
$dur = (int) $event['default_duration'];
/** Cita real a 3+ días y liberada "a mano" (sin los ganchos de cancelación) para controlar cuándo se ofrece. */
$freed = static function (int $days, int $nth = 0): array {
    $b = Sb1::booking(1, $days, [], $nth);
    Db::update('bookings', ['status' => 'cancelled'], 'id = ?', [(int) $b['id']]);
    return $b;
};
$guest = static fn (string $n, string $mail, string $phone = ''): array => ['name' => $n, 'email' => $mail, 'phone' => $phone, 'timezone' => 'America/Guatemala'];
$status = static fn (int $id): string => (string) Db::val('SELECT status FROM waitlist WHERE id = ?', [$id]);

T::section('Anotarse');
$a = WaitlistService::join($event, $dur, $guest('Ana', 'ana@example.test', '55550001'), null, null);
T::ok($a > 0 && $status($a) === 'waiting', 'join crea la fila en espera');
T::throws(fn () => WaitlistService::join($event, $dur, $guest('Ana', 'ANA@example.test'), null, null), 'mismo correo no se duplica', \InvalidArgumentException::class, 'lista de espera');
T::throws(fn () => WaitlistService::join($event, $dur, $guest('Otra', 'otra@example.test', '5555-0001'), null, null), 'mismo teléfono no se duplica', \InvalidArgumentException::class);
T::throws(fn () => WaitlistService::join($event, $dur, $guest('', 'x@example.test'), null, null), 'nombre obligatorio', \InvalidArgumentException::class);
T::throws(fn () => WaitlistService::join($event, $dur, ['name' => 'Sin contacto'], null, null), 'correo o teléfono obligatorio', \InvalidArgumentException::class);
T::throws(fn () => WaitlistService::join($event, $dur, $guest('Mal', 'no-es-correo'), null, null), 'correo inválido', \InvalidArgumentException::class);
$ev2 = EventRepository::find(2);
T::ok(WaitlistService::join($ev2, 60, $guest('Ana', 'ana@example.test'), null, null) > 0, 'el mismo contacto puede esperar en otro evento');
$b = WaitlistService::join($event, $dur, $guest('Beto', 'beto@example.test'), null, null);
$c = WaitlistService::join($event, $dur, $guest('Carla', 'carla@example.test', '55550003'), null, null);

T::section('Oferta al liberarse un horario');
$slotBooking = $freed(3);
WaitlistService::onSlotFreed($slotBooking);
$w = Db::one('SELECT * FROM waitlist WHERE id = ?', [$a]);
T::ok($w['status'] === 'offered' && preg_match('/^[a-f0-9]{32}$/', (string) $w['offer_token']) && $w['offer_starts_at'] === $slotBooking['starts_at'], 'el primero en la fila recibe la oferta con token de 128 bits');
T::eq(Clock::now() + 15 * 60, \App\Core\Tz::ts((string) $w['offer_expires_at']), 'vence en 15 minutos');
T::eq('waiting', $status($b), 'el segundo sigue esperando');
$mail = Db::one("SELECT * FROM email_queue WHERE to_email = 'ana@example.test' ORDER BY id DESC LIMIT 1");
T::ok($mail !== null && str_contains($mail['body_html'], '/espera/' . $w['offer_token']) && str_contains($mail['body_html'], '15 minutos'), 'correo encolado con el enlace /espera/{token}');
$wa = Db::one("SELECT * FROM message_queue WHERE phone = '50255550001' ORDER BY id DESC LIMIT 1");
T::ok($wa !== null && str_contains($wa['body'], '/espera/' . $w['offer_token']) && $wa['status'] === 'pending', 'mensaje de WhatsApp encolado');
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM message_queue WHERE phone = '' OR phone IS NULL"), 'sin teléfono no se encola WhatsApp');
WaitlistService::onSlotFreed($slotBooking);
T::eq(1, (int) Db::val("SELECT COUNT(*) FROM waitlist WHERE status = 'offered' AND offer_starts_at = ?", [$slotBooking['starts_at']]), 'no se vuelve a ofrecer un horario con oferta vigente');

T::section('Consulta de la oferta');
$o = WaitlistService::offerByToken((string) $w['offer_token']);
T::ok($o !== null && (int) $o['id'] === $a && $o['start'] === $slotBooking['starts_at'] && $o['seconds_left'] > 800 && $o['event']['id'] === 1, 'offerByToken devuelve la oferta vigente con evento y segundos restantes');
T::eq(null, WaitlistService::offerByToken(str_repeat('a', 32)), 'token desconocido → null');
T::eq(null, WaitlistService::offerByToken("x' OR 1=1"), 'token mal formado → null');

T::section('Expiración y siguiente en la fila');
Clock::set(Clock::now() + 16 * 60);
$r = WaitlistService::tick();
T::eq(['expired' => 1, 'offered' => 1], $r, 'tick expira la oferta y ofrece al siguiente');
T::eq(['expired', 'offered'], [$status($a), $status($b)], 'Ana expiró; Beto recibió la oferta');
T::eq(null, WaitlistService::offerByToken((string) $w['offer_token']), 'la oferta vencida ya no es válida');
$wb = Db::one('SELECT * FROM waitlist WHERE id = ?', [$b]);
T::eq($slotBooking['starts_at'], $wb['offer_starts_at'], 'se ofrece el mismo horario');
Clock::set(Clock::now() + 5 * 60);
T::eq(['expired' => 0, 'offered' => 0], WaitlistService::tick(), 'tick sin ofertas vencidas no hace nada');
WaitlistService::markBooked((string) $wb['offer_token'], 4242);
T::eq(['booked', '4242'], [$status($b), (string) Db::val('SELECT booking_id FROM waitlist WHERE id = ?', [$b])], 'markBooked');
T::eq('waiting', $status($c), 'Carla sigue esperando');
Clock::set(null);

T::section('Rechazo y cancelación');
$slot2 = $freed(4);
WaitlistService::onSlotFreed($slot2);
T::eq('offered', $status($c), 'Carla recibe la oferta');
$tok = (string) Db::val('SELECT offer_token FROM waitlist WHERE id = ?', [$c]);
$d = WaitlistService::join($event, $dur, $guest('Diego', 'diego@example.test'), null, null);
T::ok(WaitlistService::cancelByToken($tok), 'Carla rechaza desde el enlace');
T::eq(['cancelled', 'offered'], [$status($c), $status($d)], 'al rechazar, el horario pasa a Diego');
T::eq(false, WaitlistService::cancelByToken($tok), 'rechazar dos veces no hace nada');
WaitlistService::cancel($d);
T::eq('cancelled', $status($d), 'cancelar desde el panel');

T::section('Compatibilidad: duración, fecha deseada y anfitrión');
Db::exec("UPDATE waitlist SET status = 'cancelled' WHERE status IN ('waiting','offered')");
$long = WaitlistService::join($event, 45, $guest('Larga', 'larga@example.test'), null, null);
$slot3 = $freed(5);
WaitlistService::onSlotFreed($slot3);
T::eq('waiting', $status($long), 'duración distinta no se ofrece');
$localDate = \App\Core\Tz::format($slot3['starts_at'], 'America/Guatemala', 'Y-m-d');
$otherDate = gmdate('Y-m-d', strtotime($localDate . ' UTC') + 86400);
$wrongDay = WaitlistService::join($event, $dur, $guest('Otro Dia', 'otrodia@example.test'), $otherDate, null);
$rightDay = WaitlistService::join($event, $dur, $guest('Mismo Dia', 'mismodia@example.test'), $localDate, null);
$wrongHost = WaitlistService::join($event, $dur, $guest('Otro Host', 'otrohost@example.test'), null, 9999);
$slot4 = $freed(5, 1);
WaitlistService::onSlotFreed($slot3);
T::eq(['waiting', 'offered', 'waiting'], [$status($wrongDay), $status($rightDay), $status($wrongHost)], 'solo se ofrece a quien coincide con fecha y anfitrión');
Db::exec("UPDATE waitlist SET status = 'cancelled' WHERE status = 'offered'");

T::section('Horario ya ocupado');
$slot5 = $freed(6);
$sb = Db::one('SELECT * FROM bookings WHERE id = ?', [(int) $slot5['id']]);
Db::update('bookings', ['status' => 'confirmed'], 'id = ?', [(int) $slot5['id']]);
$before = (int) Db::val("SELECT COUNT(*) FROM waitlist WHERE status = 'offered'");
WaitlistService::onSlotFreed($sb);
T::eq($before, (int) Db::val("SELECT COUNT(*) FROM waitlist WHERE status = 'offered'"), 'si el horario ya no está libre no se ofrece');
Db::update('bookings', ['status' => 'cancelled'], 'id = ?', [(int) $slot5['id']]);

T::section('Carrera: un solo ganador');
Db::exec("UPDATE waitlist SET status = 'cancelled' WHERE status IN ('waiting','offered')");
foreach (['u1', 'u2', 'u3', 'u4', 'u5', 'u6'] as $u) {
    WaitlistService::join($event, $dur, $guest($u, $u . '@example.test'), null, null);
}
$slot6 = $freed(2, 2);
$params = [];
for ($i = 0; $i < 8; $i++) {
    $params[] = ['booking' => $slot6];
}
$res = Sb1::race('sb1_waitlist', 'offer', $params);
T::eq(8, count(array_filter($res, fn ($x) => $x === 'OK')), 'los 8 procesos terminan sin error');
T::eq(1, (int) Db::val("SELECT COUNT(*) FROM waitlist WHERE status = 'offered' AND offer_starts_at = ?", [$slot6['starts_at']]), 'exactamente una oferta para el horario con 8 procesos simultáneos');
T::eq('u1', (string) Db::val("SELECT name FROM waitlist WHERE status = 'offered'"), 'la oferta es para el primero de la fila');
T::eq(1, (int) Db::val("SELECT COUNT(*) FROM email_queue WHERE to_email LIKE 'u%@example.test'"), 'un solo correo de oferta');

T::section('Fecha deseada vencida');
$old = WaitlistService::join($event, $dur, $guest('Pasado', 'pasado@example.test'), gmdate('Y-m-d', Clock::now() + 86400), null);
Clock::set(Clock::now() + 3 * 86400);
WaitlistService::tick();
T::eq('expired', $status($old), 'tick vence a quien deseaba una fecha que ya pasó');
Clock::set(null);

T::done();
