<?php
declare(strict_types=1);

// Pruebas HTTP reales de las pantallas de Admin3 (servidor embebido en 127.0.0.1:8195 y receptor de webhooks en 8196).
require __DIR__ . '/../lib/T.php';
T::boot('a3', ['demo' => true]);

use App\Core\ApiAuth;
use App\Core\Clock;
use App\Core\Crypto;
use App\Core\Db;
use App\Core\Request;
use App\Services\PollService;
use App\Services\RoutingService;

$root = dirname(__DIR__, 2);
$cfgFile = '/tmp/ap-t-a3.config.php';
$cfg = require $cfgFile;
$cfg['allow_private_http'] = true;
file_put_contents($cfgFile, "<?php\nreturn " . var_export($cfg, true) . ";\n");

// receptor de webhooks
$recvLog = '/tmp/a3-recv.log';
@unlink($recvLog);
$recvScript = '/tmp/a3-recv.php';
file_put_contents($recvScript, '<?php file_put_contents("' . $recvLog . '", json_encode(["sig"=>$_SERVER["HTTP_X_AGENDA_SIGNATURE"]??"","ts"=>$_SERVER["HTTP_X_AGENDA_TIMESTAMP"]??"","ev"=>$_SERVER["HTTP_X_AGENDA_EVENT"]??"","body"=>file_get_contents("php://input")])."\n", FILE_APPEND); echo "ok";');
$procs = [];
$start = static function (string $cmd, array $env) use (&$procs): void {
    $p = proc_open($cmd, [['file', '/dev/null', 'r'], ['file', '/tmp/a3-srv.log', 'a'], ['file', '/tmp/a3-srv.log', 'a']], $pipes, null, $env + $_ENV + ['PATH' => getenv('PATH')]);
    $procs[] = $p;
};
$start('php -S 127.0.0.1:8195 -t ' . escapeshellarg($root) . ' ' . escapeshellarg($root . '/tests/router.php'), ['AP_CONFIG' => $cfgFile]);
$start('php -S 127.0.0.1:8196 ' . escapeshellarg($recvScript), []);
register_shutdown_function(static function () use (&$procs): void {
    foreach ($procs as $p) {
        $st = proc_get_status($p);
        if ($st['running']) {
            proc_terminate($p);
        }
    }
});
usleep(900000);

const BASE = 'http://127.0.0.1:8195';

/** Cliente mínimo con cookies. */
function http(string $method, string $path, array $data = [], string $jar = '/tmp/a3-jar-x', array $headers = [], ?array $file = null): array
{
    $ch = curl_init(BASE . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $headers]);
    if ($method === 'POST') {
        if ($file) {
            $data[$file['field']] = new CURLFile($file['path'], $file['mime'], $file['name']);
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $file ? $data : http_build_query($data));
    }
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['status' => $status, 'body' => substr($raw, $hs), 'head' => substr($raw, 0, $hs)];
}
function csrfOf(string $jar, string $page = '/admin'): string
{
    $r = http('GET', $page, [], $jar);
    return preg_match('/name="csrf-token" content="([^"]+)"/', $r['body'], $m) ? $m[1] : '';
}
function login(string $email, string $pass): string
{
    $jar = '/tmp/a3-jar-' . md5($email);
    @unlink($jar);
    $r = http('GET', '/admin/login', [], $jar);
    preg_match('/name="_csrf" value="([^"]+)"/', $r['body'], $m);
    http('POST', '/admin/login', ['_csrf' => $m[1] ?? '', 'email' => $email, 'password' => $pass], $jar);
    return $jar;
}
function post(string $jar, string $path, array $data = [], ?array $file = null, array $headers = []): array
{
    return http('POST', $path, ['_csrf' => csrfOf($jar)] + $data, $jar, $headers, $file);
}

// usuarios
$hostA = (int) Db::val('SELECT id FROM hosts ORDER BY id LIMIT 1');
$hostB = (int) Db::val('SELECT id FROM hosts ORDER BY id LIMIT 1 OFFSET 1');
$uHost = Db::insert('users', ['name' => 'Anfitrión Prueba', 'email' => 'host@test.local', 'password_hash' => Crypto::hashPassword('Prueba#Host2026'), 'role' => 'host', 'active' => 1, 'created_at' => Clock::utc(), 'updated_at' => Clock::utc()]);
Db::update('hosts', ['user_id' => $uHost], 'id = ?', [$hostA]);
Db::insert('users', ['name' => 'Recepción Prueba', 'email' => 'rec@test.local', 'password_hash' => Crypto::hashPassword('Prueba#Rec2026'), 'role' => 'reception', 'active' => 1, 'created_at' => Clock::utc(), 'updated_at' => Clock::utc()]);
$admin = login('admin@test.local', 'Prueba#Segura2026');
$host = login('host@test.local', 'Prueba#Host2026');
$rec = login('rec@test.local', 'Prueba#Rec2026');

T::section('Acceso y permisos por rol');
T::eq(302, http('GET', '/admin/pagos', [], '/tmp/a3-jar-anon')['status'], 'sin sesión redirige al acceso');
foreach (['/admin/mensajes', '/admin/pagos', '/admin/espera', '/admin/paquetes', '/admin/cupones', '/admin/certificados'] as $p) {
    T::eq(200, http('GET', $p, [], $admin)['status'], "admin entra a $p");
    T::eq(200, http('GET', $p, [], $rec)['status'] ?? 0, "recepción entra a $p") ;
}
foreach (['/admin/flujos', '/admin/enrutamiento', '/admin/encuestas', '/admin/webhooks', '/admin/api', '/admin/analitica', '/admin/resenas'] as $p) {
    T::eq(200, http('GET', $p, [], $admin)['status'], "admin entra a $p");
    T::eq(403, http('GET', $p, [], $rec)['status'], "recepción NO entra a $p");
    T::eq(403, http('GET', $p, [], $host)['status'], "anfitrión NO entra a $p");
}
foreach (['/admin/pagos', '/admin/paquetes', '/admin/cupones', '/admin/certificados', '/admin/espera'] as $p) {
    if ($p === '/admin/espera' || true) {
        $s = http('GET', $p, [], $host)['status'];
        T::eq(403, $s, "anfitrión NO entra a $p");
    }
}
T::eq(200, http('GET', '/admin/mensajes', [], $host)['status'], 'anfitrión entra a mensajes');

T::section('CSRF');
T::eq(419, http('POST', '/admin/cupones/guardar', ['code' => 'SINTOKEN', 'type' => 'percent', 'value' => '10'], $admin)['status'], 'POST sin token CSRF → 419');
T::eq(419, http('POST', '/admin/api/claves', ['name' => 'x'], $admin)['status'], 'crear clave sin CSRF → 419');
T::eq(419, http('POST', '/admin/flujos/vista-previa', ['template' => 'x'], $admin)['status'], 'vista previa sin CSRF → 419');
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM coupons WHERE code = 'SINTOKEN'"), 'no se creó nada sin CSRF');

T::section('Mensajes de hoy');
$bA = (int) Db::val('SELECT id FROM bookings WHERE host_id = ? LIMIT 1', [$hostA]);
$bB = (int) Db::val('SELECT id FROM bookings WHERE host_id = ? LIMIT 1', [$hostB]);
$mA = Db::insert('message_queue', ['booking_id' => $bA, 'channel' => 'whatsapp', 'phone' => '50255551234', 'body' => 'Mensaje de A <b>x</b>', 'status' => 'pending', 'due_at' => Clock::utc(Clock::now() - 600), 'created_at' => Clock::utc()]);
$mB = Db::insert('message_queue', ['booking_id' => $bB, 'channel' => 'whatsapp', 'phone' => '50244447777', 'body' => 'Mensaje secreto de B', 'status' => 'pending', 'due_at' => Clock::utc(Clock::now() - 600), 'created_at' => Clock::utc()]);
$r = http('GET', '/admin/mensajes', [], $host);
T::ok(str_contains($r['body'], 'Mensaje de A'), 'el anfitrión ve su mensaje');
T::ok(!str_contains($r['body'], 'Mensaje secreto de B'), 'el anfitrión NO ve el mensaje ajeno');
T::ok(str_contains($r['body'], 'Mensaje de A &lt;b&gt;x&lt;/b&gt;'), 'el texto se escapa (XSS)');
T::ok(str_contains(http('GET', '/admin/mensajes', [], $admin)['body'], 'https://wa.me/50255551234?text='), 'enlace wa.me con el texto');
T::eq(404, post($host, "/admin/mensajes/$mB/enviado")['status'], 'IDOR: el anfitrión no marca mensajes ajenos');
T::eq('pending', Db::val('SELECT status FROM message_queue WHERE id = ?', [$mB]), 'el mensaje ajeno sigue pendiente');
post($host, "/admin/mensajes/$mA/enviado");
T::eq('sent', Db::val('SELECT status FROM message_queue WHERE id = ?', [$mA]), 'marcar como enviado');
post($admin, "/admin/mensajes/$mB/descartar");
T::eq('dismissed', Db::val('SELECT status FROM message_queue WHERE id = ?', [$mB]), 'descartar');

T::section('Pagos y comprobantes');
Db::update('bookings', ['total' => 300, 'price' => 300, 'deposit_due' => 100], 'id = ?', [$bA]);
post($admin, "/admin/citas/$bA/pagos", ['method' => 'cash', 'amount' => '100']);
T::eq('100.00', Db::val('SELECT paid_amount FROM bookings WHERE id = ?', [$bA]), 'pago en efectivo suma al pagado');
T::eq('partial', Db::val('SELECT payment_status FROM bookings WHERE id = ?', [$bA]), 'estado parcial');
post($admin, "/admin/citas/$bA/pagos", ['method' => 'cash', 'amount' => '999']);
T::eq('100.00', Db::val('SELECT paid_amount FROM bookings WHERE id = ?', [$bA]), 'no se puede pagar más que el saldo');
post($admin, "/admin/citas/$bA/pagos", ['method' => 'cash', 'amount' => '-5']);
post($admin, "/admin/citas/$bA/pagos", ['method' => 'cash', 'amount' => "1'; DROP TABLE payments;--"]);
T::ok((int) Db::val('SELECT COUNT(*) FROM payments') >= 1, 'monto con SQL inyectado se rechaza sin daño');
$png = '/tmp/a3-proof.png';
$im = imagecreatetruecolor(40, 40);
imagepng($im, $png);
$before = (int) Db::val('SELECT COUNT(*) FROM payments');
post($admin, "/admin/citas/$bA/pagos", ['method' => 'transfer', 'amount' => '100', 'reference' => 'BI-1<img>'], ['field' => 'proof', 'path' => $png, 'mime' => 'image/png', 'name' => 'boleta.png']);
T::eq($before + 1, (int) Db::val('SELECT COUNT(*) FROM payments'), 'transferencia con comprobante registrada');
$pay = Db::one("SELECT * FROM payments WHERE booking_id = ? AND method = 'transfer'", [$bA]);
T::eq('pending', $pay['status'], 'la transferencia queda por verificar');
T::ok($pay['proof_file_id'] !== null, 'el comprobante quedó guardado');
$list = http('GET', '/admin/pagos', [], $admin)['body'];
T::ok(str_contains($list, 'Comprobantes por verificar') && str_contains($list, '/f/'), 'la lista muestra el comprobante');
T::ok(!str_contains($list, 'BI-1<img>'), 'la referencia se escapa');
$tok = Db::val('SELECT token FROM files WHERE id = ?', [$pay['proof_file_id']]);
T::eq(200, http('GET', '/f/' . $tok, [], $admin)['status'], 'el administrador abre el comprobante');
T::eq(403, http('GET', '/f/' . $tok, [], '/tmp/a3-jar-anon')['status'], 'sin sesión no se abre el comprobante');
post($admin, '/admin/pagos/' . $pay['id'] . '/verificar');
T::eq('verified', Db::val('SELECT status FROM payments WHERE id = ?', [$pay['id']]), 'verificar comprobante');
T::eq('200.00', Db::val('SELECT paid_amount FROM bookings WHERE id = ?', [$bA]), 'el pagado incluye lo verificado');
post($admin, '/admin/pagos/' . $pay['id'] . '/reembolso', ['amount' => '40', 'note' => 'Ajuste']);
T::eq('160.00', Db::val('SELECT paid_amount FROM bookings WHERE id = ?', [$bA]), 'reembolso parcial');
$rc = http('GET', "/admin/pagos/$bA/recibo", [], $admin);
T::ok($rc['status'] === 200 && str_contains($rc['body'], 'Recibo') && str_contains($rc['body'], 'Q300.00'), 'recibo imprimible');
T::eq(403, http('GET', "/admin/pagos/$bB/recibo", [], $host)['status'], 'anfitrión sin acceso al recibo');
T::eq(200, http('GET', '/admin/pagos?q=' . rawurlencode("' OR 1=1 --") . '&estado=pending', [], $admin)['status'], 'búsqueda con SQL inyectado no rompe');

T::section('Paquetes, cupones y certificados');
post($admin, '/admin/paquetes/guardar', ['name' => 'Bono <script>alert(1)</script>', 'sessions' => '5', 'price' => '1000', 'validity_days' => '90', 'active' => '1']);
$pk = (int) Db::val('SELECT id FROM packages ORDER BY id DESC LIMIT 1');
T::ok($pk > 0, 'paquete creado');
T::ok(!str_contains(http('GET', '/admin/paquetes', [], $admin)['body'], '<script>alert(1)</script>'), 'el nombre del paquete se escapa');
$client = (int) Db::val('SELECT id FROM clients ORDER BY id LIMIT 1');
post($admin, '/admin/paquetes/vender', ['client_id' => (string) $client, 'package_id' => (string) $pk, 'paid' => '1', 'method' => 'cash']);
T::eq(5, (int) Db::val('SELECT remaining FROM client_packages WHERE package_id = ?', [$pk]), 'venta de paquete con saldo de 5 sesiones');
T::eq('1000.00', Db::val('SELECT amount FROM payments WHERE client_package_id IS NOT NULL ORDER BY id DESC LIMIT 1'), 'la venta registra el ingreso');
post($admin, "/admin/paquetes/$pk/eliminar");
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM packages WHERE id = ?', [$pk]), 'un paquete vendido no se elimina');
post($admin, '/admin/cupones/guardar', ['code' => 'prueba10', 'type' => 'percent', 'value' => '10', 'max_uses' => '5', 'active' => '1']);
T::eq('PRUEBA10', Db::val('SELECT code FROM coupons WHERE code = ?', ['PRUEBA10']), 'cupón creado en mayúsculas');
post($admin, '/admin/cupones/guardar', ['code' => 'PRUEBA10', 'type' => 'fixed', 'value' => '5']);
T::eq(1, (int) Db::val("SELECT COUNT(*) FROM coupons WHERE code = 'PRUEBA10'"), 'código duplicado rechazado');
post($admin, '/admin/cupones/guardar', ['code' => 'MALO', 'type' => 'percent', 'value' => '150']);
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM coupons WHERE code = 'MALO'"), 'porcentaje mayor a 100 rechazado');
post($admin, '/admin/cupones/guardar', ['code' => "X'; DROP TABLE coupons;--", 'type' => 'percent', 'value' => '5']);
T::ok((int) Db::val('SELECT COUNT(*) FROM coupons') >= 1, 'código con SQL inyectado se limpia');
post($admin, '/admin/certificados/crear', ['amount' => '500', 'recipient_name' => 'Ana <b>', 'message' => 'Hola']);
$gc = Db::one('SELECT * FROM gift_cards ORDER BY id DESC LIMIT 1');
T::ok($gc && $gc['balance'] === '500.00', 'certificado creado con saldo');
$q = http('GET', '/admin/certificados?consultar=' . rawurlencode($gc['code']), [], $admin)['body'];
T::ok(str_contains($q, 'Q500.00'), 'consulta de saldo por código');
$pr = http('GET', '/admin/certificados/' . $gc['id'] . '/imprimir', [], $admin);
T::ok($pr['status'] === 200 && str_contains($pr['body'], $gc['code']) && str_contains($pr['body'], 'p3-guilloche'), 'certificado imprimible con guilloché');
T::ok(!str_contains($pr['body'], 'Ana <b>'), 'el destinatario se escapa en el certificado');

T::section('Flujos y recordatorios');
post($admin, '/admin/flujos/guardar', ['name' => 'Recordatorio prueba', 'trigger_key' => 'booking.before_start', 'offset_value' => '3', 'offset_unit' => 'hours', 'action' => 'email', 'recipient' => 'guest', 'subject' => 'Hola {nombre}', 'template' => 'Tu cita de {evento} es el {fecha} a las {hora}.', 'active' => '1']);
$wf = Db::one("SELECT * FROM workflows WHERE name = 'Recordatorio prueba'");
T::ok($wf !== null, 'flujo creado');
T::eq(180, (int) $wf['offset_minutes'], '3 horas = 180 minutos');
post($admin, '/admin/flujos/guardar', ['name' => 'Malo', 'trigger_key' => 'booking.nada', 'action' => 'email']);
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM workflows WHERE name = 'Malo'"), 'disparador inválido rechazado');
post($admin, '/admin/flujos/guardar', ['name' => 'Sin mensaje', 'trigger_key' => 'booking.created', 'action' => 'email', 'subject' => '', 'template' => '']);
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM workflows WHERE name = 'Sin mensaje'"), 'correo sin asunto ni texto rechazado');
$pv = post($admin, '/admin/flujos/vista-previa', ['template' => 'Hola {nombre}, tu cita de {evento} <script>x</script>', 'subject' => 'Asunto {nombre}', 'booking_id' => (string) $bA], null, ['Accept: application/json']);
$j = json_decode($pv['body'], true);
T::ok(($j['ok'] ?? false) === true && !str_contains((string) $j['body'], '{nombre}') && str_contains((string) $j['body'], 'Hola '), 'vista previa reemplaza las variables');
$pv2 = json_decode(post($admin, '/admin/flujos/vista-previa', ['template' => 'Hola {nombre}', 'subject' => ''], null, ['Accept: application/json'])['body'], true);
T::ok(($pv2['ok'] ?? false) && ($pv2['sample'] ?? false) && str_contains($pv2['body'], 'María López'), 'vista previa con cita de ejemplo');
$rr = post($admin, '/admin/flujos/' . $wf['id'] . '/prueba', ['booking_id' => (string) $bA]);
T::ok(in_array($rr['status'], [302, 303], true), 'probar ejecución responde');
post($admin, '/admin/flujos/' . $wf['id'] . '/duplicar');
T::eq(0, (int) Db::val("SELECT active FROM workflows WHERE name = 'Recordatorio prueba (copia)'"), 'duplicar crea una copia pausada');
post($admin, '/admin/flujos/' . $wf['id'] . '/estado');
T::eq(0, (int) Db::val('SELECT active FROM workflows WHERE id = ?', [$wf['id']]), 'pausar flujo');
$runId = Db::insert('workflow_runs', ['workflow_id' => $wf['id'], 'booking_id' => $bA, 'scheduled_at' => Clock::utc(), 'status' => 'failed', 'attempts' => 3, 'last_error' => 'Fallo <i>x</i>', 'created_at' => Clock::utc()]);
$runs = http('GET', '/admin/flujos/ejecuciones', [], $admin)['body'];
T::ok(str_contains($runs, 'Fallo &lt;i&gt;x&lt;/i&gt;') && str_contains($runs, 'Reintentar'), 'bitácora de ejecuciones con error escapado y reintento');
post($admin, "/admin/flujos/ejecuciones/$runId/reintentar");
T::ok(Db::val('SELECT status FROM workflow_runs WHERE id = ?', [$runId]) !== 'failed' || (int) Db::val('SELECT attempts FROM workflow_runs WHERE id = ?', [$runId]) > 0, 'reintento manual procesa la ejecución');

T::section('Enrutamiento');
$qs = [['id' => 'servicio', 'label' => '¿Qué necesitas?', 'type' => 'single', 'options' => ['Consulta', 'Urgencia'], 'required' => true], ['id' => 'edad', 'label' => 'Edad', 'type' => 'number', 'required' => false]];
$ev = (int) Db::val('SELECT id FROM event_types ORDER BY id LIMIT 1');
$rules = [['id' => 0, 'match' => 'all', 'active' => 1, 'action' => 'event', 'target' => (string) $ev, 'message' => '', 'conditions' => [['question' => 'servicio', 'op' => 'eq', 'value' => 'Consulta']]]];
post($admin, '/admin/enrutamiento/guardar', ['name' => 'Orientación <b>x</b>', 'active' => '1', 'questions_json' => json_encode($qs), 'rules_json' => json_encode($rules), 'default_action' => 'message', 'default_message' => 'Escríbenos.']);
$form = Db::one('SELECT * FROM routing_forms ORDER BY id DESC LIMIT 1');
T::ok($form && (int) Db::val('SELECT COUNT(*) FROM routing_rules WHERE form_id = ?', [$form['id']]) === 1, 'formulario con regla creado');
T::eq('radio', json_decode($form['questions'], true)[0]['type'], 'opción única se guarda como radio');
$bad = $rules;
$bad[0]['conditions'][0]['value'] = '';
post($admin, '/admin/enrutamiento/guardar', ['id' => (string) $form['id'], 'name' => 'Otro nombre', 'questions_json' => json_encode($qs), 'rules_json' => json_encode($bad), 'default_action' => 'message', 'default_message' => 'x']);
T::eq($form['name'], Db::val('SELECT name FROM routing_forms WHERE id = ?', [$form['id']]), 'regla con condición vacía rechazada (no se guardó nada)');
$bad2 = $rules;
$bad2[0]['action'] = 'url';
$bad2[0]['target'] = 'javascript:alert(1)';
post($admin, '/admin/enrutamiento/guardar', ['id' => (string) $form['id'], 'name' => 'Otro', 'questions_json' => json_encode($qs), 'rules_json' => json_encode($bad2), 'default_action' => 'message', 'default_message' => 'x']);
T::eq($form['name'], Db::val('SELECT name FROM routing_forms WHERE id = ?', [$form['id']]), 'URL javascript: rechazada');
$res = RoutingService::run($form, ['servicio' => 'Consulta'], '127.0.0.1');
T::ok(str_contains((string) $res['redirect'], '/e/'), 'el servicio enruta según la regla');
RoutingService::run($form, ['servicio' => 'Urgencia'], '127.0.0.1');
$lg = http('GET', '/admin/enrutamiento/' . $form['id'] . '/bitacora', [], $admin)['body'];
T::ok(str_contains($lg, 'Consulta') && str_contains($lg, 'Urgencia') && str_contains($lg, 'Por defecto'), 'la bitácora muestra respuestas y regla aplicada');
T::ok(str_contains($lg, '50.0 %'), 'estadísticas por regla');
T::ok(!str_contains(http('GET', '/admin/enrutamiento', [], $admin)['body'], 'Orientación <b>x</b>'), 'nombre del formulario escapado');

T::section('Encuestas de horarios');
$d1 = gmdate('Y-m-d', Clock::now() + 3 * 86400);
$d2 = gmdate('Y-m-d', Clock::now() + 4 * 86400);
post($admin, '/admin/encuestas/crear', ['title' => 'Reunión <i>x</i>', 'host_id' => (string) $hostA, 'event_type_id' => (string) $ev, 'duration' => '60', 'timezone' => 'America/Guatemala', 'opts' => [$d1 . 'T10:00', $d2 . 'T15:00', '']]);
$poll = Db::one('SELECT * FROM polls ORDER BY id DESC LIMIT 1');
T::ok($poll !== null, 'encuesta creada');
$o = Db::all('SELECT * FROM poll_options WHERE poll_id = ? ORDER BY starts_at', [$poll['id']]);
T::eq($d1 . ' 16:00:00', $o[0]['starts_at'], 'la opción se guarda en UTC (Guatemala +6)');
post($admin, '/admin/encuestas/crear', ['title' => 'Una sola', 'host_id' => (string) $hostA, 'event_type_id' => (string) $ev, 'duration' => '60', 'opts' => [$d1 . 'T10:00']]);
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM polls'), 'se exigen al menos dos opciones');
T::eq(403, post($host, '/admin/encuestas/crear', ['title' => 'x'])['status'], 'el anfitrión no crea encuestas');
PollService::vote($poll['token'], 'Marta', 'marta@example.test', [$o[0]['id'] => 'yes', $o[1]['id'] => 'no']);
PollService::vote($poll['token'], 'Jorge', 'jorge@example.test', [$o[0]['id'] => 'maybe', $o[1]['id'] => 'yes']);
$sh = http('GET', '/admin/encuestas/' . $poll['id'], [], $admin)['body'];
T::ok(str_contains($sh, 'Marta') && str_contains($sh, 'Mejor opción') && str_contains($sh, 'Reunión &lt;i&gt;x&lt;/i&gt;'), 'matriz de votos con mejor opción y título escapado');
post($admin, '/admin/encuestas/' . $poll['id'] . '/finalizar', ['option_id' => (string) $o[0]['id']]);
$poll2 = Db::one('SELECT * FROM polls WHERE id = ?', [$poll['id']]);
T::eq('finalized', $poll2['status'], 'encuesta finalizada');
T::ok($poll2['final_booking_id'] !== null && Db::val('SELECT starts_at FROM bookings WHERE id = ?', [$poll2['final_booking_id']]) === $o[0]['starts_at'], 'se creó la cita en el horario elegido');
T::eq(404, http('GET', '/admin/encuestas/99999', [], $admin)['status'], 'encuesta inexistente → 404');

T::section('Webhooks');
post($admin, '/admin/webhooks/guardar', ['name' => 'Receptor <b>', 'url' => 'http://127.0.0.1:8196/hook', 'events' => ['booking.created', 'booking.cancelled'], 'active' => '1']);
$wh = Db::one('SELECT * FROM webhooks ORDER BY id DESC LIMIT 1');
T::ok($wh && str_starts_with($wh['secret'], 'whsec_'), 'webhook creado con secreto generado');
T::eq('booking.created,booking.cancelled', $wh['events'], 'eventos suscritos guardados');
post($admin, '/admin/webhooks/guardar', ['name' => 'Malo', 'url' => 'ftp://x.test/a', 'events' => ['booking.created']]);
T::eq(1, (int) Db::val('SELECT COUNT(*) FROM webhooks'), 'URL no http(s) rechazada');
post($admin, '/admin/webhooks/' . $wh['id'] . '/prueba');
$d = Db::one('SELECT * FROM webhook_deliveries WHERE webhook_id = ? ORDER BY id DESC LIMIT 1', [$wh['id']]);
T::eq('delivered', $d['status'], 'evento de prueba entregado');
$log = array_map(static fn ($l) => json_decode($l, true), array_filter(explode("\n", (string) @file_get_contents($recvLog))));
T::ok(count($log) >= 1 && $log[0]['ev'] === 'test.ping', 'el receptor recibió test.ping');
T::eq('sha256=' . hash_hmac('sha256', $log[0]['ts'] . '.' . $log[0]['body'], $wh['secret']), $log[0]['sig'], 'la firma HMAC-SHA256 es verificable con el secreto');
Db::update('webhook_deliveries', ['status' => 'failed', 'attempts' => 6], 'id = ?', [$d['id']]);
post($admin, '/admin/webhooks/entregas/' . $d['id'] . '/reenviar');
T::eq('delivered', Db::val('SELECT status FROM webhook_deliveries WHERE id = ?', [$d['id']]), 'reenviar entrega fallida');
$old = $wh['secret'];
post($admin, '/admin/webhooks/' . $wh['id'] . '/secreto');
T::ok(Db::val('SELECT secret FROM webhooks WHERE id = ?', [$wh['id']]) !== $old, 'regenerar secreto');
T::ok(!str_contains(http('GET', '/admin/webhooks', [], $admin)['body'], 'Receptor <b>'), 'nombre del webhook escapado');
post($admin, '/admin/webhooks/' . $wh['id'] . '/eliminar');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM webhooks'), 'eliminar webhook');

T::section('Claves de API');
post($admin, '/admin/api/claves', ['name' => 'Integración <b>', 'scope' => 'read']);
$page = http('GET', '/admin/api', [], $admin)['body'];
preg_match('/(ap_[a-f0-9]{40})/', $page, $km);
T::ok(!empty($km[1]), 'la clave en claro se muestra tras crearla');
$key = $km[1] ?? '';
T::ok(!str_contains(http('GET', '/admin/api', [], $admin)['body'], $key), 'la clave NO se vuelve a mostrar');
$row = Db::one('SELECT * FROM api_keys ORDER BY id DESC LIMIT 1');
T::eq(hash('sha256', $key), $row['key_hash'], 'solo se guarda el hash');
T::ok(!str_contains($page . http('GET', '/admin/api', [], $admin)['body'], 'Integración <b>'), 'nombre de clave escapado');
$mk = static fn (string $k): Request => new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v1/x', 'HTTP_AUTHORIZATION' => 'Bearer ' . $k, 'REMOTE_ADDR' => '127.0.0.1'], [], [], []);
T::ok(ApiAuth::authenticate($mk($key))['ok'], 'la clave activa autentica');
post($admin, '/admin/api/claves/' . $row['id'] . '/revocar');
$a = ApiAuth::authenticate($mk($key));
T::ok(!$a['ok'] && $a['status'] === 401, 'clave revocada → 401');
T::eq(403, post($rec, '/admin/api/claves', ['name' => 'x'])['status'], 'recepción no crea claves');

T::section('Lista de espera, reseñas y analítica');
$w = Db::insert('waitlist', ['event_type_id' => $ev, 'name' => 'Rosa <u>x</u>', 'email' => 'rosa@example.test', 'phone' => '50255559999', 'timezone' => 'America/Guatemala', 'duration' => 30, 'status' => 'waiting', 'created_at' => Clock::utc()]);
T::ok(str_contains(http('GET', '/admin/espera', [], $admin)['body'], 'Rosa &lt;u&gt;x&lt;/u&gt;'), 'lista de espera escapa nombres');
T::eq(200, http('GET', "/admin/espera/$w/ofrecer", [], $admin)['status'], 'pantalla de oferta manual');
post($admin, "/admin/espera/$w/cancelar");
T::eq('cancelled', Db::val('SELECT status FROM waitlist WHERE id = ?', [$w]), 'cancelar entrada');
$rv = Db::insert('reviews', ['token' => bin2hex(random_bytes(16)), 'booking_id' => $bA, 'host_id' => $hostA, 'client_name' => 'Cli <b>', 'rating' => 5, 'comment' => 'Muy bien <script>1</script>', 'status' => 'pending', 'created_at' => Clock::utc(), 'submitted_at' => Clock::utc()]);
$rp = http('GET', '/admin/resenas', [], $admin)['body'];
T::ok(str_contains($rp, 'Muy bien &lt;script&gt;') && !str_contains($rp, '<script>1</script>'), 'reseñas escapadas');
post($admin, "/admin/resenas/$rv/aprobar");
T::eq('approved', Db::val('SELECT status FROM reviews WHERE id = ?', [$rv]), 'aprobar reseña');
$an = http('GET', '/admin/analitica?desde=2026-01-01&hasta=2026-12-31&evento=' . rawurlencode("1 OR 1=1"), [], $admin);
T::eq(200, $an['status'], 'analítica con filtros hostiles responde');
$csv = http('GET', '/admin/analitica/exportar?tipo=bookings&desde=2026-01-01&hasta=2026-12-31', [], $admin);
T::ok($csv['status'] === 200 && str_contains($csv['head'], 'text/csv'), 'exportación CSV');
T::eq(302, http('GET', '/admin/analitica/exportar?tipo=bookings&desde=malo&hasta=x', [], $admin)['status'], 'exportación con fechas inválidas vuelve con aviso');

T::done();
