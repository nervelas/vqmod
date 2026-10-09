<?php
declare(strict_types=1);
/**
 * Parche de acceso aplicado SOBRE una instalación existente (ZIP anterior, instalado con su instalador):
 * login tolerante, recuperación de acceso por archivo, sin tocar configuración ni datos.
 * Uso: php tests/e2e/parche.php <zip_anterior> <parche.zip>
 */
require __DIR__ . '/lib.php';
$old = realpath($argv[1] ?? '/tmp/oldzip/old.zip') ?: ($argv[1] ?? "");
$patch = realpath($argv[2] ?? dirname(__DIR__, 2) . '/dist/parche-acceso.zip') ?: ($argv[2] ?? "");
$W = '/tmp/s5patch';
$port = 8204;
shell_exec("fuser -k $port/tcp 2>/dev/null"); sleep(1);
shell_exec("rm -rf $W; mkdir -p $W/portal $W/webs; cd $W/portal && unzip -q " . escapeshellarg($old));
shell_exec('mysql -e "DROP DATABASE IF EXISTS s5patch; CREATE DATABASE s5patch CHARACTER SET utf8mb4; GRANT ALL ON s5patch.* TO \'p5\'@\'localhost\'"');
file_put_contents("$W/router.php", '<?php $p=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH); if(preg_match("#^/(app|storage|library|wp-pack|tools|vendor)(/|$)#",$p)){http_response_code(403);exit;} $f=__DIR__."/portal".$p; if($p!=="/"&&is_file($f)&&!preg_match("/\.php$/",$f)){return false;} if($p==="/install.php"&&is_file($f)){require $f;return true;} require __DIR__."/portal/index.php";');
$start = function () use ($W, $port) {
    shell_exec("fuser -k $port/tcp 2>/dev/null"); usleep(600000);
    shell_exec("(S5_CONFIG_FILE=$W/config.php setsid nohup php -S 127.0.0.1:$port -t $W/portal $W/router.php >$W/server.log 2>&1 &)"); sleep(1);
};
$start();
$new = fn() => new Client("http://127.0.0.1:$port", 'crear.servicom.gt');

echo "== Instalación con el ZIP anterior (como en el hosting real)\n";
$email = 'Info@Servicom.GT'; $pass = 'Clave-Real-2026!';
$c = $new(); $r = $c->req('GET', '/install.php'); preg_match('/name="t" value="([a-f0-9]+)"/', $r['body'], $m);
$post = http_build_query(['t' => $m[1], 'db_host' => 'localhost', 'db_name' => 's5patch', 'db_user' => 'p5', 'db_pass' => 'p5pass', 'db_prefix' => 'pt_', 'dominio' => 'servicom.gt', 'portal' => 'crear', 'email' => $email, 'pass' => $pass, 'webs' => "$W/webs", 'cp_host' => 'localhost', 'cp_port' => '2083', 'cp_user' => '', 'cp_token' => '', 'cp_home' => '/tmp']);
$r = $c->req('POST', '/install.php', $post, ['Content-Type: application/x-www-form-urlencoded']);
t_ok(str_contains($r['body'], 'quedó instalado'), 'instalación previa OK');
$cfgBefore = md5_file("$W/config.php");
$login = function (string $e, string $p) use ($new) { $a = $new(); $a->page('/admin/login'); $a->form('/admin/login', ['email' => $e, 'password' => $p]); $d = $a->page('/admin'); return $d['status'] === 200 && str_contains($d['body'], 'Resumen'); };
t_ok($login('info@servicom.gt', $pass), 'línea base: el acceso normal funciona antes del parche');

echo "== Estado dañado típico: contraseña guardada con espacio final, segundo usuario, límite de intentos, 2FA\n";
$pdo = new PDO('mysql:host=localhost;dbname=s5patch;charset=utf8mb4', 'p5', 'p5pass', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->prepare('UPDATE pt_users SET pass_hash=? WHERE id=1')->execute([password_hash('Con-Espacio-Final-77 ', PASSWORD_DEFAULT)]);
$pdo->prepare('INSERT INTO pt_users (email,pass_hash,created_at) VALUES (?,?,NOW())')->execute(['viejo@servicom.gt', password_hash('otra-clave-vieja', PASSWORD_DEFAULT)]);
t_ok(!$login('info@servicom.gt', 'Con-Espacio-Final-77'), 'ANTES del parche: sin el espacio final NO entra (reproduce el síntoma)');

echo "== Se aplica el parche encima (unzip -o)\n";
$names = trim((string) shell_exec('unzip -Z1 ' . escapeshellarg($patch)));
$list = explode("\n", $names);
t_ok(count($list) === 5, 'el parche trae solo 5 archivos', $names);
foreach ($list as $f) { t_ok(!preg_match('#(config|install|\.htaccess|storage|tools|library|wp-pack)#', $f), "no toca configuración/datos: $f"); }
shell_exec("cd $W/portal && unzip -oq " . escapeshellarg($patch));
$start();
t_ok(md5_file("$W/config.php") === $cfgBefore, 'config.php intacto');
t_ok($login('info@servicom.gt', 'Con-Espacio-Final-77'), 'DESPUÉS: entra sin el espacio final');
t_ok($login('info@servicom.gt', 'Con-Espacio-Final-77 '), 'DESPUÉS: entra también con el espacio');
t_ok($login('  INFO@servicom.gt ', ' Con-Espacio-Final-77'), 'DESPUÉS: entra con correo en mayúsculas/espacios y espacio inicial');
t_ok(!$login('info@servicom.gt', 'con-espacio-final-77'), 'una contraseña realmente distinta sigue rechazada');
$pdo->prepare('UPDATE pt_users SET pass_hash=? WHERE id=1')->execute([password_hash(' Clave Con Espacios ', PASSWORD_DEFAULT)]);
t_ok($login('info@servicom.gt', 'Clave Con Espacios'), 'espacios internos se respetan y los de los bordes se toleran');
t_ok(!$login('info@servicom.gt', 'ClaveConEspacios'), 'espacios internos quitados → rechazada');

echo "== Pantalla de login\n";
$a = $new(); $r = $a->page('/admin/login');
t_ok(str_contains($r['body'], 'autocapitalize="none"') && str_contains($r['body'], 'data-show-pass') && str_contains($r['body'], '/admin/recuperar'), 'login con anti-autocorrección, «mostrar contraseña» y enlace de recuperación');
t_ok($a->req('GET', '/assets/admin/admin.js')['status'] === 200, 'admin.js se sirve');

echo "== Bloqueo por intentos fallidos (el síntoma que se agrava al reintentar)\n";
for ($i = 0; $i < 8; $i++) { $login('info@servicom.gt', 'mala-' . $i); }
$a = $new(); $a->page('/admin/login'); $r = $a->form('/admin/login', ['email' => 'info@servicom.gt', 'password' => 'Clave Con Espacios']);
$d = $a->page('/admin');
t_ok($d['status'] !== 200, 'tras muchos intentos fallidos queda bloqueado (comportamiento de seguridad)');

echo "== Recuperación de acceso\n";
$a = $new(); $a->page('/admin/login'); $r = $a->page('/admin/recuperar');
t_ok($r['status'] === 200 && preg_match('/recuperar-([a-f0-9]{12})\.txt/', $r['body'], $m), 'la pantalla muestra el nombre del archivo a crear');
$tok = $m[1] ?? 'x';
$r = $a->form('/admin/recuperar', ['email' => 'nuevo@servicom.gt', 'password' => 'Nueva-Clave-Segura-1', 'password2' => 'Nueva-Clave-Segura-1']);
t_ok(str_contains($r['body'], 'Todavía no se encuentra'), 'sin archivo de comprobación: se rechaza');
t_ok(!$login('nuevo@servicom.gt', 'Nueva-Clave-Segura-1'), 'y no cambió nada');
file_put_contents("$W/portal/recuperar-aaaaaaaaaaaa.txt", 'x');
$r = $a->form('/admin/recuperar', ['email' => 'nuevo@servicom.gt', 'password' => 'Nueva-Clave-Segura-1', 'password2' => 'Nueva-Clave-Segura-1']);
t_ok(str_contains($r['body'], 'Todavía no se encuentra'), 'archivo con clave equivocada: se rechaza');
file_put_contents("$W/portal/recuperar-$tok.txt", 'ok');
$r = $a->page('/admin/recuperar');
t_ok(str_contains($r['body'], 'Archivo encontrado') && str_contains($r['body'], 'data-show-pass'), 'con el archivo correcto aparece el formulario');
$r = $a->form('/admin/recuperar', ['email' => 'nuevo@servicom.gt', 'password' => 'corta', 'password2' => 'corta']);
t_ok(str_contains($r['body'], 'al menos 12'), 'contraseña corta rechazada');
$r = $a->form('/admin/recuperar', ['email' => 'nuevo@servicom.gt', 'password' => 'Nueva-Clave-Segura-1', 'password2' => 'Distinta-Clave-Segura']);
t_ok(str_contains($r['body'], 'no coinciden'), 'contraseñas distintas rechazadas');
$r = $a->form('/admin/recuperar', ['email' => 'no-es-correo', 'password' => 'Nueva-Clave-Segura-1', 'password2' => 'Nueva-Clave-Segura-1']);
t_ok(str_contains($r['body'], 'correo no es válido'), 'correo inválido rechazado');
$a2 = $new(); $r = $a2->req('POST', '/admin/recuperar', http_build_query(['email' => 'nuevo@servicom.gt', 'password' => 'Nueva-Clave-Segura-1', 'password2' => 'Nueva-Clave-Segura-1']), ['Content-Type: application/x-www-form-urlencoded']);
t_ok($r['status'] === 403, 'sin CSRF: 403');
$r = $a->form('/admin/recuperar', ['email' => 'Nuevo@Servicom.GT', 'password' => '  Nueva-Clave-Segura-1 ', 'password2' => 'Nueva-Clave-Segura-1']);
t_ok(str_contains($r['body'], 'Listo'), 'recuperación exitosa', substr(strip_tags($r['body']), 0, 300) . ' status=' . $r['status']);
t_ok(!is_file("$W/portal/recuperar-$tok.txt"), 'el archivo de comprobación se borró solo');
t_ok((int) $pdo->query('SELECT COUNT(*) FROM pt_users')->fetchColumn() === 1, 'queda un solo usuario (se eliminó el viejo)');
t_ok(!$login('info@servicom.gt', 'Clave Con Espacios'), 'el acceso anterior ya no sirve');
t_ok($login('nuevo@servicom.gt', 'Nueva-Clave-Segura-1'), 'entra con el correo y la contraseña nuevos (límite de intentos liberado)');
$r = $new()->form('/admin/recuperar', ['email' => 'otro@servicom.gt', 'password' => 'Otra-Clave-Segura-22', 'password2' => 'Otra-Clave-Segura-22']);
t_ok(!$login('otro@servicom.gt', 'Otra-Clave-Segura-22'), 'sin archivo no se puede repetir la recuperación');
$logs = (string) $pdo->query("SELECT GROUP_CONCAT(action) FROM pt_audit_log")->fetchColumn();
t_ok(str_contains($logs, 'acceso_recuperado'), 'queda en la bitácora');

echo "== Recuperación con 2FA activo\n";
$pdo->prepare('UPDATE pt_users SET totp_enabled=1, totp_secret=? WHERE id=1')->execute(['basura']);
file_put_contents("$W/portal/recuperar-$tok.txt", 'ok');
$a = $new(); $a->page('/admin/recuperar');
$r = $a->form('/admin/recuperar', ['email' => 'nuevo@servicom.gt', 'password' => 'Clave-Despues-2FA-9', 'password2' => 'Clave-Despues-2FA-9']);
t_ok(str_contains($r['body'], 'Listo') && $login('nuevo@servicom.gt', 'Clave-Despues-2FA-9'), '2FA desactivado y entra directo');

echo "== Las demás pantallas del panel siguen funcionando tras el parche\n";
$a = $new(); $a->page('/admin/login'); $a->form('/admin/login', ['email' => 'nuevo@servicom.gt', 'password' => 'Clave-Despues-2FA-9']);
foreach (['/admin', '/admin/pedidos', '/admin/demos', '/admin/ajustes', '/admin/diagnostico', '/admin/cuenta', '/admin/bitacora', '/admin/renovaciones', '/admin/ia', '/admin/correos'] as $p) {
    $r = $a->req('GET', $p); t_ok($r['status'] === 200 && !preg_match('/Fatal|Warning|Notice|Deprecated/', $r['body']), "panel $p");
}
$r = $a->page('/admin/cuenta');
$r = $a->form('/admin/cuenta', ['accion' => 'password', 'actual' => 'Clave-Despues-2FA-9', 'nueva' => 'Cambiada-Desde-Panel-5']);
t_ok($login('nuevo@servicom.gt', 'Cambiada-Desde-Panel-5'), 'cambiar contraseña desde el panel sigue funcionando');
shell_exec("fuser -k $port/tcp 2>/dev/null");
echo "\nParche: " . ($GLOBALS["__t_n"] - $GLOBALS["__t_fail"]) . "/" . $GLOBALS["__t_n"] . " OK\n";
exit(($GLOBALS["__t_fail"] ?? 0) ? 1 : 0);
