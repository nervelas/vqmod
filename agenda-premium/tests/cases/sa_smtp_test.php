<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
require __DIR__ . '/../lib/SaHelper.php';
T::boot('sa_smtp');

use App\Core\Config;
use App\Core\Crypto;
use App\Core\Db;
use App\Core\Settings;
use App\Services\Mailer;
use App\Services\SmtpClient;

$dir = SaHelper::workDir('smtp');
[$crt, $key] = SaHelper::selfSignedCert();
$out = [];
foreach (['login' => [25251, 'plain'], 'plainauth' => [25254, 'plain'], 'tls' => [25252, 'starttls'], 'ssl' => [25253, 'ssl'], 'open' => [25255, 'plain']] as $name => [$port, $mode]) {
    $out[$name] = $dir . '/' . $name . '.jsonl';
    $extra = in_array($mode, ['starttls', 'ssl'], true) ? ['--cert', $crt, '--key', $key] : [];
    if ($name !== 'open') {
        $extra = array_merge($extra, ['--auth', 'usuario:clave#ñ1']);
    }
    if ($name === 'plainauth') {
        $extra = array_merge($extra, ['--auth-mechs', 'PLAIN']);
    }
    SaHelper::startSmtp($port, $mode, $out[$name], $extra);
}
$set = static function (int $port, string $secure, string $user = 'usuario', string $pass = 'clave#ñ1'): void {
    Settings::setMany(['smtp_host' => '127.0.0.1', 'smtp_port' => (string) $port, 'smtp_secure' => $secure, 'smtp_user' => $user, 'smtp_pass' => $pass,
        'mail_from_name' => 'Consultorio Ñandú', 'mail_from_email' => 'citas@example.test', 'email' => 'contacto@example.test']);
};

T::section('SMTP con AUTH LOGIN, UTF-8, adjuntos y relleno de puntos');
$set(25251, 'none');
$ics = "BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n";
$html = '<p>Hola José, tu cita está lista ✓.</p><p>.punto al inicio</p>';
$r = Mailer::sendNow('maria.perez@example.test', 'María Pérez', 'Confirmación de tu cita · ñandú ✓ con un asunto bastante largo para obligar al plegado de la cabecera', $html, "Hola José\n.línea con punto\nFin", [['name' => 'cita ñ.ics', 'mime' => 'text/calendar', 'content' => $ics]]);
T::eq(['ok' => true, 'error' => null], $r, 'sendNow por SMTP devuelve ok');
$got = SaHelper::jsonl($out['login']);
T::eq(1, count($got), 'el servidor recibió un correo');
T::eq('usuario', $got[0]['user'] ?? null, 'se autenticó con AUTH LOGIN');
T::eq('citas@example.test', $got[0]['from'] ?? null, 'remitente del sobre');
T::eq(['maria.perez@example.test'], $got[0]['rcpt'] ?? null, 'destinatario del sobre');
[$h, $parts] = SaHelper::parseEml($got[0]['data']);
T::eq('Confirmación de tu cita · ñandú ✓ con un asunto bastante largo para obligar al plegado de la cabecera', iconv_mime_decode($h['subject'], 0, 'UTF-8'), 'asunto UTF-8 decodificado');
T::ok(preg_match('/^=\?UTF-8\?B\?/', $h['subject']) === 1, 'asunto codificado en palabras UTF-8');
T::ok(strpos($got[0]['data'], "Subject: =?UTF-8?B?") !== false, 'cabecera Subject presente');
T::ok(stripos(iconv_mime_decode($h['from'], 0, 'UTF-8'), 'Consultorio Ñandú') !== false && strpos($h['from'], '<citas@example.test>') !== false, 'From con nombre UTF-8');
T::ok(stripos(iconv_mime_decode($h['to'], 0, 'UTF-8'), 'María Pérez') !== false, 'To con nombre UTF-8');
T::ok(strpos($h['content-type'], 'multipart/mixed') === 0, 'multipart/mixed con adjunto');
$byType = [];
foreach ($parts as $p) {
    $byType[$p['type']] = $p;
}
T::eq("Hola José\n.línea con punto\nFin", rtrim(str_replace("\r\n", "\n", $byType['text/plain']['content'] ?? '')), 'texto plano intacto (incluida la línea que empieza con punto)');
T::ok(strpos($byType['text/html']['content'] ?? '', '<p>.punto al inicio</p>') !== false && strpos($byType['text/html']['content'], 'José') !== false, 'HTML intacto con acentos');
T::eq($ics, $byType['text/calendar']['content'] ?? null, 'adjunto .ics idéntico');
T::eq('cita ñ.ics', $byType['text/calendar']['name'] ?? null, 'nombre de adjunto UTF-8');
T::ok(max(array_map('strlen', explode("\r\n", $got[0]['data']))) <= 998, 'ninguna línea supera 998 caracteres');

T::section('Texto automático desde el HTML cuando no se da texto');
$r = Mailer::sendNow('a@example.test', null, 'Sin texto', '<h1>Título</h1><p>Visita <a href="https://example.test/x?a=1&amp;b=2">este enlace</a>.</p>');
$got = SaHelper::jsonl($out['login']);
[, $parts] = SaHelper::parseEml($got[1]['data']);
T::ok($r['ok'] && strpos($parts[0]['content'], 'este enlace (https://example.test/x?a=1&b=2)') !== false, 'versión de texto generada con el enlace visible');

T::section('AUTH PLAIN, credenciales incorrectas, destinatario rechazado');
$set(25254, 'none');
T::ok(Mailer::sendNow('b@example.test', null, 'Plain', '<p>x</p>')['ok'], 'AUTH PLAIN aceptado');
T::eq('usuario', SaHelper::jsonl($out['plainauth'])[0]['user'] ?? null, 'el servidor registró AUTH PLAIN');
$set(25251, 'none', 'usuario', 'incorrecta');
$r = Mailer::sendNow('b@example.test', null, 'Mal', '<p>x</p>');
T::ok(!$r['ok'] && strpos((string) $r['error'], 'rechazó el usuario o la contraseña') !== false, 'contraseña incorrecta: mensaje amable');
T::ok(strpos((string) $r['error'], 'incorrecta') === false, 'el mensaje de error no contiene la contraseña');
$set(25251, 'none');
$r = Mailer::sendNow('rechazado@example.test', null, 'X', '<p>x</p>');
T::ok(!$r['ok'] && strpos((string) $r['error'], '550') !== false, 'destinatario rechazado: error con código');
$r = Mailer::sendNow('no es un correo', null, 'X', '<p>x</p>');
T::ok(!$r['ok'] && strpos((string) $r['error'], 'no es válida') !== false, 'dirección inválida');
$r = Mailer::sendNow("a@example.test\r\nBcc: x@example.test", null, 'X', '<p>x</p>');
T::ok(!$r['ok'], 'inyección de cabeceras en la dirección rechazada');
$before = count(SaHelper::jsonl($out['login']));
Mailer::sendNow('c@example.test', "Nombre\r\nBcc: espia@example.test", "Asunto\r\nBcc: espia2@example.test", '<p>x</p>');
$all = SaHelper::jsonl($out['login']);
T::ok(count($all) === $before + 1 && stripos($all[$before]['data'], "\r\nBcc:") === false, 'saltos de línea en nombre y asunto no inyectan cabeceras');

T::section('STARTTLS y SSL implícito (certificado autofirmado)');
$set(25252, 'tls');
$r = Mailer::sendNow('t@example.test', null, 'TLS', '<p>x</p>');
T::ok(!$r['ok'] && strpos((string) $r['error'], 'conexión cifrada') !== false, 'por defecto se rechaza el certificado autofirmado');
Config::set('smtp_insecure_tls', true);
$r = Mailer::sendNow('t@example.test', null, 'TLS', '<p>x</p>');
T::ok($r['ok'], 'STARTTLS correcto cuando se acepta el certificado de prueba');
$g = SaHelper::jsonl($out['tls']);
T::ok(($g[0]['tls'] ?? false) === true && ($g[0]['user'] ?? '') === 'usuario', 'el servidor confirma TLS y autenticación');
$set(25253, 'ssl');
T::ok(Mailer::sendNow('s@example.test', null, 'SSL', '<p>x</p>')['ok'], 'SSL implícito correcto');
T::ok((SaHelper::jsonl($out['ssl'])[0]['tls'] ?? false) === true, 'el servidor confirma SSL');
Config::set('smtp_insecure_tls', false);
T::ok(!Mailer::sendNow('s@example.test', null, 'SSL', '<p>x</p>')['ok'], 'SSL con certificado autofirmado falla sin la excepción de pruebas');
$set(25251, 'tls');
Config::set('smtp_insecure_tls', true);
$r = Mailer::sendNow('s@example.test', null, 'SSL', '<p>x</p>');
T::ok(!$r['ok'] && strpos((string) $r['error'], 'STARTTLS') !== false, 'pedir STARTTLS a un servidor que no lo ofrece da un mensaje claro');

T::section('Contraseña cifrada con prefijo v1:');
$set(25251, 'none', 'usuario', Crypto::encrypt('clave#ñ1'));
T::ok(strncmp((string) Settings::get('smtp_pass'), 'v1:', 3) === 0 && Mailer::sendNow('e@example.test', null, 'Cifrada', '<p>x</p>')['ok'], 'smtp_pass cifrada se descifra y autentica');
T::ok(Mailer::testConnection()['ok'], 'testConnection correcto con credenciales cifradas');

T::section('testConnection y errores de red');
$set(25251, 'none', 'usuario', 'mala');
$t = Mailer::testConnection();
T::ok(!$t['ok'] && $t['error'] !== null, 'testConnection con clave mala falla con mensaje');
$set(25299, 'none');
$t = Mailer::testConnection();
T::ok(!$t['ok'] && strpos((string) $t['error'], 'rechazó la conexión') !== false, 'puerto cerrado: mensaje amable');
Settings::set('smtp_host', 'servidor-que-no-existe.invalid');
$t = Mailer::testConnection();
T::ok(!$t['ok'] && $t['error'] !== null, 'servidor inexistente: mensaje amable (' . $t['error'] . ')');
SaHelper::startSilent(25256);
$c = new SmtpClient('127.0.0.1', 25256, 'none', '', '', 3);
$t0 = microtime(true);
try {
    $c->connect();
    T::ok(false, 'servidor mudo debía fallar');
} catch (RuntimeException $e) {
    T::ok(strpos($e->getMessage(), 'tardó demasiado') !== false && microtime(true) - $t0 < 6, 'servidor que no responde: tiempo de espera de 3 s (' . round(microtime(true) - $t0, 1) . ' s)');
}

T::section('Cola con reintentos y espera creciente');
$set(25255, 'none', '', '');
$id = Mailer::queue('cola@example.test', 'Cola', 'En cola', '<p>cola</p>', 'cola', [['name' => 'a.txt', 'mime' => 'text/plain', 'content' => "bin\0ario"]]);
T::ok($id > 0 && Db::val('SELECT status FROM email_queue WHERE id = ?', [$id]) === 'pending', 'queue deja el correo pendiente');
file_put_contents($out['open'] . '.fail', '2');
$res = Mailer::processQueue();
T::eq(['sent' => 0, 'failed' => 0, 'retry' => 1], $res, 'primer intento falla y queda para reintento');
$row = Db::one('SELECT * FROM email_queue WHERE id = ?', [$id]);
T::ok((int) $row['attempts'] === 1 && $row['last_error'] !== null && strtotime($row['send_after'] . ' UTC') > time() + 30, 'se registró el error y la próxima hora es futura');
T::eq(['sent' => 0, 'failed' => 0, 'retry' => 0], Mailer::processQueue(), 'antes de la hora no se reintenta');
Db::exec('UPDATE email_queue SET send_after = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 1), $id]);
T::eq(1, Mailer::processQueue()['retry'], 'segundo intento falla');
$gap2 = strtotime(Db::val('SELECT send_after FROM email_queue WHERE id = ?', [$id]) . ' UTC') - time();
T::ok($gap2 > 4 * 60 && $gap2 <= 5 * 60 + 5, 'espera creciente: segundo reintento a ~5 min');
Db::exec('UPDATE email_queue SET send_after = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 1), $id]);
T::eq(1, Mailer::processQueue()['sent'], 'tercer intento entrega el correo');
$row = Db::one('SELECT * FROM email_queue WHERE id = ?', [$id]);
T::ok($row['status'] === 'sent' && $row['sent_at'] !== null && $row['last_error'] === null && (int) $row['attempts'] === 3, 'estado enviado, 3 intentos');
$g = SaHelper::jsonl($out['open']);
[, $parts] = SaHelper::parseEml(end($g)['data']);
T::eq("bin\0ario", $parts[2]['content'] ?? null, 'el adjunto binario viajó por la cola intacto');

file_put_contents($out['open'] . '.fail', '99');
$id2 = Mailer::queue('falla@example.test', null, 'Siempre falla', '<p>x</p>');
$last = null;
for ($i = 0; $i < 6; $i++) {
    Db::exec("UPDATE email_queue SET send_after = ? WHERE id = ? AND status = 'pending'", [gmdate('Y-m-d H:i:s', time() - 1), $id2]);
    $last = Mailer::processQueue();
}
$row = Db::one('SELECT * FROM email_queue WHERE id = ?', [$id2]);
T::ok($row['status'] === 'failed' && (int) $row['attempts'] === 5 && strpos((string) $row['last_error'], '451') !== false, 'tras 5 intentos queda fallido con el último error');
T::eq(0, Mailer::queue('malo', null, 'x', 'x'), 'queue con dirección inválida devuelve 0');
T::eq('failed', Db::val("SELECT status FROM email_queue WHERE to_email = 'malo'"), 'y queda registrado como fallido');
unlink($out['open'] . '.fail');

T::section('Respaldo a mail() sin smtp_host');
Settings::set('smtp_host', '');
$t = Mailer::testConnection();
T::ok($t['ok'] && strpos($t['detail'], 'mail()') !== false, 'testConnection sin SMTP informa del respaldo');
$sendmail = $dir . '/fake-sendmail.sh';
$eml = $dir . '/mail.eml';
file_put_contents($sendmail, "#!/bin/sh\ncat > " . escapeshellarg($eml) . "\n");
chmod($sendmail, 0755);
$code = 'require ' . var_export(dirname(__DIR__, 2) . '/app/bootstrap.php', true) . ';'
    . 'echo json_encode(App\\Services\\Mailer::sendNow("destino@example.test", "Destino Ñ", "Prueba mail() ñ", "<p>Hola ñ</p>", "Hola ñ", [["name"=>"x.ics","mime"=>"text/calendar","content"=>"ABC"]]));';
$j = json_decode((string) shell_exec(escapeshellarg(PHP_BINARY) . ' -d sendmail_path=' . escapeshellarg($sendmail . ' -t -i') . ' -r ' . escapeshellarg($code) . ' 2>&1'), true);
T::ok(($j['ok'] ?? false) === true, 'sendNow usa mail() cuando no hay servidor SMTP');
$raw = (string) @file_get_contents($eml);
T::ok($raw !== '' && stripos($raw, 'To:') !== false && stripos($raw, 'Subject: =?UTF-8?B?') !== false, 'sendmail recibió cabeceras To y Subject codificada');
[$h, $parts] = SaHelper::parseEml(str_replace("\n", "\r\n", str_replace("\r\n", "\n", $raw)));
T::ok(count($parts) === 3 && $parts[2]['content'] === 'ABC', 'el mensaje de mail() trae texto, HTML y adjunto');
$j = json_decode((string) shell_exec(escapeshellarg(PHP_BINARY) . ' -d sendmail_path=' . escapeshellarg('/bin/false') . ' -r ' . escapeshellarg($code) . ' 2>&1'), true);
T::ok(($j['ok'] ?? true) === false && strpos((string) ($j['error'] ?? ''), 'SMTP') !== false, 'si mail() falla, mensaje que sugiere configurar SMTP');

T::section('Plantilla de marca y seguridad del HTML');
Settings::set('color_gold', '#B8860B');
Settings::set('business_name', 'Clínica <b>Ñ</b>');
$l = Mailer::layout('Hola <script>alert(1)</script>', '<p>contenido</p>', ['button' => ['label' => 'Abrir', 'url' => 'https://example.test/r?a=1&b=2'], 'preheader' => 'Vista previa "x"']);
T::ok(strpos($l, '<script>') === false && strpos($l, '&lt;script&gt;') !== false, 'el título se escapa');
T::ok(strpos($l, '&lt;b&gt;') !== false && strpos($l, '<b>Ñ') === false, 'el nombre del negocio se escapa');
T::ok(strpos($l, '#B8860B') !== false && strpos($l, 'href="https://example.test/r?a=1&amp;b=2"') !== false && strpos($l, 'Abrir') !== false, 'color de oro y botón presentes');
T::ok(strpos($l, '<table') !== false && strpos($l, 'style="') !== false && strpos($l, '<script') === false, 'tablas y estilos en línea, sin scripts');
$l2 = Mailer::layout('x', '<p>y</p>', ['button' => ['label' => 'Mal', 'url' => 'javascript:alert(1)']]);
T::ok(strpos($l2, 'javascript:') === false, 'un botón con esquema peligroso se descarta');
$h = Mailer::textToHtml("Hola <img src=x onerror=alert(1)>\n\n**negrita** y https://example.test/a?x=1&y=2.");
T::ok(strpos($h, '<img') === false && strpos($h, '<strong>negrita</strong>') !== false && strpos($h, 'href="https://example.test/a?x=1&amp;y=2"') !== false, 'textToHtml escapa, pone negrita y enlaza');
T::eq("Hola\n\nBye", Mailer::htmlToText('<style>p{}</style><p>Hola</p><p>Bye</p>'), 'htmlToText limpia estilos y etiquetas');
$set(25299, 'none');
$s = Mailer::sendTest('prueba@example.test');
T::ok(!$s['ok'], 'sendTest sin servidor funcional devuelve error amable en vez de lanzar');

T::done();
