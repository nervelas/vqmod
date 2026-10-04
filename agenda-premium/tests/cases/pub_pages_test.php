<?php
declare(strict_types=1);

// Enrutamiento, encuestas, lista de espera, pago y comprobante, receta de seguridad de archivos y página de inicio.
require __DIR__ . '/../lib/T.php';
T::boot('pubp');
require __DIR__ . '/../lib/PubHttp.php';
PubHttp::start('/tmp/ap-t-pubp.config.php', 8193);

use App\Core\Clock;
use App\Core\Db;
use App\Core\Settings;
use App\Services\PollService;

Settings::set('booking_min_form_seconds', '0');
Settings::set('booking_rate_limit', '100');
$now = Clock::utc();
$sid = Db::insert('schedules', ['name' => 'Pruebas', 'timezone' => 'America/Guatemala', 'is_default' => 1, 'created_at' => $now]);
for ($d = 1; $d <= 7; $d++) { Db::insert('schedule_rules', ['schedule_id' => $sid, 'weekday' => $d, 'start_time' => '08:00:00', 'end_time' => '20:00:00']); }
$hid = Db::insert('hosts', ['name' => 'Dr. Pruebas', 'slug' => 'dr-pruebas', 'timezone' => 'America/Guatemala', 'ics_token' => bin2hex(random_bytes(16)), 'schedule_id' => $sid, 'created_at' => $now]);
$mk = static function (array $d) use ($now, $hid): int {
    $id = Db::insert('event_types', $d + ['duration_options' => '30', 'default_duration' => 30, 'min_notice_minutes' => 0, 'max_advance_days' => 60, 'slot_interval' => 30, 'cancel_hours' => 0, 'created_at' => $now, 'updated_at' => $now]);
    Db::insert('event_hosts', ['event_type_id' => $id, 'host_id' => $hid, 'weight' => 1, 'priority' => 1]);
    return $id;
};
$eFree = $mk(['slug' => 'gratis', 'name' => 'Cita gratuita']);
$ePay = $mk(['slug' => 'de-pago', 'name' => 'Cita con anticipo', 'price' => 200, 'deposit_type' => 'fixed', 'deposit_value' => 50]);
$eGroup = $mk(['slug' => 'grupal', 'name' => 'Sesión grupal', 'kind' => 'group', 'capacity' => 3]);

function http(string $m, string $p, $d = null, array $h = [], bool $j = true): array { return PubHttp::req($m, $p, $d, $h, $j); }
$bt = PubHttp::boot('/e/gratis');
$csrf = $bt['csrf']['book'];
$mcsrf = $bt['csrf']['manage'];
[, $sb] = http('GET', '/_/slots?event=gratis&duration=30');
$days = (array) json_decode($sb, true)['days'];
$dates = array_keys($days);
$slots = [];
foreach ($dates as $dt) { foreach ($days[$dt] as $s) { $slots[] = $s['s']; } }

T::section('Enrutamiento');
$fid = Db::insert('routing_forms', ['slug' => 'orientacion', 'name' => 'Te orientamos', 'questions' => json_encode([['id' => 'necesidad', 'label' => '¿Qué necesitas?', 'type' => 'select', 'options' => ['Consulta gratis', 'Otra cosa'], 'required' => true]]), 'default_action' => 'message', 'default_message' => 'Escríbenos por WhatsApp y te ayudamos.', 'created_at' => $now]);
Db::insert('routing_rules', ['form_id' => $fid, 'priority' => 1, 'match_mode' => 'all', 'conditions' => json_encode([['question' => 'necesidad', 'op' => 'eq', 'value' => 'Consulta gratis']]), 'action' => 'event', 'action_value' => 'gratis']);
[$c, $b] = http('GET', '/enrutar/orientacion');
T::ok($c === 200 && str_contains($b, '¿Qué necesitas?'), 'formulario de enrutamiento visible');
T::eq(404, http('GET', '/enrutar/no-existe')[0], 'formulario inexistente 404');
T::eq(419, http('POST', '/enrutar/orientacion', ['a' => ['necesidad' => 'Consulta gratis']], [], false)[0], 'enrutar sin CSRF → 419');
[$c, $b, $h] = http('POST', '/enrutar/orientacion', ['a' => ['necesidad' => 'Consulta gratis'], '_csrf' => $csrf], [], false);
T::ok(in_array($c, [302, 303], true) && str_contains($h['location'][0] ?? '', '/e/gratis'), 'la regla redirige al evento correcto');
[$c, $b] = http('POST', '/enrutar/orientacion', ['a' => ['necesidad' => 'Otra cosa'], '_csrf' => $csrf], [], false);
T::ok($c === 200 && str_contains($b, 'Escríbenos por WhatsApp'), 'sin regla: se muestra el mensaje por defecto');
[$c, $b] = http('POST', '/enrutar/orientacion', ['a' => ['necesidad' => ''], '_csrf' => $csrf], [], false);
T::ok($c === 422 && !str_contains($b, 'Exception'), 'respuesta obligatoria vacía → 422 amable');
[$c, $b] = http('POST', '/enrutar/orientacion', ['a' => ['necesidad' => 'Otra cosa'], 'company_site' => 'x', '_csrf' => $csrf], [], false);
T::eq(404, $c, 'honeypot en enrutamiento → 404');

T::section('Encuestas de horarios');
$poll = Db::insert('polls', ['token' => ($pt = bin2hex(random_bytes(16))), 'title' => 'Reunión <b>de equipo</b>', 'host_id' => $hid, 'event_type_id' => $eFree, 'duration' => 30, 'timezone' => 'America/Guatemala', 'status' => 'open', 'created_at' => $now]);
$o1 = Db::insert('poll_options', ['poll_id' => $poll, 'starts_at' => gmdate('Y-m-d H:i:s', Clock::now() + 86400 * 3), 'ends_at' => gmdate('Y-m-d H:i:s', Clock::now() + 86400 * 3 + 1800)]);
$o2 = Db::insert('poll_options', ['poll_id' => $poll, 'starts_at' => gmdate('Y-m-d H:i:s', Clock::now() + 86400 * 4), 'ends_at' => gmdate('Y-m-d H:i:s', Clock::now() + 86400 * 4 + 1800)]);
[$c, $b] = http('GET', '/encuesta/' . $pt);
T::ok($c === 200 && !str_contains($b, '<b>de equipo</b>') && str_contains($b, '&lt;b&gt;de equipo'), 'encuesta visible con título escapado');
T::eq(419, http('POST', '/encuesta/' . $pt, ['name' => 'A', 'email' => 'a@example.com', 'v' => [$o1 => 'yes']], [], false)[0], 'votar sin CSRF → 419');
[$c, $b, $h] = http('POST', '/encuesta/' . $pt, ['name' => 'Lucía <i>x</i>', 'email' => 'lucia@example.com', 'v' => [$o1 => 'yes', $o2 => 'maybe'], '_csrf' => $csrf], [], false);
T::ok(in_array($c, [302, 303], true), 'voto registrado');
http('POST', '/encuesta/' . $pt, ['name' => 'Pedro', 'email' => 'pedro@example.com', 'v' => [$o1 => 'no', $o2 => 'yes'], '_csrf' => $csrf], [], false);
[$c, $b] = http('GET', '/encuesta/' . $pt . '?m=ok');
T::ok(!str_contains($b, 'lucia@example.com') && !str_contains($b, 'pedro@example.com') && !str_contains($b, 'Pedro') && !str_contains($b, 'Lucía'), 'la encuesta no muestra nombres ni correos de otras personas');
T::ok(str_contains($b, 'Sí 1 · Tal vez 0 · No 1') || str_contains($b, 'Sí 1'), 'muestra solo totales');
[$c] = http('POST', '/encuesta/' . $pt, ['name' => 'X', 'email' => 'mal', 'v' => [$o1 => 'yes'], '_csrf' => $csrf], [], false);
T::eq(422, $c, 'voto con correo inválido → 422');
Db::update('polls', ['status' => 'closed'], 'id = ?', [$poll]);
[$c] = http('POST', '/encuesta/' . $pt, ['name' => 'Tarde', 'email' => 'tarde@example.com', 'v' => [$o1 => 'yes'], '_csrf' => $csrf], [], false);
T::ok($c >= 400, 'encuesta cerrada no recibe votos (' . $c . ')');

T::section('Lista de espera');
$wt = bin2hex(random_bytes(16));
Db::insert('waitlist', ['event_type_id' => $eFree, 'name' => 'Rosa Espera', 'email' => 'rosa@example.com', 'phone' => '50255550000', 'timezone' => 'America/Guatemala', 'duration' => 30, 'status' => 'offered', 'offer_token' => $wt, 'offer_starts_at' => $slots[4], 'offer_host_id' => $hid, 'offer_expires_at' => gmdate('Y-m-d H:i:s', Clock::now() + 900), 'created_at' => $now]);
[$c, $b] = http('GET', '/espera/' . $wt);
T::ok($c === 200 && str_contains($b, 'Rosa') && str_contains($b, 'Cita gratuita'), 'oferta vigente visible');
T::eq(419, http('POST', '/espera/' . $wt . '/aceptar', ['consent' => 1], [], false)[0], 'aceptar sin CSRF → 419');
[$c, $b] = http('POST', '/espera/' . $wt . '/aceptar', ['_csrf' => $csrf], [], false);
T::ok($c === 422, 'aceptar sin consentimiento → 422');
[$c, $b, $h] = http('POST', '/espera/' . $wt . '/aceptar', ['consent' => 1, '_csrf' => $csrf], [], false);
T::ok(in_array($c, [302, 303], true) && preg_match('#/reserva/[a-f0-9]{32}#', $h['location'][0] ?? ''), 'aceptar crea la cita y redirige a ella');
T::eq('waitlist', (string) Db::val("SELECT created_via FROM bookings WHERE guest_email = 'rosa@example.com'"), 'cita creada con origen waitlist');
T::eq('booked', (string) Db::val('SELECT status FROM waitlist WHERE offer_token = ?', [$wt]), 'oferta marcada como reservada');
T::eq(410, http('GET', '/espera/' . $wt)[0], 'la oferta usada ya no está disponible');
$wt2 = bin2hex(random_bytes(16));
Db::insert('waitlist', ['event_type_id' => $eFree, 'name' => 'Vencida', 'email' => 'v@example.com', 'timezone' => 'America/Guatemala', 'duration' => 30, 'status' => 'offered', 'offer_token' => $wt2, 'offer_starts_at' => $slots[6], 'offer_host_id' => $hid, 'offer_expires_at' => gmdate('Y-m-d H:i:s', Clock::now() - 60), 'created_at' => $now]);
T::eq(410, http('GET', '/espera/' . $wt2)[0], 'oferta vencida → 410');
[$c, $b] = http('POST', '/_/waitlist', ['event' => 'gratis', 'duration' => 30, 'name' => 'Nuevo', 'email' => 'nuevo@example.com', 'consent' => 1], ['X-CSRF-Token' => $csrf]);
T::ok($c === 200 && (int) Db::val("SELECT COUNT(*) FROM waitlist WHERE email = 'nuevo@example.com' AND status = 'waiting'") === 1, 'unirse a la lista de espera por JSON');
[$c, $b] = http('POST', '/_/waitlist', ['event' => 'gratis', 'duration' => 30, 'name' => 'Sin consentimiento', 'email' => 'sc@example.com'], ['X-CSRF-Token' => $csrf]);
T::eq(422, $c, 'lista de espera exige consentimiento');

T::section('Pago, comprobante y archivos');
Settings::set('bank_info', "Banco Ejemplo\nCuenta 123-456 <b>x</b>");
Settings::set('payment_link', 'https://pagos.example.com/abc');
$btp = PubHttp::boot('/e/de-pago');
$st = $btp['quotes']['30'];
T::ok(abs($st['deposit_due'] - 50.0) < 0.01 && $st['needs_payment'], 'cotización con anticipo en la página');
[, $sb2] = http('GET', '/_/slots?event=de-pago&duration=30');
$d2 = (array) json_decode($sb2, true)['days']; $k2 = array_key_first($d2);
[$c, $b] = http('POST', '/_/book', ['event' => 'de-pago', 'duration' => 30, 'start' => $d2[$k2][2]['s'], 'tz' => 'America/Guatemala', 'name' => 'Pago Prueba', 'email' => 'pago@example.com', 'phone' => '55557777', 'consent' => 1, 'form_ts' => $btp['form_ts']], ['X-CSRF-Token' => $btp['csrf']['book']]);
$r = json_decode($b, true);
T::ok($c === 200 && !empty($r['needs_payment']), 'la reserva indica que requiere pago');
$tok = (string) $r['token'];
[$c, $b] = http('GET', '/reserva/' . $tok);
T::ok(str_contains($b, 'Banco Ejemplo') && !str_contains($b, '<b>x</b>') && str_contains($b, 'pagos.example.com/abc') && str_contains($b, 'name="proof"'), 'datos de pago escapados, enlace de pago y subida de comprobante');
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
file_put_contents('/tmp/ap-proof.png', $png);
file_put_contents('/tmp/ap-proof.php.png', '<?php echo 1;');
T::eq(419, http('POST', '/reserva/' . $tok . '/comprobante', ['proof' => new CURLFile('/tmp/ap-proof.png', 'image/png', 'comprobante.png')], [], false)[0], 'comprobante sin CSRF → 419');
[$c, $b] = http('POST', '/reserva/' . $tok . '/comprobante', ['_csrf' => $btp['csrf']['manage'], 'proof' => new CURLFile('/tmp/ap-proof.php.png', 'image/png', 'x.php.png')], [], false);
T::eq(422, $c, 'archivo con doble extensión ejecutable rechazado');
[$c, $b, $h] = http('POST', '/reserva/' . $tok . '/comprobante', ['_csrf' => $btp['csrf']['manage'], 'proof' => new CURLFile('/tmp/ap-proof.png', 'image/png', 'comprobante.png')], [], false);
T::ok(in_array($c, [302, 303], true), 'comprobante válido aceptado (' . $c . ')');
T::eq(1, (int) Db::val("SELECT COUNT(*) FROM payments WHERE booking_id = (SELECT id FROM bookings WHERE token = ?) AND status = 'pending' AND proof_file_id IS NOT NULL", [$tok]), 'pago pendiente con comprobante registrado');
[$c, $b] = http('GET', '/reserva/' . $tok);
T::ok(str_contains($b, 'estamos verificando'), 'la página indica que el comprobante está en revisión');
$ft = (string) Db::val("SELECT token FROM files WHERE kind = 'proof' LIMIT 1");
T::eq(403, http('GET', '/f/' . $ft)[0], 'el comprobante es privado para el público (403)');
[$c, $b] = http('POST', '/_/upload', [], ['X-CSRF-Token' => $csrf]);
T::eq(422, $c, 'subida pública sin archivo → 422');
[$c, $b] = http('POST', '/_/upload', ['file' => new CURLFile('/tmp/ap-proof.png', 'image/png', 'estudio.png')], ['X-CSRF-Token' => $csrf]);
$u = json_decode($b, true);
T::ok($c === 200 && preg_match('/^[a-f0-9]{32}$/', (string) ($u['file_token'] ?? '')), 'subida pública devuelve file_token');

T::section('Evento grupal y cupón');
$bg = PubHttp::boot('/e/grupal');
[, $sg] = http('GET', '/_/slots?event=grupal&duration=30');
$dg = (array) json_decode($sg, true)['days']; $kg = array_key_first($dg);
T::ok(isset($dg[$kg][0]['n']) && (int) $dg[$kg][0]['n'] === 3, 'cupos restantes visibles en el horario grupal');
Db::insert('coupons', ['code' => 'PRUEBA20', 'type' => 'percent', 'value' => 20, 'active' => 1]);
[$c, $b] = http('POST', '/_/coupon', ['event' => 'de-pago', 'duration' => 30, 'seats' => 1, 'coupon' => 'prueba20'], ['X-CSRF-Token' => $csrf]);
$cj = json_decode($b, true);
T::ok($c === 200 && abs($cj['quote']['discount'] - 40.0) < 0.01, 'cupón válido aplica 20 %');
[$c, $b] = http('POST', '/_/coupon', ['event' => 'de-pago', 'duration' => 30, 'seats' => 1, 'coupon' => 'NOEXISTE'], ['X-CSRF-Token' => $csrf]);
T::ok($c === 422 && !empty(json_decode($b, true)['error']), 'cupón inválido → mensaje amable');

T::section('Embudo');
[$c, $b] = http('POST', '/_/track', ['step' => 'view', 'event' => 'gratis', 'visit_id' => 'abcdef0123456789', 'utm_source' => 'prueba'], ['X-CSRF-Token' => $csrf, 'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Chrome/120']);
T::ok($c === 200 && (int) Db::val("SELECT COUNT(*) FROM analytics_events WHERE visit_id = 'abcdef0123456789' AND step = 'view'") === 1, 'paso "view" registrado sin cookies');
http('POST', '/_/track', ['step' => 'booked', 'event' => 'gratis', 'visit_id' => 'abcdef0123456789'], ['X-CSRF-Token' => $csrf, 'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Chrome/120']);
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM analytics_events WHERE step = 'booked' AND visit_id = 'abcdef0123456789'"), 'el cliente no puede falsear el paso "booked"');

T::done();
