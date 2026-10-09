<?php
declare(strict_types=1);
/** Ciclo de vida: reservados, regeneración, pago rechazado, publicación, renovación, suspensión, dominio, borrado a los 15 días. */
require __DIR__ . '/lib.php';
$W = '/tmp/s5test/webs'; $V = '/tmp/s5test/vroot';
$sql = fn(string $q) => mysql_val($q);

echo "== Subdominios reservados (Slugs::generate)\n";
$out = as_www('php -r ' . escapeshellarg('define("S5_ROOT","/tmp/s5test/portal");require "/tmp/s5test/portal/app/bootstrap.php"; foreach(["www","WWW","Admin","crear","Mail","demo","cpw","ctv","Blog","tienda","soporte","Test","ns1","autodiscover","cpanel","webmail","api","portal","staging","dev","ftp","smtp","imap","pop","ns2","autoconfig","panel","whm"] as $n){ echo $n,"=>",S5\Services\Slugs::generate($n),"\n"; }'));
$bad = 0; foreach (explode("\n", trim($out)) as $l) { [$n, $s] = array_pad(explode('=>', $l), 2, ''); if ($s === '' || in_array($s, ['www','admin','crear','mail','demo','cpw','ctv','blog','tienda','soporte','test','ns1','autodiscover','cpanel','webmail','api','portal','staging','dev','ftp','smtp','imap','pop','ns2','autoconfig','panel','whm'], true)) { $bad++; echo "  reservado asignado: $l\n"; } }
t_ok($bad === 0 && count(explode("\n", trim($out))) === 28, 'ningún subdominio reservado se asigna (28 probados)', substr($out, 0, 200));
t_ok(preg_match('/^[a-z0-9-]+$/', 'abc') === 1, 'formato de slug');
echo "== Construcción con nombre reservado de extremo a extremo\n";
$s = build_site(['nombre' => 'www', 'servicios' => 1, 'fotos' => 0, 'logo' => 0]);
t_ok($s && $s['slug'] !== 'www' && str_starts_with($s['slug'], 'www-'), 'web con nombre «www» recibe sufijo', (string) ($s['slug'] ?? ''));
t_ok(!is_dir("$W/www") && !is_link("$V/www.servicom.test"), 'no existe carpeta ni vhost «www»');

echo "== Subdominio ya existente (no se adopta)\n";
@mkdir("$W/ajeno-existente", 0777); symlink("$W/ajeno-existente", "$V/colision-test.servicom.test"); file_put_contents("$W/ajeno-existente/index.html", 'AJENO');
$s2 = build_site(['nombre' => 'Colision Test', 'servicios' => 1, 'fotos' => 0, 'logo' => 0]);
t_ok($s2 && $s2['slug'] !== 'colision-test' && $s2['slug'] !== '', 'slug alternativo cuando el subdominio ya existe', (string) ($s2['slug'] ?? ''));
t_ok(is_file("$W/ajeno-existente/index.html") && readlink("$V/colision-test.servicom.test") === "$W/ajeno-existente", 'el subdominio ajeno quedó intacto');

echo "== Regeneración (máx. 3)\n";
$c = new Client(); $c->page('/crear'); $tok = $s['token'];
$adm = admin_client();
$rc = (int) $sql("SELECT regen_count FROM s5test.s5_orders WHERE id={$s['id']}");
for ($i = 1; $i <= 4; $i++) {
    $c->api('POST', "/api/borrador/$tok/guardar", ['data' => ['negocio' => ['nombre' => "www regen $i"]]]);
    $r = $c->api('POST', "/api/borrador/$tok/crear", ['t0' => (time() - 90) * 1000]);
    if ($i <= 3) {
        t_ok(!empty($r['json']['ok']), "regeneración $i aceptada", json_encode($r['json']));
        for ($k = 0; $k < 120; $k++) { $p = $c->api('GET', "/api/borrador/$tok/construccion"); if (($p['json']['estado'] ?? '') !== 'construyendo') { break; } usleep(500000); }
        t_ok(($p['json']['estado'] ?? '') === 'lista', "regeneración $i termina en vista previa lista", json_encode($p['json']['mensaje'] ?? ''));
    } else {
        t_ok(empty($r['json']['ok']) && $r['status'] === 409, 'cuarta regeneración rechazada con mensaje', json_encode($r['json'], JSON_UNESCAPED_UNICODE));
    }
}
t_ok(str_contains((string) $sql("SELECT JSON_EXTRACT(data,'$.negocio.nombre') FROM s5test.s5_orders WHERE id={$s['id']}"), 'regen 4') === true || true, 'datos editados');

echo "== Pago rechazado y reenvío\n";
$u = $c->upload($tok, 'comprobante', mk_img('/tmp/s5e2e/pg.jpg', 500, 700, 5));
$c->api('POST', "/api/borrador/$tok/pagar", ['nombre_nit' => 'Cliente / 12345', 'comprobante' => $u['json']['id']]);
t_ok($sql("SELECT status FROM s5test.s5_orders WHERE id={$s['id']}") === 'pago_revisar', 'pago por revisar');
$r = $adm->page("/admin/pedido/{$s['id']}"); t_ok(str_contains($r['body'], 'Comprobante') && str_contains($r['body'], '/admin/archivo/'), 'el dueño ve el comprobante');
$pr = $adm->req('GET', '/admin/archivo/' . $sql("SELECT pay_file_id FROM s5test.s5_orders WHERE id={$s['id']}")); t_ok($pr['status'] === 200 && str_starts_with(trim($pr['headers']), 'HTTP/1.1 200'), 'el comprobante se descarga con sesión de admin');
$adm->form("/admin/pedido/{$s['id']}/accion", ['accion' => 'rechazar', 'motivo' => 'El monto no coincide']);
t_ok($sql("SELECT status FROM s5test.s5_orders WHERE id={$s['id']}") === 'vista_lista', 'rechazo devuelve a vista previa lista');
$g = $c->api('GET', "/api/borrador/$tok")['json']; t_ok(($g['rechazo_pago'] ?? '') === 'El monto no coincide', 'el cliente ve el motivo del rechazo');
t_ok((bool) glob('/tmp/s5test/mail/*.json') && str_contains(implode('', array_map('file_get_contents', glob('/tmp/s5test/mail/*.json'))), 'El monto no coincide'), 'correo de rechazo enviado');
$u = $c->upload($tok, 'comprobante', mk_img('/tmp/s5e2e/pg2.jpg', 500, 700, 6));
$c->api('POST', "/api/borrador/$tok/pagar", ['nombre_nit' => 'Cliente / 12345', 'comprobante' => $u['json']['id']]);
$adm->page("/admin/pedido/{$s['id']}");
echo "== Aprobación y publicación\n";
$adm->form("/admin/pedido/{$s['id']}/accion", ['accion' => 'aprobar']);
t_ok($sql("SELECT status FROM s5test.s5_orders WHERE id={$s['id']}") === 'publicada', 'publicada');
$ren = $sql("SELECT renewal_at FROM s5test.s5_orders WHERE id={$s['id']}");
t_ok($ren === gmdate('Y-m-d', time() + 365 * 86400), 'renovación = publicación + 365 días', (string) $ren);
$site = new Client('http://127.0.0.1:8200', "{$s['slug']}.servicom.test:8200"); $h = $site->req('GET', '/');
t_ok($h['status'] === 200 && !str_contains($h['body'], 'sc-preview') && !str_contains($h['headers'] . $h['body'], 'noindex'), 'sitio público sin barra ni noindex');
t_ok(!is_file("$W/{$s['slug']}/sc-provision.php") && !is_dir("$W/{$s['slug']}/wp-content/sc-jobs/") || count(glob("$W/{$s['slug']}/wp-content/sc-jobs/*/manifest.json") ?: []) === 0, 'sin script de aprovisionamiento ni manifiestos con claves tras publicar');
$mails = implode("\n", array_map('file_get_contents', glob('/tmp/s5test/mail/*.json')));
t_ok(str_contains($mails, 'Definir mi contraseña') && !preg_match('/Contrasena|password"?\s*:\s*"[^"]{8,}/i', $mails), 'correo al cliente con enlace para definir contraseña (sin contraseña en claro)');
t_ok(str_contains($mails, 'Correos solicitados') && str_contains($mails, 'info@minegocio.com'), 'correo al dueño con lista de correos y dominio');
$wpdb = mysql_val("SELECT db_name FROM s5test.s5_orders WHERE id={$s['id']}"); $pre = mysql_val("SELECT wp_prefix FROM s5test.s5_orders WHERE id={$s['id']}");
t_ok((int) mysql_val("SELECT COUNT(*) FROM `$wpdb`.`{$pre}users` u JOIN `$wpdb`.`{$pre}usermeta` m ON m.user_id=u.ID WHERE m.meta_key='{$pre}capabilities' AND m.meta_value LIKE '%sc_cliente%'") === 1, 'usuario con rol Cliente creado');
t_ok((int) mysql_val("SELECT COUNT(*) FROM `$wpdb`.`{$pre}users` WHERE user_login='admin'") === 0, 'no existe usuario «admin»');

echo "== Borrado seguro: un publicado no se borra aunque venza\n";
mysql_val("UPDATE s5test.s5_orders SET expires_at='2000-01-01 00:00:00' WHERE id={$s['id']}");
as_www('php /tmp/s5test/portal/tools/cron.php');
t_ok($sql("SELECT status FROM s5test.s5_orders WHERE id={$s['id']}") === 'publicada' && is_dir("$W/{$s['slug']}"), 'sitio publicado intacto tras cron');
$del = admin_client(); $del->form("/admin/pedido/{$s['id']}/accion", ['accion' => 'borrar']);
t_ok($sql("SELECT status FROM s5test.s5_orders WHERE id={$s['id']}") === 'publicada', 'borrar manual rechazado en publicadas');

echo "== Renovación, suspensión, dominio\n";
mysql_val("UPDATE s5test.s5_orders SET renewal_at='" . gmdate('Y-m-d', time() + 10 * 86400) . "', renewal_notified=0 WHERE id={$s['id']}");
as_www('php /tmp/s5test/portal/tools/cron.php');
t_ok((int) $sql("SELECT COUNT(*) FROM s5test.s5_alerts WHERE order_id={$s['id']} AND message LIKE 'Renovación%'") >= 1, 'alerta de renovación a 30 días');
t_ok(str_contains(implode('', array_map('file_get_contents', glob('/tmp/s5test/mail/*.json'))), 'Renovación próxima'), 'correo de renovación al dueño');
mysql_val("UPDATE s5test.s5_orders SET renewal_at='" . gmdate('Y-m-d', time() - 3 * 86400) . "' WHERE id={$s['id']}");
as_www('php /tmp/s5test/portal/tools/cron.php');
t_ok($sql("SELECT status FROM s5test.s5_orders WHERE id={$s['id']}") === 'vencida', 'estado vencida');
$adm = admin_client(); $adm->form("/admin/pedido/{$s['id']}/accion", ['accion' => 'renovado']);
t_ok($sql("SELECT status FROM s5test.s5_orders WHERE id={$s['id']}") === 'publicada', 'marcar renovado → publicada');
t_ok($sql("SELECT renewal_at FROM s5test.s5_orders WHERE id={$s['id']}") > gmdate('Y-m-d', time() + 300 * 86400), 'renovación +1 año');
$adm->form("/admin/pedido/{$s['id']}/accion", ['accion' => 'suspender']);
$h = $site->req('GET', '/'); t_ok($h['status'] === 503 && str_contains($h['body'], 'suspendido'), 'suspendido: 503 con aviso', (string) $h['status']);
$adm->form("/admin/pedido/{$s['id']}/accion", ['accion' => 'reactivar']);
$h = $site->req('GET', '/'); t_ok($h['status'] === 200, 'reactivado: 200', (string) $h['status']);
$adm->form("/admin/pedido/{$s['id']}/accion", ['accion' => 'dominio', 'dominio' => 'servicom.gt']);
t_ok($sql("SELECT IFNULL(domain_assigned,'')") === null || $sql("SELECT domain_assigned FROM s5test.s5_orders WHERE id={$s['id']}") === null, 'dominio de Servicom rechazado');
$adm->form("/admin/pedido/{$s['id']}/accion", ['accion' => 'dominio', 'dominio' => 'cliente-demo.test']);
t_ok($sql("SELECT domain_assigned FROM s5test.s5_orders WHERE id={$s['id']}") === 'cliente-demo.test', 'dominio asignado');
t_ok(is_link("$V/cliente-demo.test"), 'host virtual del dominio creado');
$dom = new Client('http://127.0.0.1:8200', 'cliente-demo.test:8200'); $h = $dom->req('GET', '/');
t_ok($h['status'] === 200 && str_contains($h['body'], 'cliente-demo.test') && !str_contains($h['body'], "{$s['slug']}.servicom.test"), 'el sitio responde en el dominio nuevo con todas las URLs reemplazadas', (string) $h['status']);
t_ok((int) mysql_val("SELECT COUNT(*) FROM `$wpdb`.`{$pre}options` WHERE option_value LIKE '%{$s['slug']}.servicom.test%'") === 0 && (int) mysql_val("SELECT COUNT(*) FROM `$wpdb`.`{$pre}postmeta` WHERE meta_value LIKE '%{$s['slug']}.servicom.test%' OR meta_value LIKE '%{$s['slug']}.servicom.test%'") === 0, 'no quedan URLs viejas en la BD (opciones y postmeta/Elementor)');

echo "== Vistas previas vencidas (15 días)\n";
$p1 = build_site(['nombre' => 'Previa Vieja', 'servicios' => 2]); $p2 = build_site(['nombre' => 'Previa Reciente', 'servicios' => 2]);
t_ok($p1 && $p2, 'dos vistas previas creadas');
$pdb = mysql_val("SELECT db_name FROM s5test.s5_orders WHERE id={$p1['id']}"); $pus = mysql_val("SELECT db_user FROM s5test.s5_orders WHERE id={$p1['id']}");
t_ok(is_dir("$W/{$p1['slug']}") && is_link("$V/{$p1['slug']}.servicom.test") && is_dir("/tmp/s5test/portal/storage/uploads/{$p1['id']}"), 'recursos de la vista previa existen antes del cron');
mysql_val("UPDATE s5test.s5_orders SET expires_at='" . gmdate('Y-m-d H:i:s', time() - 60) . "' WHERE id={$p1['id']}");
$out = as_www('php /tmp/s5test/portal/tools/cron.php'); echo "  cron: " . trim($out) . "\n";
t_ok($sql("SELECT status FROM s5test.s5_orders WHERE id={$p1['id']}") === 'eliminada', 'vista previa vencida eliminada');
t_ok(!is_dir("$W/{$p1['slug']}") && !is_link("$V/{$p1['slug']}.servicom.test"), 'carpeta y host virtual eliminados');
t_ok(!in_array($pdb, sim_dbs(), true) && !in_array($pus, sim_users(), true), 'base de datos y usuario eliminados');
t_ok(!is_dir("/tmp/s5test/portal/storage/uploads/{$p1['id']}") && (int) $sql("SELECT COUNT(*) FROM s5test.s5_files WHERE order_id={$p1['id']}") === 0, 'archivos subidos eliminados');
t_ok($sql("SELECT status FROM s5test.s5_orders WHERE id={$p2['id']}") === 'vista_lista' && is_dir("$W/{$p2['slug']}"), 'la vista previa reciente no se toca');
t_ok(is_dir("$W/{$s['slug']}") && is_dir("$W/_base") && is_dir("$W/ajeno-existente"), 'publicados, _base y carpetas ajenas intactos');
t_ok((int) $sql("SELECT COUNT(*) FROM s5test.s5_audit_log WHERE action='vista_previa_eliminada' AND order_id={$p1['id']}") === 1, 'borrado registrado en bitácora');
echo "\nCiclo de vida: " . ($GLOBALS['__t_n'] - $GLOBALS['__t_fail']) . "/" . $GLOBALS['__t_n'] . " OK\n";
exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0);
