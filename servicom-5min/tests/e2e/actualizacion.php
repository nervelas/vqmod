<?php
declare(strict_types=1);
/**
 * Actualización LUXE aplicada SOBRE una instalación existente (ZIP anterior + parches, instalado con su instalador):
 * nada se pierde (config, usuario, ajustes) y el panel/portal siguen funcionando con el código nuevo.
 * Uso: php tests/e2e/actualizacion.php <zip_anterior> <parche3.zip> <actualizacion.zip>
 */
require __DIR__ . '/lib.php';
$old = realpath($argv[1] ?? '/tmp/oldzip/old.zip');
$p3 = realpath($argv[2] ?? dirname(__DIR__, 2) . '/entrega/parche-3.zip');
$upd = realpath($argv[3] ?? dirname(__DIR__, 2) . '/dist/actualizacion-lujo.zip');
$W = '/tmp/s5upd'; $port = 8205;
shell_exec("fuser -k $port/tcp 2>/dev/null"); sleep(1);
shell_exec("rm -rf $W; mkdir -p $W/portal $W/webs; cd $W/portal && unzip -q " . escapeshellarg($old) . " && unzip -oq " . escapeshellarg($p3));
shell_exec('mysql -e "DROP DATABASE IF EXISTS s5upd; CREATE DATABASE s5upd CHARACTER SET utf8mb4; GRANT ALL ON s5upd.* TO \'p5\'@\'localhost\'"');
file_put_contents("$W/router.php", '<?php $p=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH); if(preg_match("#^/(app|storage|library|wp-pack|tools|vendor)(/|$)#",$p)){http_response_code(403);exit;} $f=__DIR__."/portal".$p; if($p!=="/"&&is_file($f)&&!preg_match("/\.php$/",$f)){return false;} if($p==="/install.php"&&is_file($f)){require $f;return true;} require __DIR__."/portal/index.php";');
$start = function () use ($W, $port) { shell_exec("fuser -k $port/tcp 2>/dev/null"); usleep(600000); shell_exec("(S5_CONFIG_FILE=$W/config.php setsid nohup php -S 127.0.0.1:$port -t $W/portal $W/router.php >$W/server.log 2>&1 &)"); sleep(1); };
$start();
$new = fn() => new Client("http://127.0.0.1:$port", 'crear.servicom.gt');
$email = 'dueno@servicom.gt'; $pass = 'Clave-Actual-2026!';
$c = $new(); $r = $c->req('GET', '/install.php'); preg_match('/name="t" value="([a-f0-9]+)"/', $r['body'], $m);
$r = $c->req('POST', '/install.php', http_build_query(['t' => $m[1], 'db_host' => 'localhost', 'db_name' => 's5upd', 'db_user' => 'p5', 'db_pass' => 'p5pass', 'db_prefix' => 'up_', 'dominio' => 'servicom.gt', 'portal' => 'crear', 'email' => $email, 'pass' => $pass, 'webs' => "$W/webs", 'cp_host' => 'localhost', 'cp_port' => '2083', 'cp_user' => '', 'cp_token' => '', 'cp_home' => '/tmp']), ['Content-Type: application/x-www-form-urlencoded']);
t_ok(str_contains($r['body'], 'quedó instalado'), 'instalación previa (ZIP anterior) OK');
$pdo = new PDO('mysql:host=localhost;dbname=s5upd;charset=utf8mb4', 'p5', 'p5pass', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$cfg = md5_file("$W/config.php");
$login = function () use ($new, $email, $pass) { $a = $new(); $a->page('/admin/login'); $a->form('/admin/login', ['email' => $email, 'password' => $pass]); return $a; };
$a = $login(); $d = $a->page('/admin'); t_ok($d['status'] === 200, 'acceso al panel antes de actualizar');
$pdo->prepare("INSERT INTO up_settings (k,v,is_secret) VALUES ('banco_nombre','Banrural',0) ON DUPLICATE KEY UPDATE v=VALUES(v)")->execute();

echo "== Se aplica la actualización encima (unzip -o)\n";
shell_exec("cd $W/portal && unzip -oq " . escapeshellarg($upd));
$lint = trim((string) shell_exec("cd $W/portal && find app tools wp-pack index.php -name '*.php' | xargs -n1 php -l 2>&1 | grep -v '^No syntax' | head -3"));
t_ok($lint === '', 'toda la instalación sin errores de sintaxis', $lint);
$start();
t_ok(md5_file("$W/config.php") === $cfg, 'config.php intacto');
t_ok(!is_file("$W/portal/install.php"), 'install.php sigue eliminado');
$a = $login(); $d = $a->page('/admin'); t_ok($d['status'] === 200 && !preg_match('/Fatal|Parse error|Warning:/', $d['body']), 'el acceso sigue funcionando');
foreach (['/admin', '/admin/pedidos', '/admin/demos', '/admin/ajustes', '/admin/diagnostico', '/admin/cuenta', '/admin/bitacora', '/admin/renovaciones', '/admin/ia', '/admin/correos'] as $p) {
    $r = $a->req('GET', $p); t_ok($r['status'] === 200 && !preg_match('/Fatal|Parse error|Warning:|Notice:|Deprecated:/', $r['body']), "panel $p");
}
$r = $a->page('/admin/ajustes');
t_ok(str_contains($r['body'], 'pexels_key') && str_contains($r['body'], 'stock_online') && str_contains($r['body'], 'Banrural'), 'Ajustes: sección de imágenes y datos anteriores conservados');
$f = []; preg_match_all('/<(?:input|textarea|select)[^>]*name="([^"]+)"[^>]*>/', $r['body'], $mm);
foreach ($mm[0] as $i => $tag) { if (preg_match('/type="checkbox"/', $tag)) continue; $v = preg_match('/value="([^"]*)"/', $tag, $mv) ? html_entity_decode($mv[1]) : ''; $f[$mm[1][$i]] = $v; }
$f['pexels_key'] = 'KEY-DE-PRUEBA-12345';
$r2 = $a->form('/admin/ajustes', $f); t_ok($r2['status'] === 302, 'guardar ajustes con la clave de Pexels');
$st = (string) $pdo->query("SELECT CONCAT(is_secret,':',LEFT(v,5)) FROM up_settings WHERE k='pexels_key'")->fetchColumn();
t_ok(str_starts_with($st, '1:enc2:'), 'la clave de Pexels se guarda cifrada', $st);
$r = $a->page('/admin/diagnostico'); t_ok(str_contains($r['body'], 'Fotos de stock') && !str_contains($r['body'], 'Elementor'), 'Diagnóstico: fotos de stock y sin Elementor');
$r = $new()->req('GET', '/crear'); t_ok($r['status'] === 200 && str_contains($r['body'], 'Ambiente de tu web') && str_contains($r['body'], 'Oscuro elegante'), 'formulario del portal con «Ambiente» (colores desde el logo)');
$r = $new()->req('GET', '/'); t_ok($r['status'] === 200, 'portal público');
$st = trim((string) shell_exec("cd $W/portal && S5_CONFIG_FILE=$W/config.php php tools/build_base.php --webs=$W/webs --only-pack 2>&1"));
t_ok(str_contains($st, 'No existe un paquete base') || str_contains($st, 'ERROR'), '--only-pack sin paquete base avisa con claridad', substr($st, 0, 120));
shell_exec("fuser -k $port/tcp 2>/dev/null");
echo "\nActualización: " . ($GLOBALS['__t_n'] - $GLOBALS['__t_fail']) . "/" . $GLOBALS['__t_n'] . " OK\n";
exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0);
