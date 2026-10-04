<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
require __DIR__ . '/../lib/SaHelper.php';
T::boot('sa_extcal', ['allow_private_http' => true]);

use App\Core\Cache;
use App\Core\Config;
use App\Core\Db;
use App\Core\Logger;
use App\Core\Settings;
use App\Services\AvailabilityService;
use App\Services\ExternalCalendarService as X;

$dir = SaHelper::workDir('extcal');
SaHelper::startPhp(8174, __DIR__ . '/../lib/sa_receiver.php');
$base = 'http://127.0.0.1:8174';
$hostId = (int) Db::val('SELECT id FROM hosts ORDER BY id LIMIT 1');

$tomorrow = gmdate('Ymd', time() + 86400);
$d3 = gmdate('Ymd', time() + 3 * 86400);
$feed = static fn (string $events): string => "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Prueba//ES\r\n" . $events . "END:VCALENDAR\r\n";
$ev = static fn (string $uid, string $body): string => "BEGIN:VEVENT\r\nUID:$uid\r\n$body" . "END:VEVENT\r\n";
file_put_contents($dir . '/feed_a.ics', $feed(
    $ev('uno@t', "DTSTART:{$tomorrow}T100000Z\r\nDTEND:{$tomorrow}T110000Z\r\n")
    . $ev('serie@t', "DTSTART:{$d3}T150000Z\r\nDTEND:{$d3}T160000Z\r\nRRULE:FREQ=DAILY;COUNT=3\r\n")
    . $ev('libre@t', "DTSTART:{$d3}T180000Z\r\nDTEND:{$d3}T190000Z\r\nTRANSP:TRANSPARENT\r\n")
));
$cal = static fn (string $url, array $o = []): int => Db::insert('external_calendars', $o + ['host_id' => $hostId, 'name' => 'Calendario de prueba', 'url' => $url, 'active' => 1]);
$row = static fn (int $id): array => (array) Db::one('SELECT * FROM external_calendars WHERE id = ?', [$id]);
$busy = static fn (int $id): array => Db::col('SELECT starts_at FROM external_busy WHERE calendar_id = ? ORDER BY starts_at', [$id]);

T::section('Sincronización correcta: bloquea horarios y guarda el estado');
$a = $cal($base . '/feed/a.ics');
$v0 = Cache::availabilityVersion();
usleep(3000);
$r = X::sync($a);
T::ok($r['ok'] && $r['events'] === 4 && !$r['unchanged'], 'sync correcto con 4 ocupaciones (1 evento + serie de 3; el libre se ignora)');
$c = $row($a);
T::ok($c['last_status'] === 'ok' && $c['last_error'] === null && (int) $c['fail_count'] === 0 && $c['last_ok_at'] !== null && $c['last_parse_at'] !== null && $c['etag'] !== null, 'estado ok, sin error, con ETag y fecha de lectura');
$wait = strtotime($c['next_fetch_at'] . ' UTC') - time();
T::ok($wait > 29 * 60 && $wait <= 30 * 60 + 5, 'próxima descarga en ics_cache_minutes (30 min)');
T::eq(4, count($busy($a)), 'external_busy tiene 4 filas');
T::eq(gmdate('Y-m-d', time() + 86400) . ' 10:00:00', $busy($a)[0], 'el primer bloqueo es el de mañana 10:00 UTC');
T::ok(Cache::availabilityVersion() !== $v0, 'se invalidó la caché de disponibilidad');
T::eq('uno@t', Db::val('SELECT uid FROM external_busy WHERE calendar_id = ? ORDER BY starts_at LIMIT 1', [$a]), 'se guarda el UID');
T::ok(AvailabilityService::hostBusy($hostId, gmdate('Y-m-d', time() + 86400) . ' 10:15:00', gmdate('Y-m-d', time() + 86400) . ' 10:45:00'), 'AvailabilityService::hostBusy ve el horario bloqueado');
T::ok(!AvailabilityService::hostBusy($hostId, gmdate('Y-m-d', time() + 86400) . ' 12:00:00', gmdate('Y-m-d', time() + 86400) . ' 13:00:00'), 'y un horario libre sigue libre');
Db::update('external_calendars', ['active' => 0], 'id = ?', [$a]);
T::ok(!AvailabilityService::hostBusy($hostId, gmdate('Y-m-d', time() + 86400) . ' 10:15:00', gmdate('Y-m-d', time() + 86400) . ' 10:45:00'), 'un calendario desactivado no bloquea');
Db::update('external_calendars', ['active' => 1], 'id = ?', [$a]);

T::section('Peticiones condicionales (ETag)');
$r = X::sync($a);
T::ok($r['ok'] && $r['unchanged'], 'segunda lectura: 304 sin cambios');
$log = SaHelper::jsonl($dir . '/log_feed_a.jsonl');
T::ok(($log[0]['inm'] ?? 'x') === null && is_string($log[1]['inm'] ?? null), 'la segunda petición envía If-None-Match');
T::eq(4, count($busy($a)), 'los bloqueos se conservan con 304');
Db::update('external_calendars', ['last_parse_at' => gmdate('Y-m-d H:i:s', time() - 13 * 3600)], 'id = ?', [$a]);
$r = X::sync($a);
T::ok($r['ok'] && !$r['unchanged'], 'tras 12 h se vuelve a leer completo aunque el ETag coincida');
$log = SaHelper::jsonl($dir . '/log_feed_a.jsonl');
T::ok(($log[2]['inm'] ?? 'x') === null, 'esa lectura no fue condicional');
file_put_contents($dir . '/feed_a.ics', $feed($ev('nuevo@t', "DTSTART:{$d3}T120000Z\r\nDTEND:{$d3}T130000Z\r\n")));
$r = X::sync($a);
T::ok($r['ok'] && !$r['unchanged'] && $r['events'] === 1, 'si el calendario cambia, se reemplazan los bloqueos');
T::eq([gmdate('Y-m-d', time() + 3 * 86400) . ' 12:00:00'], $busy($a), 'solo queda el evento nuevo');

T::section('Fallos: no rompen nada, se conservan los datos y hay espera creciente');
Db::update('external_calendars', ['url' => $base . '/feed/a.ics?status=500'], 'id = ?', [$a]);
$expected = [5, 15, 30, 60, 360, 360];
foreach ($expected as $i => $mins) {
    $r = X::sync($a);
    $c = $row($a);
    $gap = (strtotime($c['next_fetch_at'] . ' UTC') - time()) / 60;
    T::ok(!$r['ok'] && $c['last_status'] === 'error' && (int) $c['fail_count'] === $i + 1 && abs($gap - $mins) < 1.1, 'fallo ' . ($i + 1) . ': fail_count=' . $c['fail_count'] . ', próximo intento en ' . round($gap) . ' min (esperado ' . $mins . ')');
}
T::ok(strpos((string) $c['last_error'], 'problema') !== false && strlen((string) $c['last_error']) <= 255, 'last_error es un mensaje amable');
T::eq(1, count($busy($a)), 'tras seis fallos seguidos siguen los últimos bloqueos buenos');
T::ok(count(array_filter(Logger::tail(50), static fn (string $l): bool => strpos($l, 'Calendario externo #' . $a) !== false)) > 0, 'el fallo quedó registrado en el log');
Db::update('external_calendars', ['url' => $base . '/feed/a.ics'], 'id = ?', [$a]);
$r = X::sync($a);
$c = $row($a);
T::ok($r['ok'] && (int) $c['fail_count'] === 0 && $c['last_error'] === null && $c['last_status'] === 'ok', 'al recuperarse se reinicia el contador y se limpia el error');

$n = $cal($base . '/notics');
$r = X::sync($n);
T::ok(!$r['ok'] && strpos((string) $r['error'], '.ics') !== false && $row($n)['last_status'] === 'error', 'una página que no es .ics da un error claro');
$m = $cal($base . '/feed/inexistente.ics');
T::ok(!X::sync($m)['ok'] && strpos((string) $row($m)['last_error'], '404') !== false, 'un 404 se informa');
T::ok(!X::sync(999999)['ok'], 'calendario inexistente: error sin excepción');
Config::set('allow_private_http', false);
$p = $cal('http://169.254.169.254/latest/meta-data/');
$r = X::sync($p);
T::ok(!$r['ok'] && strpos((string) $r['error'], 'internas o privadas') !== false && (int) $row($p)['fail_count'] === 1, 'SSRF: un enlace a una IP interna se rechaza y se anota');
$f = $cal('file:///etc/passwd');
T::ok(!X::sync($f)['ok'], 'SSRF: file:// rechazado');
Config::set('allow_private_http', true);

T::section('syncDue respeta next_fetch_at, activos y ics_cache_minutes');
Db::exec('DELETE FROM external_calendars');
$d1 = $cal($base . '/feed/a.ics');
$d2 = $cal($base . '/feed/a.ics', ['next_fetch_at' => gmdate('Y-m-d H:i:s', time() + 3600)]);
$d3id = $cal($base . '/feed/a.ics', ['active' => 0]);
$d4 = $cal($base . '/feed/a.ics', ['next_fetch_at' => gmdate('Y-m-d H:i:s', time() - 60)]);
Settings::set('ics_cache_minutes', '45');
$res = X::syncDue();
T::eq(['synced' => 2, 'failed' => 0], $res, 'solo se sincronizan los calendarios activos que ya tocaban');
$gap = strtotime($row($d1)['next_fetch_at'] . ' UTC') - time();
T::ok($gap > 44 * 60 && $gap <= 45 * 60 + 5, 'ics_cache_minutes=45 fija la próxima descarga');
T::ok($row($d2)['last_fetch_at'] === null && $row($d3id)['last_fetch_at'] === null, 'los que no tocaban no se descargaron');
T::eq(['synced' => 0, 'failed' => 0], X::syncDue(), 'una segunda pasada inmediata no repite trabajo');
Db::update('external_calendars', ['url' => $base . '/feed/a.ics?status=503', 'next_fetch_at' => null], 'id = ?', [$d1]);
T::eq(['synced' => 0, 'failed' => 1], X::syncDue(), 'syncDue cuenta los fallos sin lanzar');

T::section('testUrl y normalización');
$t = X::testUrl($base . '/feed/a.ics');
T::ok($t['ok'] && $t['events'] === 1, 'testUrl: enlace válido con 1 ocupación en la ventana');
T::ok(!X::testUrl($base . '/notics')['ok'] && !X::testUrl('ftp://example.com/x.ics')['ok'] && !X::testUrl('')['ok'], 'testUrl rechaza HTML, esquemas raros y vacío');
T::eq('https://example.com/c.ics', X::normalizeUrl('webcal://example.com/c.ics'), 'webcal:// se convierte a https://');
T::eq('https://example.com/c.ics', X::normalizeUrl('  WEBCALS://example.com/c.ics '), 'webcals:// también');

SaHelper::stopAll();
T::done();
