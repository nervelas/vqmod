<?php
declare(strict_types=1);

// Experiencia pública por HTTP real: reserva completa, CSRF, honeypot, límite de frecuencia, evento pausado, XSS, tokens.
require __DIR__ . '/../lib/T.php';
T::boot('pub');

use App\Core\Clock;
use App\Core\Db;
use App\Core\Settings;

require __DIR__ . '/../lib/PubHttp.php';
PubHttp::start('/tmp/ap-t-pub.config.php', 8192);

function http(string $method, string $path, $data = null, array $headers = [], bool $json = true): array
{
    return PubHttp::req($method, $path, $data, $headers, $json);
}
function boot(string $slug, string $q = ''): array
{
    return PubHttp::boot('/e/' . $slug . $q);
}

// ---------------------------------------------------------------- datos de prueba
Settings::set('booking_min_form_seconds', '0');
Settings::set('booking_rate_limit', '100');
$now = Clock::utc();
$sid = Db::insert('schedules', ['name' => 'Pruebas', 'timezone' => 'America/Guatemala', 'is_default' => 1, 'created_at' => $now]);
for ($d = 1; $d <= 7; $d++) { Db::insert('schedule_rules', ['schedule_id' => $sid, 'weekday' => $d, 'start_time' => '08:00:00', 'end_time' => '20:00:00']); }
$hid = Db::insert('hosts', ['name' => 'Dra. Prueba', 'slug' => 'dra-prueba', 'timezone' => 'America/Guatemala', 'ics_token' => bin2hex(random_bytes(16)), 'schedule_id' => $sid, 'created_at' => $now]);
$mk = static function (array $d) use ($now, $hid): int {
    $id = Db::insert('event_types', $d + ['duration_options' => '30', 'default_duration' => 30, 'min_notice_minutes' => 0, 'max_advance_days' => 60, 'slot_interval' => 30, 'created_at' => $now, 'updated_at' => $now]);
    Db::insert('event_hosts', ['event_type_id' => $id, 'host_id' => $hid, 'weight' => 1, 'priority' => 1]);
    return $id;
};
$e1 = $mk(['slug' => 'consulta', 'name' => 'Consulta de prueba', 'price' => 100, 'cancel_hours' => 0, 'description' => 'Descripción **clara**']);
$e2 = $mk(['slug' => 'pausado', 'name' => 'Evento pausado', 'active' => 0]);
$e3 = $mk(['slug' => 'secreto', 'name' => 'Evento secreto privado', 'visibility' => 'secret']);
$e4 = $mk(['slug' => 'otro-evento', 'name' => 'Otro evento']);
$f1 = Db::insert('custom_fields', ['event_type_id' => $e1, 'name' => 'tipo', 'label' => 'Tipo de consulta', 'type' => 'radio', 'options' => "Primera vez\nSeguimiento", 'required' => 1, 'sort_order' => 1]);
$f2 = Db::insert('custom_fields', ['event_type_id' => $e1, 'name' => 'detalle', 'label' => 'Cuéntanos más', 'type' => 'textarea', 'required' => 1, 'condition_field' => 'tipo', 'condition_value' => 'Primera vez', 'sort_order' => 2]);

// ---------------------------------------------------------------- páginas públicas
T::section('Páginas públicas');
[$c, $b, $h] = http('GET', '/');
T::eq(200, $c, 'inicio responde 200');
T::ok(str_contains($b, 'Consulta de prueba') && !str_contains($b, 'Evento secreto privado') && !str_contains($b, 'Evento pausado'), 'inicio lista públicos y oculta secretos y pausados');
T::ok(empty($h['set-cookie']), 'el inicio no envía cookies');
[$c, $b, $h] = http('GET', '/e/consulta');
T::eq(200, $c, 'página de reserva 200');
T::ok(empty($h['set-cookie']), 'la reserva no envía cookies');
T::ok(!preg_match('/<script(?![^>]*src=)(?![^>]*type="application\/json")[^>]*>/i', $b), 'sin <script> en línea (solo JSON no ejecutable)');
T::ok(!preg_match('/\sstyle="/i', $b), 'sin atributos style en línea');
T::ok(!preg_match('/\son(click|load|error)=/i', $b), 'sin manejadores en línea');
T::ok(!str_contains($b, 'challenges.cloudflare.com') && !str_contains($b, 'hcaptcha'), 'sin captcha ni recursos externos por defecto');
T::ok(in_array('SAMEORIGIN', $h['x-frame-options'] ?? [], true), 'sin ?embed=1 no se permite incrustar');
[$c, $b, $h] = http('GET', '/e/consulta?embed=1');
T::ok(empty($h['x-frame-options']) && str_contains(implode(' ', $h['content-security-policy'] ?? []), 'frame-ancestors'), 'con ?embed=1 se permite incrustar');
T::eq(404, http('GET', '/e/no-existe')[0], 'evento inexistente 404');
[$c, $b] = http('GET', '/e/pausado');
T::ok(!str_contains($b, 'data-form') && str_contains($b, 'no está disponible'), 'evento pausado: sin formulario y con mensaje amable');
T::eq(200, http('GET', '/e/secreto')[0], 'evento secreto accesible por enlace');
T::eq(404, http('GET', '/h/no-existe')[0], 'anfitrión inexistente 404');
T::eq(200, http('GET', '/h/dra-prueba')[0], 'perfil del anfitrión 200');
T::eq(200, http('GET', '/privacidad')[0], 'privacidad 200');
T::eq(200, http('GET', '/terminos')[0], 'términos 200');

// ---------------------------------------------------------------- tokens inválidos
T::section('Tokens inválidos');
foreach (['/reserva/zzzz', '/reserva/' . str_repeat('a', 32), '/reserva/' . str_repeat('a', 31), '/reserva/' . str_repeat('a', 32) . '/recibo', '/reserva/' . str_repeat('b', 32) . '/evento.ics', '/espera/' . str_repeat('c', 31), '/encuesta/' . str_repeat('d', 32), '/resena/' . str_repeat('e', 32)] as $p) {
    T::eq(404, http('GET', $p)[0], 'GET ' . substr($p, 0, 20) . '… → 404');
}
// la oferta de espera vencida o desconocida es 410 amable
T::eq(410, http('GET', '/espera/' . str_repeat('c', 32))[0], 'oferta desconocida → 410 con mensaje');

// ---------------------------------------------------------------- horarios
T::section('Horarios');
[$c, $b] = http('GET', '/_/slots?event=consulta&duration=30&next=1&tz=America/Guatemala');
$sl = json_decode($b, true);
T::ok($c === 200 && !empty($sl['ok']) && !empty($sl['days']) && !empty($sl['next']), 'slots devuelve días y próximo horario');
T::ok(!str_contains($b, 'host_id') && !str_contains($b, 'email'), 'slots no expone datos internos');
T::eq(404, http('GET', '/_/slots?event=pausado')[0], 'slots de evento pausado → 404');
[$c, $b] = http('GET', '/_/slots?event=consulta&duration=30&tz=Mars/Olympus&from=basura&to=basura');
T::ok($c === 200 && json_decode($b, true)['tz'] === 'America/Guatemala', 'zona y fechas inválidas se corrigen');
$days = (array) $sl['days'];
$dates = array_keys($days);
$slotA = $days[$dates[0]][0]['s'];
$slotB = $days[$dates[0]][1]['s'];
$slotC = $days[$dates[1]][0]['s'];

// ---------------------------------------------------------------- reserva: seguridad
T::section('CSRF, honeypot, tiempo y frecuencia');
$bt = boot('consulta');
$csrf = $bt['csrf']['book'];
$body = ['event' => 'consulta', 'duration' => 30, 'start' => $slotA, 'tz' => 'America/Guatemala', 'name' => 'Ana Prueba', 'email' => 'ana@example.com', 'phone' => '5555 1234', 'consent' => 1, 'answers' => [$f1 => 'Seguimiento'], 'form_ts' => $bt['form_ts']];
T::eq(419, http('POST', '/_/book', $body)[0], 'POST sin CSRF público → 419');
T::eq(419, http('POST', '/_/book', $body, ['X-CSRF-Token' => 'basura.basura.basura'])[0], 'CSRF falso → 419');
T::eq(419, http('POST', '/_/book', $body, ['X-CSRF-Token' => $bt['csrf']['manage']])[0], 'CSRF de otro alcance (manage) → 419');
T::eq(419, http('POST', '/_/track', ['step' => 'view'])[0], 'track sin CSRF → 419');
T::eq(419, http('POST', '/_/coupon', ['event' => 'consulta'])[0], 'cupón sin CSRF → 419');
T::eq(419, http('POST', '/_/upload', ['x' => 1])[0], 'upload sin CSRF → 419');
T::eq(419, http('POST', '/_/waitlist', ['x' => 1])[0], 'lista de espera sin CSRF → 419');
$before = (int) Db::val('SELECT COUNT(*) FROM bookings');
[$c, $b] = http('POST', '/_/book', $body + ['company_site' => 'http://spam.example'], ['X-CSRF-Token' => $csrf]);
T::ok($c === 422 && (int) Db::val('SELECT COUNT(*) FROM bookings') === $before, 'honeypot lleno → rechazo y sin reserva');
[$c, $b] = http('POST', '/_/book', array_merge($body, ['form_ts' => '1.abc']), ['X-CSRF-Token' => $csrf]);
T::eq(419, $c, 'marca de tiempo del formulario falsificada → 419');
Settings::set('booking_min_form_seconds', '60');
$bt2 = boot('consulta');
[$c, $b] = http('POST', '/_/book', array_merge($body, ['form_ts' => $bt2['form_ts']]), ['X-CSRF-Token' => $bt2['csrf']['book']]);
T::ok($c === 429 && (int) Db::val('SELECT COUNT(*) FROM bookings') === $before, 'llenado demasiado rápido → 429 sin reserva');
Settings::set('booking_min_form_seconds', '0');
[$c, $b] = http('POST', '/_/book', $body + ['start' => $slotA], ['X-CSRF-Token' => $csrf]);
// (este intento válido se usa más abajo: se anula para probar el límite con intentos inválidos)
$resBad = json_decode($b, true);
T::ok($c === 200 && !empty($resBad['ok']), 'reserva válida pasa los controles de seguridad');
$firstToken = (string) ($resBad['token'] ?? '');
Settings::set('booking_rate_limit', '3');
Db::exec('DELETE FROM rate_limits');
$codes = [];
for ($i = 0; $i < 5; $i++) {
    $codes[] = http('POST', '/_/book', ['event' => 'consulta', 'form_ts' => $bt['form_ts']], ['X-CSRF-Token' => $csrf])[0];
}
T::ok(!in_array(429, array_slice($codes, 0, 3), true) && $codes[3] === 429 && $codes[4] === 429, 'límite de frecuencia por IP: 429 tras el máximo (' . implode(',', $codes) . ')');
Settings::set('booking_rate_limit', '100');
Db::exec('DELETE FROM rate_limits');

T::section('Evento pausado y validación');
$bp = boot('pausado');
[$c, $b] = http('POST', '/_/book', ['event' => 'pausado', 'duration' => 30, 'start' => $slotB, 'name' => 'Pepe', 'email' => 'pepe@example.com', 'phone' => '55551234', 'consent' => 1, 'form_ts' => $bt['form_ts']], ['X-CSRF-Token' => $csrf]);
T::ok($c === 409 && (int) Db::val('SELECT COUNT(*) FROM bookings WHERE event_type_id = ?', [$e2]) === 0, 'reservar evento pausado → 409 sin reserva');
[$c, $b] = http('POST', '/_/book', ['event' => 'consulta', 'duration' => 30, 'start' => $slotB, 'tz' => 'America/Guatemala', 'name' => '', 'email' => 'mal', 'phone' => '123', 'consent' => 0, 'form_ts' => $bt['form_ts']], ['X-CSRF-Token' => $csrf]);
$j = json_decode($b, true);
T::ok($c === 422 && isset($j['errors']['name'], $j['errors']['email'], $j['errors']['phone'], $j['errors']['consent'], $j['errors']['f_' . $f1]), 'validación estricta con mensajes por campo');
T::ok(!str_contains($b, 'Exception') && !str_contains($b, '.php'), 'los errores no exponen excepciones ni rutas');
[$c, $b] = http('POST', '/_/book', ['event' => 'consulta', 'duration' => 30, 'start' => $slotB, 'tz' => 'America/Guatemala', 'name' => 'Luis', 'email' => 'luis@example.com', 'phone' => '55551234', 'consent' => 1, 'answers' => [$f1 => 'Primera vez'], 'form_ts' => $bt['form_ts']], ['X-CSRF-Token' => $csrf]);
$j = json_decode($b, true);
T::ok($c === 422 && isset($j['errors']['f_' . $f2]), 'pregunta condicional visible y obligatoria se exige');
[$c, $b] = http('POST', '/_/book', ['event' => 'consulta', 'duration' => 30, 'start' => $slotA, 'tz' => 'America/Guatemala', 'name' => 'Repetido', 'email' => 'rep@example.com', 'phone' => '55551234', 'consent' => 1, 'answers' => [$f1 => 'Seguimiento'], 'form_ts' => $bt['form_ts']], ['X-CSRF-Token' => $csrf]);
T::ok($c === 409 && (json_decode($b, true)['code'] ?? '') === 'slot_unavailable', 'horario ya tomado → 409 slot_unavailable');

// ---------------------------------------------------------------- reserva completa + XSS
T::section('Reserva completa y XSS');
$xssName = '<script>alert("n")</script>Eva';
$xssNote = '"><img src=x onerror=alert(2)>';
$xssAns = '<svg/onload=alert(3)>';
[$c, $b] = http('POST', '/_/book', [
    'event' => 'consulta', 'duration' => 30, 'start' => $slotB, 'tz' => 'America/New_York', 'name' => $xssName, 'email' => 'eva@example.com',
    'phone' => '+502 5555 4321', 'notes' => $xssNote, 'consent' => 1, 'answers' => [$f1 => 'Primera vez', $f2 => $xssAns],
    'utm_source' => 'prueba', 'visit_id' => bin2hex(random_bytes(8)), 'form_ts' => $bt['form_ts'],
], ['X-CSRF-Token' => $csrf]);
$r = json_decode($b, true);
T::ok($c === 200 && !empty($r['ok']) && preg_match('/^[a-f0-9]{32}$/', (string) $r['token']), 'reserva completa por HTTP → token de 128 bits');
T::eq('confirmed', $r['status'] ?? '', 'estado confirmado');
$tok = (string) $r['token'];
$row = Db::one('SELECT * FROM bookings WHERE token = ?', [$tok]);
T::ok($row && $row['guest_timezone'] === 'America/New_York' && $row['created_via'] === 'public', 'zona del invitado y origen guardados');
T::ok((int) Db::val('SELECT COUNT(*) FROM consents WHERE booking_id = ?', [$row['id']]) >= 1, 'consentimiento registrado');
[$c, $b, $h] = http('GET', '/reserva/' . $tok);
T::eq(200, $c, 'página de confirmación 200');
T::ok(!str_contains($b, '<script>alert') && !str_contains($b, '<img src=x') && !str_contains($b, '<svg/onload'), 'XSS en nombre/notas/respuestas neutralizado en la confirmación');
T::ok(str_contains($b, '&lt;script&gt;alert'), 'el nombre se muestra escapado');
T::ok(empty($h['set-cookie']) && str_contains(implode(' ', $h['cache-control'] ?? []), 'no-store'), 'confirmación sin cookies y sin caché');
T::ok(str_contains($b, 'Eva') && str_contains($b, 'Consulta de prueba'), 'resumen con servicio y nombre');
[$c, $b] = http('GET', '/reserva/' . $tok . '/evento.ics');
T::ok($c === 200 && str_contains($b, 'BEGIN:VCALENDAR'), 'descarga .ics');
[$c, $b] = http('GET', '/reserva/' . $tok . '/recibo');
T::ok($c === 200 && !str_contains($b, '<script>alert') && str_contains($b, 'R-'), 'recibo imprimible sin XSS');
// la confirmación de una persona no revela a otra
T::ok(!str_contains($b, 'ana@example.com') && !str_contains($b, 'luis@example.com'), 'sin datos de otros invitados');

T::section('Gestión del invitado');
T::eq(419, http('POST', '/reserva/' . $tok . '/cancelar', ['reason' => 'x'], ['Accept' => 'text/html'])[0], 'cancelar sin CSRF → 419');
T::eq(419, http('POST', '/reserva/' . $tok . '/cancelar', ['reason' => 'x', '_csrf' => $csrf], [], false)[0], 'cancelar con CSRF del alcance book → 419');
T::eq(404, http('POST', '/reserva/' . str_repeat('9', 32) . '/cancelar', ['reason' => 'x', '_csrf' => $bt['csrf']['manage']], [], false)[0], 'cancelar token inexistente → 404');
$mc = $bt['csrf']['manage'];
[$c, $b, $h] = http('POST', '/reserva/' . $tok . '/reprogramar', ['start' => $slotC, '_csrf' => $mc], [], false);
T::ok(in_array($c, [302, 303], true) && str_contains($h['location'][0] ?? '', 'm=rescheduled'), 'reprogramar válido redirige con mensaje (' . $c . ')');
T::eq($slotC, (string) Db::val('SELECT starts_at FROM bookings WHERE token = ?', [$tok]), 'la cita cambió de horario');
[$c, $b, $h] = http('POST', '/reserva/' . $tok . '/cancelar', ['reason' => 'No puedo asistir <b>x</b>', '_csrf' => $mc], [], false);
T::ok(in_array($c, [302, 303], true), 'cancelar válido redirige');
T::eq('cancelled', (string) Db::val('SELECT status FROM bookings WHERE token = ?', [$tok]), 'la cita quedó cancelada');
[$c, $b] = http('GET', '/reserva/' . $tok);
T::ok($c === 200 && str_contains($b, 'cancelada') && !str_contains($b, 'data-resched-form'), 'la página refleja la cancelación sin reprogramar');
[$c, $b, $h] = http('POST', '/reserva/' . $tok . '/reprogramar', ['start' => $slotA, '_csrf' => $mc], [], false);
T::ok($c >= 400, 'no se reprograma una cita cancelada (' . $c . ')');

// ---------------------------------------------------------------- captcha
T::section('Captcha opcional');
Settings::set('captcha_provider', 'turnstile');
Settings::set('captcha_site_key', 'clave-publica');
Settings::set('captcha_secret', 'clave-secreta');
[$c, $b, $h] = http('GET', '/e/consulta');
T::ok(str_contains($b, 'challenges.cloudflare.com/turnstile') && str_contains($b, 'cf-turnstile'), 'con proveedor activo se carga el widget');
T::ok(str_contains(implode(' ', $h['content-security-policy'] ?? []), 'challenges.cloudflare.com'), 'la CSP admite el proveedor');
$bt3 = boot('consulta');
[$c, $b] = http('POST', '/_/book', array_merge($body, ['start' => $slotA, 'form_ts' => $bt3['form_ts']]), ['X-CSRF-Token' => $bt3['csrf']['book']]);
T::ok($c === 422 && ($j = json_decode($b, true)) && ($j['code'] ?? '') === 'captcha', 'sin token del captcha → 422');
Settings::set('captcha_provider', 'none');
[$c, $b] = http('GET', '/e/consulta');
T::ok(!str_contains($b, 'challenges.cloudflare.com'), 'al desactivarlo desaparece el script externo');

// ---------------------------------------------------------------- reseñas
T::section('Reseñas');
$rt = bin2hex(random_bytes(16));
Db::insert('reviews', ['token' => $rt, 'host_id' => $hid, 'client_name' => 'Marta Gómez', 'status' => 'requested', 'created_at' => $now]);
[$c, $b] = http('GET', '/resena/' . $rt);
T::ok($c === 200 && str_contains($b, 'Marta'), 'formulario de reseña');
$rcsrf = $bt['csrf']['book'];
T::eq(419, http('POST', '/resena/' . $rt, ['rating' => 5, 'comment' => 'x'], [], false)[0], 'reseña sin CSRF → 419');
[$c] = http('POST', '/resena/' . $rt, ['rating' => 9, 'comment' => 'x', '_csrf' => $rcsrf], [], false);
T::ok($c === 422, 'calificación fuera de rango rechazada');
[$c] = http('POST', '/resena/' . $rt, ['rating' => 5, 'comment' => '<b>Excelente</b>', '_csrf' => $rcsrf], [], false);
T::ok(in_array($c, [302, 303], true), 'reseña válida');
$rv = Db::one('SELECT * FROM reviews WHERE token = ?', [$rt]);
T::ok($rv['status'] === 'pending' && (int) $rv['rating'] === 5, 'reseña queda pendiente de moderación');
http('POST', '/resena/' . $rt, ['rating' => 1, 'comment' => 'cambio', '_csrf' => $rcsrf], [], false);
T::eq(5, (int) Db::val('SELECT rating FROM reviews WHERE token = ?', [$rt]), 'la reseña no se puede reescribir');
[$c, $b] = http('GET', '/');
T::ok(!str_contains($b, 'Excelente'), 'las reseñas pendientes no se publican');

T::done();
