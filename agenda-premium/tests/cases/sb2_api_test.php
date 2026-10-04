<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
T::boot('sb2_api', ['profession' => 'medico']);

use App\Core\ApiAuth;
use App\Core\Db;
use App\Core\Settings;
use App\Services\ApiDocs;
use App\Services\ProfessionService;

const PORT = 8109;
$root = dirname(__DIR__, 2);
ProfessionService::apply('medico', true);
Settings::set('api_rate_per_minute', '100000');
$read = ApiAuth::issue('Lectura', 'read', null)['key'];
$write = ApiAuth::issue('Escritura', 'write', null);
$wkey = $write['key'];
$revoked = ApiAuth::issue('Revocada', 'write', null);
ApiAuth::revoke($revoked['id']);

// Servidor embebido con la configuración de esta prueba
$log = '/tmp/ap-sb2-api-server.log';
$proc = proc_open(
    ['php', '-S', '127.0.0.1:' . PORT, '-t', $root, $root . '/tests/router.php'],
    [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']],
    $pipes,
    $root,
    ['AP_CONFIG' => getenv('AP_CONFIG'), 'PATH' => getenv('PATH')]
);
register_shutdown_function(static function () use ($proc): void {
    if (is_resource($proc)) {
        proc_terminate($proc);
        proc_close($proc);
    }
});
for ($i = 0; $i < 50; $i++) {
    if (@fsockopen('127.0.0.1', PORT)) {
        break;
    }
    usleep(100000);
}

function call(string $method, string $path, ?string $key = null, $body = null, array $headers = []): array
{
    $ch = curl_init('http://127.0.0.1:' . PORT . $path);
    $h = $headers;
    if ($key !== null) {
        $h[] = 'Authorization: Bearer ' . $key;
    }
    if ($body !== null) {
        $h[] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => 20]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $head = substr($raw, 0, $hs);
    $text = substr($raw, $hs);
    return ['status' => $status, 'head' => $head, 'text' => $text, 'json' => json_decode($text, true)];
}

T::section('Autenticación');
$r = call('GET', '/api/v1/events');
T::eq(401, $r['status'], 'sin clave: 401');
T::ok(isset($r['json']['error']), 'sin clave: mensaje de error en JSON');
T::ok(str_contains($r['head'], 'application/json'), 'sin clave: Content-Type JSON');
$r = call('GET', '/api/v1/events', 'ap_' . str_repeat('0', 40));
T::eq(401, $r['status'], 'clave con formato válido pero inexistente: 401');
$r = call('GET', '/api/v1/events', 'basura');
T::eq(401, $r['status'], 'clave con formato inválido: 401');
$r = call('GET', '/api/v1/events', $revoked['key']);
T::eq(401, $r['status'], 'clave revocada: 401');
T::ok(str_contains((string) ($r['json']['error'] ?? ''), 'revocada'), 'clave revocada: mensaje claro');
$r = call('GET', '/api/v1/events', null, null, ['X-API-Key: ' . $read]);
T::eq(200, $r['status'], 'X-API-Key también funciona');
$r = call('GET', '/api/v1/events?x=1', "$read' OR '1'='1");
T::eq(401, $r['status'], 'inyección en la clave: 401');
$r = call('POST', '/api/v1/bookings', $read, ['event_id' => 1]);
T::eq(403, $r['status'], 'clave de solo lectura intentando escribir: 403');
T::ok(isset($r['json']['error']), 'solo lectura: mensaje amable');
$r = call('POST', '/api/v1/clients', $read, ['name' => 'X Y', 'email' => 'x@example.test']);
T::eq(403, $r['status'], 'solo lectura no crea clientes');
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM clients WHERE email = 'x@example.test'"), 'y no se creó nada');
$r = call('POST', '/api/v1/bookings/1/cancel', $read, []);
T::eq(403, $r['status'], 'solo lectura no cancela');

T::section('Eventos');
$r = call('GET', '/api/v1/events', $read);
T::eq(200, $r['status'], 'GET /events: 200');
T::ok(is_array($r['json']['data']) && count($r['json']['data']) === 5, 'cinco eventos del preset médico');
T::ok(isset($r['json']['meta']['total'], $r['json']['meta']['limit'], $r['json']['meta']['offset'], $r['json']['meta']['has_more'], $r['json']['meta']['request_id']), 'meta con paginación y request_id');
$ev = $r['json']['data'][0];
T::ok(isset($ev['id'], $ev['slug'], $ev['durations'], $ev['questions'], $ev['hosts'], $ev['booking_url']) && $ev['currency'] === 'GTQ', 'evento con duraciones, preguntas, anfitriones y enlace');
foreach (['schedule_id', 'team_id', 'video_url', 'single_use', 'expires_at', 'created_at'] as $k) {
    T::ok(!array_key_exists($k, $ev), "el evento no expone $k");
}
$eventId = (int) $ev['id'];
$r = call('GET', '/api/v1/events?limit=2&offset=1', $read);
T::eq(2, count($r['json']['data']), 'paginación: limit=2');
T::eq(5, $r['json']['meta']['total'], 'paginación: total');
T::eq(true, $r['json']['meta']['has_more'], 'paginación: has_more');
$r = call('GET', '/api/v1/events?limit=2&offset=4', $read);
T::eq([1, false], [count($r['json']['data']), $r['json']['meta']['has_more']], 'paginación: última página');
foreach (['limit=0', 'limit=101', 'limit=abc', 'offset=-1', 'limit[]=3', 'kind=hack', 'mode=%27%20OR%201%3D1'] as $q) {
    $r = call('GET', '/api/v1/events?' . $q, $read);
    T::eq(400, $r['status'], "parámetro inválido $q: 400");
    T::eq('bad_request', $r['json']['code'] ?? null, "$q: código bad_request");
}
$r = call('GET', '/api/v1/events?mode=video_auto', $read);
T::eq(1, count($r['json']['data']), 'filtro por modalidad');
$r = call('GET', "/api/v1/events/$eventId", $read);
T::eq([200, $eventId], [$r['status'], $r['json']['data']['id'] ?? 0], 'GET /events/{id}');
$r = call('GET', '/api/v1/events/999999', $read);
T::eq([404, 'not_found'], [$r['status'], $r['json']['code'] ?? null], 'evento inexistente: 404');
$r = call('GET', '/api/v1/events/1%20OR%201=1', $read);
T::eq(404, $r['status'], 'id no numérico: 404');
$r = call('GET', '/api/v1/events/1;DROP%20TABLE%20bookings', $read);
T::eq(404, $r['status'], 'id con SQL: 404');
T::eq(1, (int) Db::val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings'"), 'la tabla bookings sigue ahí');

T::section('Disponibilidad');
$r = call('GET', "/api/v1/availability?event_id=$eventId", $read);
T::eq(200, $r['status'], 'GET /availability: 200');
$slots = $r['json']['data'];
T::ok(count($slots) > 0, 'hay horarios libres (' . count($slots) . ')');
$s0 = $slots[0];
T::ok(isset($s0['start'], $s0['end'], $s0['start_utc'], $s0['host_ids']) && str_contains($s0['start'], '-06:00'), 'horario con hora local de Guatemala y UTC');
$r2 = call('GET', "/api/v1/availability?event_id=$eventId&tz=America/New_York", $read);
T::ok(preg_match('/-0[45]:00$/', $r2['json']['data'][0]['start'] ?? '') === 1 && $r2['json']['data'][0]['start_utc'] === $s0['start_utc'], 'tz cambia la hora local, no el instante UTC');
$from = substr($s0['start_utc'], 0, 10);
$r2 = call('GET', "/api/v1/availability?event_id=$eventId&duration=45&from=$from&to=" . gmdate('Y-m-d', strtotime($from . ' +3 days')), $read);
T::eq(200, $r2['status'], 'duración y rango válidos');
foreach ([
    'event_id=abc' => 400, '' => 400, 'event_id=999999' => 404, "event_id=$eventId&duration=7" => 400, "event_id=$eventId&from=ayer" => 400,
    "event_id=$eventId&from=2026-10-10&to=2026-10-01" => 400, "event_id=$eventId&from=2026-01-01&to=2026-12-31" => 400, "event_id=$eventId&tz=Marte/Olympus" => 400,
    "event_id=$eventId&from=2026-10-05'%20OR%201=1--" => 400, "event_id=$eventId&host_id=x" => 400,
] as $q => $code) {
    $x = call('GET', '/api/v1/availability?' . $q, $read);
    T::eq($code, $x['status'], "availability?$q: $code");
}

T::section('Clientes');
$r = call('POST', '/api/v1/clients', $wkey, ['name' => 'Lucía <script>alert(1)</script> Pérez', 'email' => 'Lucia.Perez@Example.TEST', 'phone' => '5555 4321', 'nit' => '987654-3', 'source' => 'Formulario web']);
T::eq(201, $r['status'], 'POST /clients: 201');
$cid = (int) ($r['json']['data']['id'] ?? 0);
T::eq('lucia.perez@example.test', $r['json']['data']['email'] ?? '', 'correo normalizado');
T::eq('50255554321', $r['json']['data']['phone'] ?? '', 'teléfono normalizado');
T::eq(true, $r['json']['meta']['created'] ?? null, 'meta.created = true');
T::ok(str_contains($r['text'], '<script>') && str_contains($r['head'], 'application/json') && stripos($r['head'], 'x-content-type-options: nosniff') !== false, 'XSS: se devuelve como dato JSON con nosniff (no se interpreta)');
$r = call('POST', '/api/v1/clients', $wkey, ['name' => 'Otro Nombre', 'email' => 'lucia.perez@example.test']);
T::eq([200, false, $cid], [$r['status'], $r['json']['meta']['created'] ?? null, $r['json']['data']['id'] ?? 0], 'mismo correo: 200 y mismo cliente');
foreach ([
    [['email' => 'a@example.test'], 'sin nombre'], [['name' => 'A', 'email' => 'a@example.test'], 'nombre corto'], [['name' => 'Ana Sin Contacto'], 'sin correo ni teléfono'],
    [['name' => 'Ana Mala', 'email' => 'no-es-correo'], 'correo inválido'], [['name' => 'Ana Mala', 'phone' => '123'], 'teléfono inválido'],
    [['name' => 'Ana Mala', 'email' => 'ana@example.test', 'timezone' => 'Marte'], 'zona inválida'], [['name' => ['x'], 'email' => 'ana@example.test'], 'nombre como arreglo'],
] as [$body, $why]) {
    $x = call('POST', '/api/v1/clients', $wkey, $body);
    T::eq([422, 'validation'], [$x['status'], $x['json']['code'] ?? null], "cliente $why: 422");
}
$r = call('POST', '/api/v1/clients', $wkey, '{no es json');
T::eq([400, 'invalid_json'], [$r['status'], $r['json']['code'] ?? null], 'JSON corrupto: 400');
$r = call('POST', '/api/v1/clients', $wkey, '[1,2,3]');
T::eq(400, $r['status'], 'una lista JSON no es un objeto: 400');
$r = call('POST', '/api/v1/clients', $wkey, null, ['Content-Type: text/plain']);
T::eq([415, 'unsupported_media_type'], [$r['status'], $r['json']['code'] ?? null], 'sin JSON: 415');
$r = call('GET', '/api/v1/clients?q=' . rawurlencode('Lucía'), $read);
T::eq(1, count($r['json']['data']), 'búsqueda por nombre');
$total = (int) call('GET', '/api/v1/clients', $read)['json']['meta']['total'];
T::ok($total >= 5, "listado de clientes ($total)");
foreach (["' OR '1'='1", "' OR 1=1 -- ", '%', '_', "\\", "'; DROP TABLE clients; --", '" OR ""="'] as $q) {
    $x = call('GET', '/api/v1/clients?q=' . rawurlencode($q), $read);
    T::ok($x['status'] === 200 && $x['json']['meta']['total'] < $total, 'inyección en q «' . $q . '» no devuelve todo');
}
$x = call('GET', '/api/v1/clients?email=' . rawurlencode("' OR 1=1 --"), $read);
T::eq(0, $x['json']['meta']['total'], 'inyección en email: 0 resultados');
$x = call('GET', '/api/v1/clients?phone=5555%204321', $read);
T::eq($cid, $x['json']['data'][0]['id'] ?? 0, 'filtro por teléfono normalizado');
$r = call('GET', "/api/v1/clients/$cid", $read);
T::eq([200, 0], [$r['status'], $r['json']['data']['bookings_count'] ?? -1], 'GET /clients/{id}');
foreach (['password_hash', 'internal_note', 'anonymized_at', 'updated_at'] as $k) {
    T::ok(!str_contains($r['text'], $k), "cliente sin $k");
}
T::eq(404, call('GET', '/api/v1/clients/999999', $read)['status'], 'cliente inexistente: 404');
Db::exec("UPDATE clients SET name = 'Persona eliminada', email = NULL, phone = NULL, anonymized_at = UTC_TIMESTAMP() WHERE id = ?", [$cid]);
T::eq(404, call('GET', "/api/v1/clients/$cid", $read)['status'], 'un cliente anonimizado no se expone');
T::eq(0, call('GET', '/api/v1/clients?q=eliminada', $read)['json']['meta']['total'], 'ni aparece en listados');

T::section('Citas');
$when = $s0['start'];
$answers = [];
foreach ($ev['questions'] as $q) {
    if ($q['required']) {
        $answers[(string) $q['id']] = $q['options'][0] ?? 'Respuesta de prueba';
    }
}
$payload = ['event_id' => $eventId, 'start' => $when, 'name' => "Carlos <b>Ruiz</b>'); DROP TABLE bookings;--", 'email' => 'carlos.ruiz@example.test', 'phone' => '55550000', 'notes' => 'Primera vez', 'answers' => $answers];
$r = call('POST', '/api/v1/bookings', $wkey, $payload);
T::eq(201, $r['status'], 'POST /bookings: 201 (' . ($r['json']['error'] ?? '') . ')');
$bid = (int) ($r['json']['data']['id'] ?? 0);
T::ok($bid > 0 && $r['json']['data']['created_via'] === 'api', 'creada con origen api');
T::ok(in_array($r['json']['data']['status'], ['confirmed', 'pending'], true) && isset($r['json']['meta']['needs_payment']), 'estado y needs_payment');
T::eq($s0['start_utc'], $r['json']['data']['starts_at'], 'hora UTC correcta');
$respBody = $r['text'];
T::eq(1, (int) Db::val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings'"), 'SQLi en el nombre neutralizada (la tabla sigue)');
T::eq("Carlos <b>Ruiz</b>'); DROP TABLE bookings;--", Db::val('SELECT guest_name FROM bookings WHERE id = ?', [$bid]), 'el nombre se guardó literal');
$r = call('POST', '/api/v1/bookings', $wkey, $payload);
T::eq([409, 'slot_unavailable'], [$r['status'], $r['json']['code'] ?? null], 'doble reserva del mismo horario: 409');
$av = call('GET', "/api/v1/availability?event_id=$eventId", $read)['json']['data'];
T::ok(!in_array($s0['start_utc'], array_column($av, 'start_utc'), true), 'el horario reservado ya no aparece como libre');
$r = call('GET', "/api/v1/bookings/$bid", $read);
T::eq([200, $bid], [$r['status'], $r['json']['data']['id'] ?? 0], 'GET /bookings/{id}');
T::ok(isset($r['json']['data']['hosts'], $r['json']['data']['answers'], $r['json']['data']['attendees']), 'detalle con anfitriones, respuestas y acompañantes');

// Datos sensibles
Db::exec("UPDATE bookings SET internal_note = 'NOTA-INTERNA-SECRETA' WHERE id = ?", [$bid]);
$token = (string) Db::val('SELECT token FROM bookings WHERE id = ?', [$bid]);
foreach (['/api/v1/bookings', "/api/v1/bookings/$bid", '/api/v1/clients', '/api/v1/events'] as $path) {
    $x = call('GET', $path, $read);
    T::ok(!str_contains($x['text'], 'NOTA-INTERNA-SECRETA') && !str_contains($x['text'], $token), "$path: sin notas internas ni token de la cita");
    foreach (['key_hash', 'password', 'totp', 'ics_token', 'reset_token', 'smtp_pass', 'app_key', 'cron_token', 'series_token', 'internal_note'] as $bad) {
        T::ok(!str_contains($x['text'], $bad), "$path: sin «$bad»");
    }
}

// Asignación masiva ignorada
$next = $av[1] ?? $av[0];
$r = call('POST', '/api/v1/bookings', $wkey, ['event_id' => $eventId, 'start' => $next['start_utc'], 'name' => 'Marta Intrusa', 'email' => 'marta@example.test', 'answers' => $answers, 'force' => true, 'status' => 'completed', 'created_via' => 'admin', 'created_by' => 1, 'price' => 0, 'total' => 0, 'host_id' => 1]);
T::eq(201, $r['status'], 'reserva con campos extra: 201');
T::ok($r['json']['data']['created_via'] === 'api' && $r['json']['data']['status'] !== 'completed' && $r['json']['data']['payment']['total'] > 0, 'campos privilegiados ignorados (force, status, created_via, precio)');
$bid2 = (int) $r['json']['data']['id'];
// Reserva en el pasado / fuera de aviso mínimo
$r = call('POST', '/api/v1/bookings', $wkey, ['event_id' => $eventId, 'start' => '2020-01-06T15:00:00Z', 'name' => 'Pasado Lejano', 'email' => 'p@example.test']);
T::ok(in_array($r['status'], [409, 422], true) && isset($r['json']['code']), 'horario en el pasado rechazado (' . $r['status'] . ' ' . ($r['json']['code'] ?? '') . ')');
foreach ([
    [['start' => $when, 'name' => 'Ana Prueba', 'email' => 'a@example.test'], 'sin event_id'],
    [['event_id' => $eventId, 'name' => 'Ana Prueba', 'email' => 'a@example.test'], 'sin start'],
    [['event_id' => $eventId, 'start' => '2026-10-07 09:00', 'name' => 'Ana Prueba', 'email' => 'a@example.test'], 'start sin zona'],
    [['event_id' => $eventId, 'start' => 'mañana', 'name' => 'Ana Prueba', 'email' => 'a@example.test'], 'start inválido'],
    [['event_id' => 'x', 'start' => $when, 'name' => 'Ana Prueba'], 'event_id no numérico'],
    [['event_id' => $eventId, 'start' => $when, 'email' => 'a@example.test'], 'sin nombre'],
    [['event_id' => $eventId, 'start' => $when, 'name' => 'Ana Prueba'], 'sin correo ni teléfono'],
    [['event_id' => $eventId, 'start' => $when, 'name' => 'Ana Prueba', 'email' => 'mal'], 'correo inválido'],
    [['event_id' => $eventId, 'start' => $when, 'name' => 'Ana Prueba', 'email' => 'a@example.test', 'answers' => 'texto'], 'answers no es objeto'],
    [['event_id' => $eventId, 'start' => $when, 'name' => 'Ana Prueba', 'email' => 'a@example.test', 'guests' => 'x'], 'guests no es lista'],
    [['event_id' => $eventId, 'start' => $when, 'name' => 'Ana Prueba', 'email' => 'a@example.test', 'duration' => 7], 'duración no ofrecida'],
] as [$body, $why]) {
    $x = call('POST', '/api/v1/bookings', $wkey, $body);
    T::ok(in_array($x['status'], [409, 422], true) && isset($x['json']['error'], $x['json']['code']), "cita $why: " . $x['status'] . ' ' . ($x['json']['code'] ?? '?'));
}
T::eq(404, call('POST', '/api/v1/bookings', $wkey, ['event_id' => 999999, 'start' => $when, 'name' => 'Ana Prueba', 'email' => 'a@example.test'])['status'], 'reservar un evento inexistente: 404');
$list = call('GET', '/api/v1/bookings', $read);
T::eq(200, $list['status'], 'GET /bookings');
T::ok($list['json']['meta']['total'] >= 9, 'incluye las citas demo y las nuevas');
$first = $list['json']['data'][0];
T::ok(isset($first['guest']['name'], $first['event']['name'], $first['host']['name'], $first['payment']['total']) && !isset($first['answers']), 'listado liviano con evento, anfitrión y pago');
$st = call('GET', '/api/v1/bookings?status=pending,confirmed', $read)['json']['data'];
T::ok($st && !array_filter($st, static fn ($b) => !in_array($b['status'], ['pending', 'confirmed'], true)), 'filtro por estado');
T::eq(400, call('GET', '/api/v1/bookings?status=hackeado', $read)['status'], 'estado inválido: 400');
T::eq(400, call('GET', "/api/v1/bookings?status=confirmed'%20OR%201=1", $read)['status'], 'inyección en status: 400');
$byEvent = call('GET', "/api/v1/bookings?event_id=$eventId&limit=100", $read)['json'];
T::ok($byEvent['data'] && !array_filter($byEvent['data'], static fn ($b) => $b['event']['id'] !== $eventId), 'filtro por evento');
$byHost = call('GET', '/api/v1/bookings?host_id=' . (int) $first['host']['id'] . '&limit=100', $read)['json'];
T::ok($byHost['data'] && !array_filter($byHost['data'], static fn ($b) => $b['host']['id'] !== $first['host']['id']), 'filtro por anfitrión');
$day = substr($s0['start_utc'], 0, 10);
$byDay = call('GET', "/api/v1/bookings?from=$day&to=$day&tz=UTC", $read)['json']['data'];
T::ok(count($byDay) >= 2 && !array_filter($byDay, static fn ($b) => substr($b['starts_at'], 0, 10) !== $day), 'filtro por rango de fechas (to con fecha incluye el día)');
$asc = call('GET', '/api/v1/bookings?order=asc&limit=2', $read)['json']['data'];
T::ok(strcmp($asc[0]['starts_at'], $asc[1]['starts_at']) <= 0, 'order=asc');
T::eq(400, call('GET', '/api/v1/bookings?from=ayer', $read)['status'], 'from inválido: 400');
T::eq(404, call('GET', '/api/v1/bookings/999999', $read)['status'], 'cita inexistente: 404');
T::eq(404, call('GET', '/api/v1/bookings/abc', $read)['status'], 'id no numérico: 404');
// cancelar
$r = call('POST', "/api/v1/bookings/$bid/cancel", $wkey, ['reason' => 'La persona avisó <img src=x onerror=alert(1)>']);
T::eq(200, $r['status'], 'POST /bookings/{id}/cancel: 200 (' . ($r['json']['error'] ?? '') . ')');
T::eq('cancelled', $r['json']['data']['status'] ?? '', 'estado cancelled');
T::eq('host', $r['json']['data']['cancellation']['by'] ?? '', 'cancelada por el negocio');
T::eq('cancelled', Db::val('SELECT status FROM bookings WHERE id = ?', [$bid]), 'persistido');
$r = call('POST', "/api/v1/bookings/$bid/cancel", $wkey, []);
T::eq([409, 'policy'], [$r['status'], $r['json']['code'] ?? null], 'cancelar dos veces: 409');
$r = call('POST', "/api/v1/bookings/$bid2/cancel", $wkey);
T::eq(200, $r['status'], 'cancelar sin cuerpo funciona');
T::eq(404, call('POST', '/api/v1/bookings/999999/cancel', $wkey, [])['status'], 'cancelar inexistente: 404');
$av2 = call('GET', "/api/v1/availability?event_id=$eventId", $read)['json']['data'];
T::ok(in_array($s0['start_utc'], array_column($av2, 'start_utc'), true), 'el horario cancelado vuelve a estar libre');
T::ok(Db::val("SELECT COUNT(*) FROM booking_history WHERE booking_id = ?", [$bid]) >= 1, 'queda historial de la cita');

T::section('Rutas inexistentes y métodos');
$r = call('GET', '/api/v1/nada', $read);
T::eq([404, 'not_found'], [$r['status'], $r['json']['code'] ?? null], 'ruta inexistente: 404 JSON');
$r = call('DELETE', '/api/v1/events', $write['key']);
T::eq([405, 'method_not_allowed'], [$r['status'], $r['json']['code'] ?? null], 'método no permitido: 405 JSON');
$r = call('PUT', "/api/v1/bookings/$bid", $wkey, []);
T::eq(405, $r['status'], 'PUT sobre una cita: 405');
T::eq(401, call('GET', '/api/v1/nada')['status'], 'sin clave ni siquiera se revela si existe');
T::eq(401, call('POST', '/api/v1/bookings', null, ['event_id' => 1])['status'], 'POST sin clave: 401');

T::section('Límite de frecuencia');
$limited = ApiAuth::issue('Limitada', 'read', null)['key'];
Settings::set('api_rate_per_minute', '5');
$codes = [];
for ($i = 0; $i < 8; $i++) {
    $codes[] = call('GET', '/api/v1/events?limit=1', $limited)['status'];
}
T::eq([200, 200, 200, 200, 200, 429, 429, 429], $codes, 'al superar api_rate_per_minute (5): 429');
$r = call('GET', '/api/v1/events', $limited);
T::ok(isset($r['json']['error']) && str_contains($r['text'], 'límite'), '429 con mensaje amable');
T::eq(200, call('GET', '/api/v1/events?limit=1', ApiAuth::issue('Otra', 'read', null)['key'])['status'], 'otra clave tiene su propio contador');
Db::exec("DELETE FROM rate_limits WHERE bucket LIKE 'api:%'");
Settings::set('api_rate_per_minute', '1000');

T::section('Documentación pública');
$r = call('GET', '/api-docs');
T::eq(200, $r['status'], 'GET /api-docs sin clave: 200');
T::ok(str_contains($r['head'], 'text/html'), 'HTML');
foreach (ApiDocs::endpoints() as $e) {
    T::ok(str_contains($r['text'], htmlspecialchars(ApiDocs::displayPath($e), ENT_QUOTES)), 'documenta ' . $e['method'] . ' ' . ApiDocs::displayPath($e));
}
foreach (['Authorization: Bearer', 'X-Agenda-Signature', 'hash_hmac', 'solicitudes por minuto', 'booking.created', 'api_docs_no', 'core.css', 'api-docs.css'] as $needle) {
    if ($needle === 'api_docs_no') {
        T::ok(!str_contains($r['text'], 'Warning') && !str_contains($r['text'], 'Fatal error') && !str_contains($r['text'], 'Undefined'), 'sin errores de PHP en la página');
        continue;
    }
    T::ok(str_contains($r['text'], $needle), "la página incluye «$needle»");
}
T::ok(!str_contains($r['text'], 'TU_CLAVE_DE_API\'  ') && !preg_match('/ap_[a-f0-9]{40}/', $r['text']), 'sin claves reales en la documentación');
T::ok(!preg_match('/<script(?![^>]*\bsrc=)/i', $r['text']) && !preg_match('/\sstyle="/', $r['text']) && !preg_match('/\sonclick=/i', $r['text']), 'cumple la CSP: sin scripts ni estilos en línea');
T::ok(str_contains($r['head'], "script-src 'self'"), 'cabecera CSP presente');
T::ok(str_contains($r['text'], '<html lang="es"'), 'página en español');
T::ok(!preg_match('/lorem|ipsum|https?:\/\/(?!127\.0\.0\.1|tusitio\.com)[a-z0-9.-]+\.(com|net|org)/i', preg_replace('#https?://example\.(com|test)\S*#', '', $r['text'])), 'sin relleno ni recursos externos');
$cssOk = call('GET', '/assets/css/api-docs.css');
T::eq(200, $cssOk['status'], 'la hoja de estilos se sirve');

$logTxt = is_file(APP_ROOT . '/storage/logs/app.log') ? (string) file_get_contents(APP_ROOT . '/storage/logs/app.log') : '';
T::ok(!preg_match('/server_error|Controllers.Api|Services.(ApiDocs|ConfigPortability|PrivacyService|LegalService|ProfessionService)/', $logTxt), 'el registro de errores no tiene fallos de la API ni de los servicios');
T::done();
