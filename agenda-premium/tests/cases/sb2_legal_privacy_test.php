<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
T::boot('sb2_legal');

use App\Core\Clock;
use App\Core\Db;
use App\Core\Settings;
use App\Services\LegalService;
use App\Services\PrivacyService;

T::section('LegalService: textos por defecto');
$d = LegalService::defaults();
T::eq(['privacy', 'terms', 'cookies'], array_keys($d), 'tres documentos');
foreach ($d as $k => $t) {
    T::ok(str_contains($t, 'Negocio de Prueba'), "$k menciona el nombre del negocio");
    T::ok(mb_strlen($t) > 600, "$k tiene contenido sustancial");
    T::ok(!preg_match('/lorem|ipsum|\{\w+\}/i', $t), "$k sin relleno ni marcadores");
}
T::ok(str_contains($d['privacy'], 'negocio@example.test') && str_contains($d['privacy'], '+502 5555 1234'), 'el aviso incluye correo y teléfono de contacto');
T::ok(str_contains($d['privacy'], 'NIT') && str_contains($d['privacy'], 'nombre, correo electrónico y teléfono'), 'declara los datos que se recogen');
T::ok(str_contains($d['cookies'], 'estrictamente necesarios') && str_contains($d['cookies'], 'No usamos cookies de publicidad'), 'cookies: solo necesarias');
T::ok(!preg_match('/GDPR|LGPD|RGPD|ley de protección/i', implode(' ', $d)), 'sin prometer cumplimiento de leyes específicas');
Settings::set('retention_months', '24');
T::ok(str_contains(LegalService::defaults()['privacy'], '24 meses'), 'el plazo de conservación sale de la configuración');
Settings::set('retention_months', '0');
T::ok(str_contains(LegalService::defaults()['privacy'], 'hasta que nos pidas eliminarlos'), 'sin plazo: conserva hasta que lo pidan');

T::section('LegalService: guardar y versionar');
$v0 = Settings::int('legal_version');
T::eq(1, $v0, 'la instalación deja la versión 1 (el primer guardado no la incrementa)');
T::ok(Settings::get('privacy_text') !== '' && Settings::get('terms_text') !== '' && Settings::get('cookies_notice') !== '', 'el instalador guardó los tres textos');
T::eq(hash('sha256', (string) Settings::get('privacy_text')), Settings::get('legal_hash_privacy'), 'hash SHA-256 del aviso guardado');
LegalService::save(LegalService::defaults());
T::eq($v0, Settings::int('legal_version'), 'guardar sin cambios no incrementa la versión');
LegalService::save(['privacy' => LegalService::defaults()['privacy'] . "\n\n**10. Contacto de privacidad**\nEscríbenos a privacidad@example.test."]);
T::eq($v0 + 1, Settings::int('legal_version'), 'un cambio incrementa la versión');
T::ok(Settings::get('terms_text') !== '', 'los documentos no enviados se conservan');
T::eq(hash('sha256', (string) Settings::get('privacy_text')), Settings::get('legal_hash_privacy'), 'hash actualizado');
T::throws(static fn () => LegalService::save(['terms' => str_repeat('a', 100001)]), 'texto excesivo rechazado', InvalidArgumentException::class, 'demasiado largo');
$c = LegalService::consentText();
T::eq($v0 + 1, $c['version'], 'consentText: versión vigente');
T::eq(hash('sha256', $c['texts']['privacy']), $c['hashes']['privacy'], 'consentText: hash coincide con el texto');
T::ok($c['label'] !== '' && isset($c['texts']['terms'], $c['hashes']['cookies']), 'consentText: etiqueta, textos y hashes');

T::section('LegalService: consentimiento');
LegalService::recordConsent(null, null, ' Persona@Example.TEST ', '203.0.113.77');
$rows = Db::all('SELECT * FROM consents ORDER BY id');
T::eq(2, count($rows), 'dos filas: privacidad y términos');
T::eq('203.0.113.0', $rows[0]['ip_trunc'], 'IPv4 truncada al /24');
T::eq('persona@example.test', $rows[0]['email'], 'correo normalizado');
T::eq($c['hashes']['privacy'], $rows[0]['text_hash'], 'hash del texto vigente');
T::eq($v0 + 1, (int) $rows[0]['version'], 'versión vigente');
T::ok(abs(strtotime($rows[0]['created_at'] . ' UTC') - Clock::now()) < 5, 'fecha UTC actual');
LegalService::recordConsent(null, null, null, '2001:db8:abcd:1234:5678::1');
T::eq('2001:db8:abcd::', Db::val('SELECT ip_trunc FROM consents ORDER BY id DESC LIMIT 1'), 'IPv6 truncada al /48');
LegalService::recordConsent(null, null, null, 'no-es-ip');
T::eq(null, Db::val('SELECT ip_trunc FROM consents ORDER BY id DESC LIMIT 1'), 'IP inválida no se guarda');
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM consents WHERE ip_trunc LIKE '%.77'"), 'nunca se guarda la IP completa');

// ---------------------------------------------------------------- Privacidad
T::section('PrivacyService: preparación de datos');
$pdo = Db::pdo();
$now = Clock::utc();
$host = (int) Db::val('SELECT id FROM hosts ORDER BY id LIMIT 1');
\App\Services\ProfessionService::apply('medico', false);
$ev = (int) Db::val('SELECT id FROM event_types ORDER BY id LIMIT 1');

$mk = static function (string $name, string $email, string $phone, string $start, string $status = 'completed', float $total = 300) use ($host, $ev, $now): array {
    $cid = Db::insert('clients', ['name' => $name, 'email' => $email, 'phone' => $phone, 'nit' => '1234567-8', 'tags' => 'vip,demo', 'source' => 'web', 'timezone' => 'America/Guatemala', 'created_at' => $now, 'updated_at' => $now]);
    $ts = strtotime($start . ' UTC');
    $bid = Db::insert('bookings', [
        'token' => bin2hex(random_bytes(16)), 'event_type_id' => $ev, 'host_id' => $host, 'client_id' => $cid,
        'starts_at' => $start, 'ends_at' => gmdate('Y-m-d H:i:s', $ts + 1800), 'blocked_start' => $start, 'blocked_end' => gmdate('Y-m-d H:i:s', $ts + 1800),
        'duration' => 30, 'status' => $status, 'guest_name' => $name, 'guest_email' => $email, 'guest_phone' => $phone, 'price' => $total, 'total' => $total,
        'paid_amount' => $total, 'payment_status' => 'paid', 'notes' => 'Me duele la rodilla ' . strtok($name, ' '), 'internal_note' => 'Paciente puntual', 'cancel_reason' => null,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    Db::insert('booking_hosts', ['booking_id' => $bid, 'host_id' => $host]);
    Db::insert('booking_attendees', ['booking_id' => $bid, 'name' => 'Acompañante de ' . $name, 'email' => 'acomp@example.test']);
    Db::insert('booking_answers', ['booking_id' => $bid, 'label' => 'Motivo', 'value' => 'Dolor de rodilla derecha ' . strtok($name, ' ')]);
    Db::insert('client_notes', ['client_id' => $cid, 'body' => 'Alérgica a la penicilina ' . strtok($name, ' '), 'created_at' => $now]);
    Db::insert('payments', ['booking_id' => $bid, 'client_id' => $cid, 'amount' => $total, 'method' => 'transfer', 'status' => 'verified', 'reference' => 'BI-998877-' . strtok($name, ' '), 'note' => 'Transferencia de ' . $name, 'created_at' => $now]);
    Db::insert('consents', ['client_id' => $cid, 'booking_id' => $bid, 'email' => $email, 'document' => 'privacy', 'version' => 1, 'text_hash' => str_repeat('a', 64), 'ip_trunc' => '10.0.0.0', 'created_at' => $now]);
    Db::insert('waitlist', ['event_type_id' => $ev, 'name' => $name, 'email' => $email, 'phone' => $phone, 'duration' => 30, 'created_at' => $now]);
    Db::insert('reviews', ['token' => bin2hex(random_bytes(16)), 'booking_id' => $bid, 'host_id' => $host, 'client_name' => $name, 'rating' => 5, 'comment' => 'Excelente trato', 'status' => 'approved', 'created_at' => $now]);
    Db::insert('message_queue', ['booking_id' => $bid, 'client_id' => $cid, 'phone' => $phone, 'body' => 'Hola ' . $name, 'due_at' => $now, 'created_at' => $now]);
    Db::insert('email_queue', ['to_email' => $email, 'to_name' => $name, 'subject' => 'Tu cita', 'body_html' => '<p>Hola ' . $name . '</p>', 'send_after' => $now, 'created_at' => $now]);
    $dir = APP_ROOT . '/storage/uploads/test';
    @mkdir($dir, 0750, true);
    $file = $dir . '/' . bin2hex(random_bytes(6));
    file_put_contents($file, 'contenido privado');
    $fid = Db::insert('files', ['token' => bin2hex(random_bytes(16)), 'original_name' => 'examen.pdf', 'stored_name' => 'test/' . basename($file), 'mime' => 'application/pdf', 'size' => 17, 'owner_type' => 'booking', 'owner_id' => $bid, 'created_at' => $now]);
    $proof = $dir . '/' . bin2hex(random_bytes(6));
    file_put_contents($proof, 'comprobante');
    $pid = Db::insert('files', ['token' => bin2hex(random_bytes(16)), 'original_name' => 'comprobante.jpg', 'stored_name' => 'test/' . basename($proof), 'mime' => 'image/jpeg', 'size' => 11, 'created_at' => $now]);
    Db::exec('UPDATE payments SET proof_file_id = ? WHERE booking_id = ?', [$pid, $bid]);
    Db::insert('booking_answers', ['booking_id' => $bid, 'label' => 'Examen', 'file_id' => $fid]);
    return ['client' => $cid, 'booking' => $bid, 'files' => [$file, $proof], 'file_ids' => [$fid, $pid]];
};
$a = $mk('María López Secreta', 'maria.secreta@example.test', '50255550001', '2026-09-01 15:00:00');
$b = $mk('Pedro Persona Distinta', 'pedro.otro@example.test', '50255550002', '2026-09-02 15:00:00');
$before = ['bookings' => (int) Db::val('SELECT COUNT(*) FROM bookings'), 'payments' => (int) Db::val('SELECT COUNT(*) FROM payments'), 'total' => (float) Db::val('SELECT SUM(total) FROM bookings')];

T::section('PrivacyService::exportPerson');
$x = PrivacyService::exportPerson($a['client']);
json_encode($x, JSON_THROW_ON_ERROR);
T::ok(true, 'el arreglo es serializable a JSON');
T::eq('María López Secreta', $x['cliente']['name'], 'cliente');
T::eq(1, count($x['citas']), 'sus citas');
T::ok(!isset($x['citas'][0]['token']), 'sin token de la cita');
T::eq(2, count($x['respuestas']), 'respuestas');
T::eq(1, count($x['invitados']), 'invitados');
T::eq(1, count($x['notas']), 'notas');
T::eq(1, count($x['pagos']), 'pagos');
T::eq(1, count($x['consentimientos']), 'consentimientos');
T::eq(2, count($x['archivos']), 'archivos (solo metadatos)');
T::ok(!isset($x['archivos'][0]['stored_name']) && !isset($x['archivos'][0]['token']), 'metadatos sin rutas ni tokens');
T::eq(1, count($x['lista_de_espera']), 'lista de espera');
T::eq(1, count($x['resenas']), 'reseñas');
T::ok(!str_contains(json_encode($x), 'Pedro Persona'), 'no incluye datos de otras personas');
T::throws(static fn () => PrivacyService::exportPerson(999999), 'persona inexistente', InvalidArgumentException::class, 'No encontramos');

T::section('PrivacyService::erasePerson');
PrivacyService::erasePerson($a['client']);
$cl = Db::one('SELECT * FROM clients WHERE id = ?', [$a['client']]);
T::eq('Persona eliminada', $cl['name'], 'cliente anonimizado');
T::ok($cl['email'] === null && $cl['phone'] === null && $cl['nit'] === null && $cl['tags'] === null && $cl['source'] === null, 'correo, teléfono, NIT, etiquetas y origen borrados');
T::ok($cl['anonymized_at'] !== null, 'anonymized_at establecido');
$bk = Db::one('SELECT * FROM bookings WHERE id = ?', [$a['booking']]);
T::ok($bk['guest_name'] === 'Persona eliminada' && $bk['guest_email'] === null && $bk['guest_phone'] === null && $bk['notes'] === null && $bk['internal_note'] === null, 'datos de la cita anonimizados');
T::eq(300.0, (float) $bk['total'], 'se conserva el total');
T::eq('completed', $bk['status'], 'se conserva el estado');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM booking_answers WHERE booking_id = ?', [$a['booking']]), 'respuestas borradas');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM booking_attendees WHERE booking_id = ?', [$a['booking']]), 'invitados borrados');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM client_notes WHERE client_id = ?', [$a['client']]), 'notas borradas');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM waitlist WHERE email = ?', ['maria.secreta@example.test']), 'lista de espera borrada');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM consents WHERE client_id = ? OR email = ?', [$a['client'], 'maria.secreta@example.test']), 'consentimientos borrados');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM message_queue WHERE client_id = ?', [$a['client']]), 'mensajes en cola borrados');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM email_queue WHERE to_email = ?', ['maria.secreta@example.test']), 'correos en cola borrados');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM files WHERE id IN (?, ?)', $a['file_ids']), 'registros de archivos borrados');
T::ok(!is_file($a['files'][0]) && !is_file($a['files'][1]), 'archivos físicos borrados');
$pay = Db::one('SELECT * FROM payments WHERE booking_id = ?', [$a['booking']]);
T::ok($pay !== null && (float) $pay['amount'] === 300.0 && $pay['reference'] === null && $pay['note'] === null && $pay['proof_file_id'] === null, 'pago conservado sin referencia, nota ni comprobante');
$rv = Db::one('SELECT * FROM reviews WHERE booking_id = ?', [$a['booking']]);
T::ok($rv['client_name'] === 'Persona eliminada' && $rv['comment'] === null && (int) $rv['rating'] === 5, 'reseña anonimizada con su calificación');
T::ok(Db::val("SELECT COUNT(*) FROM audit_log WHERE action = 'privacy.erase' AND entity_id = ?", [(string) $a['client']]) == 1, 'queda registro en la auditoría');
T::ok(!str_contains(json_encode(Db::all("SELECT detail FROM audit_log WHERE action = 'privacy.erase'")), 'María'), 'la auditoría no guarda el nombre');
// Búsqueda en TODAS las tablas del texto personal
$needles = ['María López Secreta', 'maria.secreta@example.test', '50255550001', 'Me duele la rodilla María', 'Alérgica a la penicilina María', 'BI-998877-María', 'Dolor de rodilla derecha María'];
$hit = [];
foreach (Db::col('SHOW TABLES') as $t) {
    $cols = Db::col("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND DATA_TYPE IN ('varchar','char','text','mediumtext')", [$t]);
    foreach ($cols as $col) {
        foreach ($needles as $n) {
            if ((int) Db::val("SELECT COUNT(*) FROM `$t` WHERE `$col` LIKE ?", ['%' . $n . '%']) > 0) {
                $hit[] = "$t.$col";
            }
        }
    }
}
T::eq([], $hit, 'la persona es irrecuperable en todas las tablas');
T::eq($before['bookings'], (int) Db::val('SELECT COUNT(*) FROM bookings'), 'se conservan todas las citas (estadística)');
T::eq($before['payments'], (int) Db::val('SELECT COUNT(*) FROM payments'), 'se conservan todos los pagos');
T::eq($before['total'], (float) Db::val('SELECT SUM(total) FROM bookings'), 'se conserva la suma de ingresos');
$other = Db::one('SELECT * FROM clients WHERE id = ?', [$b['client']]);
T::ok($other['name'] === 'Pedro Persona Distinta' && Db::val('SELECT COUNT(*) FROM booking_answers WHERE booking_id = ?', [$b['booking']]) == 2 && is_file($b['files'][0]), 'otra persona no se toca');
PrivacyService::erasePerson($a['client']);
T::ok(true, 'repetir la eliminación no falla (idempotente)');
$exp = PrivacyService::exportPerson($a['client']);
T::ok($exp['cliente']['name'] === 'Persona eliminada' && !$exp['respuestas'] && !$exp['notas'], 'exportar a alguien ya eliminado no devuelve datos');

T::section('PrivacyService::applyRetention');
T::eq(0, PrivacyService::applyRetention(), '0 meses = nunca');
Clock::set(strtotime('2026-10-04 12:00:00 UTC'));
// Cliente C: última cita hace ~13 meses; cliente D: cita reciente
$c = $mk('Carla Antigua', 'carla.antigua@example.test', '50255550003', '2025-08-01 15:00:00');
$d = $mk('Diego Reciente', 'diego.reciente@example.test', '50255550004', '2026-09-20 15:00:00');
Db::exec('UPDATE clients SET created_at = ? WHERE id IN (?, ?)', ['2025-07-01 00:00:00', $c['client'], $d['client']]);
Db::exec("UPDATE consents SET created_at = '2025-07-01 00:00:00' WHERE client_id = ?", [$d['client']]);
Db::insert('consents', ['email' => 'viejo@example.test', 'document' => 'terms', 'version' => 1, 'text_hash' => str_repeat('b', 64), 'created_at' => '2024-01-01 00:00:00']);
Db::exec("UPDATE bookings SET starts_at = '2025-05-01 15:00:00', ends_at = '2025-05-01 15:30:00' WHERE id = ?", [$d['booking']]);
$d2 = Db::insert('bookings', ['token' => bin2hex(random_bytes(16)), 'event_type_id' => $ev, 'host_id' => $host, 'client_id' => $d['client'], 'starts_at' => '2026-09-20 15:00:00', 'ends_at' => '2026-09-20 15:30:00', 'blocked_start' => '2026-09-20 15:00:00', 'blocked_end' => '2026-09-20 15:30:00', 'duration' => 30, 'status' => 'completed', 'guest_name' => 'Diego Reciente', 'guest_email' => 'diego.reciente@example.test', 'guest_phone' => '50255550004', 'created_at' => $now, 'updated_at' => $now]);
Settings::set('retention_months', '12');
$n = PrivacyService::applyRetention();
T::ok($n >= 3, "retención procesó registros ($n)");
T::eq('Persona eliminada', Db::val('SELECT name FROM clients WHERE id = ?', [$c['client']]), 'cliente inactivo anonimizado');
T::eq('Persona eliminada', Db::val('SELECT guest_name FROM bookings WHERE id = ?', [$c['booking']]), 'su cita antigua anonimizada');
T::eq('Diego Reciente', Db::val('SELECT name FROM clients WHERE id = ?', [$d['client']]), 'cliente con cita reciente se conserva');
T::eq('Persona eliminada', Db::val('SELECT guest_name FROM bookings WHERE id = ?', [$d['booking']]), 'cita de hace más de 12 meses del cliente activo anonimizada');
T::eq('Diego Reciente', Db::val('SELECT guest_name FROM bookings WHERE id = ?', [$d2]), 'su cita reciente se conserva');
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM consents WHERE created_at < '2025-10-04'"), 'consentimientos viejos borrados');
T::eq(0, PrivacyService::applyRetention(), 'segunda pasada no encuentra nada que procesar');
T::eq('Persona eliminada', Db::val('SELECT name FROM clients WHERE id = ?', [$a['client']]), 'los ya eliminados siguen eliminados');
Clock::set(null);
foreach (glob(APP_ROOT . '/storage/uploads/test/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir(APP_ROOT . '/storage/uploads/test');
T::done();
