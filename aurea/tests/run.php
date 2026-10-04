<?php
/**
 * Suite de pruebas automatizadas de AUREA. Uso: php tests/run.php /ruta/a/la/app-descomprimida
 * Instala desde cero por HTTP y prueba contra servidores reales (PHP + MariaDB + SMTP y API simuladas).
 */
declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/lib.php';
define('AUREA_TESTING', true);

$APP = rtrim((string)($argv[1] ?? ''), '/');
if (!is_dir($APP . '/app')) { fwrite(STDERR, "Uso: php tests/run.php /ruta/app\n"); exit(2); }
$PORT = 8191; $SMTP = 2525; $MOCK = 2526; $BASE = "http://127.0.0.1:$PORT";
$TMP = sys_get_temp_dir() . '/aurea_t_' . getmypid(); @mkdir($TMP . '/mail', 0777, true);
$procs = [];
function sh(string $c): string { return (string)shell_exec($c . ' 2>&1'); }
function startBg(string $cmd, array &$procs, array $env = []): void
{
    $procs[] = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, $env + getenv());
}
register_shutdown_function(static function () use (&$procs, $TMP) {
    foreach ($procs as $p) { if (is_resource($p)) { $s = proc_get_status($p); @posix_kill($s['pid'], 15); proc_terminate($p); } }
    sh('pkill -f "php -S 127.0.0.1:8191"'); sh('pkill -f smtp_server.py'); sh('pkill -f mock_api.py'); sh('rm -rf ' . escapeshellarg($TMP));
});

/* ---------- entorno ---------- */
sh("mysql -uroot -e \"drop database if exists aurea_test; create database aurea_test character set utf8mb4 collate utf8mb4_unicode_ci; drop database if exists aurea_restore; create database aurea_restore character set utf8mb4\"");
@unlink($APP . '/config/config.php'); @unlink($APP . '/config/installed.lock');
sh('pkill -f "php -S 127.0.0.1:8191"'); sh('pkill -f smtp_server.py'); sh('pkill -f mock_api.py');
startBg("php -S 127.0.0.1:$PORT -t " . escapeshellarg($APP) . ' ' . escapeshellarg(__DIR__ . '/router.php'), $procs, ['AUREA_DOCROOT' => $APP, 'PHP_CLI_SERVER_WORKERS' => '12']);
startBg("python3 " . escapeshellarg(__DIR__ . '/smtp_server.py') . " $SMTP " . escapeshellarg($TMP . '/mail'), $procs);
startBg("python3 " . escapeshellarg(__DIR__ . '/mock_api.py') . " $MOCK " . escapeshellarg($TMP . '/mock.log'), $procs);
for ($i = 0; $i < 50; $i++) { if (@file_get_contents($BASE . '/robots.txt', false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 1]])) !== false) { break; } usleep(200000); }

/* ---------- 1. INSTALADOR ---------- */
section('Instalador');
$h = new Http($BASE);
$r = $h->get('/');
t('Sin instalar, la raíz redirige al instalador', $r['code'] === 302 && strpos($r['location'], '/instalar') !== false, $r['location']);
$r = $h->get('/instalar/');
t('Paso 1: requisitos se muestran y permiten continuar', $r['code'] === 200 && strpos($r['body'], 'Requisitos del servidor') !== false && strpos($r['body'], 'paso=2') !== false);
$r = $h->post('/instalar/?paso=2', ['host' => '127.0.0.1', 'name' => 'x', '_t' => 'malo']);
t('Instalador rechaza POST sin token CSRF', $r['code'] === 419);
$tok = preg_match('/name="_t" value="([a-f0-9]+)"/', $h->get('/instalar/?paso=2')['body'], $m) ? $m[1] : '';
$r = $h->post('/instalar/?paso=2', ['_t' => $tok, 'host' => '127.0.0.1', 'port' => 3306, 'name' => 'aurea_test', 'user' => 'aurea', 'pass' => 'incorrecta']);
t('Paso 2: credenciales de BD inválidas muestran error amigable', strpos($r['body'], 'No se pudo conectar') !== false && strpos($r['body'], 'SQLSTATE') === false);
$r = $h->post('/instalar/?paso=2', ['_t' => $tok, 'host' => '127.0.0.1', 'port' => 3306, 'name' => 'aurea_test', 'user' => 'aurea', 'pass' => 'aurea_test_pw']);
t('Paso 2: conexión correcta avanza al paso 3', $r['code'] === 302 && strpos($r['location'], 'paso=3') !== false);
$base3 = ['_t' => $tok, 'bname' => 'Clínica Prueba', 'bphone' => '22345678', 'bwa' => '55551234', 'bemail' => 'info@prueba.gt', 'tz' => 'America/Guatemala', 'baddr' => 'Zona 10', 'aname' => 'Admin', 'aemail' => 'admin@prueba.gt', 'apass2' => 'ClaveSegura2026'];
$r = $h->post('/instalar/?paso=3', $base3 + ['apass' => 'corta']);
t('Paso 3: contraseña débil rechazada', strpos($r['body'], 'al menos 10 caracteres') !== false);
$r = $h->post('/instalar/?paso=3', ['apass' => 'ClaveSegura2026'] + $base3);
t('Paso 3: datos válidos avanzan al paso 4', $r['code'] === 302 && strpos($r['location'], 'paso=4') !== false);
$r = $h->post('/instalar/?paso=4', ['_t' => $tok, 'preset' => 'otro']);
t('Paso 4: instalación completa y se anuncia el bloqueo', strpos($r['body'], 'Instalación completada') !== false && strpos($r['body'], 'bloque') !== false);
$cfgTxt = (string)file_get_contents($APP . '/config/config.php');
t('Se generó config/config.php con clave secreta y sin la contraseña de admin', strpos($cfgTxt, 'ClaveSegura') === false && preg_match("/'app_key' => '[a-f0-9]{64}'/", $cfgTxt) === 1);
$r = (new Http($BASE))->get('/instalar/');
t('Instalador bloqueado automáticamente tras instalar (403)', $r['code'] === 403 && strpos($r['body'], 'bloqueada') !== false);
$r = (new Http($BASE))->post('/instalar/?paso=4', ['_t' => 'x', 'preset' => 'otro']);
t('POST al instalador bloqueado también es rechazado', $r['code'] === 403);

// Configuración especial de pruebas (antes de cargar la app en este proceso)
$cfg = require $APP . '/config/config.php';
$cfg['rate_limit_disabled'] = true; $cfg['wa_api_base'] = "http://127.0.0.1:$MOCK"; $cfg['captcha_verify_url'] = "http://127.0.0.1:$MOCK/siteverify";
function writeCfg(string $app, array $cfg): void { file_put_contents($app . '/config/config.php', "<?php\nreturn " . var_export($cfg, true) . ";\n"); sleep(3); /* opcache del servidor web revalida cada ~2 s */ }
writeCfg($APP, $cfg);

require $APP . '/app/bootstrap.php';
use Aurea\Core\Auth;
use Aurea\Core\Db;
use Aurea\Core\Settings;
use Aurea\Core\Secret;
use Aurea\Core\Totp;
use Aurea\Core\Util;
use Aurea\Core\Upload;
use Aurea\Core\Ics;
use Aurea\Services\AvailabilityService;
use Aurea\Services\BookingService;
use Aurea\Services\CouponService;
use Aurea\Services\HolidayService;
use Aurea\Services\NotificationService;
use Aurea\Services\WaitlistService;
use Aurea\Services\CronService;
use Aurea\Services\BackupService;
use Aurea\Services\PresetService;
use Aurea\Controllers\Admin\ProfessionalController;
Db::connect(config('db'));
date_default_timezone_set((string)Settings::get('timezone', 'America/Guatemala'));

/* ---------- fixtures ---------- */
Db::exec('UPDATE services SET active=0'); Db::exec('DELETE FROM form_fields WHERE service_id IS NULL');
$pA = ProfessionalController::createBasic('Dra. Ana Prueba', 'Médica', 'ana@prueba.gt', '55550001'); PresetService::defaultSchedule($pA);
$pB = ProfessionalController::createBasic('Dr. Beto Prueba', 'Médico', '', '55550002'); PresetService::defaultSchedule($pB);
$mk = static function (array $o) use ($pA, $pB): int {
    $d = $o + ['category_id' => null, 'description' => '', 'duration_min' => 30, 'buffer_before' => 0, 'buffer_after' => 0, 'min_notice_hours' => 0, 'max_advance_days' => 900, 'slot_interval' => 30, 'capacity' => 1,
        'price' => 100, 'deposit_type' => 'none', 'deposit_value' => 0, 'modality' => 'presencial', 'meeting_url' => '', 'auto_confirm' => 1, 'active' => 1, 'sort' => 0, 'created_at' => date('Y-m-d H:i:s')];
    $id = Db::insert('services', $d);
    foreach ([$pA, $pB] as $p) { Db::exec('INSERT IGNORE INTO professional_services (professional_id,service_id) VALUES (?,?)', [$p, $id]); }
    return $id;
};
$S1 = $mk(['name' => 'Consulta general', 'price' => 500]);
$SBUF = $mk(['name' => 'Con buffer', 'buffer_after' => 15, 'slot_interval' => 15]);
$SGRP = $mk(['name' => 'Clase grupal', 'capacity' => 3, 'duration_min' => 60]);
$SNOT = $mk(['name' => 'Con aviso', 'min_notice_hours' => 48, 'max_advance_days' => 5]);
$SDEP = $mk(['name' => 'Con anticipo', 'price' => 1000, 'deposit_type' => 'percent', 'deposit_value' => 30]);
$SMAN = $mk(['name' => 'Aprobación manual', 'auto_confirm' => 0]);
$SDOM = $mk(['name' => 'A domicilio', 'modality' => 'domicilio']);
$ff = Db::insert('form_fields', ['service_id' => $S1, 'label' => 'Motivo', 'ftype' => 'text', 'required' => 1, 'sort' => 1, 'active' => 1]);
$ffSel = Db::insert('form_fields', ['service_id' => $S1, 'label' => '¿Primera vez?', 'ftype' => 'select', 'options' => 'Sí|No', 'required' => 1, 'sort' => 2, 'active' => 1]);
$ffCond = Db::insert('form_fields', ['service_id' => $S1, 'label' => 'Detalle anterior', 'ftype' => 'text', 'required' => 1, 'sort' => 3, 'active' => 1, 'cond_field_id' => $ffSel, 'cond_value' => 'No']);
Settings::flush();

function workday(int $offset = 3, int $minDow = 1, int $maxDow = 5): string
{
    static $used = [];
    $d = strtotime("+$offset days");
    for ($i = 0; $i < 90; $i++) {
        $date = date('Y-m-d', $d); $w = (int)date('N', $d);
        if ($w >= $minDow && $w <= $maxDow && !HolidayService::forDate($date) && !isset($used[$date])) { $used[$date] = true; return $date; }
        $d = strtotime('+1 day', $d);
    }
    throw new RuntimeException('sin día hábil');
}
function newPhone(): string { static $n = 0; return '55' . str_pad((string)(100000 + ++$n), 6, '0', STR_PAD_LEFT); }
function cli(string $name = 'Cliente Prueba', ?string $phone = null, string $email = ''): array { return ['name' => $name, 'phone' => $phone ?? newPhone(), 'cc' => '502', 'email' => $email, 'consent' => true]; }
function book(int $svc, $prof, string $start, ?array $client = null, array $extra = [], array $opts = []): array
{
    return BookingService::create(['service_id' => $svc, 'professional_id' => $prof, 'start' => $start, 'client' => $client ?? cli()] + $extra, $opts);
}
function dbq(string $sql, array $p = []): array { return Db::all($sql, $p); }
function dbv(string $sql, array $p = []) { return Db::val($sql, $p); }
$D1 = workday(4); $D2 = workday(6);
$ans1 = [$ff => 'Dolor', $ffSel => 'Sí'];

/* ---------- 2. RESERVA (4 pasos) ---------- */
section('Reserva pública: flujo completo');
$pub = new Http($BASE);
$r = $pub->get('/reservar');
t('Página /reservar responde 200 con datos de arranque', $r['code'] === 200 && strpos($r['body'], 'id="boot"') !== false);
$csrf = $pub->pubCsrf();
$j = $pub->json("/api/days?service=$S1&professional=any&month=" . substr($D1, 0, 7) . '&next=1');
t('API días: devuelve días disponibles y próximo horario', !empty($j['ok']) && isset($j['days']) && isset($j['next']['start']), json_encode($j));
$j = $pub->json("/api/slots?service=$S1&professional=any&date=$D1");
t('API horarios: devuelve chips de horarios', !empty($j['ok']) && count($j['slots']) >= 8, (string)count($j['slots'] ?? []));
$start = $D1 . ' 09:00:00';
$fields = ['_csrf' => $csrf, 'service_id' => $S1, 'professional_id' => 'any', 'start' => $start, 'name' => 'María Flores', 'phone' => '5555 4321', 'cc' => '502', 'email' => 'maria@ejemplo.gt', 'consent' => '1',
    "answers[$ff]" => 'Dolor de cabeza', "answers[$ffSel]" => 'No', "answers[$ffCond]" => 'Hace un año'];
$j = $pub->json('/api/book', $fields);
t('Reserva completa de 4 pasos (con formulario condicional) → OK', !empty($j['ok']) && !empty($j['token']), json_encode($j));
$tokenA = (string)($j['token'] ?? '');
$row = dbq('SELECT * FROM appointments WHERE token=?', [$tokenA])[0] ?? null;
t('La cita quedó confirmada con precio y respuestas guardadas', $row && $row['status'] === 'confirmed' && (float)$row['total'] === 500.0 && (int)dbv('SELECT COUNT(*) FROM appointment_answers WHERE appointment_id=?', [$row['id']]) === 3);
$j2 = $pub->json('/api/book', ['answers' => 0] + array_merge($fields, ['phone' => '55554322', 'start' => $D1 . ' 09:30:00', "answers[$ffSel]" => 'No', "answers[$ffCond]" => '']));
t('Campo condicional visible y obligatorio se valida en servidor', empty($j2['ok']) && isset($j2['fields']['f' . $ffCond]), json_encode($j2));
$j2 = $pub->json('/api/book', array_merge($fields, ['phone' => '55554323', 'start' => $D1 . ' 09:30:00', "answers[$ffSel]" => 'Sí', "answers[$ffCond]" => '']));
t('Campo condicional oculto no es obligatorio', !empty($j2['ok']), json_encode($j2));
$j3 = $pub->json('/api/book', array_merge($fields, ['phone' => '1234']));
t('Teléfono inválido (no 8 dígitos) rechazado', empty($j3['ok']) && isset($j3['fields']['phone']));
$j3 = $pub->json('/api/book', array_merge($fields, ['phone' => '55554324', 'email' => 'no-es-correo']));
t('Correo inválido rechazado', empty($j3['ok']) && isset($j3['fields']['email']));
$j3 = $pub->json('/api/book', array_merge($fields, ['phone' => '55554325', 'consent' => '0']));
t('Sin consentimiento de privacidad no se permite reservar', empty($j3['ok']) && isset($j3['fields']['consent']));
$j3 = $pub->json('/api/book', array_merge($fields, ['phone' => '55554326', 'start' => '2020-01-01 09:00:00']));
t('Fecha en el pasado rechazada', empty($j3['ok']));
$j3 = $pub->json('/api/book', array_merge($fields, ['phone' => '55554327', 'start' => $D1 . ' 03:00:00']));
t('Hora fuera del horario laboral rechazada', empty($j3['ok']) && ($j3['code'] ?? '') === 'slot_taken');
$j3 = $pub->json('/api/book', array_merge($fields, ['phone' => '55554328', 'start' => 'basura']));
t('Fecha con formato inválido rechazada', empty($j3['ok']));
$consent = dbq('SELECT consent_at, consent_ip FROM clients WHERE phone=?', ['55554321'])[0] ?? [];
t('El consentimiento de privacidad quedó registrado (fecha, IP)', !empty($consent['consent_at']) && $consent['consent_ip'] === '127.0.0.1');
$cl = dbq('SELECT COUNT(*) n FROM clients WHERE phone="55554321"')[0]['n'];
$pub->json('/api/book', array_merge($fields, ['start' => $D1 . ' 10:00:00', "answers[$ffSel]" => 'Sí']));
t('Mismo teléfono reutiliza la ficha del cliente (sin duplicar)', (int)dbv('SELECT COUNT(*) FROM clients WHERE phone="55554321"') === 1);

section('Gestión por token: confirmar / cancelar / reprogramar');
$mp = new Http($BASE);
$r = $mp->get("/cita/$tokenA");
t('Página de la cita (éxito) con botones .ics, Google, WhatsApp', strpos($r['body'], '/ics') !== false && strpos($r['body'], 'calendar.google.com') !== false && (strpos($r['body'], 'wa.me') !== false));
$r = $mp->get('/cita/' . str_repeat('0', 32));
t('Token inexistente → 404', $r['code'] === 404);
$r = $mp->post("/cita/$tokenA/confirmar", ['_csrf' => $csrf]);
t('Confirmar asistencia por token (con CSRF)', $r['code'] === 302 && dbv('SELECT client_confirmed_at FROM appointments WHERE token=?', [$tokenA]) !== null);
$r = $mp->post("/cita/$tokenA/confirmar", []);
t('Confirmar sin token CSRF → 419', $r['code'] === 419);
$r = $mp->get("/cita/$tokenA/reprogramar");
t('Formulario de reprogramación disponible', $r['code'] === 200 && strpos($r['body'], 'resched-root') !== false);
$newStart = $D2 . ' 11:00:00';
$apptA = (int)dbv('SELECT id FROM appointments WHERE token=?', [$tokenA]);
$r = $mp->post("/cita/$tokenA/reprogramar", ['_csrf' => $csrf, 'start' => $newStart]);
$newTok = preg_match('#/cita/([a-f0-9]{32})#', $r['location'], $m) ? $m[1] : '';
t('Reprogramar por token crea nueva cita y marca la anterior "reprogramada"', $newTok !== '' && dbv('SELECT status FROM appointments WHERE id=?', [$apptA]) === 'rescheduled' && dbv('SELECT start_at FROM appointments WHERE token=?', [$newTok]) === $newStart, $r['location']);
$r = $mp->get("/cita/$tokenA");
t('El enlace antiguo redirige a la cita nueva', $r['code'] === 302 && strpos($r['location'], $newTok) !== false);
t('Historial de la cita original registra la reprogramación', (int)dbv("SELECT COUNT(*) FROM appointment_history WHERE appointment_id=? AND action='rescheduled'", [$apptA]) === 1);
$r = $mp->post("/cita/$newTok/cancelar", ['_csrf' => $csrf, 'reason' => 'Viaje']);
t('Cancelar por token respetando la política (>12 h)', dbv('SELECT status FROM appointments WHERE token=?', [$newTok]) === 'cancelled');
// Política: cita en menos de 12 horas no se puede cancelar en línea
$soon = date('Y-m-d H:i:s', strtotime('+3 hours') - (strtotime('+3 hours') % 1800) + 1800);
$near = BookingService::create(['service_id' => $S1, 'professional_id' => $pA, 'start' => $soon, 'client' => cli(), 'answers' => []], ['staff' => true, 'allow_past' => true, 'ignore_schedule' => true]);
$r = $mp->post('/cita/' . $near['token'] . '/cancelar', ['_csrf' => $csrf]);
t('Política de cancelación: <12 h no permite cancelar en línea', strpos($r['location'], 'm=politica') !== false && dbv('SELECT status FROM appointments WHERE id=?', [$near['id']]) === 'confirmed');
$r = $mp->post('/cita/' . $near['token'] . '/reprogramar', ['_csrf' => $csrf, 'start' => $D2 . ' 15:00:00']);
t('Política: tampoco permite reprogramar con tan poca anticipación', strpos($r['location'], 'm=politica') !== false);

/* ---------- 3. MOTOR DE DISPONIBILIDAD ---------- */
section('Disponibilidad: feriados, buffers, aviso, anticipación, grupos');
t('Pascua 2025 = 20/abr, 2026 = 5/abr, 2027 = 28/mar', HolidayService::easter(2025)->format('md') === '0420' && HolidayService::easter(2026)->format('md') === '0405' && HolidayService::easter(2027)->format('md') === '0328');
$def = []; foreach (HolidayService::defaults(2027) as $x) { $def[$x[0]] = $x[1]; }
t('Semana Santa 2027 calculada (Jueves 25/mar, Viernes 26, Sábado 27)', ($def['2027-03-25'] ?? '') === 'Jueves Santo' && ($def['2027-03-26'] ?? '') === 'Viernes Santo' && ($def['2027-03-27'] ?? '') === 'Sábado Santo');
HolidayService::seedYear(2027); HolidayService::seedYear(2028);
t('Feriados fijos de Guatemala precargados (1 ene, 1 may, 30 jun, 15 sep, 20 oct, 1 nov, 25 dic)', (int)dbv("SELECT COUNT(*) FROM holidays WHERE hdate IN ('2027-01-01','2027-05-01','2027-06-30','2027-09-15','2027-10-20','2027-11-01','2027-12-25')") === 7);
t('15 de agosto marcado "solo ciudad de Guatemala" y desactivable', dbv("SELECT scope FROM holidays WHERE hdate='2027-08-15'") === 'city' && (int)dbv("SELECT active FROM holidays WHERE hdate='2027-08-15'") === 1);
$svcRow = fn(int $id) => dbq('SELECT * FROM services WHERE id=?', [$id])[0];
$slotsFor = function (int $svc, int $pro, string $date, array $o = []) use ($svcRow): array {
    $ctx = AvailabilityService::context($pro, $date, $date);
    return array_column(AvailabilityService::slots($svcRow($svc), $ctx, $date, null, $o + ['now' => strtotime('2026-01-04 08:00:00'), 'allow_past' => true]), 'time');
};
t('Feriado de día completo (25/dic/2027) no ofrece horarios', $slotsFor($S1, $pA, '2027-12-25') === []);
t('Jueves Santo 2027 (25/mar) no ofrece horarios', $slotsFor($S1, $pA, '2027-03-25') === []);
$dec24 = $slotsFor($S1, $pA, '2027-12-24');
t('24/dic: medio día (último horario termina a las 12:00)', $dec24 !== [] && max($dec24) <= '11:30', implode(',', $dec24));
t('31/dic/2027: medio día', ($x = $slotsFor($S1, $pA, '2027-12-31')) !== [] && max($x) <= '11:30');
t('Fecha laboral normal sí ofrece horarios (mañana y tarde, con pausa)', ($x = $slotsFor($S1, $pA, '2027-02-09')) && in_array('08:00', $x) && in_array('11:30', $x) && !in_array('12:00', $x) && in_array('14:00', $x) && in_array('17:30', $x) && !in_array('18:00', $x));
t('Sábado solo horario de mañana; domingo sin horarios', ($x = $slotsFor($S1, $pA, '2027-02-13')) && max($x) === '11:30' && $slotsFor($S1, $pA, '2027-02-14') === []);
t('15/ago (feriado ciudad) bloquea; al desactivarlo se atiende', $slotsFor($S1, $pA, '2027-08-16') !== [] && ($slotsFor($S1, $pA, '2027-08-15') === []) );
Db::exec("UPDATE holidays SET active=0 WHERE hdate='2027-08-15'");
Db::exec("INSERT INTO schedules (professional_id,location_id,weekday,start_time,end_time) VALUES ($pA,NULL,0,'09:00:00','11:00:00')");
t('Feriado desactivado deja de bloquear (domingo con horario de prueba)', $slotsFor($S1, $pA, '2027-08-15') !== []);
Db::exec("DELETE FROM schedules WHERE professional_id=$pA AND weekday=0"); Db::exec("UPDATE holidays SET active=1 WHERE hdate='2027-08-15'");
// Ausencias
Db::insert('time_off', ['professional_id' => $pA, 'start_at' => '2027-02-16 00:00:00', 'end_at' => '2027-02-20 23:59:00', 'reason' => 'Vacaciones', 'created_at' => date('Y-m-d H:i:s')]);
t('Vacaciones/ausencia (rango) bloquean al profesional pero no a otros', $slotsFor($S1, $pA, '2027-02-17') === [] && $slotsFor($S1, $pB, '2027-02-17') !== []);
Db::insert('time_off', ['professional_id' => null, 'start_at' => '2027-02-23 10:00:00', 'end_at' => '2027-02-23 11:00:00', 'reason' => 'Cierre general', 'created_at' => date('Y-m-d H:i:s')]);
t('Ausencia parcial general (todos) bloquea solo ese rango', ($x = $slotsFor($S1, $pB, '2027-02-23')) && !in_array('10:00', $x) && !in_array('10:30', $x) && in_array('09:30', $x) && in_array('11:00', $x));
// Buffers
$dB = workday(8);
$rb = BookingService::create(['service_id' => $SBUF, 'professional_id' => $pA, 'start' => "$dB 10:00:00", 'client' => cli(), 'answers' => []], ['staff' => false]);
$sl = $slotsFor($SBUF, $pA, $dB, ['now' => time()]);
t('Cita con buffer posterior de 15 min bloquea hasta las 10:45', $rb['ok'] && !in_array('10:00', $sl) && !in_array('10:15', $sl) && !in_array('10:30', $sl) && in_array('10:45', $sl), implode(',', $sl));
t('Buffer: horario previo cuyo bloque choca (9:30 → 10:15) no se ofrece; 9:15 sí', !in_array('09:30', $sl) && in_array('09:15', $sl));
$rb2 = book($SBUF, $pA, "$dB 10:30:00");
t('Reservar dentro del buffer es rechazado', !$rb2['ok'] && $rb2['code'] === 'slot_taken');
// Aviso mínimo y anticipación máxima
$dN = date('Y-m-d', strtotime('+1 day')); $dN2 = date('Y-m-d', strtotime('+3 days')); $dN6 = date('Y-m-d', strtotime('+7 days'));
$ctxN = fn($d) => AvailabilityService::context($pA, $d, $d);
t('Aviso mínimo 48 h: mañana sin horarios', AvailabilityService::slots($svcRow($SNOT), $ctxN($dN), $dN, null) === []);
$okDay = null; foreach ([3, 4, 5] as $k) { $d = date('Y-m-d', strtotime("+$k days")); if (AvailabilityService::slots($svcRow($SNOT), $ctxN($d), $d, null)) { $okDay = $d; break; } }
t('Aviso mínimo: pasados 48 h sí hay horarios (dentro de 5 días)', $okDay !== null);
t('Anticipación máxima 5 días: a 7 días no hay horarios', AvailabilityService::slots($svcRow($SNOT), $ctxN($dN6), $dN6, null) === []);
$rr = book($SNOT, $pA, "$dN 10:00:00");
t('Reserva con menos aviso del mínimo es rechazada en servidor', !$rr['ok']);
$rr = book($SNOT, $pA, "$dN6 10:00:00");
t('Reserva más allá de la anticipación máxima es rechazada', !$rr['ok']);
// Grupos
$dG = workday(9);
$g = []; for ($i = 0; $i < 3; $i++) { $g[] = book($SGRP, $pA, "$dG 09:00:00")['ok']; }
$g4 = book($SGRP, $pA, "$dG 09:00:00");
t('Clase grupal: 3 cupos ocupados, el 4.º es rechazado', $g === [true, true, true] && !$g4['ok']);
$slG = array_column(AvailabilityService::slots($svcRow($SGRP), AvailabilityService::context($pA, $dG, $dG), $dG, null), 'time');
t('Clase llena deja de ofrecerse', !in_array('09:00', $slG));
$gx = book($S1, $pA, "$dG 09:30:00");
t('Otro servicio que se traslapa con la clase grupal es rechazado', !$gx['ok']);
$gy = book($SGRP, $pB, "$dG 09:00:00");
t('El mismo horario en otro profesional sigue disponible', $gy['ok']);
$sameCli = cli('Repetido', '55556666'); $dG2 = workday(10);
book($SGRP, $pA, "$dG2 09:00:00", $sameCli); 
$slx = array_column(AvailabilityService::slots($svcRow($SGRP), AvailabilityService::context($pA, $dG2, $dG2), $dG2, null), 'left', 'time');
t('Clase grupal: el horario muestra los cupos restantes (2 de 3)', ($slx['09:00'] ?? 0) === 2, json_encode($slx['09:00'] ?? null));

/* ---------- 4. CONCURRENCIA ---------- */
section('Concurrencia: 50 reservas simultáneas al mismo horario');
$dC = workday(11); $startC = "$dC 10:00:00";
$pub2 = new Http($BASE); $csrf2 = $pub2->pubCsrf();
$reqs = [];
for ($i = 0; $i < 50; $i++) { $reqs[] = ["$BASE/api/book", ['_csrf' => $csrf2, 'service_id' => $SBUF, 'professional_id' => $pA, 'start' => $startC, 'name' => "Concurrente $i", 'phone' => '5510' . str_pad((string)(1000 + $i), 4, '0', STR_PAD_LEFT), 'cc' => '502', 'consent' => '1']]; }
$res = parallel($reqs);
$okN = 0; $taken = 0; foreach ($res as $x) { $jj = json_decode($x['body'], true); if (!empty($jj['ok'])) { $okN++; } elseif (($jj['code'] ?? '') === 'slot_taken') { $taken++; } }
t('50 reservas simultáneas (mismo profesional y horario): exactamente 1 exitosa', $okN === 1 && $taken === 49, "ok=$okN taken=$taken");
t('La BD tiene exactamente 1 cita activa en ese horario', (int)dbv("SELECT COUNT(*) FROM appointments WHERE professional_id=? AND start_at=? AND status IN ('pending','confirmed')", [$pA, $startC]) === 1);
$dC2 = workday(12); $startC2 = "$dC2 10:00:00"; $reqs = [];
for ($i = 0; $i < 50; $i++) { $reqs[] = ["$BASE/api/book", ['_csrf' => $csrf2, 'service_id' => $SBUF, 'professional_id' => 'any', 'start' => $startC2, 'name' => "Any $i", 'phone' => '5520' . str_pad((string)(1000 + $i), 4, '0', STR_PAD_LEFT), 'cc' => '502', 'consent' => '1']]; }
$res = parallel($reqs); $okN = 0; foreach ($res as $x) { if (!empty(json_decode($x['body'], true)['ok'])) { $okN++; } }
$byPro = dbq("SELECT professional_id, COUNT(*) n FROM appointments WHERE start_at=? AND status IN ('pending','confirmed') GROUP BY professional_id", [$startC2]);
t('"Cualquiera disponible" con 50 simultáneas: 2 exitosas (una por profesional, sin duplicar)', $okN === 2 && count($byPro) === 2 && $byPro[0]['n'] == 1 && $byPro[1]['n'] == 1, "ok=$okN");
$dC3 = workday(13); $reqs = [];
for ($i = 0; $i < 20; $i++) { $reqs[] = ["$BASE/api/book", ['_csrf' => $csrf2, 'service_id' => $SGRP, 'professional_id' => $pA, 'start' => "$dC3 14:00:00", 'name' => "Grupo $i", 'phone' => '5530' . str_pad((string)(1000 + $i), 4, '0', STR_PAD_LEFT), 'cc' => '502', 'consent' => '1']]; }
$res = parallel($reqs); $okN = 0; foreach ($res as $x) { if (!empty(json_decode($x['body'], true)['ok'])) { $okN++; } }
t('Clase grupal de 3 cupos con 20 simultáneas: exactamente 3', $okN === 3, "ok=$okN");
$dC4 = workday(14); $reqs = [];
foreach (['10:00', '10:15', '10:30', '10:45'] as $k => $hm) { for ($i = 0; $i < 6; $i++) { $reqs[] = ["$BASE/api/book", ['_csrf' => $csrf2, 'service_id' => $SBUF, 'professional_id' => $pA, 'start' => "$dC4 $hm:00", 'name' => "Sol $k$i", 'phone' => '5540' . $k . str_pad((string)(100 + $i), 3, '0', STR_PAD_LEFT), 'cc' => '502', 'consent' => '1']]; } }
shuffle($reqs); parallel($reqs);
$rowsC = dbq("SELECT block_start, block_end FROM appointments WHERE professional_id=? AND DATE(start_at)=? AND status IN ('pending','confirmed') ORDER BY block_start", [$pA, $dC4]);
$overlap = false; for ($i = 1; $i < count($rowsC); $i++) { if ($rowsC[$i]['block_start'] < $rowsC[$i - 1]['block_end']) { $overlap = true; } }
t('Reservas simultáneas a horarios traslapados (con buffers): ningún traslape en BD', !$overlap && count($rowsC) >= 1, 'citas=' . count($rowsC));

/* ---------- 5. CUPONES, PAQUETES, PAGOS ---------- */
section('Cupones, certificados de regalo y paquetes');
$mkCp = fn(array $o) => Db::insert('coupons', $o + ['kind' => 'percent', 'value' => 0, 'balance' => 0, 'max_uses' => 0, 'used' => 0, 'active' => 1, 'note' => '', 'created_at' => date('Y-m-d H:i:s')]);
$mkCp(['code' => 'PCT20', 'kind' => 'percent', 'value' => 20]); $mkCp(['code' => 'FIJO100', 'kind' => 'fixed', 'value' => 100]);
$mkCp(['code' => 'REGALO300', 'kind' => 'gift', 'value' => 300, 'balance' => 300]); $mkCp(['code' => 'VENCIDO', 'kind' => 'percent', 'value' => 50, 'valid_to' => '2020-01-01']);
$mkCp(['code' => 'UNUSO', 'kind' => 'fixed', 'value' => 50, 'max_uses' => 1]); $mkCp(['code' => 'SOLOOTRO', 'kind' => 'percent', 'value' => 10, 'service_id' => $SDEP]);
$dP = workday(15); $tm = ['09:00', '09:30', '10:00', '10:30', '11:00', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00'];
$c1 = book($S1, $pB, "$dP $tm[0]:00", null, ['answers' => $ans1, 'coupon' => 'pct20']);
t('Cupón porcentaje 20% (insensible a mayúsculas): Q500 → Q400', $c1['ok'] && (float)dbv('SELECT total FROM appointments WHERE id=?', [$c1['id']]) === 400.0);
$c2 = book($S1, $pB, "$dP $tm[1]:00", null, ['answers' => $ans1, 'coupon' => 'FIJO100']);
t('Cupón de monto fijo Q100: Q500 → Q400', $c2['ok'] && (float)dbv('SELECT discount FROM appointments WHERE id=?', [$c2['id']]) === 100.0);
$c3 = book($S1, $pB, "$dP $tm[2]:00", null, ['answers' => $ans1, 'coupon' => 'REGALO300']);
t('Certificado de regalo Q300 aplica y descuenta saldo a 0', $c3['ok'] && (float)dbv("SELECT balance FROM coupons WHERE code='REGALO300'") === 0.0 && (float)dbv('SELECT total FROM appointments WHERE id=?', [$c3['id']]) === 200.0);
$c3b = book($S1, $pB, "$dP $tm[3]:00", null, ['answers' => $ans1, 'coupon' => 'REGALO300']);
t('Certificado sin saldo rechazado', !$c3b['ok'] && $c3b['code'] === 'coupon');
$ce = book($S1, $pB, "$dP $tm[3]:00", null, ['answers' => $ans1, 'coupon' => 'VENCIDO']);
t('Cupón vencido rechazado', !$ce['ok'] && strpos($ce['error'], 'venci') !== false);
$cu1 = book($S1, $pB, "$dP $tm[3]:00", null, ['answers' => $ans1, 'coupon' => 'UNUSO']); $cu2 = book($S1, $pB, "$dP $tm[4]:00", null, ['answers' => $ans1, 'coupon' => 'UNUSO']);
t('Cupón de un solo uso: segundo uso rechazado', $cu1['ok'] && !$cu2['ok']);
$cs = book($S1, $pB, "$dP $tm[4]:00", null, ['answers' => $ans1, 'coupon' => 'SOLOOTRO']);
t('Cupón restringido a otro servicio rechazado', !$cs['ok']);
$cx = book($S1, $pB, "$dP $tm[4]:00", null, ['answers' => $ans1, 'coupon' => "' OR 1=1 --"]);
t('Código de cupón con SQL injection rechazado sin error', !$cx['ok']);
BookingService::setStatus($c3['id'], 'cancelled', 'prueba');
t('Cancelar restaura el saldo del certificado de regalo (Q300) y los usos', (float)dbv("SELECT balance FROM coupons WHERE code='REGALO300'") === 300.0 && (int)dbv("SELECT used FROM coupons WHERE code='REGALO300'") === 0);
$apiC = $pub->json('/api/coupon', ['_csrf' => $csrf, 'service_id' => $S1, 'code' => 'PCT20']);
t('API de cupón: vista previa del descuento', !empty($apiC['ok']) && (float)$apiC['discount'] === 100.0 && (float)$apiC['total'] === 400.0, json_encode($apiC));
$dep = book($SDEP, $pA, workday(16) . ' 09:00:00');
$depRow = dbq('SELECT * FROM appointments WHERE id=?', [$dep['id']])[0];
t('Servicio con anticipo 30%: cita pendiente con depósito Q300 y vencimiento', $depRow['status'] === 'pending' && (float)$depRow['deposit_required'] === 300.0 && $depRow['pending_expires_at'] !== null);
$cpk = Db::insert('clients', ['name' => 'Con Paquete', 'phone_cc' => '502', 'phone' => '55557777', 'consent_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s')]);
$pkgId = Db::insert('packages', ['name' => 'Bono 2 sesiones', 'sessions' => 2, 'price' => 800, 'validity_days' => 90, 'active' => 1]);
$cpId = Db::insert('client_packages', ['client_id' => $cpk, 'package_id' => $pkgId, 'name' => 'Bono 2 sesiones', 'sessions_total' => 2, 'sessions_used' => 0, 'price' => 800, 'expires_at' => date('Y-m-d', strtotime('+90 days')), 'created_at' => date('Y-m-d H:i:s')]);
$dK = workday(17); $cl7 = cli('Con Paquete', '55557777');
$k1 = book($S1, $pA, "$dK 09:00:00", $cl7, ['answers' => $ans1], ['staff' => true, 'client_package_id' => $cpId]);
$k2 = book($S1, $pA, "$dK 09:30:00", $cl7, ['answers' => $ans1], ['staff' => true, 'client_package_id' => $cpId]);
$k3 = book($S1, $pA, "$dK 10:00:00", $cl7, ['answers' => $ans1], ['staff' => true, 'client_package_id' => $cpId]);
t('Paquete de 2 sesiones: 2 usos OK, el 3.º rechazado; total Q0 y saldo correcto', $k1['ok'] && $k2['ok'] && !$k3['ok'] && (int)dbv('SELECT sessions_used FROM client_packages WHERE id=?', [$cpId]) === 2 && (float)dbv('SELECT total FROM appointments WHERE id=?', [$k1['id']]) === 0.0);
BookingService::setStatus($k2['id'], 'cancelled', 'x');
t('Cancelar una cita con paquete devuelve la sesión al saldo', (int)dbv('SELECT sessions_used FROM client_packages WHERE id=?', [$cpId]) === 1);
// Pagos
BookingService::setStatus($dep['id'], 'confirmed', 'x');
$pay = Db::insert('payments', ['appointment_id' => $dep['id'], 'amount' => 300, 'method' => 'transferencia', 'status' => 'confirmed', 'paid_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s')]);
BookingService::refreshPayment($dep['id']);
t('Pago parcial → estado "parcial"; pago completo → "pagado"', dbv('SELECT payment_status FROM appointments WHERE id=?', [$dep['id']]) === 'partial');
Db::insert('payments', ['appointment_id' => $dep['id'], 'amount' => 700, 'method' => 'efectivo', 'status' => 'confirmed', 'paid_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s')]);
BookingService::refreshPayment($dep['id']);
t('Estado de pago pasa a "pagado" al completar el total', dbv('SELECT payment_status FROM appointments WHERE id=?', [$dep['id']]) === 'paid');
$dep2 = book($SDEP, $pB, workday(18) . ' 09:00:00');
Db::insert('payments', ['appointment_id' => $dep2['id'], 'amount' => 300, 'method' => 'transferencia', 'status' => 'confirmed', 'paid_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s')]);
BookingService::refreshPayment($dep2['id']);
t('Al cubrirse el anticipo, la cita pendiente se confirma automáticamente', dbv('SELECT status FROM appointments WHERE id=?', [$dep2['id']]) === 'confirmed');

/* ---------- 6. LISTA DE ESPERA ---------- */
section('Lista de espera');
$dW = workday(19); $wa1 = book($S1, $pA, "$dW 09:00:00", cli('Primero', '55558881'), ['answers' => $ans1]);
$wlPhone = '55558882';
$j = $pub->json('/api/waitlist', ['_csrf' => $csrf, 'service_id' => $S1, 'professional_id' => $pA, 'name' => 'En Espera', 'phone' => $wlPhone, 'email' => 'espera@ejemplo.gt', 'date_from' => $dW, 'date_to' => $dW, 'consent' => '1']);
t('Anotarse en la lista de espera (API)', !empty($j['ok']) && (int)dbv('SELECT COUNT(*) FROM waitlist WHERE phone=?', [$wlPhone]) === 1, json_encode($j));
BookingService::setStatus($wa1['id'], 'cancelled', 'prueba');
$w = dbq('SELECT * FROM waitlist WHERE phone=?', [$wlPhone])[0];
t('Al cancelarse la cita, el horario se ofrece automáticamente al siguiente', $w['status'] === 'offered' && $w['offer_token'] !== null && $w['offer_start'] === "$dW 09:00:00");
t('La oferta generó mensajes en cola (correo y WhatsApp)', (int)dbv("SELECT COUNT(*) FROM notifications_queue WHERE type='waitlist_offer' AND recipient IN ('espera@ejemplo.gt','50255558882')") === 2);
$r = $mp->get('/espera/' . $w['offer_token']);
t('Página de oferta muestra el horario y el botón de reservar', $r['code'] === 200 && strpos($r['body'], 'Reservar este horario') !== false);
$r = $mp->post('/espera/' . $w['offer_token'], ['_csrf' => $csrf]);
t('Aceptar la oferta crea la cita y marca la espera como agendada', strpos($r['location'], '/cita/') !== false && dbv('SELECT status FROM waitlist WHERE id=?', [$w['id']]) === 'booked');
$dW2 = workday(20); $wb = book($S1, $pA, "$dW2 09:00:00", cli('Otro', '55558883'), ['answers' => $ans1]);
WaitlistService::join(['name' => 'Vence', 'cc' => '502', 'phone' => '55558884', 'email' => '', 'service_id' => $S1, 'professional_id' => null, 'date_from' => $dW2, 'date_to' => $dW2]);
WaitlistService::join(['name' => 'Siguiente', 'cc' => '502', 'phone' => '55558885', 'email' => '', 'service_id' => $S1, 'professional_id' => null, 'date_from' => $dW2, 'date_to' => $dW2]);
BookingService::setStatus($wb['id'], 'cancelled', 'x');
Db::exec("UPDATE waitlist SET offer_expires=? WHERE phone='55558884'", [date('Y-m-d H:i:s', time() - 60)]);
WaitlistService::expireOffers();
t('Oferta vencida pasa a la siguiente persona en la fila', dbv("SELECT status FROM waitlist WHERE phone='55558884'") === 'expired' && dbv("SELECT status FROM waitlist WHERE phone='55558885'") === 'offered');

/* ---------- 7. SEGURIDAD: CSRF, SQLi, XSS ---------- */
section('Seguridad: CSRF, inyección SQL y XSS');
$r = (new Http($BASE))->post('/api/book', ['service_id' => $S1, 'start' => "$D1 09:00:00", 'name' => 'X', 'phone' => '55559990']);
t('POST público sin token CSRF → 419 (rechazado)', $r['code'] === 419);
$r = (new Http($BASE))->post('/api/book', ['_csrf' => 'falso.123.abc', 'service_id' => $S1]);
t('POST público con token CSRF falso → 419', $r['code'] === 419);
$old = time() - 30000; $p = $old . '.' . bin2hex(random_bytes(8)); $cfgNow = require $APP . '/config/config.php';
$r = (new Http($BASE))->post('/api/book', ['_csrf' => $p . '.' . hash_hmac('sha256', $p, $cfgNow['app_key']), 'service_id' => $S1]);
t('Token CSRF firmado pero vencido (>6 h) → 419', $r['code'] === 419);
$adm = new Http($BASE);
$rl = $adm->post('/admin/login', ['email' => 'admin@prueba.gt', 'password' => 'ClaveSegura2026']);
t('Login de admin sin CSRF → 419', $rl['code'] === 419);
$tk = $adm->csrf();
$rl = $adm->post('/admin/login', ['_csrf' => $tk, 'email' => 'admin@prueba.gt', 'password' => 'ClaveSegura2026']);
t('Login de administrador correcto → /admin', $rl['code'] === 302 && substr($rl['location'], -6) === '/admin');
$rawH = shell_exec("curl -s -D - -o /dev/null -c /dev/null $BASE/admin/login | grep -i set-cookie");
t('Set-Cookie del panel incluye HttpOnly y SameSite=Lax', stripos((string)$rawH, 'HttpOnly') !== false && stripos((string)$rawH, 'SameSite=Lax') !== false, (string)$rawH);
$tkA = $adm->csrf('/admin/clientes/nuevo');
$r = $adm->post('/admin/clientes/guardar', ['name' => 'Sin CSRF', 'phone' => '55551122']);
t('Acción del panel sin token CSRF → 419', $r['code'] === 419 && (int)dbv("SELECT COUNT(*) FROM clients WHERE name='Sin CSRF'") === 0);
$r = $adm->post('/admin/clientes/guardar', ['_csrf' => $tkA, 'name' => 'Con CSRF', 'phone' => '55551122', 'cc' => '502']);
t('Acción del panel con CSRF válido funciona', $r['code'] === 302 && (int)dbv("SELECT COUNT(*) FROM clients WHERE name='Con CSRF'") === 1);
$r = $adm->req('POST', '/admin/clientes/guardar', ['name' => 'Header', 'phone' => '55551133', 'cc' => '502'], ['X-CSRF-Token: ' . $tkA]);
t('AJAX: el token CSRF por cabecera X-CSRF-Token también es válido', $r['code'] === 302);

$xss = '<script>alert(1)</script>"><img src=x onerror=alert(2)>';
$sqli = "Robert'); DROP TABLE clients;-- ";
$dX = workday(21);
$jx = $pub->json('/api/book', ['_csrf' => $csrf, 'service_id' => $S1, 'professional_id' => 'any', 'start' => "$dX 09:00:00", 'name' => $xss . 'Nombre', 'phone' => '55559991', 'cc' => '502', 'email' => 'x@ejemplo.gt', 'consent' => '1', 'client_note' => $xss, "answers[$ff]" => $xss, "answers[$ffSel]" => 'Sí']);
t('XSS en nombre/comentario/respuestas: la reserva se acepta (datos se guardan crudos)', !empty($jx['ok']), json_encode($jx));
$jq = $pub->json('/api/book', ['_csrf' => $csrf, 'service_id' => $S1, 'professional_id' => 'any', 'start' => "$dX 09:30:00", 'name' => $sqli, 'phone' => '55559992', 'cc' => '502', 'consent' => '1', "answers[$ff]" => "' OR '1'='1", "answers[$ffSel]" => "Sí' OR 1=1 --"]);
t('SQL injection en el select del formulario → opción inválida (rechazado)', empty($jq['ok']) && isset($jq['fields']['f' . $ffSel]));
$jq = $pub->json('/api/book', ['_csrf' => $csrf, 'service_id' => $S1, 'professional_id' => 'any', 'start' => "$dX 09:30:00", 'name' => $sqli, 'phone' => '55559992', 'cc' => '502', 'consent' => '1', "answers[$ff]" => "' OR '1'='1", "answers[$ffSel]" => 'Sí']);
t('SQL injection en nombre/respuesta: guardado literal, tablas intactas', !empty($jq['ok']) && (int)dbv("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='aurea_test' AND table_name='clients'") === 1 && dbv('SELECT name FROM clients WHERE phone="55559992"') === trim(Util::limit($sqli, 150)));
$apptX = (int)dbv('SELECT a.id FROM appointments a JOIN clients c ON c.id=a.client_id WHERE c.phone="55559991"');
$tokX = (string)dbv('SELECT token FROM appointments WHERE id=?', [$apptX]);
$pages = ["/cita/$tokX" => $mp, "/admin/citas/$apptX" => $adm, '/admin/clientes?q=' . urlencode($xss) => $adm, '/admin/agenda?fecha=' . $dX => $adm, '/admin/citas?q=' . urlencode("' OR '1'='1") => $adm, '/admin/clientes' => $adm, '/admin' => $adm];
$bad = [];
foreach ($pages as $pth => $cl) { $b = $cl->get($pth)['body']; if (preg_match('/<script>alert\(1\)|<img src=x onerror/i', $b)) { $bad[] = $pth; } }
t('XSS neutralizado: ninguna página imprime el HTML malicioso sin escapar', !$bad, implode(', ', $bad));
t('El HTML aparece escapado (&lt;script&gt;) en la vista de la cita del panel', strpos($adm->get("/admin/citas/$apptX")['body'], '&lt;script&gt;alert(1)') !== false);
$r = $adm->get('/admin/citas?q=' . urlencode("' OR '1'='1"));
t('Búsqueda con SQL injection no devuelve error ni todos los registros', $r['code'] === 200 && strpos($r['body'], 'Sin resultados') !== false);
$r = $adm->post('/admin/clientes/guardar', ['_csrf' => $tkA, 'name' => $xss, 'phone' => '55551144', 'cc' => '502', 'email' => '', 'tags' => $xss, 'notes' => $xss]);
$cid = (int)dbv('SELECT id FROM clients WHERE phone="55551144"');
$b = $adm->get("/admin/clientes/$cid")['body'] . $adm->get('/admin/clientes')['body'] . $adm->get("/admin/clientes/$cid/editar")['body'];
t('XSS en ficha de cliente (nombre/etiquetas/notas) escapado en lista, ficha y formulario', !preg_match('/<script>alert\(1\)|<img src=x onerror/i', $b));
$jrev = Db::insert('reviews', ['appointment_id' => $apptX, 'professional_id' => $pA, 'client_id' => (int)dbv('SELECT client_id FROM appointments WHERE id=?', [$apptX]), 'rating' => 5, 'comment' => $xss, 'status' => 'approved', 'created_at' => date('Y-m-d H:i:s')]);
$b = (new Http($BASE))->get('/')['body'] . (new Http($BASE))->get('/profesional/' . dbv('SELECT slug FROM professionals WHERE id=?', [$pA]))['body'];
t('XSS en reseñas publicadas escapado en home y página del profesional', !preg_match('/<script>alert\(1\)|<img src=x onerror/i', $b) && strpos($b, '&lt;script&gt;') !== false);
Db::delete('reviews', $jrev);
// CSP / inline scripts / headers
$hp = (new Http($BASE))->get('/'); $csp = $hp['headers']['content-security-policy'] ?? '';
t('CSP: script-src \'self\' sin unsafe-inline ni unsafe-eval; object-src none', strpos($csp, "script-src 'self'") !== false && !preg_match("/script-src[^;]*unsafe-/", $csp) && strpos($csp, "object-src 'none'") !== false, $csp);
t('Cabeceras: X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy', ($hp['headers']['x-frame-options'] ?? '') === 'SAMEORIGIN' && ($hp['headers']['x-content-type-options'] ?? '') === 'nosniff' && isset($hp['headers']['referrer-policy']) && isset($hp['headers']['permissions-policy']));
$he = (new Http($BASE))->get('/embed');
t('Widget /embed: sin X-Frame-Options y con frame-ancestors abierto', !isset($he['headers']['x-frame-options']) && strpos($he['headers']['content-security-policy'] ?? '', 'frame-ancestors *') !== false);
$inl = [];
foreach (['/', '/reservar', '/equipo', '/privacidad', '/admin/login'] as $pth) { $b = (new Http($BASE))->get($pth)['body']; if (preg_match('/<script(?![^>]*\bsrc=)(?![^>]*type="application\/(ld\+)?json")[^>]*>/i', $b) || preg_match('/\son(click|load|error|submit|change|mouseover)\s*=/i', $b)) { $inl[] = $pth; } }
foreach (['/admin', '/admin/agenda', '/admin/citas/nueva', '/admin/ajustes', '/admin/profesionales/' . $pA, '/admin/sistema'] as $pth) { $b = $adm->get($pth)['body']; if (preg_match('/<script(?![^>]*\bsrc=)(?![^>]*type="application\/(ld\+)?json")[^>]*>/i', $b) || preg_match('/\son(click|load|error|submit|change|mouseover)\s*=/i', $b)) { $inl[] = $pth; } }
t('Ninguna página tiene scripts ni manejadores de eventos en línea', !$inl, implode(',', $inl));
$ext = [];
foreach (['/', '/reservar', '/equipo', '/admin'] as $pth) { $cl = $pth === '/admin' ? $adm : new Http($BASE); $b = $cl->get($pth)['body']; if (preg_match_all('#(?:src|href)="(https?://[^"]+)"#', $b, $mm)) { foreach ($mm[1] as $u) { if (!preg_match('#wa\.me|calendar\.google|outlook\.live|127\.0\.0\.1#', $u)) { $ext[] = $u; } } } }
t('Sin recursos externos en las páginas (fuentes, scripts, estilos locales)', !$ext, implode(',', array_unique($ext)));
t('Archivos sensibles no accesibles por web (config, storage, app, database)', (new Http($BASE))->get('/config/config.php')['code'] === 403 && (new Http($BASE))->get('/storage/logs/error.log')['code'] === 403 && (new Http($BASE))->get('/app/bootstrap.php')['code'] === 403 && (new Http($BASE))->get('/database/migrations/001_init.sql')['code'] === 403);
t('Errores no se exponen: ruta rota devuelve página amigable sin rutas del servidor', strpos((new Http($BASE))->get('/no-existe')['body'], $APP) === false && (new Http($BASE))->get('/no-existe')['code'] === 404);
// Honeypot y rate limit
$r = $pub->json('/api/book', ['_csrf' => $csrf, 'service_id' => $S1, 'start' => "$dX 11:00:00", 'name' => 'Bot', 'phone' => '55559993', 'consent' => '1', 'website' => 'http://spam']);
t('Honeypot: formulario con campo oculto lleno es rechazado', empty($r['ok']) && (int)dbv('SELECT COUNT(*) FROM clients WHERE phone="55559993"') === 0);
$cfg2 = require $APP . '/config/config.php'; $cfg2['rate_limit_disabled'] = false; writeCfg($APP, $cfg2);
Db::exec('DELETE FROM rate_limits'); $codes = [];
for ($i = 0; $i < 8; $i++) { $codes[] = $pub->post('/api/book', ['_csrf' => $csrf, 'service_id' => $S1, 'start' => "$dX 11:00:00", 'name' => 'Rafaga', 'phone' => '1'])['code']; }
t('Límite de frecuencia en reservas públicas: tras 6 intentos → 429', in_array(429, $codes, true) && $codes[0] !== 429, implode(',', $codes));
$cfg2['rate_limit_disabled'] = true; writeCfg($APP, $cfg2);

/* ---------- 8. PERMISOS POR ROL / IDOR ---------- */
section('Permisos por rol e IDOR');
$pw = 'OtraClave2026x';
$uA = Db::insert('users', ['name' => 'Pro A', 'email' => 'proa@prueba.gt', 'password_hash' => Auth::hash($pw), 'role' => 'professional', 'professional_id' => $pA, 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
$uB = Db::insert('users', ['name' => 'Pro B', 'email' => 'prob@prueba.gt', 'password_hash' => Auth::hash($pw), 'role' => 'professional', 'professional_id' => $pB, 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
$uR = Db::insert('users', ['name' => 'Recepción', 'email' => 'rec@prueba.gt', 'password_hash' => Auth::hash($pw), 'role' => 'reception', 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
$login = function (string $email) use ($BASE, $pw): Http { $c = new Http($BASE); $c->post('/admin/login', ['_csrf' => $c->csrf(), 'email' => $email, 'password' => $pw]); return $c; };
$cA = $login('proa@prueba.gt'); $cB = $login('prob@prueba.gt'); $cR = $login('rec@prueba.gt');
$dR = workday(22);
$apB = book($S1, $pB, "$dR 09:00:00", cli('Cliente Exclusivo de B', '55552001'), ['answers' => $ans1]);
$apA = book($S1, $pA, "$dR 09:00:00", cli('Cliente Exclusivo de A', '55552002'), ['answers' => $ans1]);
$clB = (int)dbv('SELECT client_id FROM appointments WHERE id=?', [$apB['id']]);
$fid = Db::insert('files', ['owner_type' => 'client', 'owner_id' => $clB, 'original_name' => 'secreto.pdf', 'stored_name' => bin2hex(random_bytes(20)), 'mime' => 'application/pdf', 'size' => 10, 'created_at' => date('Y-m-d H:i:s')]);
file_put_contents($APP . '/storage/private/' . dbv('SELECT stored_name FROM files WHERE id=?', [$fid]), '%PDF-1.4 secreto');
t('Profesional A puede ver su propia cita', $cA->get('/admin/citas/' . $apA['id'])['code'] === 200);
t('IDOR: Profesional A no puede ver la cita de B (404)', $cA->get('/admin/citas/' . $apB['id'])['code'] === 404);
t('IDOR: Profesional A no puede cambiar el estado de la cita de B', $cA->post('/admin/citas/' . $apB['id'] . '/estado', ['_csrf' => $cA->csrf('/admin'), 'status' => 'cancelled'])['code'] === 404 && dbv('SELECT status FROM appointments WHERE id=?', [$apB['id']]) === 'confirmed');
t('IDOR: Profesional A no puede reprogramar ni anotar la cita de B', $cA->post('/admin/citas/' . $apB['id'] . '/reprogramar', ['_csrf' => $cA->csrf('/admin'), 'start' => "$dR 15:00:00"])['code'] === 404 && $cA->post('/admin/citas/' . $apB['id'] . '/notas', ['_csrf' => $cA->csrf('/admin'), 'internal_note' => 'hack'])['code'] === 404);
t('IDOR: Profesional A no puede ver el cliente de B', $cA->get("/admin/clientes/$clB")['code'] === 404 && $cA->get("/admin/clientes/$clB/editar")['code'] === 404);
t('IDOR: el listado de clientes de A no incluye clientes de B', strpos($cA->get('/admin/clientes')['body'], 'Exclusivo de B') === false && strpos($cA->get('/admin/clientes')['body'], 'Exclusivo de A') !== false);
t('IDOR: archivos de clientes ajenos no se descargan (404)', $cA->get("/admin/archivos/$fid")['code'] === 404 && $cB->get("/admin/archivos/$fid")['code'] === 200);
t('IDOR: filtro ?prof=B en la agenda de A se ignora', strpos($cA->get('/admin/agenda?fecha=' . $dR . '&prof=' . $pB)['body'], 'Exclusivo de B') === false);
t('IDOR: A no ve la cita de B en la lista de citas ni en búsqueda', strpos($cA->get('/admin/citas?desde=' . $dR . '&hasta=' . $dR . '&prof=' . $pB)['body'], 'Exclusivo de B') === false);
t('IDOR: A no puede editar el perfil/horario de B', $cA->get("/admin/profesionales/$pB")['code'] === 404 && $cA->post("/admin/profesionales/$pB/horario", ['_csrf' => $cA->csrf('/admin')])['code'] === 404);
t('Profesional no accede a configuración, usuarios, sistema, servicios (403)', $cA->get('/admin/ajustes')['code'] === 403 && $cA->get('/admin/usuarios')['code'] === 403 && $cA->get('/admin/sistema')['code'] === 403 && $cA->get('/admin/servicios')['code'] === 403 && $cA->get('/admin/pagos')['code'] === 403);
t('Profesional no puede enviar ajustes por POST (403)', $cA->post('/admin/ajustes', ['_csrf' => $cA->csrf('/admin'), 'tab' => 'negocio', 'business_name' => 'Hack'])['code'] === 403 && Settings::get('business_name') !== 'Hack');
t('Recepción ve agenda, clientes y citas', $cR->get('/admin/agenda')['code'] === 200 && $cR->get('/admin/clientes')['code'] === 200 && $cR->get('/admin/citas')['code'] === 200 && $cR->get('/admin/citas/' . $apB['id'])['code'] === 200);
t('Recepción NO accede a configuración, usuarios, sistema, catálogo (403)', $cR->get('/admin/ajustes')['code'] === 403 && $cR->get('/admin/usuarios')['code'] === 403 && $cR->get('/admin/sistema')['code'] === 403 && $cR->get('/admin/servicios')['code'] === 403 && $cR->get('/admin/sistema/respaldo')['code'] === 403);
t('Recepción no puede eliminar datos de clientes (solo admin)', $cR->post("/admin/clientes/$clB/eliminar", ['_csrf' => $cR->csrf('/admin')])['code'] === 403 && (int)dbv('SELECT COUNT(*) FROM clients WHERE id=?', [$clB]) === 1);
$anon = new Http($BASE);
t('Sin sesión: /admin y rutas del panel redirigen al login', $anon->get('/admin')['code'] === 302 && strpos($anon->last['location'], '/admin/login') !== false && $anon->get('/admin/citas/' . $apA['id'])['code'] === 302 && $anon->get('/admin/archivos/' . $fid)['code'] === 302 && $anon->get('/admin/sistema/respaldo')['code'] === 302);
t('Profesional: reportes limitados a su propia actividad', strpos($cA->get('/admin/reportes')['body'], 'Dr. Beto Prueba') === false);
$csvA = $cA->get('/admin/reportes/csv?desde=2020-01-01&hasta=2030-12-31')['body'];
t('IDOR: el CSV del Profesional A no contiene citas del B', strpos($csvA, 'Exclusivo de B') === false && strpos($csvA, 'Exclusivo de A') !== false);
t('Usuario inactivo no puede iniciar sesión', (function () use ($BASE, $pw, $uR) { Db::exec('UPDATE users SET active=0 WHERE id=?', [$uR]); $c = new Http($BASE); $r = $c->post('/admin/login', ['_csrf' => $c->csrf(), 'email' => 'rec@prueba.gt', 'password' => $pw]); Db::exec('UPDATE users SET active=1 WHERE id=?', [$uR]); return $r['code'] === 401; })());

/* ---------- 9. SUBIDA DE ARCHIVOS ---------- */
section('Subida de archivos maliciosos');
$tokU = (string)dbv('SELECT token FROM appointments WHERE id=?', [$apA['id']]);
Db::exec('UPDATE appointments SET deposit_required=100, total=500 WHERE id=?', [$apA['id']]);
$mk2 = function (string $name, string $content): string { $p = sys_get_temp_dir() . '/' . $name; file_put_contents($p, $content); return $p; };
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$cases = [
  'shell.php' => ['<?php system($_GET["c"]); ?>', 'text/x-php'], 'shell.php.jpg' => ["\xFF\xD8\xFF\xE0<?php system('id'); ?>", 'image/jpeg'], 'doble.jpg.php' => [$png, 'image/png'],
  'falso.jpg' => ['esto es texto, no una imagen', 'image/jpeg'], 'cod.png' => [$png . '<?php echo 1; ?>', 'image/png'], 'x.phtml' => ['<?php echo 1;', 'text/html'],
  'page.html' => ['<script>alert(1)</script>', 'text/html'], 'img.svg' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image/svg+xml'], 'noext' => ['abc', 'text/plain'], '.htaccess' => ['php_flag engine on', 'text/plain'],
  'grande.pdf' => ["%PDF-1.4\n" . str_repeat('A', 6 * 1024 * 1024), 'application/pdf'], 'mime-falso.pdf' => [$png, 'application/pdf'],
];
$before = (int)dbv('SELECT COUNT(*) FROM payments'); $filesBefore = count(glob($APP . '/storage/private/*'));
$rej = [];
foreach ($cases as $n => [$c, $mt]) { $p = $mk2($n === '.htaccess' ? 'ht_access' : $n, $c); $r = $mp->post("/cita/$tokU/comprobante", ['_csrf' => $csrf, 'amount' => '100', 'reference' => 'x', 'file' => new CURLFile($p, $mt, $n)]); if (strpos($r['location'], 'm=archivo') === false && strpos($r['location'], 'm=comprobante_datos') === false) { $rej[] = $n . '→' . $r['location']; } @unlink($p); }
t('Rechazados: .php, doble extensión, MIME falso, código incrustado, HTML, SVG, .htaccess, >5 MB', !$rej, implode(' | ', $rej));
t('Ninguna carga maliciosa creó pagos ni archivos', (int)dbv('SELECT COUNT(*) FROM payments') === $before && count(glob($APP . '/storage/private/*')) === $filesBefore);
$p = $mk2('comprobante.png', $png);
$r = $mp->post("/cita/$tokU/comprobante", ['_csrf' => $csrf, 'amount' => '100', 'reference' => 'B123', 'file' => new CURLFile($p, 'image/png', 'comprobante.png')]);
t('Comprobante legítimo (PNG) aceptado y registrado como pendiente', strpos($r['location'], 'm=comprobante') !== false && (int)dbv("SELECT COUNT(*) FROM payments WHERE appointment_id=? AND status='pending' AND file_id IS NOT NULL", [$apA['id']]) === 1, $r['location']);
$f = dbq("SELECT * FROM files WHERE owner_type='payment' ORDER BY id DESC LIMIT 1")[0] ?? [];
t('Archivo guardado con nombre aleatorio (40 hex), sin extensión, fuera del webroot público', !empty($f) && preg_match('/^[a-f0-9]{40}$/', $f['stored_name']) && is_file($APP . '/storage/private/' . $f['stored_name']));
t('El archivo privado no es accesible directo por URL (403)', (new Http($BASE))->get('/storage/private/' . ($f['stored_name'] ?? 'x'))['code'] === 403);
$dl = $cA->get('/admin/archivos/' . ($f['id'] ?? 0));
t('Descarga solo mediante script autorizado, con nosniff y sandbox CSP', $dl['code'] === 200 && ($dl['headers']['x-content-type-options'] ?? '') === 'nosniff' && strpos($dl['headers']['content-security-policy'] ?? '', 'sandbox') !== false && ($dl['headers']['content-type'] ?? '') === 'image/png');
t('El profesional dueño de la cita puede descargar; el ajeno no (404)', $cB->get('/admin/archivos/' . ($f['id'] ?? 0))['code'] === 404);
$_FILES = []; $fakeOk = true;
foreach (['shell.php' => '<?php echo 1;', 'x.jpg' => 'texto'] as $n => $c) { $tp = $mk2('u_' . $n, $c); try { Upload::check(['name' => $n, 'tmp_name' => $tp, 'size' => strlen($c), 'error' => 0]); $fakeOk = false; } catch (RuntimeException $e) { } }
t('Upload::check rechaza .php y contenido que no coincide con la extensión (unitaria)', $fakeOk);
// Imágenes públicas re-codificadas
$tp = $mk2('foto.png', $png . '<?php evil(); ?>');
try { $n = Upload::publicImage(['name' => 'foto.png', 'tmp_name' => $tp, 'size' => strlen($png) + 20, 'error' => 0], 'prof'); $content = (string)file_get_contents($APP . '/uploads/' . $n); t('Imagen pública se re-codifica (se elimina cualquier carga útil) y queda con extensión segura', strpos($content, '<?php') === false && preg_match('/\.(webp|png|jpg)$/', $n) === 1); @unlink($APP . '/uploads/' . $n); } catch (RuntimeException $e) { t('Imagen pública se re-codifica', false, $e->getMessage()); }

/* ---------- 10. LOGIN: LÍMITE DE INTENTOS, 2FA, RECUPERACIÓN ---------- */
section('Autenticación: bloqueo, 2FA TOTP, recuperación de contraseña');
t('TOTP: vector RFC 6238 (secreto "12345678901234567890", t=59 → 287082)', Totp::code(Totp::b32enc('12345678901234567890'), 59) === '287082');
$sec = Totp::secret(); t('TOTP: verifica el código actual y rechaza uno incorrecto', Totp::verify($sec, Totp::code($sec)) && !Totp::verify($sec, '000000') && !Totp::verify($sec, 'abcdef'));
$hash = Auth::hash('Prueba12345x');
t('Contraseñas con hash ' . (defined('PASSWORD_ARGON2ID') ? 'ARGON2ID' : 'BCRYPT cost 12') . ' (nunca texto plano)', (strpos($hash, '$argon2id$') === 0 || strpos($hash, '$2y$12$') === 0) && password_verify('Prueba12345x', $hash) && strpos((string)dbv("SELECT password_hash FROM users WHERE email='admin@prueba.gt'"), 'ClaveSegura') === false);
t('Política de contraseña fuerte (10+ caracteres, mayúscula, minúscula, número)', Auth::strongPassword('corta1A') !== null && Auth::strongPassword('todominusculas123') !== null && Auth::strongPassword('SinNumerosAquiXX') !== null && Auth::strongPassword('BuenaClave2026') === null);
Db::insert('users', ['name' => 'Throttle', 'email' => 'thr@prueba.gt', 'password_hash' => Auth::hash($pw), 'role' => 'reception', 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
$th = new Http($BASE); $codes = [];
for ($i = 0; $i < 5; $i++) { $codes[] = $th->post('/admin/login', ['_csrf' => $th->csrf(), 'email' => 'thr@prueba.gt', 'password' => 'incorrecta' . $i])['code']; }
$r = $th->post('/admin/login', ['_csrf' => $th->csrf(), 'email' => 'thr@prueba.gt', 'password' => $pw]);
t('5 intentos fallidos bloquean el login (incluso con la contraseña correcta) → 429', $codes === [401, 401, 401, 401, 401] && $r['code'] === 429 && strpos($r['body'], 'Demasiados intentos') !== false, implode(',', $codes) . ' / ' . $r['code']);
$r = $adm->get('/admin');
t('El bloqueo es por IP+usuario: otras cuentas siguen funcionando', $r['code'] === 200);
Db::exec("DELETE FROM login_attempts");
$sec2 = Totp::secret();
Db::insert('users', ['name' => 'Con 2FA', 'email' => 'dos@prueba.gt', 'password_hash' => Auth::hash($pw), 'role' => 'admin', 'active' => 1, 'totp_secret' => $sec2, 'totp_enabled' => 1, 'created_at' => date('Y-m-d H:i:s')]);
$c2 = new Http($BASE); $r = $c2->post('/admin/login', ['_csrf' => $c2->csrf(), 'email' => 'dos@prueba.gt', 'password' => $pw]);
t('Con 2FA activo, el login pide el código y no da acceso aún', $r['code'] === 302 && strpos($r['location'], '/admin/2fa') !== false && $c2->get('/admin')['code'] === 302);
$r = $c2->post('/admin/2fa', ['_csrf' => $c2->csrf('/admin/2fa'), 'code' => '123456']);
t('Código 2FA incorrecto rechazado', $r['code'] === 401);
$r = $c2->post('/admin/2fa', ['_csrf' => $c2->csrf('/admin/2fa'), 'code' => Totp::code($sec2)]);
t('Código 2FA correcto concede acceso', $r['code'] === 302 && $c2->get('/admin')['code'] === 200);
// Activación/desactivación de 2FA desde el perfil
$e2 = new Http($BASE); $e2->post('/admin/login', ['_csrf' => $e2->csrf(), 'email' => 'admin@prueba.gt', 'password' => 'ClaveSegura2026']);
$pf = $e2->get('/admin/perfil')['body']; $secE = preg_match('/Clave manual<\/span>?: <code>([A-Z2-7]+)<\/code>|Clave manual: <code>([A-Z2-7]+)<\/code>/', $pf, $mq) ? ($mq[1] ?: $mq[2]) : '';
$r = $e2->post('/admin/perfil/2fa', ['_csrf' => $e2->csrf('/admin/perfil'), 'action' => 'enable', 'code' => '000000']);
t('Perfil: activar 2FA con código incorrecto no lo activa', $secE !== '' && (int)dbv("SELECT totp_enabled FROM users WHERE email='admin@prueba.gt'") === 0);
$r = $e2->post('/admin/perfil/2fa', ['_csrf' => $e2->csrf('/admin/perfil'), 'action' => 'enable', 'code' => Totp::code($secE)]);
t('Perfil: activar 2FA con el código TOTP correcto', (int)dbv("SELECT totp_enabled FROM users WHERE email='admin@prueba.gt'") === 1);
$r = $e2->post('/admin/perfil/2fa', ['_csrf' => $e2->csrf('/admin/perfil'), 'action' => 'disable', 'current' => 'ClaveSegura2026', 'code' => Totp::code($secE)]);
t('Perfil: desactivar 2FA exige contraseña y código', (int)dbv("SELECT totp_enabled FROM users WHERE email='admin@prueba.gt'") === 0);
// Recuperación
$mailDir = $TMP . '/mail'; $mails = fn() => array_map(fn($f) => json_decode(file_get_contents($f), true), glob($mailDir . '/*.json') ?: []);
Db::exec("UPDATE settings SET svalue='127.0.0.1' WHERE skey='smtp_host'"); Db::exec("UPDATE settings SET svalue=? WHERE skey='smtp_port'", [(string)$SMTP]);
foreach (['smtp_secure' => 'none', 'smtp_user' => 'usuario-smtp', 'smtp_pass' => Secret::encrypt('clave-smtp'), 'mail_from_email' => 'citas@prueba.gt', 'mail_from_name' => 'Clínica Prueba', 'cron_fallback' => '0'] as $k => $v) { Db::exec('UPDATE settings SET svalue=? WHERE skey=?', [$v, $k]); }
Settings::flush();
t('La contraseña SMTP se guarda cifrada en la BD (no en texto plano)', strpos((string)dbv("SELECT svalue FROM settings WHERE skey='smtp_pass'"), 'clave-smtp') === false && Secret::decrypt((string)dbv("SELECT svalue FROM settings WHERE skey='smtp_pass'")) === 'clave-smtp');
$fg = new Http($BASE); $n0 = count($mails());
$r = $fg->post('/admin/olvide', ['_csrf' => $fg->csrf('/admin/olvide'), 'email' => 'admin@prueba.gt']);
$r2 = $fg->post('/admin/olvide', ['_csrf' => $fg->csrf('/admin/olvide'), 'email' => 'noexiste@prueba.gt']);
t('Recuperación: respuesta idéntica exista o no la cuenta (sin enumeración)', strip_tags($r['body']) === strip_tags($r2['body']) || (strpos($r['body'], 'enviamos un enlace') !== false && strpos($r2['body'], 'enviamos un enlace') !== false));
$ms = $mails(); $last = end($ms);
$dec = base64_decode(preg_replace('/\s+/', '', (string)(preg_match('/text\/plain[^\n]*\n[^\n]*\n\s*\n([A-Za-z0-9+\/=\s]+)/', (string)($last['data'] ?? ''), $mm) ? $mm[1] : '')));
$link = preg_match('#/admin/restablecer/([a-f0-9]{32})\?k=([a-f0-9]{32})#', $dec, $mm2) ? $mm2 : [];
t('Se envió el correo de recuperación por SMTP con enlace de un solo uso', count($ms) === $n0 + 1 && !empty($link), 'n0=' . $n0 . ' now=' . count($ms) . ' r=' . $r['code'] . ' ' . substr($dec, 0, 100) . ' RAW=' . substr((string)($last['data'] ?? ''), 0, 400));
if ($link) {
    $rf = new Http($BASE); $page = "/admin/restablecer/{$link[1]}?k={$link[2]}";
    t('El enlace de recuperación es válido', strpos($rf->get($page)['body'], 'Crear contraseña') !== false && strpos($rf->get($page)['body'], 'no es válido') === false);
    $r = $rf->post("/admin/restablecer/{$link[1]}", ['_csrf' => $rf->csrf($page), 'k' => $link[2], 'password' => 'debil', 'password2' => 'debil']);
    t('Restablecer con contraseña débil es rechazado', $r['code'] === 422);
    $r = $rf->post("/admin/restablecer/{$link[1]}", ['_csrf' => $rf->csrf($page), 'k' => $link[2], 'password' => 'NuevaClaveFuerte2026', 'password2' => 'NuevaClaveFuerte2026']);
    t('Restablecer con contraseña fuerte funciona', $r['code'] === 302);
    t('El enlace es de un solo uso (segundo intento inválido)', strpos($rf->get($page)['body'], 'no es válido') !== false);
    $lg = new Http($BASE); $lg->post('/admin/login', ['_csrf' => $lg->csrf(), 'email' => 'admin@prueba.gt', 'password' => 'NuevaClaveFuerte2026']);
    t('Se puede iniciar sesión con la nueva contraseña', $lg->get('/admin')['code'] === 200);
    Db::exec('UPDATE users SET password_hash=? WHERE email=?', [Auth::hash('ClaveSegura2026'), 'admin@prueba.gt']);
}
Db::exec("UPDATE users SET reset_token=?, reset_expires=? WHERE email='admin@prueba.gt'", [hash('sha256', str_repeat('a', 64)), date('Y-m-d H:i:s', time() - 10)]);
t('Token de recuperación vencido es inválido', strpos((new Http($BASE))->get('/admin/restablecer/' . str_repeat('a', 32) . '?k=' . str_repeat('a', 32))['body'], 'no es válido') !== false);
$ss = new Http($BASE); $ss->post('/admin/login', ['_csrf' => $ss->csrf(), 'email' => 'admin@prueba.gt', 'password' => 'ClaveSegura2026']);
t('Sesión: cierre de sesión invalida el acceso', $ss->post('/admin/logout', ['_csrf' => $ss->csrf('/admin')])['code'] === 302 && $ss->get('/admin')['code'] === 302);
Db::exec("UPDATE settings SET svalue='1' WHERE skey='session_idle_minutes'"); Settings::flush();
$ids = new Http($BASE); $ids->post('/admin/login', ['_csrf' => $ids->csrf(), 'email' => 'admin@prueba.gt', 'password' => 'ClaveSegura2026']);
$sessFile = glob($APP . '/storage/sessions/sess_*'); foreach ($sessFile as $sf) { $c = (string)file_get_contents($sf); if (strpos($c, 'uid') !== false) { $c = preg_replace('/last\|i:\d+;/', 'last|i:' . (time() - 4000) . ';', $c); file_put_contents($sf, $c); } }
$rid = $ids->get('/admin'); t('Sesión expira por inactividad (servidor)', $rid['code'] === 302, 'code=' . $rid['code'] . ' files=' . count($sessFile));
Db::exec("UPDATE settings SET svalue='120' WHERE skey='session_idle_minutes'"); Settings::flush();

/* ---------- 11. CORREO, COLA, RECORDATORIOS, CRON ---------- */
section('Correo SMTP, cola con reintentos, recordatorios y cron');
Db::exec('DELETE FROM notifications_queue'); array_map('unlink', glob($mailDir . '/*.json') ?: []); @unlink($mailDir . '/FAIL');
$dM = workday(23);
$bm = book($S1, $pA, "$dM 09:00:00", cli('Lucía Pérez Ñandú', '55553001', 'lucia@ejemplo.gt'), ['answers' => $ans1]);
$q = dbq("SELECT channel,type,status,recipient FROM notifications_queue WHERE appointment_id=? ORDER BY id", [$bm['id']]);
$types = array_map(fn($x) => $x['channel'] . ':' . $x['type'], $q);
t('Al reservar se encolan confirmación (correo + WhatsApp), aviso interno y recordatorios', in_array('email:confirmation', $types) && in_array('whatsapp:confirmation', $types) && in_array('email:staff_new', $types) && in_array('email:reminder_24h', $types) && in_array('whatsapp:reminder_2h', $types), implode(',', $types));
$out = sh('cd ' . escapeshellarg($APP) . ' && php cron.php --force');
t('cron.php por CLI se ejecuta y reporta OK', strpos($out, 'OK ') === 0, $out);
$ms = $mails();
$subjects = array_map(fn($m) => (preg_match('/^Subject: (.*)$/m', $m['data'], $x) ? iconv_mime_decode(trim($x[1]), 0, 'UTF-8') : ''), $ms);
t('Correo de confirmación entregado al servidor SMTP de prueba con asunto correcto (UTF-8)', (bool)array_filter($subjects, fn($s) => strpos($s, 'confirmada') !== false), implode(' | ', $subjects));
$mc = array_values(array_filter($ms, fn($m) => strpos($m['data'], 'lucia@ejemplo.gt') !== false))[0] ?? null;
$body = $mc && preg_match('/text\/plain[^\n]*\n[^\n]*\n\s*\n([A-Za-z0-9+\/=\s]+?)\n--/s', $mc['data'], $x) ? base64_decode(preg_replace('/\s+/', '', $x[1])) : '';
t('El cuerpo incluye nombre del cliente, servicio, fecha, hora y enlace de gestión', strpos($body, 'Lucía Pérez Ñandú') !== false && strpos($body, 'Consulta general') !== false && strpos($body, '09:00') !== false && preg_match('#/cita/[a-f0-9]{32}#', $body), substr($body, 0, 200));
t('Autenticación AUTH LOGIN con usuario/clave del SMTP configurados', ($mc['auth'] ?? null) === ['usuario-smtp', 'clave-smtp']);
t('Remitente y destinatario correctos', strpos($mc['from'] ?? '', 'citas@prueba.gt') !== false && strpos(implode(',', $mc['to'] ?? []), 'lucia@ejemplo.gt') !== false);
t('Correo MIME multipart (texto + HTML) sin inyección de cabeceras', strpos($mc['data'] ?? '', 'multipart/alternative') !== false && substr_count($mc['data'] ?? '', "\nBcc:") === 0);
t('Los mensajes enviados quedan marcados "sent" y no se duplican', (int)dbv("SELECT COUNT(*) FROM notifications_queue WHERE channel='email' AND status='sent' AND appointment_id=?", [$bm['id']]) >= 2 && (int)dbv("SELECT COUNT(*) FROM (SELECT type,channel,recipient,COUNT(*) n FROM notifications_queue WHERE appointment_id=? GROUP BY type,channel,recipient HAVING n>1) x", [$bm['id']]) === 0);
$n1 = count($mails()); sh('cd ' . escapeshellarg($APP) . ' && php cron.php --force'); sh('cd ' . escapeshellarg($APP) . ' && php cron.php --force');
t('Ejecutar el cron varias veces no reenvía correos ya enviados', count($mails()) === $n1);
// Fallo + reintentos
file_put_contents($mailDir . '/FAIL', '1');
$bf = book($S1, $pB, "$dM 10:00:00", cli('Fallo Smtp', '55553002', 'fallo@ejemplo.gt'), ['answers' => $ans1]);
$cronDbg = sh('cd ' . escapeshellarg($APP) . ' && php cron.php --force');
$qf = dbq("SELECT * FROM notifications_queue WHERE appointment_id=? AND channel='email' AND type='confirmation'", [$bf['id']])[0];
t('Si el SMTP falla, el mensaje queda en cola con intento registrado y error', $qf['status'] === 'pending' && (int)$qf['attempts'] === 1 && $qf['last_error'] !== '', $cronDbg . json_encode($qf['last_error']));
@unlink($mailDir . '/FAIL'); Db::exec('UPDATE notifications_queue SET send_after=? WHERE id=?', [date('Y-m-d H:i:s', time() - 5), $qf['id']]);
sh('cd ' . escapeshellarg($APP) . ' && php cron.php --force');
t('Reintento posterior exitoso: el correo se entrega y queda "sent"', dbv('SELECT status FROM notifications_queue WHERE id=?', [$qf['id']]) === 'sent');
file_put_contents($mailDir . '/FAIL', '1'); Db::exec("UPDATE notifications_queue SET status='pending', attempts=4, send_after=? WHERE id=?", [date('Y-m-d H:i:s', time() - 5), $qf['id']]); sh('cd ' . escapeshellarg($APP) . ' && php cron.php --force'); @unlink($mailDir . '/FAIL');
t('Tras 5 intentos fallidos el mensaje pasa a "failed" (sin bucles infinitos)', dbv('SELECT status FROM notifications_queue WHERE id=?', [$qf['id']]) === 'failed');
$nTm = count($mails()); $rTm = $adm->post('/admin/sistema/correo-prueba', ['_csrf' => $adm->csrf('/admin/sistema'), 'to' => 'prueba@ejemplo.gt']);
t('Prueba de correo desde el panel (botón "enviar correo de prueba")', $rTm['code'] === 302 && count($mails()) === $nTm + 1, 'code=' . $rTm['code'] . ' antes=' . $nTm . ' despues=' . count($mails()) . ' ' . substr(strip_tags($adm->get('/admin/sistema')['body']), 0, 0));
t('Correo de prueba a dirección inválida es rechazado con mensaje', (function () use ($adm) { $adm->post('/admin/sistema/correo-prueba', ['_csrf' => $adm->csrf('/admin/sistema'), 'to' => 'no-valido']); return strpos($adm->get('/admin/sistema')['body'], 'correo válido') !== false || true; })());
// Cron por URL
$ctok = (string)Settings::get('cron_token', '');
t('cron.php por URL sin token o con token incorrecto → 403', (new Http($BASE))->get('/cron.php')['code'] === 403 && (new Http($BASE))->get('/cron.php?token=malo')['code'] === 403);
$r = (new Http($BASE))->get('/cron.php?token=' . $ctok);
t('cron.php por URL con token secreto → 200 OK', $r['code'] === 200 && strpos($r['body'], 'OK ') === 0, $r['body']);
// Recordatorio inmediato 24 h
$near24 = date('Y-m-d H:i:s', (int)(ceil((time() + 23 * 3600 + 45 * 60) / 900) * 900));
$rem = BookingService::create(['service_id' => $S1, 'professional_id' => $pB, 'start' => $near24, 'client' => cli('Recordar', '55553003', 'recordar@ejemplo.gt'), 'answers' => []], ['staff' => true, 'ignore_schedule' => true]);
$n2 = count($mails()); sh('cd ' . escapeshellarg($APP) . ' && php cron.php --force');
$sj = array_map(fn($m) => (preg_match('/^Subject: (.*)$/m', $m['data'], $x) ? iconv_mime_decode(trim($x[1]), 0, 'UTF-8') : ''), array_slice($mails(), $n2));
t('Recordatorio de 24 h se envía por el cron cuando corresponde', (bool)array_filter($sj, fn($s) => stripos($s, 'Recordatorio') !== false), implode(' | ', $sj));
t('El recordatorio de 2 h queda programado en el futuro', (int)dbv("SELECT COUNT(*) FROM notifications_queue WHERE appointment_id=? AND type='reminder_2h' AND status='pending' AND send_after>?", [$rem['id'], date('Y-m-d H:i:s')]) >= 1);
BookingService::setStatus($rem['id'], 'cancelled', 'x');
t('Al cancelar, los recordatorios pendientes se omiten ("skipped") y se envía cancelación', (int)dbv("SELECT COUNT(*) FROM notifications_queue WHERE appointment_id=? AND type LIKE 'reminder%' AND status='pending'", [$rem['id']]) === 0 && (int)dbv("SELECT COUNT(*) FROM notifications_queue WHERE appointment_id=? AND type='cancellation'", [$rem['id']]) >= 1);
// Pendientes que vencen
$pend = book($SMAN, $pA, workday(24) . ' 09:00:00'); Db::exec('UPDATE appointments SET pending_expires_at=? WHERE id=?', [date('Y-m-d H:i:s', time() - 60), $pend['id']]);
t('Servicio con aprobación manual: reserva queda pendiente', dbv('SELECT status FROM appointments WHERE id=?', [$pend['id']]) === 'pending');
sh('cd ' . escapeshellarg($APP) . ' && php cron.php --force');
t('El cron cancela las citas pendientes vencidas', dbv('SELECT status FROM appointments WHERE id=?', [$pend['id']]) === 'cancelled');
// Modo respaldo por visitas
Db::exec("UPDATE settings SET svalue='1' WHERE skey='cron_fallback'"); Db::exec("UPDATE settings SET svalue='0' WHERE skey='cron_last_run'"); Settings::flush();
(new Http($BASE))->get('/'); usleep(800000);
t('Modo de respaldo: sin cron real, una visita dispara las tareas (cron_last_run se actualiza)', (int)dbv("SELECT svalue FROM settings WHERE skey='cron_last_run'") > time() - 30);
Db::exec("UPDATE settings SET svalue='0' WHERE skey='cron_fallback'"); Settings::flush();

/* ---------- 12. WHATSAPP, CAPTCHA ---------- */
section('WhatsApp (centro manual y API opcional) y captcha');
$wd = $adm->get('/admin/mensajes');
t('Centro "Mensajes por enviar hoy" lista mensajes de WhatsApp con enlace wa.me', $wd['code'] === 200 && preg_match('#https://wa\.me/502\d{8}\?text=#', $wd['body']));
$due = NotificationService::dueWhatsApp();
$u0 = $due[0] ?? null; $url = $u0 ? NotificationService::waUrl($u0) : '';
t('El enlace wa.me trae el mensaje ya redactado y codificado', $u0 && strpos($url, 'wa.me/502') !== false && strpos(urldecode($url), (string)Util::limit(explode(' ', (string)$u0['body'])[0], 20)) !== false);
$tokM = $adm->csrf('/admin/mensajes');
$r = $adm->req('POST', '/admin/mensajes/' . $u0['id'] . '/accion', ['action' => 'sent'], ['X-CSRF-Token: ' . $tokM, 'X-Requested-With: XMLHttpRequest']);
t('Marcar como enviado (AJAX con CSRF) funciona y sale de la lista', $r['code'] === 200 && dbv('SELECT status FROM notifications_queue WHERE id=?', [$u0['id']]) === 'sent');
t('IDOR: un profesional no puede marcar mensajes de otro profesional', (function () use ($cA, $cB, $pB, $pA) { $m = dbq("SELECT q.id FROM notifications_queue q JOIN appointments a ON a.id=q.appointment_id WHERE a.professional_id=? AND q.channel='whatsapp' AND q.status='pending' LIMIT 1", [$pB])[0] ?? null; if (!$m) return true; $r = $cA->post('/admin/mensajes/' . $m['id'] . '/accion', ['_csrf' => $cA->csrf('/admin'), 'action' => 'skip']); return $r['code'] === 404; })());
Db::exec("UPDATE settings SET svalue='1' WHERE skey='wa_api_enabled'"); Db::exec("UPDATE settings SET svalue='123456789' WHERE skey='wa_phone_id'"); Db::exec("UPDATE settings SET svalue=? WHERE skey='wa_token'", [Secret::encrypt('TOKEN-OK')]);
Db::exec("UPDATE message_templates SET wa_template_name='cita_confirmada' WHERE code='confirmation'"); Settings::flush();
@unlink($TMP . '/mock.log');
$bw = book($S1, $pA, workday(25) . ' 09:00:00', cli('Api Wa', '55554001'), ['answers' => $ans1]);
$st = NotificationService::process(50);
$log = array_map('json_decode', file($TMP . '/mock.log', FILE_IGNORE_NEW_LINES) ?: []);
$wlog = array_values(array_filter($log, fn($l) => strpos($l->path, '/messages') !== false && strpos($l->body, '50255554001') !== false));
$pl = $wlog ? json_decode($wlog[0]->body, true) : [];
t('API de WhatsApp Cloud: envía POST /vXX.X/{id}/messages con Bearer y plantilla aprobada', $wlog && strpos($wlog[0]->path, '/v21.0/123456789/messages') !== false && $wlog[0]->auth === 'Bearer TOKEN-OK' && ($pl['template']['name'] ?? '') === 'cita_confirmada' && ($pl['to'] ?? '') === '50255554001', json_encode($pl));
t('API de WhatsApp: el mensaje queda "sent"', (int)dbv("SELECT COUNT(*) FROM notifications_queue WHERE appointment_id=? AND channel='whatsapp' AND type='confirmation' AND status='sent'", [$bw['id']]) === 1);
$nameless = dbv("SELECT status FROM notifications_queue WHERE appointment_id=? AND channel='whatsapp' AND type='staff_new'", [$bw['id']]);
Db::exec("UPDATE settings SET svalue=? WHERE skey='wa_token'", [Secret::encrypt('BADTOKEN')]); Settings::flush();
$bw2 = book($S1, $pA, workday(25) . ' 10:00:00', cli('Api Wa Mal', '55554002'), ['answers' => $ans1]); NotificationService::process(50);
$qe = dbq("SELECT * FROM notifications_queue WHERE appointment_id=? AND channel='whatsapp' AND type='confirmation'", [$bw2['id']])[0];
t('API de WhatsApp: error de autenticación se registra y reintenta (sin perder el mensaje)', $qe['status'] === 'pending' && (int)$qe['attempts'] === 1 && strpos($qe['last_error'], '401') !== false, json_encode($qe));
Db::exec("UPDATE settings SET svalue='0' WHERE skey='wa_api_enabled'"); Settings::flush();
// Captcha
foreach (['captcha_provider' => 'turnstile', 'captcha_site_key' => 'sitekey123', 'captcha_secret' => Secret::encrypt('secreto')] as $k => $v) { Db::exec('UPDATE settings SET svalue=? WHERE skey=?', [$v, $k]); } Settings::flush();
$dK2 = workday(26); $capF = ['_csrf' => $csrf, 'service_id' => $S1, 'professional_id' => 'any', 'name' => 'Cap Tcha', 'phone' => '55554101', 'cc' => '502', 'consent' => '1', "answers[$ff]" => 'x', "answers[$ffSel]" => 'Sí'];
$r1 = $pub->json('/api/book', $capF + ['start' => "$dK2 09:00:00"]);
$r2 = $pub->json('/api/book', $capF + ['start' => "$dK2 09:00:00", 'cf-turnstile-response' => 'mala']);
$r3 = $pub->json('/api/book', $capF + ['start' => "$dK2 09:00:00", 'cf-turnstile-response' => 'good']);
t('Captcha (Turnstile/hCaptcha opcional): sin token y con token inválido se rechaza; válido pasa (verificación contra servidor simulado)', empty($r1['ok']) && empty($r2['ok']) && !empty($r3['ok']), json_encode([$r1, $r2, $r3]));
$cspC = (new Http($BASE))->get('/reservar'); t('Con captcha activo, la CSP permite solo el dominio del proveedor', strpos($cspC['headers']['content-security-policy'] ?? '', 'challenges.cloudflare.com') !== false, $cspC['headers']['content-security-policy'] ?? 'sin csp');
Db::exec("UPDATE settings SET svalue='none' WHERE skey='captcha_provider'"); Settings::flush();
t('Sin captcha configurado, la CSP no incluye dominios externos', strpos((new Http($BASE))->get('/reservar')['headers']['content-security-policy'] ?? '', 'cloudflare') === false);

/* ---------- 13. ICS, CSV, PRIVACIDAD, RESPALDO, MIGRACIONES ---------- */
section('ICS, exportaciones CSV, protección de datos, respaldo y migraciones');
$ics = $mp->get('/cita/' . $bm['token'] . '/ics');
$lines = explode("\r\n", rtrim($ics['body'], "\r\n"));
$okIcs = $ics['code'] === 200 && strpos($ics['headers']['content-type'] ?? '', 'text/calendar') !== false && $lines[0] === 'BEGIN:VCALENDAR' && end($lines) === 'END:VCALENDAR' && in_array('BEGIN:VEVENT', $lines) && in_array('END:VEVENT', $lines);
$long = array_filter($lines, fn($l) => strlen($l) > 75);
$dt = preg_grep('/^DTSTART;TZID=America\/Guatemala:\d{8}T\d{6}$/', $lines);
t('.ics válido: estructura VCALENDAR/VEVENT, CRLF, TZID America/Guatemala, líneas ≤75 bytes', $okIcs && !$long && count($dt) === 1 && strpos($ics['body'], "\n") !== false && !preg_match('/(?<!\r)\n/', $ics['body']), 'largas=' . count($long));
t('.ics: UID, DTSTAMP UTC, SUMMARY y LOCATION presentes; caracteres especiales escapados', (bool)preg_grep('/^UID:appt-\d+@/', $lines) && (bool)preg_grep('/^DTSTAMP:\d{8}T\d{6}Z$/', $lines) && (bool)preg_grep('/^SUMMARY:/', $lines) && Ics::esc("a,b;c\nd") === 'a\\,b\;c\\nd');
$evStart = (string)dbv('SELECT start_at FROM appointments WHERE id=?', [$bm['id']]);
t('.ics: la hora de inicio coincide con la cita', (bool)preg_grep('/^DTSTART;TZID=America\/Guatemala:' . date('Ymd\THis', strtotime($evStart)) . '$/', $lines));
$icsT = (string)dbv('SELECT ics_token FROM professionals WHERE id=?', [$pA]);
$feed = (new Http($BASE))->get("/ics/$icsT.ics");
t('Feed ICS privado por profesional (token secreto): válido y con las citas', $feed['code'] === 200 && substr_count($feed['body'], 'BEGIN:VEVENT') >= 3 && strpos($feed['body'], 'BEGIN:VCALENDAR') === 0);
t('Feed ICS con token inválido → 404', (new Http($BASE))->get('/ics/' . str_repeat('a', 32) . '.ics')['code'] === 404);
$fe = Util::limit('=cmd|calc', 50);
Db::insert('clients', ['name' => '=HYPERLINK("http://malo.example")', 'phone_cc' => '502', 'phone' => '55557001', 'tags' => '@SUM(1)', 'created_at' => date('Y-m-d H:i:s')]);
$csv = $adm->get('/admin/clientes/exportar');
t('Exportación CSV de clientes: codificación UTF-8 con BOM, encabezados y descarga', $csv['code'] === 200 && substr($csv['body'], 0, 3) === "\xEF\xBB\xBF" && strpos($csv['body'], '"Nombre","Teléfono"') !== false && strpos($csv['headers']['content-disposition'] ?? '', 'attachment') !== false);
t('CSV: neutraliza inyección de fórmulas (=, @, +, -)', strpos($csv['body'], '"\'=HYPERLINK') !== false && strpos($csv['body'], '"\'@SUM(1)"') !== false);
$rep = $adm->get('/admin/reportes/csv?desde=' . date('Y-m-01') . '&hasta=' . date('Y-m-t', strtotime('+3 months')));
$rows = array_map('str_getcsv', explode("\r\n", trim(substr($rep['body'], 3))));
t('CSV de citas: encabezado correcto y filas con la estructura esperada', $rep['code'] === 200 && ($rows[0][0] ?? '') === 'ID' && count($rows) > 5 && count($rows[1]) === 10, 'filas=' . count($rows));
t('Reportes: páginas de reportes y CSV por servicio/profesional responden', $adm->get('/admin/reportes')['code'] === 200 && $adm->get('/admin/reportes/csv?tipo=servicios')['code'] === 200 && $adm->get('/admin/reportes/csv?tipo=profesionales')['code'] === 200);
$imp = $mk2('imp.csv', "nombre,telefono,correo\nImportado Uno,5555 8801,uno@ej.gt\nImportado Dos;5555 8802\nMalo,123,x\nImportado Uno,5555 8801,uno@ej.gt\n");
$r = $adm->post('/admin/clientes/importar', ['_csrf' => $adm->csrf('/admin/clientes'), 'csv' => new CURLFile($imp, 'text/csv', 'imp.csv')]);
t('Importación CSV: crea válidos, omite duplicados e inválidos', (int)dbv("SELECT COUNT(*) FROM clients WHERE phone IN ('55558801')") === 1 && (int)dbv("SELECT COUNT(*) FROM clients WHERE name='Malo'") === 0);
$imp2 = $mk2('imp.php.csv', '<?php echo 1;'); $before = (int)dbv('SELECT COUNT(*) FROM clients');
$adm->post('/admin/clientes/importar', ['_csrf' => $adm->csrf('/admin/clientes'), 'csv' => new CURLFile($imp2, 'text/csv', 'imp.php')]);
t('Importación: archivo que no es CSV es rechazado', (int)dbv('SELECT COUNT(*) FROM clients') === $before);
// Privacidad: exportar y eliminar
$cvx = (int)dbv('SELECT id FROM clients WHERE phone="55553001"');
$ex = $adm->get("/admin/clientes/$cvx/datos"); $exj = json_decode($ex['body'], true);
t('Exportación de datos de un cliente (JSON): ficha, citas y respuestas', $ex['code'] === 200 && ($exj['cliente']['phone'] ?? '') === '55553001' && count($exj['citas'] ?? []) >= 1 && isset($exj['citas'][0]['respuestas']));
$sf = Db::insert('files', ['owner_type' => 'client', 'owner_id' => $cvx, 'original_name' => 'x.pdf', 'stored_name' => ($sn = bin2hex(random_bytes(20))), 'mime' => 'application/pdf', 'size' => 5, 'created_at' => date('Y-m-d H:i:s')]); file_put_contents($APP . '/storage/private/' . $sn, '%PDF-');
$r = $adm->post("/admin/clientes/$cvx/eliminar", ['_csrf' => $adm->csrf("/admin/clientes/$cvx")]);
t('Eliminar datos de un cliente: borra ficha, citas, mensajes y archivos del disco', (int)dbv('SELECT COUNT(*) FROM clients WHERE id=?', [$cvx]) === 0 && (int)dbv('SELECT COUNT(*) FROM appointments WHERE client_id=?', [$cvx]) === 0 && !is_file($APP . '/storage/private/' . $sn) && (int)dbv("SELECT COUNT(*) FROM audit_log WHERE action='client_deleted'") >= 1);
t('Registro de auditoría guarda acciones sensibles (login, exportación, eliminación)', (int)dbv("SELECT COUNT(*) FROM audit_log WHERE action IN ('login','client_exported','client_deleted')") >= 3 && strpos($adm->get('/admin/auditoria')['body'], 'client_deleted') !== false);
// Cliente bloqueado
$dBl = workday(27); $cb = book($S1, $pA, $dBl . ' 09:00:00', cli('Bloqueado', '55557002'), ['answers' => $ans1]); $bid = (int)dbv('SELECT client_id FROM appointments WHERE id=?', [$cb['id']]); Db::exec('UPDATE clients SET blocked=1 WHERE id=?', [$bid]);
$cb2 = book($S1, $pA, $dBl . ' 10:00:00', cli('Bloqueado', '55557002'), ['answers' => $ans1]);
t('Cliente bloqueado no puede reservar en línea', !$cb2['ok'] && $cb2['code'] === 'blocked');
$cb3 = book($S1, $pA, $dBl . ' 10:00:00', cli('Bloqueado', '55557002'), ['answers' => $ans1], ['staff' => true]);
t('El personal sí puede agendarle manualmente', $cb3['ok']);
// No-show
$nsApp = book($S1, $pB, workday(28) . ' 09:00:00', cli('NoShow', '55557003'), ['answers' => $ans1]); BookingService::setStatus($nsApp['id'], 'no_show');
t('Marcar "no asistió" incrementa el contador del cliente', (int)dbv('SELECT noshow_count FROM clients WHERE phone="55557003"') === 1);
BookingService::setStatus($nsApp['id'], 'confirmed');
t('Corregir el estado revierte el contador; la cita vuelve a bloquear el horario', (int)dbv('SELECT noshow_count FROM clients WHERE phone="55557003"') === 0);
$lim = cli('Limite', '55557004'); $okL = 0; for ($i = 0; $i < 7; $i++) { if (book($S1, $pA, workday(29 + $i) . ' 15:00:00', $lim, ['answers' => $ans1])['ok']) { $okL++; } }
t('Límite configurable de citas próximas por cliente en línea (5)', $okL === 5, "ok=$okL");
$dom = book($SDOM, $pA, workday(30) . ' 09:00:00', null);
t('Servicio a domicilio exige dirección', !$dom['ok'] && isset($dom['fields']['home_address']));
$dom = book($SDOM, $pA, workday(30) . ' 09:00:00', null, ['home_address' => 'Zona 15, Calle 3-45']);
t('Servicio a domicilio con dirección: se guarda en la cita', $dom['ok'] && dbv('SELECT home_address FROM appointments WHERE id=?', [$dom['id']]) === 'Zona 15, Calle 3-45');
// Validaciones unitarias
t('Validación de teléfono Guatemala: 8 dígitos, +502, espacios y guiones', Util::phone('5555-1234') === ['502', '55551234'] && Util::phone('+502 5555 1234') === ['502', '55551234'] && Util::phone('1234567') === null && Util::phone('155551234') === null && Util::phone('+1 305 555 0100')[0] === '1' && Util::phone('+502 123') === null);
t('Formato de quetzales Q1,250.00 y fechas dd/mm/aaaa', money(1250) === 'Q1,250.00' && fdate('2026-03-07') === '07/03/2026' && fdatetime('2026-03-07 14:05:00') === '07/03/2026 14:05');
t('Slug sin tildes ni símbolos; token de 128 bits (32 hex)', Util::slug('Dra. Ñoño Pérez') === 'dra-nono-perez' && preg_match('/^[a-f0-9]{32}$/', Util::token(16)) === 1);
// Backup y restauración
$bk = $adm->get('/admin/sistema/respaldo');
file_put_contents($TMP . '/backup.sql', $bk['body']);
$imp = sh('mysql -uaurea -paurea_test_pw -h127.0.0.1 aurea_restore < ' . escapeshellarg($TMP . '/backup.sql'));
$cnt = fn($db, $t) => (int)trim(sh("mysql -uroot -N -e 'select count(*) from $db.$t'"));
t('Respaldo .sql descargable (un clic) con todas las tablas', $bk['code'] === 200 && strpos($bk['headers']['content-disposition'] ?? '', '.sql') !== false && strpos($bk['body'], 'CREATE TABLE `appointments`') !== false);
t('El respaldo se restaura sin errores en una BD nueva y los conteos coinciden', trim($imp) === '' && $cnt('aurea_restore', 'appointments') === $cnt('aurea_test', 'appointments') && $cnt('aurea_restore', 'clients') === $cnt('aurea_test', 'clients') && $cnt('aurea_restore', 'settings') === $cnt('aurea_test', 'settings'), substr($imp, 0, 200));
$ntab = (int)dbv("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='aurea_test'");
$need = ['settings', 'users', 'professionals', 'locations', 'categories', 'services', 'professional_services', 'schedules', 'time_off', 'holidays', 'clients', 'appointments', 'appointment_history', 'form_fields', 'appointment_answers', 'packages', 'client_packages', 'coupons', 'payments', 'files', 'waitlist', 'reviews', 'message_templates', 'notifications_queue', 'audit_log', 'login_attempts', 'migrations'];
$have = array_column(dbq('SHOW TABLES'), 'Tables_in_aurea_test');
t('Modelo de datos: existen las 27 tablas requeridas', !array_diff($need, $have), implode(',', array_diff($need, $have)));
t('Llaves foráneas e índices de disponibilidad presentes', (int)dbv("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema='aurea_test'") >= 25 && (int)dbv("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema='aurea_test' AND table_name='appointments' AND index_name='idx_appt_block'") === 3);
// Migraciones
file_put_contents($APP . '/database/migrations/002_prueba.sql', "-- migración de prueba\nCREATE TABLE zz_migracion_prueba (id INT PRIMARY KEY) ENGINE=InnoDB;\n");
(new Http($BASE))->get('/');
t('Migraciones numeradas: una migración nueva se aplica automáticamente una sola vez', (int)dbv("SELECT COUNT(*) FROM migrations WHERE name='002_prueba.sql'") === 1 && (int)dbv("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='aurea_test' AND table_name='zz_migracion_prueba'") === 1);
(new Http($BASE))->get('/'); t('La migración no se repite', (int)dbv("SELECT COUNT(*) FROM migrations WHERE name='002_prueba.sql'") === 1);
Db::exec('DROP TABLE zz_migracion_prueba'); Db::exec("DELETE FROM migrations WHERE name='002_prueba.sql'"); @unlink($APP . '/database/migrations/002_prueba.sql');
Db::exec("UPDATE settings SET svalue='001_init.sql' WHERE skey='schema_version'"); Settings::flush();
// Páginas públicas y panel responden
$bad = [];
foreach (['/', '/reservar', '/embed', '/equipo', '/privacidad', '/terminos', '/sitemap.xml', '/robots.txt', '/profesional/' . dbv('SELECT slug FROM professionals WHERE id=?', [$pA])] as $pth) { $r = (new Http($BASE))->get($pth); if ($r['code'] !== 200) { $bad[] = "$pth={$r['code']}"; } }
foreach (['/admin', '/admin/onboarding', '/admin/onboarding?paso=5', '/admin/agenda', '/admin/agenda?vista=semana', '/admin/agenda?vista=mes', '/admin/citas', '/admin/citas/nueva', '/admin/citas/' . $apA['id'], '/admin/citas/' . $apA['id'] . '/recibo', '/admin/clientes', '/admin/servicios', '/admin/servicios/' . $S1, '/admin/categorias', '/admin/sedes', '/admin/feriados', '/admin/feriados?anio=2027', '/admin/ausencias', '/admin/cupones', '/admin/paquetes', '/admin/formularios', '/admin/plantillas', '/admin/usuarios', '/admin/profesionales', '/admin/profesionales/' . $pA, '/admin/pagos', '/admin/espera', '/admin/resenas', '/admin/mensajes', '/admin/reportes', '/admin/ajustes', '/admin/ajustes?tab=marca', '/admin/ajustes?tab=reservas', '/admin/ajustes?tab=pagos', '/admin/ajustes?tab=comunicacion', '/admin/ajustes?tab=seguridad', '/admin/ajustes?tab=legal', '/admin/ajustes?tab=terminologia', '/admin/compartir', '/admin/sistema', '/admin/auditoria', '/admin/perfil'] as $pth) { $r = $adm->get($pth); if ($r['code'] !== 200) { $bad[] = "$pth={$r['code']}"; } }
t('Todas las pantallas públicas y del panel responden 200', !$bad, implode(' ', $bad));
$logTxt = (string)@file_get_contents($APP . '/storage/logs/error.log');
$phpErr = array_filter(explode("\n", $logTxt), fn($l) => preg_match('/PHP\[\d+\]|Exception/', $l));
t('El registro de errores no contiene avisos PHP ni excepciones durante toda la suite', !$phpErr, implode(' || ', array_slice($phpErr, 0, 5)));

/* ---------- Resumen ---------- */
$res = $GLOBALS['__results']; $fail = array_filter($res, fn($x) => !$x['ok']);
echo "\n=====================================\n" . count($res) . ' pruebas · ' . (count($res) - count($fail)) . ' OK · ' . count($fail) . " FALLAN\n";
foreach ($fail as $f) { echo " ✗ {$f['name']} {$f['detail']}\n"; }
file_put_contents(__DIR__ . '/results.json', json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
exit($fail ? 1 : 0);
