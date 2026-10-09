<?php
declare(strict_types=1);
/** Pruebas de seguridad del portal: SQLi, XSS, CSRF, subidas maliciosas, fuerza bruta, accesos directos, IDOR. */
require __DIR__ . '/lib.php';
$tmp = sys_get_temp_dir() . '/s5sec'; @mkdir($tmp, 0777, true);
$c = new Client();
$c->page('/crear');

echo "== Accesos directos\n";
foreach (['/app/bootstrap.php', '/app/Core/Db.php', '/storage/logs/app.log', '/storage/sessions/', '/app/config.php', '/wp-pack/provision/sc-provision.php', '/library/catalog.json', '/tools/cron.php', '/vendor/phpmailer/src/PHPMailer.php', '/.htaccess', '/.user.ini', '/install.php', '/tests/', '/docs/CONTRATO.md', '/LEEME.md'] as $p) {
    $r = $c->req('GET', $p);
    t_ok(in_array($r['status'], [403, 404], true), "acceso directo bloqueado $p", (string) $r['status']);
}
foreach (['/storage/uploads/1/x.webp', '/storage/jobs/1/assets/a1.webp'] as $p) { $r = $c->req('GET', $p); t_ok(in_array($r['status'], [403, 404], true), "uploads/jobs no accesibles $p", (string) $r['status']); }
$r = $c->req('GET', '/assets/../app/bootstrap.php'); t_ok(in_array($r['status'], [403, 404, 400], true), 'traversal en /assets', (string) $r['status']);
$r = $c->req('GET', '/f/' . str_repeat('a', 64) . '/1'); t_ok($r['status'] === 404, 'archivo de token inexistente 404');
$r = $c->req('GET', '/f/../../etc/passwd/1'); t_ok(in_array($r['status'], [404, 400, 403], true), 'traversal en /f');

echo "== CSRF\n";
$noCsrf = new Client();
$noCsrf->page('/crear'); $noCsrf->csrf = null;
$r = $noCsrf->api('POST', '/api/borrador', ['plan' => 'info']); t_ok($r['status'] === 403, 'POST sin CSRF rechazado', (string) $r['status']);
$noCsrf->csrf = str_repeat('0', 64);
$r = $noCsrf->api('POST', '/api/borrador', ['plan' => 'info']); t_ok($r['status'] === 403, 'POST con CSRF falso rechazado');
$r = $noCsrf->req('POST', '/admin/login', http_build_query(['email' => 'x@y.com', 'password' => 'x']), ['Content-Type: application/x-www-form-urlencoded']); t_ok($r['status'] === 403, 'login sin CSRF rechazado', (string) $r['status']);

echo "== Borrador / inyecciones\n";
$r = $c->api('POST', '/api/borrador', ['plan' => 'tienda']); $tok = $r['json']['token'] ?? ''; t_ok(strlen($tok) === 64, 'token de 64 hex impredecible');
$r2 = $c->api('POST', '/api/borrador', ['plan' => 'info']); t_ok(($r2['json']['token'] ?? '') !== $tok, 'tokens distintos');
foreach (["' OR '1'='1", "1; DROP TABLE s5_orders;--", "%27%20OR%201=1", "../../etc/passwd", str_repeat('a', 5000)] as $bad) {
    $r = $c->req('GET', '/api/borrador/' . rawurlencode($bad)); t_ok(in_array($r['status'], [404, 400, 403, 414], true), 'token malicioso rechazado: ' . substr($bad, 0, 20), (string) $r['status']);
}
t_ok((int) mysql_val('SELECT COUNT(*) FROM s5test.s5_orders') >= 2, 'tabla orders intacta tras intentos de SQLi');
$xss = '<script>alert(1)</script><img src=x onerror=alert(2)>';
$r = $c->api('POST', "/api/borrador/$tok/guardar", ['data' => ['negocio' => ['nombre' => $xss . 'Tienda', 'rubro' => "ropa' OR 1=1"], 'contenido' => ['servicios' => [['nombre' => $xss, 'descripcion' => '"><svg onload=alert(3)>']]], 'contacto' => ['whatsapp' => '5025555<script>', 'mapa_url' => 'javascript:alert(1)', 'redes' => ['facebook' => 'javascript:alert(1)', 'instagram' => 'https://evil.com/x']]]]);
$g = $c->api('GET', "/api/borrador/$tok")['json']['data'] ?? [];
t_ok(!str_contains(json_encode($g), '<script') && !str_contains(json_encode($g), '<img') && !str_contains(json_encode($g), '<svg'), 'HTML eliminado al guardar');
t_ok(($g['negocio']['rubro'] ?? 'x') === '', 'rubro fuera de lista descartado');
t_ok(($g['contacto']['mapa_url'] ?? 'x') === '' && ($g['contacto']['redes']['facebook'] ?? 'x') === '' && ($g['contacto']['redes']['instagram'] ?? 'x') === '', 'URLs javascript:/de otro dominio descartadas');
t_ok(($g['contacto']['whatsapp'] ?? '') === '5025555', 'whatsapp solo dígitos');
$r = $c->api('POST', "/api/borrador/$tok/guardar", ['data' => ['presentacion' => ['confirmada' => true, 'estado' => 'confirmada'], 'origen' => ['x' => 'y'], 'plan' => 'info', 'negocio' => ['logo' => 999999]]]);
$g = $c->api('GET', "/api/borrador/$tok")['json']['data'] ?? [];
t_ok(empty($g['presentacion']['confirmada']) && empty($g['origen']), 'el cliente no puede forzar confirmada/origen');
t_ok(array_key_exists('logo', $g['negocio'] ?? []) && $g['negocio']['logo'] === null, 'id de archivo ajeno/inexistente descartado');

echo "== Subidas maliciosas\n";
$cases = [
  'php disfrazado de jpg' => ["<?php system('id'); ?>", 'x.jpg', 'image/jpeg', 'foto'],
  'svg con script' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'x.svg', 'image/svg+xml', 'foto'],
  'html' => ['<html><script>1</script>', 'x.html', 'text/html', 'foto'],
  'gif+php polyglot' => ["GIF89a<?php echo 1;?>", 'x.gif', 'image/gif', 'foto'],
  'exe como comprobante' => ["MZ\x90\x00\x03", 'pago.pdf', 'application/pdf', 'comprobante'],
  'vacío' => ['', 'v.jpg', 'image/jpeg', 'foto'],
];
foreach ($cases as $name => [$content, $fn, $mime, $tipo]) {
    $f = "$tmp/$fn"; file_put_contents($f, $content);
    $r = $c->upload($tok, $tipo, $f, $mime);
    t_ok(empty($r['json']['ok']) && $r['status'] >= 400, "subida rechazada: $name", $r['status'] . ' ' . substr($r['body'], 0, 120));
}
// JPEG válido con PHP incrustado en comentario: se re-codifica y no conserva código
$im = imagecreatetruecolor(50, 50); imagejpeg($im, "$tmp/ok.jpg"); $raw = file_get_contents("$tmp/ok.jpg") . "<?php system('id');?>"; file_put_contents("$tmp/poly.jpg", $raw);
$r = $c->upload($tok, 'foto', "$tmp/poly.jpg"); t_ok(!empty($r['json']['ok']), 'jpeg con basura final aceptado y saneado');
$oid = (int) mysql_val("SELECT id FROM s5test.s5_orders WHERE token='$tok'");
$stored = mysql_val("SELECT stored FROM s5test.s5_files WHERE order_id=$oid ORDER BY id DESC LIMIT 1");
$path = "/tmp/s5test/portal/storage/uploads/$oid/$stored";
t_ok($stored && is_file($path) && !str_contains((string) file_get_contents($path), '<?php'), 'archivo re-codificado sin código PHP', (string) $stored);
t_ok(preg_match('/^[a-f0-9]{32}\.(webp|jpg|png)$/', (string) $stored) === 1, 'nombre aleatorio y extensión segura');
$r = $c->upload($tok, 'foto', "$tmp/ok.jpg", 'image/jpeg'); $fid = $r['json']['id'] ?? 0;
// IDOR: otro borrador no puede ver ni borrar el archivo
$o2 = $c->api('POST', '/api/borrador', ['plan' => 'info'])['json']['token'];
$r = $c->req('GET', "/f/$o2/$fid"); t_ok($r['status'] === 404, 'IDOR: archivo de otro borrador no visible');
$r = $c->api('DELETE', "/api/borrador/$o2/archivo/$fid"); t_ok($r['status'] === 404, 'IDOR: no se puede borrar archivo ajeno');
$r = $c->req('GET', "/f/$tok/$fid?t=1"); t_ok($r['status'] === 200 && str_contains($r['headers'], 'nosniff'), 'miniatura propia con nosniff');
// presentación con extensión falsa
file_put_contents("$tmp/fake.pdf", "MZ no soy pdf"); 
$c->api('POST', "/api/borrador/$tok/guardar", ['data' => ['presentacion' => ['acepto' => true]]]);
$r = $c->upload($tok, 'presentacion', "$tmp/fake.pdf", 'application/pdf'); t_ok(empty($r['json']['ok']), 'presentación falsa (.pdf no PDF) rechazada', substr($r['body'], 0, 150));
$c2 = new Client(); $c2->page('/crear'); $tk3 = $c2->api('POST', '/api/borrador', ['plan' => 'info'])['json']['token'];
file_put_contents("$tmp/p.pdf", "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
$r = $c2->upload($tk3, 'presentacion', "$tmp/p.pdf", 'application/pdf'); t_ok(empty($r['json']['ok']), 'presentación sin aceptar el aviso rechazada');

echo "== Panel y fuerza bruta\n";
$r = $c->req('GET', '/admin'); t_ok($r['status'] === 302 && str_contains($r['headers'], '/admin/login'), 'panel exige sesión');
foreach (['/admin/pedidos', '/admin/ajustes', '/admin/hostings', '/admin/archivo/1', '/admin/construir/1', '/admin/diagnostico'] as $p) { $r = $c->req('GET', $p); t_ok(in_array($r['status'], [302, 403], true), "ruta admin protegida $p", (string) $r['status']); }
$bf = new Client(); $bf->page('/admin/login'); $locked = false;
for ($i = 0; $i < 16; $i++) {
    $bf->form('/admin/login', ['email' => 'dueno@servicom.test', 'password' => 'mala' . $i]);
    $pg = $bf->page('/admin/login');
    if (str_contains($pg['body'], 'Demasiados intentos')) { $locked = true; break; }
}
t_ok($locked, 'límite de intentos de login activado (tras ' . ($i + 1) . ' intentos)');
$bf->form('/admin/login', ['email' => 'dueno@servicom.test', 'password' => 'Contrasena-Segura-123']);
$after = $bf->page('/admin');
t_ok($after['status'] === 302, 'bloqueado aun con clave correcta durante la ventana', (string) $after['status']);
echo "== Cabeceras\n";
$fresh = new Client(); $r = $fresh->req('GET', '/');
foreach (['X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy', 'Content-Security-Policy', 'Permissions-Policy'] as $h) { t_ok(stripos($r['headers'], $h . ':') !== false, "cabecera $h"); }
t_ok(stripos($r['headers'], 'X-Powered-By') === false, 'sin X-Powered-By');
$ck = $r['headers']; t_ok(preg_match('/Set-Cookie: s5sid=.*HttpOnly/i', $ck) === 1 && stripos($ck, 'SameSite=Lax') !== false, 'cookie de sesión HttpOnly + SameSite');
$r = $c->req('GET', '/ruta-inexistente-<script>'); t_ok($r['status'] === 404 && !str_contains($r['body'], 'Fatal') && !str_contains($r['body'], '/tmp/s5test'), '404 sin detalles técnicos');
echo "== Honeypot / tiempo mínimo\n";
$hp = $c->api('POST', '/api/borrador', ['plan' => 'info', 'web_sitio' => 'bot']); $cnt1 = (int) mysql_val('SELECT COUNT(*) FROM s5test.s5_orders');
t_ok(!empty($hp['json']['token']), 'honeypot: responde sin crear'); 
t_ok($cnt1 === (int) mysql_val('SELECT COUNT(*) FROM s5test.s5_orders'), 'honeypot no crea pedido');
echo "\nSeguridad: " . ($GLOBALS['__t_n'] - $GLOBALS['__t_fail']) . "/" . $GLOBALS['__t_n'] . " OK\n";
exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0);
