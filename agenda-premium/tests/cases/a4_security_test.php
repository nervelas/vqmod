<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
require __DIR__ . '/a4_http.inc.php';
T::boot('a4sec');

use App\Core\Crypto;
use App\Core\Db;
use App\Core\Settings;

$cfg = '/tmp/ap-t-a4sec.config.php';
$now = gmdate('Y-m-d H:i:s');
foreach ([['Anfitrión', 'host@test.local', 'host'], ['Recepción', 'rec@test.local', 'reception']] as [$n, $e, $r]) {
    Db::insert('users', ['name' => $n, 'email' => $e, 'password_hash' => App\Core\Crypto::hashPassword('Prueba#Segura2026'), 'role' => $r, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
}
$http = new A4Http(8191, $cfg);
register_shutdown_function(static fn () => $http->stop());

$gets = ['/admin/ajustes', '/admin/legal', '/admin/comunicaciones', '/admin/sistema', '/admin/actividad', '/admin/respaldo', '/admin/asistente', '/admin/sistema/registro', '/admin/respaldo/exportar', '/admin/legal/consentimientos.csv', '/admin/actividad/exportar'];
$posts = ['/admin/ajustes', '/admin/legal', '/admin/comunicaciones', '/admin/sistema/cron', '/admin/respaldo/crear', '/admin/asistente/1', '/admin/comunicaciones/cron-token', '/admin/sistema/registro/vaciar'];

T::section('Roles: anfitrión y recepción reciben 403');
foreach (['host@test.local', 'rec@test.local'] as $u) {
    T::ok($http->login($u, 'Prueba#Segura2026'), "login $u");
    $bad = [];
    foreach ($gets as $g) {
        if ($http->req('GET', $g)['code'] !== 403) {
            $bad[] = $g;
        }
    }
    T::eq([], $bad, "$u: GET prohibidos");
    $csrf = $http->csrf('/admin');
    $bad = [];
    foreach ($posts as $p) {
        if ($http->req('POST', $p, ['_csrf' => $csrf])['code'] !== 403) {
            $bad[] = $p;
        }
    }
    T::eq([], $bad, "$u: POST prohibidos");
}
$http->newSession();
T::eq(302, $http->req('GET', '/admin/ajustes')['code'], 'sin sesión redirige al login');

T::section('Administrador y CSRF');
T::ok($http->login('admin@test.local', 'Prueba#Segura2026'), 'login admin');
foreach (['/admin/ajustes', '/admin/legal', '/admin/comunicaciones', '/admin/sistema', '/admin/actividad', '/admin/respaldo', '/admin/asistente'] as $g) {
    T::eq(200, $http->req('GET', $g)['code'], "GET $g = 200");
}
foreach ($posts as $p) {
    T::eq(419, $http->req('POST', $p, ['business_name' => 'x'])['code'], "POST $p sin CSRF = 419");
}
T::eq(419, $http->req('POST', '/admin/ajustes', ['_csrf' => 'deadbeef', 'business_name' => 'x'])['code'], 'CSRF falso = 419');

T::section('Ajustes: validación, XSS y SQLi');
$ok = ['business_name' => 'Mi Negocio', 'tagline' => 'Eslogan', 'about' => '', 'color_gold' => '#C9A050', 'phone_cc' => '502', 'whatsapp' => '5555 1234', 'phone' => '', 'email' => 'a@b.com', 'address' => '', 'map_url' => '', 'website' => '', 'social_facebook' => '', 'social_instagram' => '', 'social_tiktok' => '', 'social_youtube' => '',
    'timezone' => 'America/Guatemala', 'time_format' => '12', 'currency_symbol' => 'Q', 'profession' => 'otro', 'terms_label' => 'cita', 'terms_label_plural' => 'citas', 'host_label' => 'profesional', 'client_label' => 'cliente',
    'public_home_enabled' => '1', 'pending_expire_hours' => '48', 'waitlist_offer_minutes' => '15', 'noshow_block_after' => '0', 'noshow_deposit_after' => '2', 'noshow_deposit_percent' => '50', 'video_provider_domain' => 'meet.jit.si', 'embed_allowed_origins' => '*', 'bank_info' => '', 'payment_link' => ''];
$r = $http->post('/admin/ajustes', $ok, true);
T::eq(302, $r['code'], 'guardado válido redirige');
Settings::flush();
T::eq('Mi Negocio', Settings::get('business_name'), 'nombre guardado');
T::eq('50255551234', Settings::get('whatsapp'), 'WhatsApp normalizado con 502');
foreach ([['color_gold', 'rojo'], ['color_gold', '#12'], ['map_url', 'javascript:alert(1)'], ['map_url', 'http://insegura.com'], ['website', 'ftp://x.com'], ['timezone', 'Marte/Fobos'], ['email', 'no-correo'], ['embed_allowed_origins', "https://a.com; script-src *"], ['video_provider_domain', 'evil.com/<x>'], ['pending_expire_hours', '-5'], ['noshow_deposit_percent', '900'], ['phone_cc', 'abc'], ['business_name', '']] as [$k, $v]) {
    $res = $http->post('/admin/ajustes', [$k => $v] + $ok, true);
    T::eq(422, $res['code'], "rechaza $k=" . substr($v, 0, 25));
}
$xss = '<script>alert(1)</script>';
$res = $http->post('/admin/ajustes', ['business_name' => $xss] + $ok, true);
T::eq(422, $res['code'], 'nombre con < > rechazado');
T::ok(strpos($res['body'], $xss) === false, 'el HTML no refleja el script sin escapar');
$res = $http->post('/admin/ajustes', ['about' => $xss . '"><img src=x onerror=alert(1)>', 'bank_info' => "' OR 1=1; DROP TABLE users;--"] + $ok, true);
T::eq(302, $res['code'], 'texto libre con HTML/SQL se guarda como texto');
$page = $http->req('GET', '/admin/ajustes')['body'];
T::ok(strpos($page, '<script>alert(1)') === false && strpos($page, '<img src=x') === false, 'about escapado al mostrarse');
T::ok(strpos($page, '&lt;script&gt;alert(1)') !== false, 'about visible como texto escapado');
T::eq(3, (int) Db::val('SELECT COUNT(*) FROM users'), 'SQLi no afectó la tabla users');
T::ok(substr_count($page, '<script') === substr_count($page, '<script src='), 'sin <script> en línea en ajustes');
T::ok(strpos($page, ' style="') === false, 'sin atributos style en ajustes');

T::section('Subida de logo maliciosa');
$tmp = sys_get_temp_dir() . '/a4up';
@mkdir($tmp);
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
file_put_contents("$tmp/ok.png", $png);
file_put_contents("$tmp/shell.php", '<?php system($_GET[0]);');
file_put_contents("$tmp/shell.php.png", '<?php system($_GET[0]);');
file_put_contents("$tmp/fake.png", '<?php system($_GET[0]); ?>');
file_put_contents("$tmp/vec.svg", '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
file_put_contents("$tmp/doble.jpg.php", $png);
foreach (['shell.php' => 'image/png', 'shell.php.png' => 'image/png', 'fake.png' => 'image/png', 'vec.svg' => 'image/svg+xml', 'doble.jpg.php' => 'image/jpeg'] as $f => $mime) {
    $before = (int) Db::val('SELECT COUNT(*) FROM files');
    $res = $http->post('/admin/ajustes', $ok + ['logo' => new CURLFile("$tmp/$f", $mime, $f)], true);
    T::eq(422, $res['code'], "logo $f rechazado");
    T::eq($before, (int) Db::val('SELECT COUNT(*) FROM files'), "$f no dejó registro de archivo");
}
$res = $http->post('/admin/ajustes', $ok + ['logo' => new CURLFile("$tmp/ok.png", 'image/png', 'ok.png')], true);
T::eq(302, $res['code'], 'logo PNG válido aceptado');
Settings::flush();
$fid = (int) Settings::get('logo_file_id');
T::ok($fid > 0, 'logo_file_id guardado');
$row = Db::one('SELECT * FROM files WHERE id = ?', [$fid]);
T::ok($row && (int) $row['is_public'] === 1 && strpos((string) $row['mime'], 'image/') === 0, 'archivo público y de imagen');
$f = $http->req('GET', '/f/' . $row['token']);
T::eq(200, $f['code'], 'logo se entrega por /f/token');
$res = $http->post('/admin/ajustes', $ok + ['remove_logo' => '1'], true);
Settings::flush();
T::eq('', (string) Settings::get('logo_file_id'), 'logo quitado');
T::eq(0, (int) Db::val('SELECT COUNT(*) FROM files WHERE id = ?', [$fid]), 'archivo del logo eliminado');

T::section('Secretos cifrados');
$secretPass = 'SuperClave-SMTP-98765';
$secretTok = 'EAAG-token-wa-ultra-secreto';
$secretCap = 'captcha-secret-XYZ-1234567';
$comm = ['smtp_host' => 'smtp.ejemplo.com', 'smtp_port' => '587', 'smtp_secure' => 'tls', 'smtp_user' => 'u', 'mail_from_name' => 'N', 'mail_from_email' => 'x@y.com', 'admin_notify_email' => '', 'wa_api_phone_id' => '123456789', 'wa_api_template' => 'recordatorio', 'wa_api_lang' => 'es', 'captcha_provider' => 'none', 'captcha_site_key' => '',
    'booking_rate_limit' => '10', 'booking_min_form_seconds' => '3', 'api_rate_per_minute' => '60', 'session_idle_minutes' => '120'];
$res = $http->post('/admin/comunicaciones', $comm + ['smtp_pass' => $secretPass, 'wa_api_token' => $secretTok, 'captcha_secret' => $secretCap]);
T::eq(302, $res['code'], 'comunicaciones guardadas');
foreach (['smtp_pass' => $secretPass, 'wa_api_token' => $secretTok, 'captcha_secret' => $secretCap] as $k => $plain) {
    $v = (string) Db::val('SELECT v FROM settings WHERE k = ?', [$k]);
    T::ok(strncmp($v, 'v1:', 3) === 0 && strpos($v, $plain) === false, "$k cifrado en la BD");
    T::eq($plain, Crypto::decrypt($v), "$k descifra correctamente");
}
$html = $http->req('GET', '/admin/comunicaciones')['body'];
foreach ([$secretPass, $secretTok, $secretCap] as $plain) {
    T::ok(strpos($html, $plain) === false, 'secreto no aparece en el HTML');
}
T::ok(strpos($html, '••••••• (guardada)') !== false, 'placeholder de secreto guardado');
T::ok(!preg_match('/type="password"[^>]*value="[^"]+"/', $html), 'campos de contraseña vacíos');
$before = (string) Db::val("SELECT v FROM settings WHERE k = 'smtp_pass'");
$http->post('/admin/comunicaciones', $comm + ['smtp_pass' => '']);
T::eq($before, (string) Db::val("SELECT v FROM settings WHERE k = 'smtp_pass'"), 'contraseña vacía conserva la guardada');
$res = $http->post('/admin/comunicaciones', ['captcha_provider' => 'turnstile', 'captcha_site_key' => ''] + $comm);
T::eq(422, $res['code'], 'captcha activo exige clave del sitio');
$res = $http->post('/admin/comunicaciones', ['smtp_host' => 'x; rm -rf /'] + $comm);
T::eq(422, $res['code'], 'host SMTP inválido rechazado');
T::eq(0, (int) Db::val("SELECT COUNT(*) FROM audit_log WHERE detail LIKE ? OR detail LIKE ?", ['%' . $secretPass . '%', '%' . $secretTok . '%']), 'auditoría sin secretos');
T::ok((int) Db::val("SELECT COUNT(*) FROM audit_log WHERE action = 'communications.update'") >= 1, 'cambio registrado en auditoría');

$old = Settings::get('cron_token');
$http->post('/admin/comunicaciones/cron-token');
Settings::flush();
T::ok(Settings::get('cron_token') !== $old && strlen((string) Settings::get('cron_token')) === 40, 'token de cron regenerado');

T::section('Legal');
$res = $http->post('/admin/legal', ['privacy_text' => 'Privacidad **v2**', 'terms_text' => 'Términos', 'cookies_notice' => 'Cookies']);
T::eq(302, $res['code'], 'legal guardado');
$res = $http->post('/admin/legal', ['privacy_text' => '', 'terms_text' => 'x', 'cookies_notice' => 'y']);
T::eq(422, $res['code'], 'legal vacío rechazado');
$page = $http->req('GET', '/admin/legal')['body'];
T::ok(strpos($page, 'revisadas por un abogado') !== false, 'advertencia legal visible');
$v1 = (int) Db::val("SELECT v FROM settings WHERE k = 'legal_version'");
$http->post('/admin/legal', ['privacy_text' => 'Privacidad cambiada', 'terms_text' => 'Términos', 'cookies_notice' => 'Cookies']);
T::eq($v1 + 1, (int) Db::val("SELECT v FROM settings WHERE k = 'legal_version'"), 'cambio de texto sube legal_version');
$http->post('/admin/legal/restaurar', ['doc' => 'privacy']);
T::ok(strpos((string) Db::val("SELECT v FROM settings WHERE k = 'privacy_text'"), 'Aviso de privacidad') !== false, 'restaurar plantilla');
T::eq(422, $http->post('/admin/legal/retencion', ['retention_months' => '999'])['code'], 'retención fuera de rango');
$http->post('/admin/legal/retencion', ['retention_months' => '36']);
T::eq('36', (string) Db::val("SELECT v FROM settings WHERE k = 'retention_months'"), 'retención guardada');
Db::insert('consents', ['email' => 'p@x.com', 'document' => 'privacy', 'version' => 1, 'text_hash' => str_repeat('a', 64), 'ip_trunc' => '10.0.0.0', 'created_at' => $now]);
Db::insert('consents', ['email' => '=HYPERLINK("http://x")', 'document' => 'terms', 'version' => 1, 'text_hash' => str_repeat('b', 64), 'ip_trunc' => '10.0.0.0', 'created_at' => $now]);
$csv = $http->req('GET', '/admin/legal/consentimientos.csv');
T::ok($csv['code'] === 200 && strpos($csv['headers'], 'text/csv') !== false && strpos($csv['body'], 'p@x.com') !== false, 'CSV de consentimientos');
T::ok(strpos($csv['body'], ',=HYPERLINK') === false && strpos($csv['body'], "'=HYPERLINK") !== false, 'CSV neutraliza fórmulas');

T::section('Actividad');
$a = $http->req('GET', '/admin/actividad?accion=settings');
T::ok($a['code'] === 200 && strpos($a['body'], 'settings.update') !== false, 'bitácora filtra por acción');
$a = $http->req('GET', "/admin/actividad?accion=" . urlencode("' OR 1=1 --") . '&desde=2026-13-45&usuario=abc');
T::eq(200, $a['code'], 'filtros maliciosos no rompen la página');
T::ok(strpos($http->req('GET', '/admin/actividad/exportar')['headers'], 'text/csv') !== false, 'exportar actividad CSV');

$http->stop();
T::done();
