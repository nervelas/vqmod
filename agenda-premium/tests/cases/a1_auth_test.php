<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
require __DIR__ . '/a1_http.inc.php';
T::boot('a1_auth');

use App\Core\Clock;
use App\Core\Crypto;
use App\Core\Db;
use App\Core\Totp;

A1Http::start(8113, '/tmp/ap-t-a1_auth.config.php');
$now = Clock::utc();
$mk = static function (string $email, string $pw, array $extra = []) use ($now): int {
    return Db::insert('users', array_merge(['name' => 'Usuario ' . $email, 'email' => $email, 'password_hash' => Crypto::hashPassword($pw), 'role' => 'admin', 'active' => 1, 'theme' => 'dark', 'created_at' => $now, 'updated_at' => $now], $extra));
};

T::section('Acceso y sesión');
$c = new A1Client();
$c->get('/admin');
T::eq(302, $c->last['status'], 'sin sesión /admin redirige');
T::ok(str_contains($c->location(), '/admin/login'), 'redirige a /admin/login');
$c->get('/admin/login');
T::eq(200, $c->last['status'], 'el formulario de acceso carga');
T::ok(str_contains($c->last['body'], 'Content-Security') === false && strpos($c->last['body'], '<script>') === false, 'sin <script> en línea en el acceso');
T::ok(!preg_match('/\sstyle="/', $c->last['body']), 'sin style="" en línea en el acceso');
$r = $c->request('POST', '/admin/login', ['email' => 'admin@test.local', 'password' => 'Prueba#Segura2026']);
T::eq(419, $r['status'], 'POST sin CSRF => 419');
$r = $c->postForm('/admin/login', '/admin/login', ['email' => 'admin@test.local', 'password' => 'incorrecta']);
T::eq(401, $r['status'], 'contraseña incorrecta => 401');
T::ok(str_contains($r['body'], 'Correo o contraseña incorrectos'), 'mensaje amable de error');
$r = $c->postForm('/admin/login', '/admin/login', ['email' => 'noexiste@test.local', 'password' => 'x']);
T::ok(str_contains($r['body'], 'Correo o contraseña incorrectos'), 'mismo mensaje si el correo no existe');
$r = $c->postForm('/admin/login', '/admin/login', ['email' => 'admin@test.local', 'password' => 'Prueba#Segura2026']);
T::eq(302, $r['status'], 'login correcto redirige');
T::ok(str_ends_with($c->location(), '/admin'), 'va a /admin');
$c->get('/admin');
T::eq(200, $c->last['status'], 'el panel carga con sesión');
T::ok(str_contains($c->last['body'], 'Buenos') || str_contains($c->last['body'], 'Buenas'), 'inicio con saludo');

T::section('Parámetro next');
foreach (['//evil.example/x' => '/admin', 'https://evil.example' => '/admin', '/admin/../etc' => '/admin', '/otra' => '/admin', '/admin/citas' => '/admin/citas', 'javascript:alert(1)' => '/admin'] as $next => $expect) {
    $x = new A1Client();
    $x->postForm('/admin/login', '/admin/login', ['email' => 'admin@test.local', 'password' => 'Prueba#Segura2026', 'next' => $next]);
    $loc = $x->location();
    T::ok(str_ends_with($loc, $expect) && !str_contains($loc, 'evil'), "next «{$next}» => {$expect}");
}

T::section('Bloqueo por intentos');
$mk('lock@test.local', 'Bloqueo#Pass2026');
$l = new A1Client();
for ($i = 0; $i < 5; $i++) {
    $l->postForm('/admin/login', '/admin/login', ['email' => 'lock@test.local', 'password' => 'mal' . $i]);
}
$r = $l->postForm('/admin/login', '/admin/login', ['email' => 'lock@test.local', 'password' => 'Bloqueo#Pass2026']);
T::eq(429, $r['status'], 'tras 5 fallos, ni la contraseña correcta entra');
T::ok(str_contains($r['body'], 'Demasiados intentos'), 'mensaje de espera');

T::section('Verificación en dos pasos');
$secret = Totp::newSecret();
$mk('dos@test.local', 'DosPasos#2026x', ['totp_secret' => Crypto::encrypt($secret), 'totp_enabled' => 1]);
$t = new A1Client();
$r = $t->postForm('/admin/login', '/admin/login', ['email' => 'dos@test.local', 'password' => 'DosPasos#2026x']);
T::eq(302, $r['status'], 'primer paso redirige');
T::ok(str_contains($t->location(), '/admin/2fa'), 'al segundo paso');
$t->get('/admin');
T::ok(str_contains($t->location(), '/admin/login'), 'sin el código aún no hay sesión');
$r = $t->postForm('/admin/2fa', '/admin/2fa', ['code' => '000000']);
T::ok(in_array($r['status'], [401, 200], true) && str_contains($r['body'], 'no es correcto'), 'código incorrecto rechazado');
$r = $t->postForm('/admin/2fa', '/admin/2fa', ['code' => Totp::code($secret)]);
T::eq(302, $r['status'], 'código correcto entra');
$t->get('/admin');
T::eq(200, $t->last['status'], 'sesión activa tras el 2FA');
$row = Db::one('SELECT totp_secret FROM users WHERE email = ?', ['dos@test.local']);
T::ok(str_starts_with((string) $row['totp_secret'], 'v1:'), 'el secreto TOTP está cifrado en la base');

T::section('Recuperación de contraseña');
$uid = $mk('olvido@test.local', 'Vieja#Clave2026');
$f = new A1Client();
$a = $f->postForm('/admin/olvide', '/admin/olvide', ['email' => 'olvido@test.local']);
$b = $f->postForm('/admin/olvide', '/admin/olvide', ['email' => 'nadie@test.local']);
$strip = static fn (string $h): string => (string) preg_replace(['/name="_csrf" value="[^"]+"/', '/nonce="[^"]+"/'], '', $h);
T::eq(200, $a['status'], 'respuesta 200 con correo existente');
T::eq($a['status'], $b['status'], 'mismo estado si no existe');
T::ok($strip(preg_replace('/olvido@test\.local|nadie@test\.local/', 'X', $a['body'])) === $strip(preg_replace('/olvido@test\.local|nadie@test\.local/', 'X', $b['body'])), 'respuesta idéntica exista o no el correo');
$u = Db::one('SELECT reset_token_hash, reset_expires_at FROM users WHERE id = ?', [$uid]);
T::ok(strlen((string) $u['reset_token_hash']) === 64, 'se guarda el hash SHA-256 (64 hex)');
$ttl = strtotime($u['reset_expires_at'] . ' UTC') - Clock::now();
T::ok($ttl > 3500 && $ttl <= 3600, 'caduca en 1 hora');
$q = Db::one("SELECT body_text, body_html FROM email_queue WHERE to_email = 'olvido@test.local' ORDER BY id DESC LIMIT 1");
if ($q && preg_match('#restablecer/([a-f0-9]{32})#', $q['body_text'] . $q['body_html'], $m)) {
    T::eq(hash('sha256', $m[1]), $u['reset_token_hash'], 'el token del correo coincide con el hash guardado (128 bits)');
} else {
    echo "  --   el correo salió directo (sin cola); se verifica el flujo con un token propio\n";
}
$token = bin2hex(random_bytes(16));
Db::update('users', ['reset_token_hash' => hash('sha256', $token), 'reset_expires_at' => Clock::utc(Clock::now() + 3000)], 'id = ?', [$uid]);
$f->get('/admin/restablecer/' . $token);
T::eq(200, $f->last['status'], 'el enlace vigente muestra el formulario');
$r = $f->request('POST', '/admin/restablecer/' . $token, ['_csrf' => $f->csrf(), 'password' => 'corta', 'password2' => 'corta']);
T::eq(422, $r['status'], 'contraseña débil rechazada');
$r = $f->request('POST', '/admin/restablecer/' . $token, ['_csrf' => $f->csrf(), 'password' => 'Nueva#Clave2026', 'password2' => 'Otra#Clave2026x']);
T::eq(422, $r['status'], 'contraseñas distintas rechazadas');
$r = $f->request('POST', '/admin/restablecer/' . $token, ['_csrf' => $f->csrf(), 'password' => 'Nueva#Clave2026', 'password2' => 'Nueva#Clave2026']);
T::eq(302, $r['status'], 'contraseña fuerte aceptada');
$f->get('/admin/restablecer/' . $token);
T::eq(410, $f->last['status'], 'el token es de un solo uso');
$n = new A1Client();
$r = $n->login('olvido@test.local', 'Nueva#Clave2026');
T::eq(302, $r['status'], 'la contraseña nueva funciona');
$r = (new A1Client())->login('olvido@test.local', 'Vieja#Clave2026');
T::eq(401, $r['status'], 'la anterior ya no');
$tok2 = bin2hex(random_bytes(16));
Db::update('users', ['reset_token_hash' => hash('sha256', $tok2), 'reset_expires_at' => Clock::utc(Clock::now() - 5)], 'id = ?', [$uid]);
$f->get('/admin/restablecer/' . $tok2);
T::eq(410, $f->last['status'], 'token caducado => 410');
$f->get('/admin/restablecer/' . str_repeat('a', 32));
T::eq(410, $f->last['status'], 'token inventado => 410');

T::section('Invitación');
$iid = $mk('invitado@test.local', 'Temporal#2026ab', ['role' => 'host', 'password_hash' => null]);
$itok = bin2hex(random_bytes(16));
Db::update('users', ['invite_token_hash' => hash('sha256', $itok), 'invite_expires_at' => Clock::utc(Clock::now() + 86400)], 'id = ?', [$iid]);
$i = new A1Client();
$i->get('/admin/invitacion/' . $itok);
T::eq(200, $i->last['status'], 'la invitación vigente carga');
$r = $i->request('POST', '/admin/invitacion/' . $itok, ['_csrf' => $i->csrf(), 'password' => 'Invitado#Clave26', 'password2' => 'Invitado#Clave26']);
T::eq(302, $r['status'], 'acepta la invitación');
T::eq(null, Db::val('SELECT invite_token_hash FROM users WHERE id = ?', [$iid]), 'el token de invitación se invalida');
T::eq(302, (new A1Client())->login('invitado@test.local', 'Invitado#Clave26')['status'], 'el invitado inicia sesión');

T::section('Cierre de sesión');
$o = new A1Client();
$o->login('admin@test.local', 'Prueba#Segura2026');
$o->get('/admin');
$r = $o->request('POST', '/admin/salir', ['_csrf' => $o->csrf()]);
T::eq(302, $r['status'], 'salir redirige');
$o->get('/admin');
T::ok(str_contains($o->location(), '/admin/login'), 'tras salir se pide acceso');
$r = $o->request('POST', '/admin/salir', []);
T::ok(in_array($r['status'], [302, 419], true), 'salir sin sesión no rompe');
T::done();
