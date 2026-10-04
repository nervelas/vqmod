<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
require __DIR__ . '/../lib/SaHelper.php';
T::boot('sa_cron', ['allow_private_http' => true]);

use App\Core\Cache;
use App\Core\Clock;
use App\Core\Db;
use App\Core\Settings;
use App\Services\BookingService;
use App\Services\CronService;

$dir = SaHelper::workDir('cron');
$cfgFile = (string) getenv('AP_CONFIG');
$root = dirname(__DIR__, 2);
$token = (string) Settings::get('cron_token');
$ago = static fn (int $days): string => gmdate('Y-m-d H:i:s', time() - $days * 86400);
$mkWf = static fn (string $name, array $o = []): int => Db::insert('workflows', $o + [
    'name' => $name, 'trigger_key' => 'booking.completed', 'offset_minutes' => 0, 'event_type_id' => null, 'action' => 'email', 'recipient' => 'guest',
    'subject' => 'Gracias por venir, {nombre}', 'template' => 'Hola {nombre}, gracias por tu visita.', 'action_value' => null, 'active' => 1, 'sort_order' => 0, 'created_at' => gmdate('Y-m-d H:i:s'),
]);
$cli = static function () use ($root, $cfgFile): array {
    $o = [];
    exec('AP_CONFIG=' . escapeshellarg($cfgFile) . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/cron.php') . ' 2>&1', $o, $code);
    return [$code, implode("\n", $o)];
};
$http = static function (string $qs, string $method = 'GET'): array {
    $ch = curl_init('http://127.0.0.1:8107/cron.php' . $qs);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 60, CURLOPT_CUSTOMREQUEST => $method]);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [$code, substr($raw, 0, $hs), substr($raw, $hs)];
};
$fakeSmtp = static function (int $port) use ($dir): string {
    $out = $dir . '/smtp_' . $port . '.jsonl';
    SaHelper::startSmtp($port, 'plain', $out);
    Settings::setMany(['smtp_host' => '127.0.0.1', 'smtp_port' => (string) $port, 'smtp_secure' => 'none', 'smtp_user' => '', 'smtp_pass' => '']);
    return $out;
};

T::section('Una pasada completa encadena citas, flujos y correo');
$smtpOut = $fakeSmtp(25271);
$ev = SaHelper::makeEvent(['name' => 'Consulta']);
$mkWf('gracias');
$done = SaHelper::makeBooking($ev, ['starts_at' => gmdate('Y-m-d H:i:s', time() - 7200), 'ends_at' => gmdate('Y-m-d H:i:s', time() - 5400), 'guest_email' => 'visita@example.test']);
$recent = SaHelper::makeBooking($ev, ['starts_at' => gmdate('Y-m-d H:i:s', time() - 1800), 'ends_at' => gmdate('Y-m-d H:i:s', time() - 600)]);
$stale = SaHelper::makeBooking($ev, ['starts_at' => gmdate('Y-m-d H:i:s', time() + 86400), 'status' => 'pending', 'created_at' => $ago(3)]);
$fresh = SaHelper::makeBooking($ev, ['starts_at' => gmdate('Y-m-d H:i:s', time() + 86400), 'status' => 'pending']);
Db::exec('DELETE FROM holidays WHERE date >= ?', [(gmdate('Y') + 1) . '-01-01']);
Db::exec('DELETE FROM settings WHERE k = ?', ['holidays_seeded_' . (gmdate('Y') + 1)]);
Settings::flush();
$r = CronService::run('cli');
T::ok($r['ok'] && !$r['locked'] && $r['origin'] === 'cli' && $r['errors'] === [] && $r['duration_ms'] >= 0, 'run() termina sin errores: ' . json_encode($r['errors']));
T::eq(['citas_vencidas', 'citas_completadas', 'lista_espera', 'retencion', 'feriados', 'resumen_semanal', 'flujos', 'correo', 'webhooks', 'calendarios', 'limpieza'], array_keys($r['tasks']), 'tareas ejecutadas en el orden previsto');
T::eq('completed', Db::val('SELECT status FROM bookings WHERE id = ?', [$done]), 'la cita terminada hace más de 15 min se completó');
T::eq('confirmed', Db::val('SELECT status FROM bookings WHERE id = ?', [$recent]), 'la que terminó hace 10 min sigue confirmada');
T::eq('cancelled', Db::val('SELECT status FROM bookings WHERE id = ?', [$stale]), 'la pendiente sin aprobar a tiempo se canceló');
T::eq('pending', Db::val('SELECT status FROM bookings WHERE id = ?', [$fresh]), 'la pendiente reciente se conserva');
T::ok(Db::val("SELECT status FROM workflow_runs WHERE booking_id = ?", [$done]) === 'done', 'booking.completed disparó el flujo y se ejecutó en la misma pasada');
T::ok(Db::val("SELECT status FROM email_queue WHERE to_email = 'visita@example.test'") === 'sent', 'y su correo salió en la misma pasada (flujos antes que correo)');
$sent = SaHelper::jsonl($smtpOut);
T::ok(count(array_filter($sent, static fn (array $m): bool => $m['rcpt'] === ['visita@example.test'])) === 1, 'el servidor SMTP de prueba recibió el correo de agradecimiento');
T::ok((int) Db::val('SELECT COUNT(*) FROM holidays WHERE date >= ?', [(gmdate('Y') + 1) . '-01-01']) > 0, 'se aseguraron los feriados del año siguiente');
T::ok(abs(strtotime((string) Settings::get('cron_last_run') . ' UTC') - time()) <= 5, 'cron_last_run actualizado');
Settings::flush();
$last = json_decode((string) Settings::get('cron_last_result'), true);
T::ok($last['ok'] === true && $last['origin'] === 'cli', 'cron_last_result resume la ejecución');

T::section('Un fallo en una tarea no detiene las demás');
Db::exec('RENAME TABLE webhook_deliveries TO webhook_deliveries_x');
$mkWf('otro', ['subject' => 'Segundo aviso', 'template' => 'Hola {nombre}.']);
$done2 = SaHelper::makeBooking($ev, ['starts_at' => gmdate('Y-m-d H:i:s', time() - 7200), 'ends_at' => gmdate('Y-m-d H:i:s', time() - 5400), 'guest_email' => 'segunda@example.test']);
$r = CronService::run('visita');
Db::exec('RENAME TABLE webhook_deliveries_x TO webhook_deliveries');
T::ok(!$r['ok'] && isset($r['errors']['webhooks']) && strpos($r['errors']['webhooks'], 'Falló') === 0 && $r['origin'] === 'visita', 'la tarea rota queda en errors sin lanzar');
T::ok(isset($r['tasks']['correo'], $r['tasks']['calendarios'], $r['tasks']['flujos']) && count($r['tasks']) === 10 && $r['errors'] === ['webhooks' => $r['errors']['webhooks']], 'las demás tareas sí corrieron (' . count($r['tasks']) . ' de 11)');
T::ok(is_string($r['tasks']['limpieza']['entregas_webhooks']) && is_int($r['tasks']['limpieza']['correos']), 'en la limpieza, si una parte falla las demás siguen');
T::ok(Db::val("SELECT status FROM email_queue WHERE to_email = 'segunda@example.test'") === 'sent', 'y el correo pendiente salió');
$tail = implode("\n", \App\Core\Logger::tail(30));
T::ok(strpos($tail, 'Cron: falló la tarea webhooks') !== false, 'el fallo quedó en el log');
Settings::flush();
T::eq(false, json_decode((string) Settings::get('cron_last_result'), true)['ok'], 'cron_last_result refleja el fallo');
SaHelper::stop(25271);

T::section('Limpieza');
$old = $ago(70);
Db::exec("INSERT INTO login_attempts (email, ip, success, created_at) VALUES ('a@x.test','1.1.1.1',0,?), ('b@x.test','1.1.1.1',0,?)", [$ago(31), $ago(2)]);
foreach ([['sent', $old], ['sent', $ago(5)], ['failed', $old], ['pending', $old]] as $i => [$st, $at]) {
    Db::exec("INSERT INTO email_queue (to_email, subject, body_html, status, send_after, sent_at, created_at) VALUES (?, 'x', 'x', ?, ?, ?, ?)", ['limpieza' . $i . '@x.test', $st, gmdate('Y-m-d H:i:s', time() + 86400 * 30), $st === 'sent' ? $at : null, $at]);
}
$wfid = (int) Db::val('SELECT id FROM workflows ORDER BY id LIMIT 1');
$bk = SaHelper::makeBooking($ev, ['starts_at' => gmdate('Y-m-d H:i:s', time() + 86400 * 20)]);
foreach ([['done', $ago(100), $ago(100)], ['done', $ago(10), $ago(10)], ['pending', $ago(100), gmdate('Y-m-d H:i:s', time() + 86400 * 20)]] as $i => [$st, $created, $sched]) {
    Db::exec("INSERT INTO workflow_runs (workflow_id, booking_id, scheduled_at, status, created_at) VALUES (?, ?, ?, ?, ?)", [$wfid, $bk, gmdate('Y-m-d H:i:s', strtotime($sched . ' UTC') + $i), $st, $created]);
}
$hk = Db::insert('webhooks', ['name' => 'h', 'url' => 'http://x.test', 'secret' => 's', 'events' => '*', 'active' => 0, 'created_at' => $ago(1)]);
foreach ([['delivered', $ago(100)], ['delivered', $ago(3)], ['pending', $ago(100)]] as [$st, $at]) {
    Db::exec("INSERT INTO webhook_deliveries (webhook_id, event, payload, status, next_attempt_at, created_at) VALUES (?, 'e', '{}', ?, ?, ?)", [$hk, $st, gmdate('Y-m-d H:i:s', time() + 86400), $at]);
}
foreach ([['sent', $ago(100)], ['sent', $ago(3)], ['pending', $ago(100)]] as [$st, $at]) {
    Db::exec("INSERT INTO message_queue (phone, body, status, due_at, created_at) VALUES ('50255550000', 'x', ?, ?, ?)", [$st, $at, $at]);
}
Db::exec('INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?, ?, 1), (?, ?, 1)', ['viejo', time() - 3 * 86400, 'nuevo', time()]);
@mkdir($root . '/storage/cache', 0750, true);
file_put_contents($root . '/storage/cache/zz_expired.cache', serialize(['exp' => time() - 10, 'val' => 1]));
file_put_contents($root . '/storage/cache/zz_valid.cache', serialize(['exp' => time() + 600, 'val' => 1]));
file_put_contents($root . '/storage/cache/zz_stale.cache.123.tmp', 'x');
touch($root . '/storage/cache/zz_stale.cache.123.tmp', time() - 7200);
$r = CronService::run('cli');
$c = $r['tasks']['limpieza'];
T::eq(['a@x.test' => 0, 'b@x.test' => 1], [
    'a@x.test' => (int) Db::val("SELECT COUNT(*) FROM login_attempts WHERE email = 'a@x.test'"),
    'b@x.test' => (int) Db::val("SELECT COUNT(*) FROM login_attempts WHERE email = 'b@x.test'"),
], 'intentos de acceso > 30 días eliminados; los recientes se conservan');
T::eq(['limpieza0@x.test' => 0, 'limpieza1@x.test' => 1, 'limpieza2@x.test' => 0, 'limpieza3@x.test' => 1], array_combine(
    ['limpieza0@x.test', 'limpieza1@x.test', 'limpieza2@x.test', 'limpieza3@x.test'],
    array_map(static fn (string $e): int => (int) Db::val('SELECT COUNT(*) FROM email_queue WHERE to_email = ?', [$e]), ['limpieza0@x.test', 'limpieza1@x.test', 'limpieza2@x.test', 'limpieza3@x.test'])
), 'correos enviados/fallidos > 60 días eliminados; recientes y pendientes se conservan');
T::eq(2, (int) Db::val("SELECT COUNT(*) FROM workflow_runs WHERE booking_id = ?", [$bk]), 'ejecuciones de flujos > 90 días eliminadas; las pendientes futuras no');
T::eq(2, (int) Db::val("SELECT COUNT(*) FROM webhook_deliveries WHERE webhook_id = ?", [$hk]), 'entregas de webhook > 90 días eliminadas; pendientes y recientes no');
T::eq(2, (int) Db::val("SELECT COUNT(*) FROM message_queue WHERE phone = '50255550000'"), 'mensajes de WhatsApp antiguos no pendientes eliminados');
T::eq(1, (int) Db::val("SELECT COUNT(*) FROM rate_limits WHERE bucket IN ('viejo','nuevo')"), 'rate_limits antiguos eliminados');
T::ok(!is_file($root . '/storage/cache/zz_expired.cache') && is_file($root . '/storage/cache/zz_valid.cache') && !is_file($root . '/storage/cache/zz_stale.cache.123.tmp'), 'caché vencida y temporales viejos eliminados; la vigente se conserva');
unlink($root . '/storage/cache/zz_valid.cache');
T::ok($c['correos'] >= 2 && $c['accesos'] >= 1 && $c['cache'] >= 2, 'el resultado de limpieza informa conteos');

T::section('Resumen semanal: solo lunes por la mañana (hora del negocio)');
Db::exec('DELETE FROM email_queue');
Settings::setMany(['weekly_summary' => '1', 'admin_notify_email' => 'dueno@example.test', 'smtp_host' => '127.0.0.1', 'smtp_port' => '25299', 'smtp_secure' => 'none']);
$weekly = static fn () => (int) Db::val("SELECT COUNT(*) FROM email_queue WHERE to_email = 'dueno@example.test'");
Clock::set(strtotime('2026-10-06 14:00:00 UTC')); // martes 8:00 en Guatemala
$r = CronService::run('cli');
T::ok(isset($r['tasks']['resumen_semanal']['omitido']) && $weekly() === 0, 'martes: no se envía');
Clock::set(strtotime('2026-10-05 12:00:00 UTC')); // lunes 6:00 en Guatemala (antes de las 7)
CronService::run('cli');
T::eq(0, $weekly(), 'lunes antes de las 7:00 locales: no se envía');
Clock::set(strtotime('2026-10-05 14:00:00 UTC')); // lunes 8:00 en Guatemala
CronService::run('cli');
T::eq(1, $weekly(), 'lunes 8:00 locales: se envía el resumen');
CronService::run('cli');
T::eq(1, $weekly(), 'una segunda ejecución el mismo lunes no lo repite');
Clock::set(null);

T::section('cron.php por CLI');
Db::exec('DELETE FROM email_queue');
[$code, $out] = $cli();
T::ok($code === 0 && strpos($out, 'Cron terminado correctamente') === 0 && strpos($out, ' - flujos:') !== false && strpos($out, ' - limpieza:') !== false, 'php cron.php: código 0 y resumen en texto');

T::section('cron.php por URL con token (php -S, puerto 8107)');
SaHelper::startPhp(8107, $root . '/tests/router.php', ['AP_CONFIG' => $cfgFile]);
[$st, $h, $b] = $http('?token=' . $token);
$j = json_decode($b, true);
T::ok($st === 200 && ($j['ok'] ?? false) === true && ($j['origin'] ?? '') === 'url' && isset($j['tasks']['correo']), 'token válido: 200 y JSON con las tareas');
T::ok(stripos($h, 'application/json') !== false && stripos($h, 'no-store') !== false && stripos($h, 'noindex') !== false, 'cabeceras: JSON, sin caché y noindex');
[$st, $h, $b] = $http('?token=' . $token . '&format=text');
T::ok($st === 200 && stripos($h, 'text/plain') !== false && strpos($b, 'Cron terminado correctamente') === 0, 'format=text responde texto plano');
[$st, , $b] = $http('?token=' . str_repeat('0', strlen($token)));
T::ok($st === 403 && json_decode($b, true)['ok'] === false && strpos($b, 'tasks') === false, 'token incorrecto: 403 sin ejecutar nada');
foreach (['', '?token=', '?token[]=' . $token, '?token=' . substr($token, 0, -1), '?token=' . $token . 'x', '?Token=' . $token] as $qs) {
    T::eq(403, $http($qs)[0], 'sin token válido (' . ($qs === '' ? 'vacío' : substr($qs, 0, 14) . '…') . '): 403');
}
T::eq(200, $http('?token=' . $token, 'POST')[0], 'también acepta POST con el token en la URL (algunos hostings)');
Settings::set('cron_token', '');
T::eq(403, $http('?token=')[0], 'con cron_token vacío en los ajustes nunca se acepta un token vacío');
Settings::set('cron_token', $token);
Settings::flush();
Db::exec('DELETE FROM settings WHERE k = ?', ['site_base_url']);
$http('?token=' . $token);
T::eq('http://127.0.0.1:8107', (string) Db::val("SELECT v FROM settings WHERE k = 'site_base_url'"), 'una visita válida guarda la URL pública para los correos generados por CLI');

T::section('Bloqueo de ejecuciones simultáneas');
$holder = new PDO('mysql:host=127.0.0.1;dbname=' . T::$db . ';charset=utf8mb4', 'ap', 'ap_test_pw');
T::eq(1, (int) $holder->query("SELECT GET_LOCK(CONCAT('ap_cron_', MD5(DATABASE())), 0)")->fetchColumn(), 'otra conexión toma el bloqueo del cron');
$before = (string) Db::val("SELECT v FROM settings WHERE k = 'cron_last_run'");
$r = CronService::run('cli');
T::ok($r['locked'] === true && $r['ok'] === false && $r['tasks'] === [] && strpos((string) $r['error'], 'otra ejecución') !== false, 'run() con el bloqueo tomado: no ejecuta nada');
T::eq($before, (string) Db::val("SELECT v FROM settings WHERE k = 'cron_last_run'"), 'y no actualiza cron_last_run');
[$code, $out] = $cli();
T::ok($code === 1 && strpos($out, 'otra ejecución') !== false, 'php cron.php con bloqueo: código 1 y mensaje');
[$st, , $b] = $http('?token=' . $token);
T::ok($st === 409 && json_decode($b, true)['locked'] === true, 'por URL con bloqueo: 409');
$holder->query("SELECT RELEASE_LOCK(CONCAT('ap_cron_', MD5(DATABASE())))");
T::ok(CronService::run('cli')['ok'], 'liberado el bloqueo, vuelve a ejecutar');

T::section('Dos procesos reales a la vez: solo uno trabaja');
$slowPort = 25272;
SaHelper::startSilent($slowPort);
Settings::setMany(['smtp_host' => '127.0.0.1', 'smtp_port' => (string) $slowPort, 'smtp_secure' => 'none']);
Db::exec('DELETE FROM email_queue');
Db::exec("INSERT INTO email_queue (to_email, subject, body_html, status, send_after, created_at) VALUES ('lento@example.test', 'x', 'x', 'pending', ?, ?)", [$ago(1), $ago(1)]);
$cmd = [PHP_BINARY, $root . '/cron.php'];
$p1 = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes1, null, array_merge(getenv(), ['AP_CONFIG' => $cfgFile]));
usleep(1500000);
$t0 = microtime(true);
[$code, $out] = $cli();
T::ok($code === 1 && strpos($out, 'otra ejecución') !== false && microtime(true) - $t0 < 5, 'el segundo proceso se rechaza de inmediato mientras el primero trabaja');
$out1 = stream_get_contents($pipes1[1]);
proc_close($p1);
T::ok(strpos($out1, 'Cron terminado') === 0, 'el primer proceso terminó su trabajo (tras agotar el tiempo del SMTP mudo)');
T::ok(strpos((string) Db::val("SELECT last_error FROM email_queue WHERE to_email = 'lento@example.test'"), 'tardó demasiado') !== false, 'y el correo quedó con el error de tiempo agotado para reintentar');

SaHelper::stopAll();
T::done();
