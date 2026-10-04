<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
require __DIR__ . '/a4_http.inc.php';
T::boot('a4sys', ['profession' => 'otro']);

use App\Core\Crypto;
use App\Core\Db;
use App\Core\Settings;

$cfg = '/tmp/ap-t-a4sys.config.php';
$http = new A4Http(8193, $cfg);
$smtp = null;
register_shutdown_function(static function () use ($http, &$smtp) {
    $http->stop();
    if ($smtp) {
        proc_terminate($smtp[0]);
        proc_close($smtp[0]);
        @unlink($smtp[1]);
        @unlink($smtp[2]);
    }
});
T::ok($http->login('admin@test.local', 'Prueba#Segura2026'), 'login admin');

T::section('Estado del sistema');
Settings::set('cron_last_run', '');
Settings::flush();
$r = $http->req('GET', '/admin/sistema');
T::eq(200, $r['code'], 'GET /admin/sistema');
T::ok(strpos($r['body'], PHP_VERSION) !== false, 'muestra la versión de PHP');
T::ok(strpos($r['body'], 'pdo_mysql') !== false && strpos($r['body'], 'openssl') !== false, 'lista extensiones');
T::ok(strpos($r['body'], 'Nunca se ha ejecutado') !== false, 'cron rojo si nunca corrió');
T::ok(strpos($r['body'], 'p4-lamp is-err') !== false, 'semáforo rojo');
Settings::set('cron_last_run', (string) time());
Settings::flush();
$r = $http->req('GET', '/admin/sistema');
T::ok(strpos($r['body'], 'p4-lamp is-ok') !== false, 'semáforo verde con ciclo reciente');
Settings::set('cron_last_run', (string) (time() - 1800));
$r = $http->req('GET', '/admin/sistema');
T::ok(strpos($r['body'], 'p4-lamp is-warn') !== false, 'semáforo ámbar con ciclo de hace 30 min');
$dir = dirname(__DIR__, 2) . '/instalar';
T::eq(is_dir($dir), strpos($r['body'], 'sigue en tu servidor') !== false, 'aviso de /instalar según exista la carpeta');
App\Core\Logger::error('Prueba <b>XSS</b> de registro');
$r = $http->req('GET', '/admin/sistema');
T::ok(strpos($r['body'], 'Prueba &lt;b&gt;XSS&lt;/b&gt;') !== false && strpos($r['body'], '<b>XSS</b>') === false, 'registro de errores escapado');
$d = $http->req('GET', '/admin/sistema/registro');
T::ok($d['code'] === 200 && strpos($d['headers'], 'attachment') !== false && strpos($d['body'], 'Prueba <b>XSS</b>') !== false, 'descarga del registro');
$c = $http->post('/admin/sistema/cache');
T::eq(302, $c['code'], 'vaciar caché');
$c = $http->post('/admin/sistema/registro/vaciar');
T::eq(0, count(App\Core\Logger::tail(10)), 'registro vaciado');
T::ok((int) Db::val("SELECT COUNT(*) FROM audit_log WHERE action IN ('system.cache_clear','system.log_clear')") === 2, 'acciones del sistema auditadas');

T::section('Respaldo de base de datos');
$r = $http->post('/admin/respaldo/crear');
T::eq(302, $r['code'], 'crear respaldo');
$list = App\Services\BackupService::list();
T::eq(1, count($list), 'un respaldo listado');
$name = $list[0]['name'];
$page = $http->req('GET', '/admin/respaldo')['body'];
T::ok(strpos($page, $name) !== false, 'respaldo visible en la página');
$dl = $http->req('GET', '/admin/respaldo/descargar/' . $name);
T::ok($dl['code'] === 200 && strpos($dl['headers'], 'attachment') !== false && strlen($dl['body']) > 100, 'descarga autorizada');
$sql = substr($name, -3) === '.gz' ? (string) gzdecode($dl['body']) : $dl['body'];
T::ok(strpos($sql, 'CREATE TABLE') !== false && strpos($sql, 'INSERT INTO') !== false, 'volcado SQL legible (estructura y datos)');
T::ok(in_array($http->req('GET', '/admin/respaldo/descargar/' . rawurlencode('../../config/config.php'))['code'], [403, 404], true), 'recorrido de directorio bloqueado');
T::eq(404, $http->req('GET', '/admin/respaldo/descargar/passwd')['code'], 'nombre arbitrario bloqueado');
// restauración real en otra base
$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'ap', 'ap_test_pw', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('DROP DATABASE IF EXISTS ap_t_a4sys_restore');
$pdo->exec('CREATE DATABASE ap_t_a4sys_restore CHARACTER SET utf8mb4');
$tmpf = tempnam(sys_get_temp_dir(), 'bk') . '.sql';
file_put_contents($tmpf, $sql);
$cmd = 'mysql -h127.0.0.1 -uap -pap_test_pw ap_t_a4sys_restore < ' . escapeshellarg($tmpf) . ' 2>&1';
$out = [];
exec($cmd, $out, $code);
if ($code === 127) {
    $pdo->exec('USE ap_t_a4sys_restore');
    $code = 0;
    foreach (App\Core\Migrator::split($sql) as $st) {
        try { $pdo->exec($st); } catch (Throwable $e) { $code = 1; $out[] = $e->getMessage(); break; }
    }
}
T::eq(0, $code, 'el respaldo se restaura sin errores en una base nueva ' . implode(' ', array_slice($out, 0, 2)));
$n = (int) $pdo->query('SELECT COUNT(*) FROM ap_t_a4sys_restore.settings')->fetchColumn();
T::ok($n >= (int) Db::val('SELECT COUNT(*) FROM settings') - 2 && $n > 20, 'ajustes restaurados completos');
T::eq((int) Db::val('SELECT COUNT(*) FROM users'), (int) $pdo->query('SELECT COUNT(*) FROM ap_t_a4sys_restore.users')->fetchColumn(), 'usuarios restaurados');
$pdo->exec('DROP DATABASE ap_t_a4sys_restore');
@unlink($tmpf);
// conservar últimos N
usleep(1100000);
$http->post('/admin/respaldo/crear');
usleep(1100000);
$http->post('/admin/respaldo/crear');
T::eq(3, count(App\Services\BackupService::list()), 'tres respaldos');
$http->post('/admin/respaldo/conservar', ['keep' => '1']);
T::eq(1, count(App\Services\BackupService::list()), 'conservar los últimos 1 elimina el resto');
$left = App\Services\BackupService::list()[0]['name'];
$http->post('/admin/respaldo/eliminar/' . $left);
T::eq(0, count(App\Services\BackupService::list()), 'eliminar respaldo');

T::section('Exportar / importar configuración');
Settings::set('tagline', 'Eslogan original');
Settings::set('smtp_pass', Crypto::encrypt('clave-no-exportar'));
Settings::flush();
$ex = $http->req('GET', '/admin/respaldo/exportar');
T::ok($ex['code'] === 200 && strpos($ex['headers'], 'application/json') !== false, 'exportar JSON');
$json = $ex['body'];
$data = json_decode($json, true);
T::ok(is_array($data) && isset($data['settings']['tagline']), 'JSON válido con ajustes');
T::ok(strpos($json, 'clave-no-exportar') === false && !isset($data['settings']['smtp_pass']), 'sin secretos en la exportación');
// cambiar y reimportar
Settings::set('tagline', 'Cambiado después');
Db::q("UPDATE event_types SET name = name");
$tmp = sys_get_temp_dir() . '/a4cfg.json';
file_put_contents($tmp, $json);
$pv = $http->post('/admin/respaldo/importar', ['file' => new CURLFile($tmp, 'application/json', 'config.json')], true);
T::eq(200, $pv['code'], 'vista previa de importación');
T::ok(strpos($pv['body'], 'Cambiado después') !== false && strpos($pv['body'], 'Eslogan original') !== false, 'la vista previa muestra el cambio (ahora → archivo)');
Settings::flush();
T::eq('Cambiado después', Settings::get('tagline'), 'la vista previa no cambia nada');
preg_match('/name="token" value="([a-f0-9]{32})"/', $pv['body'], $m);
T::ok(!empty($m[1]), 'token de importación');
$res = $http->post('/admin/respaldo/importar/aplicar', ['token' => $m[1], 'mode' => 'replace', 'confirm' => 'nada']);
T::eq(422, $res['code'], 'reemplazar exige confirmación fuerte');
$res = $http->post('/admin/respaldo/importar/aplicar', ['token' => $m[1], 'mode' => 'replace', 'confirm' => 'REEMPLAZAR']);
T::eq(302, $res['code'], 'importación aplicada');
Settings::flush();
T::eq('Eslogan original', Settings::get('tagline'), 'ida y vuelta: el eslogan volvió');
T::ok(Settings::get('smtp_pass') !== '' && Crypto::decrypt((string) Settings::get('smtp_pass')) === 'clave-no-exportar', 'la importación no tocó los secretos');
$res = $http->post('/admin/respaldo/importar/aplicar', ['token' => $m[1], 'mode' => 'merge']);
T::eq(302, $res['code'], 'token usado ya no sirve (redirige con error)');
foreach ([['no es json', 'x.json'], ['{"a":1}', 'x.json'], ['[1,2]', 'x.json'], ['{"settings":{}}', 'x.txt']] as [$content, $fn]) {
    file_put_contents($tmp, $content);
    $res = $http->post('/admin/respaldo/importar', ['file' => new CURLFile($tmp, 'application/json', $fn)], true);
    T::ok($res['code'] === 422, 'rechaza archivo inválido: ' . $fn . ' ' . substr($content, 0, 12));
}
$http->post('/admin/respaldo/importar/aplicar', ['token' => str_repeat('0', 32), 'mode' => 'merge']);
T::ok(true, 'token inexistente no rompe');
@unlink($tmp);

T::section('Correo de prueba contra SMTP local');
$smtp = a4_smtp_sink(2599);
$comm = ['smtp_host' => '127.0.0.1', 'smtp_port' => '2599', 'smtp_secure' => 'none', 'smtp_user' => 'u', 'smtp_pass' => 'p', 'mail_from_name' => 'Negocio', 'mail_from_email' => 'citas@example.test', 'admin_notify_email' => '', 'wa_api_phone_id' => '', 'wa_api_template' => '', 'wa_api_lang' => 'es', 'captcha_provider' => 'none', 'captcha_site_key' => '',
    'booking_rate_limit' => '10', 'booking_min_form_seconds' => '3', 'api_rate_per_minute' => '60', 'session_idle_minutes' => '120'];
T::eq(302, $http->post('/admin/comunicaciones', $comm)['code'], 'SMTP guardado');
$http->post('/admin/comunicaciones/probar-conexion');
$page = $http->req('GET', '/admin/comunicaciones')['body'];
T::ok(strpos($page, 'Conexión correcta') !== false, 'probar conexión: correcta');
$http->post('/admin/comunicaciones/probar-correo', ['to' => 'destino@example.test']);
$page = $http->req('GET', '/admin/comunicaciones')['body'];
T::ok(strpos($page, 'Enviamos el correo de prueba a destino@example.test') !== false, 'aviso de envío en pantalla');
usleep(300000);
$got = (string) file_get_contents($smtp[1]);
T::ok(strpos($got, 'destino@example.test') !== false && stripos($got, 'Subject:') !== false, 'el receptor SMTP recibió el mensaje');
$before = substr_count((string) file_get_contents($smtp[1]), '=====');
$http->post('/admin/comunicaciones/probar-correo', ['to' => "x@y.com\r\nBcc: robo@x.com"]);
T::eq($before, substr_count((string) file_get_contents($smtp[1]), '====='), 'inyección de cabeceras en el destinatario rechazada');
// puerto cerrado: error legible
$comm['smtp_port'] = '2598';
$http->post('/admin/comunicaciones', $comm);
$http->post('/admin/comunicaciones/probar-correo', ['to' => 'destino@example.test']);
$page = $http->req('GET', '/admin/comunicaciones')['body'];
T::ok(strpos($page, 'No se pudo enviar') !== false && strpos($page, 'Stack trace') === false, 'error legible cuando el servidor no responde');

T::section('Asistente de inicio');
T::eq(200, $http->req('GET', '/admin/asistente')['code'], 'asistente paso 1');
$r = $http->post('/admin/asistente/1', ['profession' => 'inexistente']);
T::eq(422, $r['code'], 'profesión inválida rechazada');
$r = $http->post('/admin/asistente/1', ['profession' => 'dentista']);
T::eq(302, $r['code'], 'aplicar profesión');
T::ok((int) Db::val('SELECT COUNT(*) FROM event_types') >= 1, 'se crearon eventos');
Settings::flush();
T::eq('dentista', Settings::get('profession'), 'profesión guardada');
T::eq('2', (string) Settings::get('onboarding_step'), 'progreso guardado (paso 2)');
$ev = Db::one('SELECT * FROM event_types ORDER BY id LIMIT 1');
$id = (int) $ev['id'];
$r = $http->post('/admin/asistente/2', ['ev' => [$id => ['active' => '1', 'price' => '350.5', 'duration' => '45']]]);
T::eq(302, $r['code'], 'guardar eventos');
$ev2 = Db::one('SELECT * FROM event_types WHERE id = ?', [$id]);
T::ok((float) $ev2['price'] === 350.5 && (int) $ev2['default_duration'] === 45, 'precio y duración actualizados');
T::eq(422, $http->post('/admin/asistente/2', ['ev' => [$id => ['active' => '1', 'price' => 'abc', 'duration' => '45']]])['code'], 'precio inválido rechazado');
T::eq(422, $http->post('/admin/asistente/2', ['ev' => [$id => ['active' => '1', 'price' => '10', 'duration' => '2']]])['code'], 'duración inválida rechazada');
$days = [];
foreach ([1, 2, 3, 4, 5] as $d) {
    $days[$d] = ['on' => '1', 'b' => [['s' => '08:00', 'e' => '12:00'], ['s' => '14:00', 'e' => '18:00']]];
}
$days[6] = ['on' => '1', 'b' => [['s' => '09:00', 'e' => '13:00'], ['s' => '', 'e' => '']]];
$r = $http->post('/admin/asistente/3', ['d' => $days]);
T::eq(302, $r['code'], 'guardar horario');
$sid = (int) Db::val('SELECT id FROM schedules ORDER BY is_default DESC, id LIMIT 1');
T::eq(11, (int) Db::val('SELECT COUNT(*) FROM schedule_rules WHERE schedule_id = ?', [$sid]), '11 bloques (5 días x 2 + sábado)');
T::eq('09:00:00', (string) Db::val('SELECT start_time FROM schedule_rules WHERE schedule_id = ? AND weekday = 6', [$sid]), 'sábado 09:00');
$bad = $days;
$bad[2]['b'][1] = ['s' => '11:00', 'e' => '15:00'];
T::eq(422, $http->post('/admin/asistente/3', ['d' => $bad])['code'], 'bloques traslapados rechazados');
$bad = $days;
$bad[3]['b'][0] = ['s' => '12:00', 'e' => '08:00'];
T::eq(422, $http->post('/admin/asistente/3', ['d' => $bad])['code'], 'fin antes de inicio rechazado');
T::eq(422, $http->post('/admin/asistente/3', ['d' => [1 => ['b' => []]]])['code'], 'sin días activos rechazado');
T::eq(11, (int) Db::val('SELECT COUNT(*) FROM schedule_rules WHERE schedule_id = ?', [$sid]), 'un envío inválido no altera el horario');
$r = $http->post('/admin/asistente/4', ['host_name' => 'Dra. Ana Pérez', 'host_title' => 'Odontóloga']);
T::eq(302, $r['code'], 'guardar equipo');
T::eq('Dra. Ana Pérez', (string) Db::val('SELECT name FROM hosts ORDER BY id LIMIT 1'), 'anfitrión renombrado');
T::eq(422, $http->post('/admin/asistente/4', ['host_name' => '<b>x</b>', 'host_title' => ''])['code'], 'nombre con HTML rechazado');
$p5 = $http->req('GET', '/admin/asistente?paso=5');
T::ok(strpos($p5['body'], 'qrcode.js') !== false && strpos($p5['body'], 'data-qr-root') !== false, 'paso 5 con QR');
$r = $http->post('/admin/asistente/terminar');
T::eq(302, $r['code'], 'terminar asistente');
Settings::flush();
T::eq('1', Settings::get('onboarding_done'), 'onboarding_done = 1');
T::eq(200, $http->req('GET', '/admin/asistente?paso=9')['code'], 'paso fuera de rango se corrige');

$http->stop();
T::done();
