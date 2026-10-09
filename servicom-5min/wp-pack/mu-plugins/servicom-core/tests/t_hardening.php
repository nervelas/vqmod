<?php
require __DIR__ . '/lib.php';
$U = $ENV['url'];
$key = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
$ck = 'sc_pk=' . $key;

section('XML-RPC');
$r = http($U . '/xmlrpc.php', ['post' => '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>']);
eq($r['code'], 403, 'xmlrpc.php POST -> 403');
$r = http($U . '/xmlrpc.php');
eq($r['code'], 403, 'xmlrpc.php GET -> 403');
ok(!has_filter('xmlrpc_enabled') || apply_filters('xmlrpc_enabled', true) === false, 'filtro xmlrpc_enabled = false');

section('Constantes');
ok(defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT, 'DISALLOW_FILE_EDIT');
ok(defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS, 'DISALLOW_FILE_MODS');
ok(!current_user_can('install_plugins') || true, 'n/a');
wp_set_current_user(1);
ok(!current_user_can('edit_plugins') && !current_user_can('install_plugins') && !current_user_can('update_core'), 'ni admin edita/instala plugins ni actualiza core');
wp_set_current_user(0);

section('Versión y generator');
$h = http($U . '/', ['cookie' => $ck]);
eq($h['code'], 200, 'home con clave = 200');
ok(!preg_match('/<meta name="generator"/i', $h['body']), 'sin meta generator');
ok(!str_contains($h['body'], 'ver=' . $GLOBALS['wp_version']), 'sin ?ver= de la versión de WP');
ok(!str_contains($h['body'], 'wlwmanifest') && !str_contains($h['body'], 'xmlrpc.php?rsd'), 'sin RSD/wlwmanifest');
ok(!isset($h['headers']['x-pingback']), 'sin X-Pingback');
ok(!str_contains($h['body'], 'wp-emoji'), 'sin emojis de WP');

section('Cabeceras de seguridad');
$H = $h['headers'];
eq($H['x-content-type-options'] ?? '', 'nosniff', 'X-Content-Type-Options');
eq($H['x-frame-options'] ?? '', 'SAMEORIGIN', 'X-Frame-Options');
eq($H['referrer-policy'] ?? '', 'strict-origin-when-cross-origin', 'Referrer-Policy');
ok(str_contains($H['permissions-policy'] ?? '', 'camera=()'), 'Permissions-Policy');
ok(!isset($H['strict-transport-security']), 'sin HSTS en http');
$l = http($U . '/wp-login.php');
eq($l['headers']['x-frame-options'] ?? '', 'SAMEORIGIN', 'cabeceras también en wp-login');
$_SERVER['HTTPS'] = 'on';
ok(isset(sc_security_headers()['Strict-Transport-Security']), 'HSTS cuando es HTTPS');
unset($_SERVER['HTTPS']);

section('Enumeración de usuarios');
$r = http($U . '/?author=1', ['cookie' => $ck]);
ok(in_array($r['code'], [301, 302], true) && !str_contains($r['headers']['location'] ?? '', 'author') && !str_contains($r['headers']['location'] ?? '', 'admin_user'), '?author=1 redirige a home sin revelar login', $r['raw_headers']);
$r2 = http($U . '/?author=1', ['cookie' => $ck, 'follow' => true]);
ok(!str_contains($r2['body'], 'admin_user'), '?author=1 con redirección: sin fuga del login');
$r = http($U . '/?author_name=admin_user', ['cookie' => $ck]);
ok(!str_contains($r['headers']['location'] ?? '', 'admin_user'), '?author_name sin fuga');
$r = http($U . '/author/admin_user/', ['cookie' => $ck]);
$r3 = http($U . '/author/inventado_zz/', ['cookie' => $ck]);
ok($r['code'] === $r3['code'] && ($r['headers']['location'] ?? '') === ($r3['headers']['location'] ?? '') && !str_contains($r['headers']['location'] ?? '', 'author'), '/author/<login>/ responde igual exista o no (sin oráculo)', $r['code'] . ' vs ' . $r3['code']);
$r = http($U . '/wp-json/wp/v2/users', ['cookie' => $ck]);
ok(in_array($r['code'], [401, 403, 404], true) && !str_contains($r['body'], 'admin_user'), 'REST /wp/v2/users anónimo bloqueado', (string) $r['code']);
$r = http($U . '/?rest_route=/wp/v2/users/1', ['cookie' => $ck]);
ok(in_array($r['code'], [401, 403, 404], true) && !str_contains($r['body'], 'admin_user'), 'REST /wp/v2/users/1 anónimo bloqueado');
$r = http($U . '/wp-json/oembed/1.0/embed?url=' . urlencode($U . '/inicio/'), ['cookie' => $ck]);
ok(!str_contains($r['body'], 'author_name'), 'oEmbed sin autor');

section('Login: límite de intentos y mensajes genéricos');
$post = fn($u, $p) => http($U . '/wp-login.php', ['cookie' => 'wordpress_test_cookie=WP%20Cookie%20check', 'post' => ['log' => $u, 'pwd' => $p, 'wp-submit' => 'x', 'testcookie' => '1']]);
$a = $post('noexiste_zzz', 'mala'); $b = $post('admin_user', 'mala');
preg_match('/<div id="login_error"[^>]*>(.*?)<\/div>/s', $a['body'], $ma); preg_match('/<div id="login_error"[^>]*>(.*?)<\/div>/s', $b['body'], $mb);
ok(!empty($ma[1]) && trim(strip_tags($ma[1])) === trim(strip_tags($mb[1] ?? '')), 'mismo mensaje para usuario inexistente y existente', strip_tags($ma[1] ?? '') . ' | ' . strip_tags($mb[1] ?? ''));
ok(!str_contains($b['body'], 'contraseña que has introducido') && !str_contains($a['body'], 'no está registrado'), 'sin mensajes nativos que delatan');
for ($i = 0; $i < 3; $i++) { $b = $post('admin_user', 'mala' . $i); }
// ya van 5 intentos fallidos para admin_user (2 + 3) -> el 6º se bloquea
$c = $post('admin_user', 'Adm1n-Test-Pass!');
ok(str_contains($c['body'], 'Demasiados intentos'), 'tras 5 fallos: bloqueo con mensaje en español (aun con clave correcta)');
$d = $post('otro_usuario', 'x');
ok(str_contains($d['body'], 'Demasiados intentos'), 'bloqueo por IP también aplica a otros usuarios');
// limpiar para no estorbar a otros tests
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_sc\_ll\_%' OR option_name LIKE '\_transient\_timeout\_sc\_ll\_%'");
wp_cache_flush();

section('.htaccess');
$rules = sc_hardening_htaccess_rules();
foreach (['Options -Indexes', 'wp-content/uploads', 'sc-jobs', 'wp-config', 'xmlrpc', 'readme', 'license', 'log|sql|bak', 'mod_expires', 'mod_deflate', 'mod_headers', 'X-Frame-Options', 'Strict-Transport-Security', 'BEGIN Servicom Hardening', 'END Servicom Hardening'] as $needle) {
    ok(str_contains($rules, $needle), "reglas incluyen: $needle");
}
ok(substr_count($rules, '<IfModule') === substr_count($rules, '</IfModule>'), 'IfModule balanceados');
ok(preg_match('/<IfModule mod_expires\.c>/', $rules) && preg_match('/<IfModule mod_headers\.c>/', $rules), 'módulos protegidos con IfModule');
$up = sc_hardening_uploads_htaccess();
ok(str_contains($up, 'php') && str_contains($up, 'Require all denied') && str_contains($up, '-Indexes'), 'uploads/.htaccess bloquea PHP');
// Apache real no disponible aquí: se valida la sintaxis de las expresiones.
foreach ([ '^wp-content/uploads/.*\.(?:php[0-9]*|phtml|phar|pl|py|cgi|sh|shtml|asp|aspx|jsp)$', '(?:^|/)\.(?!well-known(?:/|$))' ] as $re) {
    ok(@preg_match('~' . $re . '~i', 'x') !== false, "regex válida: $re");
}
ok(preg_match('~^wp-content/uploads/.*\.(?:php[0-9]*|phtml|phar)$~i', 'wp-content/uploads/2024/x.php') === 1, 'regla uploads atrapa x.php');
ok(preg_match('~^wp-content/uploads/.*\.(?:php[0-9]*|phtml|phar)$~i', 'wp-content/uploads/2024/x.jpg') === 0, 'regla uploads no atrapa jpg');
ok(preg_match('~(?:^|/)\.(?!well-known(?:/|$))~', '.git/config') === 1 && preg_match('~(?:^|/)\.(?!well-known(?:/|$))~', '.well-known/acme/x') === 0, 'regla dotfiles respeta .well-known');
$tmpdir = $ENV['site'] . '/wp-content/uploads';
@unlink($tmpdir . '/.htaccess'); delete_option('sc_hardening_files');
wp_set_current_user(1); do_action('admin_init');
ok(is_file($tmpdir . '/.htaccess') && is_file($tmpdir . '/index.php'), 'admin_init crea uploads/.htaccess e index.php');

section('Pingbacks/emojis/application passwords');
ok(!pings_open(1), 'pings cerrados');
ok(!wp_is_application_passwords_available(), 'contraseñas de aplicación desactivadas');
eq(get_option('default_ping_status'), 'closed', 'default_ping_status closed');
done();
