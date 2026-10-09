<?php
declare(strict_types=1);
/** SSL: espera real de certificado válido, reintentos, límite de espera, reanudación y HTTPS en vista previa. Requiere setup SSL (Apache :8443). */
require __DIR__ . '/lib.php';
reset_limits();
$S = '/tmp/s5test/ssl';
$cert = function (string $which) use ($S) { copy("$S/$which.crt", "$S/live.crt"); copy("$S/$which.key", "$S/live.key"); shell_exec('apache2ctl graceful 2>&1'); sleep(2); };
shell_exec("cat $S/ca.crt >> /tmp/s5test/webs/_base/wp-includes/certificates/ca-bundle.crt");
$ENV = 'env S5_SITE_PORT=8443 ';
$tickCmd = fn(int $id, int $t = 20) => as_www($ENV . 'php /home/user/vqmod/servicom-5min/tests/e2e/tick.php ' . $id . ' ' . $t);
mysql_val("INSERT INTO s5test.s5_settings (k,v,is_secret) VALUES ('force_scheme','https',0) ON DUPLICATE KEY UPDATE v='https'");
mysql_val("INSERT INTO s5test.s5_settings (k,v,is_secret) VALUES ('ssl_wait_sec','25',0) ON DUPLICATE KEY UPDATE v='25'");

function draftS(string $n): array {
    $c = new Client(); $c->page('/crear'); $tok = $c->api('POST', '/api/borrador', ['plan' => 'info'])['json']['token'];
    $c->api('POST', "/api/borrador/$tok/guardar", ['data' => ['negocio' => ['nombre' => $n, 'rubro' => 'abogado', 'estilo' => 1], 'contacto' => ['whatsapp' => '50233334444'], 'contenido' => ['servicios' => [['nombre' => 'Asesoría', 'descripcion' => 'x']]]]]);
    return [$c, $tok, (int) mysql_val("SELECT id FROM s5test.s5_orders WHERE token='$tok'")];
}
echo "== Certificado aún no válido (autofirmado): se espera y no se muestra nada inseguro\n";
$cert('bad');
[$c, $tok, $id] = draftS('Bufete Seguro SSL');
as_www($ENV . 'php -r ' . escapeshellarg('define("S5_ROOT","/tmp/s5test/portal");require "/tmp/s5test/portal/app/bootstrap.php";S5\Services\Pipeline::start(' . $id . ');'));
$waiting = false; $t0 = time(); $st = '';
for ($i = 0; $i < 12; $i++) {
    $tickCmd($id, 10);
    $st = mysql_val("SELECT status FROM s5test.s5_orders WHERE id=$id");
    $g = $c->api('GET', "/api/borrador/$tok/construccion")['json'] ?? [];
    if (str_contains((string) ($g['mensaje'] ?? ''), 'conexión segura')) { $waiting = true; }
    if ($st !== 'construyendo') { break; }
}
t_ok($waiting, 'mensaje «Estamos activando la conexión segura…» mientras espera');
t_ok($st === 'preparando', 'tras el límite de espera pasa a «preparando» (estado=' . $st . ')', (string) $st);
$g = $c->api('GET', "/api/borrador/$tok/construccion")['json'] ?? [];
t_ok(($g['estado'] ?? '') === 'preparando' && empty($g['url']) && str_contains((string) ($g['mensaje'] ?? ''), 'preparando'),  'el cliente no recibe el enlace y ve «Estamos preparando su vista previa»', json_encode(['estado' => $g['estado'] ?? null, 'url' => $g['url'] ?? null, 'mensaje' => $g['mensaje'] ?? null], JSON_UNESCAPED_UNICODE));
t_ok((int) mysql_val("SELECT COUNT(*) FROM s5test.s5_alerts WHERE order_id=$id AND message LIKE 'SSL%'") >= 1, 'alerta en el panel por SSL');
t_ok(str_contains(implode('', array_map('file_get_contents', glob('/tmp/s5test/mail/*.json'))), 'esperando SSL'), 'correo al dueño por SSL');
echo "== AutoSSL emite el certificado → el dueño reanuda\n";
$cert('srv');
as_www($ENV . 'php -r ' . escapeshellarg('define("S5_ROOT","/tmp/s5test/portal");require "/tmp/s5test/portal/app/bootstrap.php";S5\Services\Pipeline::resume(' . $id . ');'));
for ($i = 0; $i < 40; $i++) { $st = mysql_val("SELECT status FROM s5test.s5_orders WHERE id=$id"); if ($st !== 'construyendo') { break; } $tickCmd($id, 20); }
t_ok($st === 'vista_lista', 'con certificado válido la vista previa queda lista (estado=' . $st . ')', mysql_val("SELECT build_msg FROM s5test.s5_orders WHERE id=$id") . ' ' . (mysql_val("SELECT left(qa_result,300) FROM s5test.s5_orders WHERE id=$id")));
$slug = mysql_val("SELECT slug FROM s5test.s5_orders WHERE id=$id"); $key = mysql_val("SELECT preview_key FROM s5test.s5_orders WHERE id=$id");
$out = shell_exec("curl --noproxy '*' -s -i -c /tmp/s5ck.txt -b /tmp/s5ck.txt -L --resolve $slug.servicom.test:8443:127.0.0.1 'https://$slug.servicom.test:8443/?scpk=$key' 2>&1");
t_ok(str_contains((string) $out, '200 OK') && !preg_match('/(Warning|Notice|Fatal error):/', (string) $out), 'HTTPS válido sirve la vista previa sin errores');
t_ok((bool) preg_match('/Strict-Transport-Security/i', (string) $out), 'cabecera HSTS en HTTPS');
t_ok((bool) preg_match('/Set-Cookie: sc_pk=[^;]+;.*secure/i', (string) $out), 'cookie de vista previa con Secure');
t_ok(str_starts_with((string) mysql_val("SELECT option_value FROM `" . mysql_val("SELECT db_name FROM s5test.s5_orders WHERE id=$id") . "`.`" . mysql_val("SELECT wp_prefix FROM s5test.s5_orders WHERE id=$id") . "options` WHERE option_name='siteurl'"), 'https://'), 'siteurl de WordPress es https');
// restaurar
mysql_val("UPDATE s5test.s5_settings SET v='http' WHERE k='force_scheme'"); mysql_val("DELETE FROM s5test.s5_settings WHERE k='ssl_wait_sec'");
echo "\nSSL: " . ($GLOBALS['__t_n'] - $GLOBALS['__t_fail']) . "/" . $GLOBALS['__t_n'] . " OK\n";
exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0);
