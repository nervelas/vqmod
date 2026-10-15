<?php
/** Rutas públicas: landing, registro, acceso, recuperación, verificación y páginas legales. */

function password_ok(string $p): ?string {
    if (mb_strlen($p) < 8) return 'La contraseña debe tener al menos 8 caracteres.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) return 'Usa letras y números en tu contraseña.';
    return null;
}
function make_token(int $uid, string $type, int $ttl): string {
    $raw = bin2hex(random_bytes(32));
    q("DELETE FROM tokens WHERE user_id=? AND type=?", [$uid, $type]);
    insert('tokens', ['user_id' => $uid, 'type' => $type, 'token_hash' => hash('sha256', $raw), 'expires_at' => date('Y-m-d H:i:s', time() + $ttl)]);
    return $raw;
}
function use_token(string $raw, string $type, bool $consume = true): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $raw)) return null;
    $t = row("SELECT * FROM tokens WHERE token_hash=? AND type=? AND used=0 AND expires_at>=?", [hash('sha256', $raw), $type, now()]);
    if ($t && $consume) update('tokens', ['used' => 1], 'id=?', [$t['id']]);
    return $t;
}
function login_blocked(string $email): bool {
    $since = date('Y-m-d H:i:s', time() - 900);
    return (int) val("SELECT COUNT(*) FROM login_attempts WHERE email=? AND ts>=?", [$email, $since]) >= 5
        || (int) val("SELECT COUNT(*) FROM login_attempts WHERE ip=? AND ts>=?", [ip(), $since]) >= 20;
}

route('GET', '/', function () {
    $data = [
        'plans' => rows("SELECT * FROM plans WHERE active=1 ORDER BY sort_order"),
        'faqs' => rows("SELECT * FROM faqs WHERE active=1 ORDER BY sort_order"),
        'sectors' => rows("SELECT * FROM sectors WHERE active=1 ORDER BY sort_order"),
        'demo' => rows("SELECT * FROM tenders WHERE status='abierto' AND deadline>=? ORDER BY deadline LIMIT 3", [now()]),
        'stats' => ['open' => (int) val("SELECT COUNT(*) FROM tenders WHERE status='abierto' AND deadline>=?", [now()]), 'sum' => (float) val("SELECT COALESCE(SUM(amount),0) FROM tenders WHERE status='abierto' AND deadline>=?", [now()]), 'awarded' => (int) val("SELECT COUNT(*) FROM tenders WHERE status='adjudicado'")],
        'title' => S('seo_title'), 'desc' => S('seo_desc'), 'landing' => true,
    ];
    render('public/landing', $data);
});
route('GET', '/legal/{slug}', function ($slug) {
    $p = row("SELECT * FROM pages WHERE slug=?", [$slug]); if (!$p) not_found();
    render('public/legal', ['page' => $p, 'title' => $p['title']]);
});
route('GET', '/ping-rewrite', function () { header('Content-Type: text/plain'); echo 'ok'; });

route('GET', '/registro', function () { if (user()) redirect('app'); render('public/register', ['title' => 'Crear cuenta']); });
route('POST', '/registro', function () {
    $name = mb_substr(post('name'), 0, 150); $email = mb_strtolower(post('email')); $phone = preg_replace('/[^0-9+ \-]/', '', post('phone')); $pass = (string) ($_POST['pass'] ?? '');
    $err = null;
    if ($name === '' ) $err = 'Escribe tu nombre.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Revisa tu correo, parece que no es válido.';
    elseif ($e = password_ok($pass)) $err = $e;
    elseif (empty($_POST['terms'])) $err = 'Debes aceptar los términos y la política de privacidad.';
    elseif (post('website') !== '') $err = 'No pudimos procesar el registro.'; // honeypot
    elseif (val("SELECT COUNT(*) FROM users WHERE email=?", [$email])) $err = 'Ese correo ya tiene cuenta. Puedes iniciar sesión o recuperar tu contraseña.';
    if ($err) { flash($err, 'err'); render('public/register', ['title' => 'Crear cuenta']); return; }
    $uid = insert('users', ['role' => 'client', 'name' => $name, 'email' => $email, 'phone' => $phone, 'pass' => hash_pass($pass), 'sub_ends' => date('Y-m-d H:i:s', time() + 86400 * (int) S('trial_days', 7)), 'created_at' => now()]);
    insert('profiles', ['user_id' => $uid, 'wa_phone' => $phone, 'company' => '']);
    $tok = make_token($uid, 'verify', 172800);
    try {
        send_template_mail($uid, $email, 'verify', ['nombre' => $name, 'enlace' => site_url('verificar/' . $tok)]);
        send_template_mail($uid, $email, 'welcome', ['nombre' => $name, 'enlace' => site_url('app')]);
    } catch (Throwable $e) { log_error('registro correo: ' . $e->getMessage()); }
    login_user(row("SELECT * FROM users WHERE id=?", [$uid]));
    flash('¡Bienvenido! Tu prueba gratis ya está activa. Completa estos 3 pasos para empezar.');
    redirect('app/asistente');
});
route('GET', '/verificar/{tok}', function ($tok) {
    $t = use_token($tok, 'verify');
    if ($t) { update('users', ['email_verified' => 1], 'id=?', [$t['user_id']]); flash('¡Correo verificado! Gracias.'); }
    else flash('El enlace de verificación no es válido o ya venció.', 'err');
    redirect(user() ? 'app' : 'ingresar');
});
route('GET', '/ingresar', function () { if ($u = user()) redirect($u['role'] === 'admin' ? 'admin' : 'app'); render('public/login', ['title' => 'Ingresar']); });
route('POST', '/ingresar', function () {
    $email = mb_strtolower(post('email')); $pass = (string) ($_POST['pass'] ?? '');
    if (login_blocked($email)) { flash('Demasiados intentos. Espera unos 15 minutos o recupera tu contraseña.', 'err'); render('public/login', ['title' => 'Ingresar']); return; }
    $u = row("SELECT * FROM users WHERE email=?", [$email]);
    if (!$u || !password_verify($pass, $u['pass'])) {
        insert('login_attempts', ['ip' => ip(), 'email' => mb_substr($email, 0, 190), 'ts' => now()]);
        flash('Correo o contraseña incorrectos.', 'err'); render('public/login', ['title' => 'Ingresar']); return;
    }
    if ($u['status'] !== 'activo') { flash('Tu cuenta está suspendida. Escríbenos para ayudarte.', 'err'); render('public/login', ['title' => 'Ingresar']); return; }
    q("DELETE FROM login_attempts WHERE email=?", [$email]);
    if (password_needs_rehash($u['pass'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) update('users', ['pass' => hash_pass($pass)], 'id=?', [$u['id']]);
    login_user($u);
    redirect($u['role'] === 'admin' ? 'admin' : ($u['onboarded'] ? 'app' : 'app/asistente'));
});
route('GET|POST', '/salir', function () {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') { render('public/logout', ['title' => 'Salir']); return; }
    $_SESSION = []; session_destroy(); session_start(); session_regenerate_id(true); redirect('');
});
route('GET', '/recuperar', function () { render('public/forgot', ['title' => 'Recuperar contraseña']); });
route('POST', '/recuperar', function () {
    $email = mb_strtolower(post('email'));
    $u = row("SELECT * FROM users WHERE email=? AND status='activo'", [$email]);
    if ($u && (int) val("SELECT COUNT(*) FROM queue WHERE user_id=? AND subject LIKE 'Recupera%' AND created_at>=?", [$u['id'], date('Y-m-d H:i:s', time() - 300)]) < 2) {
        $tok = make_token((int) $u['id'], 'reset', 3600);
        send_template_mail((int) $u['id'], $u['email'], 'reset', ['nombre' => $u['name'], 'enlace' => site_url('restablecer/' . $tok)]);
    }
    flash('Si ese correo tiene cuenta, te enviamos un enlace para cambiar la contraseña. Revisa también tu carpeta de spam.');
    redirect('recuperar');
});
route('GET', '/restablecer/{tok}', function ($tok) {
    if (!use_token($tok, 'reset', false)) { flash('El enlace no es válido o ya venció. Pide uno nuevo.', 'err'); redirect('recuperar'); }
    render('public/reset', ['title' => 'Nueva contraseña', 'tok' => $tok]);
});
route('POST', '/restablecer/{tok}', function ($tok) {
    $pass = (string) ($_POST['pass'] ?? '');
    if ($e = password_ok($pass)) { flash($e, 'err'); render('public/reset', ['title' => 'Nueva contraseña', 'tok' => $tok]); return; }
    $t = use_token($tok, 'reset');
    if (!$t) { flash('El enlace no es válido o ya venció.', 'err'); redirect('recuperar'); }
    update('users', ['pass' => hash_pass($pass)], 'id=?', [$t['user_id']]);
    q("DELETE FROM login_attempts WHERE email=(SELECT email FROM users WHERE id=?)", [$t['user_id']]);
    flash('Contraseña actualizada. Ya puedes ingresar.'); redirect('ingresar');
});
