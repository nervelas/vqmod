<?php
declare(strict_types=1);
/** La subida acepta cualquier tipo de archivo (texto, RTF, HTML, CSV, imagen, binarios antiguos, paquetes zip) y solo rechaza programas/scripts. */
require __DIR__ . '/lib.php';
reset_limits();
file_put_contents('/tmp/s5test/run/ai-mode', 'ok');
$d = sys_get_temp_dir() . '/s5any_' . getmypid(); mkdir($d);
$info = "Grupo Aurora Ingenieria\nServicios: Diseno estructural, Supervision de obra, Topografia\nTelefono 3204 0756 correo info@servicom.gt\nSomos una empresa de ingenieria en Guatemala con 15 anos de experiencia.";
file_put_contents("$d/datos.txt", $info);
file_put_contents("$d/datos.csv", "nombre,telefono\nGrupo Aurora Ingenieria,32040756\nServicios,Diseno estructural; Supervision de obra\n");
file_put_contents("$d/datos.rtf", '{\rtf1\ansi\deff0 {\fonttbl{\f0 Arial;}}\f0 Grupo Aurora Ingenieria\par Servicios de diseno estructural, supervision de obra y topografia para proyectos en Guatemala. Telefono 32040756\par}');
file_put_contents("$d/datos.html", "<html><head><style>p{color:red}</style></head><body><h1>Grupo Aurora Ingenieria</h1><p>Servicios de diseno estructural, supervision de obra y topografia. Telefono 32040756 info@servicom.gt</p></body></html>");
file_put_contents("$d/antiguo.doc", "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat("\0", 200) . mb_convert_encoding('Grupo Aurora Ingenieria. Servicios de diseno estructural y supervision de obra. Telefono 32040756', 'UTF-16LE') . str_repeat("\0", 100));
file_put_contents("$d/rarisimo.xyz", str_repeat("\x01\x02", 50) . "Grupo Aurora Ingenieria ofrece diseno estructural, supervision de obra y topografia. Tel 32040756" . str_repeat("\x03", 50));
$im = imagecreatetruecolor(900, 600); $bg = imagecolorallocate($im, 11, 61, 145); imagefilledrectangle($im, 0, 0, 900, 600, $bg); imagestring($im, 5, 40, 40, 'Grupo Aurora Ingenieria  Tel 32040756', imagecolorallocate($im, 255, 255, 255)); imagejpeg($im, "$d/volante.jpg", 90);
imagepng($im, "$d/logo_chico.png"); imagegif($im, "$d/foto.gif"); imagewebp($im, "$d/foto.webp");
$z = new ZipArchive(); $z->open("$d/doc.odt", ZipArchive::CREATE); $z->addFromString('content.xml', '<office:document-content><text:p>Grupo Aurora Ingenieria presta servicios de diseno estructural, supervision de obra y topografia. Telefono 32040756</text:p></office:document-content>'); $z->addFromString('mimetype', 'application/vnd.oasis.opendocument.text'); $z->close();
$ok = ['datos.txt', 'datos.csv', 'datos.rtf', 'datos.html', 'antiguo.doc', 'rarisimo.xyz', 'volante.jpg', 'logo_chico.png', 'foto.gif', 'foto.webp', 'doc.odt'];
foreach ($ok as $f) {
    $c = new Client(); $c->page('/crear'); $tok = $c->api('POST', '/api/borrador', ['plan' => 'info'])['json']['token'];
    $c->api('POST', "/api/borrador/$tok/guardar", ['data' => ['presentacion' => ['acepto' => true]]]);
    $u = $c->upload($tok, 'presentacion', "$d/$f", mime_content_type("$d/$f") ?: 'application/octet-stream');
    t_ok(!empty($u['json']['ok']), "se acepta $f", json_encode($u['json'], JSON_UNESCAPED_UNICODE));
    if (empty($u['json']['ok'])) { continue; }
    $c->api('POST', "/api/borrador/$tok/analizar"); $a = [];
    for ($i = 0; $i < 90; $i++) { $a = $c->api('GET', "/api/borrador/$tok/analisis"); if (in_array($a['json']['estado'] ?? '', ['lista', 'error'], true)) { break; } usleep(600000); }
    t_ok(($a['json']['estado'] ?? '') === 'lista', "se analiza $f", json_encode($a['json'] ?? [], JSON_UNESCAPED_UNICODE));
}
foreach (['virus.exe' => "MZ\x90\x00", 'shell.php' => '<?php echo 1;', 'run.sh' => "#!/bin/sh\necho hi", 'x.js' => 'alert(1)', 'disfrazado.txt' => "MZ\x90\x00\x03"] as $f => $body) {
    file_put_contents("$d/$f", $body);
    $c = new Client(); $c->page('/crear'); $tok = $c->api('POST', '/api/borrador', ['plan' => 'info'])['json']['token'];
    $c->api('POST', "/api/borrador/$tok/guardar", ['data' => ['presentacion' => ['acepto' => true]]]);
    $u = $c->upload($tok, 'presentacion', "$d/$f", 'application/octet-stream');
    t_ok(empty($u['json']['ok']) && str_contains((string) ($u['json']['error'] ?? $u['json']['mensaje'] ?? ''), 'seguridad'), "se rechaza $f por seguridad", json_encode($u['json'], JSON_UNESCAPED_UNICODE));
}
exec('rm -rf ' . escapeshellarg($d));
exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0);
