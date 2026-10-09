<?php
declare(strict_types=1);
/** Segundo hosting vía agente: construcción remota, seguridad HMAC, anti-repetición, URLs firmadas de un solo uso. */
require __DIR__ . '/lib.php';
require dirname(__DIR__, 2) . '/app/Provision/Hmac.php';
use S5\Provision\Hmac;
$cfg = require '/tmp/s5test/agent/storage/config.php'; $secret = $cfg['secret'];
function call(string $secret, string $op, array $args = [], array $over = []): array {
    $body = json_encode(['op' => $op, 'args' => $args]);
    $ts = $over['ts'] ?? time(); $nonce = $over['nonce'] ?? bin2hex(random_bytes(12));
    $sig = $over['sig'] ?? hash_hmac('sha256', $ts . "\n" . $nonce . "\n" . hash('sha256', $body), $secret);
    $ch = curl_init('http://127.0.0.1:8202/agent.php');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json', "X-S5-Ts: $ts", "X-S5-Nonce: $nonce", "X-S5-Sig: $sig"]]);
    $raw = (string) curl_exec($ch); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); $st = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return ['status' => $st, 'headers' => substr($raw, 0, $hs), 'body' => substr($raw, $hs), 'json' => json_decode(substr($raw, $hs), true), 'nonce' => $nonce];
}
echo "== Seguridad del agente\n";
$r = call($secret, 'ping'); t_ok($r['status'] === 200 && !empty($r['json']['ok']), 'ping firmado correcto', substr($r['body'], 0, 120));
t_ok(str_contains(strtolower($r['headers']), 'x-s5-rsig'), 'la respuesta va firmada');
$r2 = call($secret, 'ping', [], ['nonce' => $r['nonce']]); t_ok($r2['status'] === 403, 'nonce repetido rechazado (anti-repetición)');
$r = call($secret, 'ping', [], ['sig' => str_repeat('0', 64)]); t_ok($r['status'] === 403, 'firma inválida → 403');
$r = call('otro-secreto-' . str_repeat('x', 40), 'ping'); t_ok($r['status'] === 403, 'secreto equivocado → 403');
$r = call($secret, 'ping', [], ['ts' => time() - 600]); t_ok($r['status'] === 403, 'marca de tiempo vencida → 403');
$r = call($secret, 'ping', [], ['ts' => time() + 600]); t_ok($r['status'] === 403, 'marca de tiempo futura → 403');
$ch = curl_init('http://127.0.0.1:8202/agent.php'); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true]); curl_exec($ch); t_ok(curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 403, 'GET sin firma → 403'); curl_close($ch);
foreach (['/storage/config.php', '/app/Provision/LocalDriver.php', '/storage/agent.log'] as $p) { $c = curl_init("http://127.0.0.1:8202$p"); curl_setopt($c, CURLOPT_RETURNTRANSFER, true); curl_exec($c); t_ok(in_array(curl_getinfo($c, CURLINFO_RESPONSE_CODE), [403, 404], true), "acceso directo bloqueado $p"); curl_close($c); }
$r = call($secret, 'removeSite', ['docroot' => '/tmp/s5test/webs/_base']); t_ok(empty($r['json']['ok']), 'removeSite fuera de su carpeta rechazado', substr($r['body'], 0, 150));
$r = call($secret, 'removeSite', ['docroot' => '/tmp/s5test/webs2/../webs/abc']); t_ok(empty($r['json']['ok']), 'removeSite con traversal rechazado');
$r = call($secret, 'removeSite', ['docroot' => '/tmp/s5test/webs2/_base']); t_ok(empty($r['json']['ok']) && is_dir('/tmp/s5test/webs2/_base'), 'el paquete _base no se puede borrar');
$r = call($secret, 'writeFile', ['docroot' => '/tmp/s5test/webs2/x', 'rel' => '../../etc/x', 'content_b64' => base64_encode('x')]); t_ok(empty($r['json']['ok']), 'writeFile con traversal rechazado');
$r = call($secret, 'stageJob', ['docroot' => '/tmp/s5test/webs2/zz', 'jobId' => 'abcdef123456', 'manifest_b64' => base64_encode('{}'), 'assets' => [['path' => 'assets/a1.jpg', 'url' => 'http://evil.example/x.jpg']], 'secret' => 'x']); t_ok(empty($r['json']['ok']), 'descarga desde URL ajena rechazada', substr($r['body'], 0, 120));

echo "== URL firmada de un solo uso\n";
$pc0 = new Client(); $pc0->page('/crear'); $pc0->api('POST', '/api/borrador', ['plan' => 'info']);
$oid = (int) mysql_val('SELECT id FROM s5test.s5_orders ORDER BY id DESC LIMIT 1'); $tok = (string) mysql_val("SELECT token FROM s5test.s5_orders WHERE id=$oid");
@mkdir("/tmp/s5test/portal/storage/jobs/$oid/assets", 0777, true); file_put_contents("/tmp/s5test/portal/storage/jobs/$oid/assets/a1.jpg", "\xFF\xD8\xFFtest"); @chmod("/tmp/s5test/portal/storage/jobs/$oid/assets/a1.jpg", 0666); @chmod("/tmp/s5test/portal/storage/jobs/$oid", 0777); @chmod("/tmp/s5test/portal/storage/jobs/$oid/assets", 0777);
$exp = time() + 300; $n = bin2hex(random_bytes(8)); $sig = Hmac::assetSig($secret, $tok, 'a1.jpg', $exp, $n);
$q = http_build_query(['t' => $tok, 'id' => 'a1.jpg', 'exp' => $exp, 'n' => $n, 'sig' => $sig]);
$pc = new Client();
$a = $pc->req('GET', '/ag/asset?' . $q); t_ok($a['status'] === 200, 'primera descarga con URL firmada', (string) $a['status']);
$b = $pc->req('GET', '/ag/asset?' . $q); t_ok($b['status'] === 403, 'segunda descarga (reuso) rechazada');
$q2 = http_build_query(['t' => $tok, 'id' => 'a1.jpg', 'exp' => time() - 5, 'n' => bin2hex(random_bytes(8)), 'sig' => $sig]); $c2 = $pc->req('GET', '/ag/asset?' . $q2); t_ok($c2['status'] === 403, 'URL vencida rechazada');
$q3 = http_build_query(['t' => $tok, 'id' => '../../config.php', 'exp' => $exp, 'n' => $n, 'sig' => $sig]); $c3 = $pc->req('GET', '/ag/asset?' . $q3); t_ok($c3['status'] === 403, 'traversal en id rechazado');

echo "== Construcción y publicación en el segundo hosting\n";
mysql_val("UPDATE s5test.s5_settings SET v='2' WHERE k='default_host'");
$out = shell_exec('cd ' . escapeshellarg(dirname(__DIR__, 2)) . ' && timeout 800 php tests/e2e/flow_basic.php --nombre="Taller Remoto" --servicios=3 2>&1');
mysql_val("UPDATE s5test.s5_settings SET v='1' WHERE k='default_host'");
echo preg_replace('/^/m', '    ', trim((string) $out)) . "\n";
t_ok(str_contains((string) $out, 'Resultado: ') && !str_contains((string) $out, 'FAIL'), 'flujo completo (formulario→pago→publicación) vía agente');
$row = mysql_val('SELECT CONCAT(slug,"|",host_id) FROM s5test.s5_orders ORDER BY id DESC LIMIT 1'); [$slug, $hid] = explode('|', (string) $row);
t_ok($hid === '2' && is_dir("/tmp/s5test/webs2/$slug") && !is_dir("/tmp/s5test/webs/$slug"), 'la web vive en la carpeta del segundo hosting, no en la del primero', "$slug host=$hid");
t_ok(!is_file("/tmp/s5test/webs2/$slug/sc-provision.php"), 'sin script de aprovisionamiento remanente');
echo "\nAgente: " . ($GLOBALS['__t_n'] - $GLOBALS['__t_fail']) . "/" . $GLOBALS['__t_n'] . " OK\n";
exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0);
