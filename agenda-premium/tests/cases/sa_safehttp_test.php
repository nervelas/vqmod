<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
require __DIR__ . '/../lib/SaHelper.php';
T::boot('sa_safehttp');

use App\Core\Config;
use App\Services\SafeHttp;

$dir = SaHelper::workDir('safehttp');
SaHelper::startPhp(8171, __DIR__ . '/../lib/sa_receiver.php');
SaHelper::startPhp(8172, __DIR__ . '/../lib/sa_receiver.php');
$base = 'http://127.0.0.1:8171';

T::section('SSRF: direcciones rechazadas por validateUrl');
$blocked = [
    'http://127.0.0.1/', 'http://127.0.0.1:8080/x', 'http://localhost/', 'http://LOCALHOST:9000/', 'http://foo.localhost/', 'http://localhost./',
    'http://10.0.0.5/', 'http://10.255.255.255/', 'http://192.168.1.1/', 'http://172.16.0.1/', 'http://172.31.255.255/', 'http://169.254.169.254/latest/meta-data/',
    'http://100.64.0.1/', 'http://100.100.100.200/', 'http://192.0.0.192/', 'http://0.0.0.0/', 'http://0/', 'http://224.0.0.1/', 'http://255.255.255.255/', 'http://168.63.129.16/',
    'http://198.18.0.1/', 'http://[::1]/', 'http://[::]/', 'http://[::ffff:127.0.0.1]/', 'http://[::ffff:7f00:1]/', 'http://[::ffff:10.0.0.1]/', 'http://[::ffff:a9fe:a9fe]/',
    'http://[fe80::1]/', 'http://[fc00::1]/', 'http://[fd00:ec2::254]/', 'http://[fec0::1]/', 'http://[ff02::1]/', 'http://[2002:7f00:1::]/', 'http://[64:ff9b::7f00:1]/', 'http://[2001:db8::1]/', 'http://[0:0:0:0:0:0:0:1]/',
    'http://2130706433/', 'http://2130706433:8080/', 'http://0x7f000001/', 'http://0X7F000001/', 'http://017700000001/', 'http://0177.0.0.1/', 'http://127.1/', 'http://0x7f.0.0.1/', 'http://127.0.1/',
    'http://2852039166/', 'http://0xa9fea9fe/', 'http://0251.0376.0251.0376/', 'http://3232235777/', 'http://167772161/',
];
foreach ($blocked as $u) {
    $e = SafeHttp::validateUrl($u);
    T::ok($e !== null, 'rechaza ' . $u . ($e === null ? ' (¡se permitió!)' : ''));
}
T::ok(strpos((string) SafeHttp::validateUrl('http://127.0.0.1/'), 'internas o privadas') !== false, 'el mensaje es amable y en español');
$hostResolvesLoopback = in_array('127.0.0.1', (array) gethostbynamel('runsc'), true);
if ($hostResolvesLoopback) {
    T::ok(SafeHttp::validateUrl('http://runsc/') !== null, 'un nombre de host que resuelve a 127.0.0.1 se rechaza por DNS (runsc)');
}

T::section('SSRF: esquemas y formatos no permitidos');
foreach (['file:///etc/passwd', 'gopher://example.com/', 'ftp://example.com/x', 'javascript:alert(1)', 'data:text/plain,hola', 'dict://127.0.0.1:11211/', 'ldap://example.com', '//example.com/x', 'example.com', '', 'http://', 'http:///x', "http://exa mple.com/", "http://example.com/\r\nHost: x", 'http://exa\\mple.com/', 'http://user:pass@example.com/', 'http://user@8.8.8.8/', 'http://8.8.8.8:99999/', 'http://%31%32%37.0.0.1/', 'http://1.2.3.4.5/', 'http://256.1.1.1/', 'http://08.8.8.8/'] as $u) {
    T::ok(SafeHttp::validateUrl($u) !== null, 'rechaza ' . json_encode($u));
}
T::ok(strpos((string) SafeHttp::validateUrl('ftp://example.com/x'), 'http o https') !== false, 'mensaje para esquemas no permitidos');
T::ok(strpos((string) SafeHttp::validateUrl('http://user:pass@example.com/'), 'usuario ni contraseña') !== false, 'mensaje para credenciales en la URL');
T::ok(strpos((string) SafeHttp::validateUrl(str_repeat('a', 2100)), 'no es válida') !== false, 'URL demasiado larga');

T::section('SSRF: direcciones públicas permitidas (sin hacer conexiones)');
foreach (['http://8.8.8.8/', 'https://1.1.1.1:8443/x?y=1', 'http://[2606:4700:4700::1111]/', 'http://134744072/', 'http://0x08080808/', 'http://8.8.8.8:8081/', 'http://[::ffff:8.8.8.8]/', 'http://[64:ff9b::808:808]/', 'http://172.15.0.1/', 'http://172.32.0.1/', 'http://100.63.255.255/', 'http://100.128.0.1/'] as $u) {
    T::ok(SafeHttp::validateUrl($u) === null, 'permite ' . $u . ' (' . (string) SafeHttp::validateUrl($u) . ')');
}
T::ok(SafeHttp::validateUrl('http://nombre-que-no-existe-xyz.invalid/') !== null, 'un nombre que no resuelve da un error amable');

T::section('Excepción solo para pruebas: allow_private_http');
T::ok(SafeHttp::validateUrl($base . '/rec/a') !== null, 'sin la excepción, el receptor local se rechaza');
Config::set('allow_private_http', true);
T::ok(SafeHttp::validateUrl($base . '/rec/a') === null && SafeHttp::validateUrl('file:///etc/passwd') !== null, 'con la excepción se permiten IP privadas pero nunca otros esquemas');
Config::set('allow_private_http', 'true');
T::ok(SafeHttp::validateUrl($base . '/rec/a') !== null, 'solo el valor booleano true activa la excepción');

foreach (['curl' => false, 'streams' => true] as $mode => $noCurl) {
    Config::set('safehttp_no_curl', $noCurl);
    Config::set('allow_private_http', true);
    T::section("Peticiones reales con " . ($noCurl ? 'sockets (sin cURL)' : 'cURL'));
    $r = SafeHttp::post($base . '/rec/p_' . $mode, '{"a":"ñ"}', ['Content-Type: application/json', 'X-Prueba: 1'], ['max_redirects' => 0]);
    T::ok($r['ok'] && $r['status'] === 200 && $r['body'] === 'ok' && $r['error'] === null, 'POST correcto');
    $log = SaHelper::jsonl($dir . '/log_p_' . $mode . '.jsonl');
    T::ok(($log[0]['body'] ?? '') === '{"a":"ñ"}' && ($log[0]['headers']['x-prueba'] ?? '') === '1' && ($log[0]['headers']['content-type'] ?? '') === 'application/json' && ($log[0]['method'] ?? '') === 'POST', 'el receptor recibió cuerpo, cabeceras y método');
    $r = SafeHttp::get($base . '/rec/g_' . $mode . '?code=404');
    T::ok(!$r['ok'] && $r['status'] === 404 && strpos((string) $r['error'], '404') !== false, 'GET 404: ok=false con mensaje amable');
    $r = SafeHttp::get($base . '/rec/h_' . $mode . '?fail=5');
    T::ok(!$r['ok'] && $r['status'] === 500 && strpos((string) $r['error'], 'problema') !== false, 'GET 500: mensaje amable');

    $r = SafeHttp::get($base . '/redirect?to=' . rawurlencode('/rec/r_' . $mode));
    T::ok($r['ok'] && $r['body'] === 'ok' && strpos($r['url'], '/rec/r_') !== false, 'sigue una redirección relativa válida');
    $r = SafeHttp::get($base . '/redirect?to=' . rawurlencode('http://127.0.0.1:8172/rec/r2_' . $mode));
    T::ok($r['ok'], 'sigue una redirección a otro puerto permitido');
    $r = SafeHttp::get($base . '/redirect?to=' . rawurlencode($base . '/redirect?to=' . rawurlencode($base . '/redirect?to=' . rawurlencode($base . '/redirect?to=' . rawurlencode($base . '/rec/loop_' . $mode)))));
    T::ok(!$r['ok'] && strpos((string) $r['error'], 'redirige demasiadas veces') !== false, 'más de 3 redirecciones: error');
    $r = SafeHttp::post($base . '/redirect?to=' . rawurlencode('/rec/rp_' . $mode), 'x', [], ['max_redirects' => 0]);
    T::ok(!$r['ok'] && $r['status'] === 302, 'POST no sigue redirecciones por defecto');

    // Redirecciones hacia destinos prohibidos: solo se permite el primer salto
    Config::set('allow_private_http', ['127.0.0.1:8171']);
    T::ok($r = SafeHttp::get($base . '/rec/ok_' . $mode), 'primer salto permitido por lista');
    foreach (['http://127.0.0.1:8172/rec/x', 'http://169.254.169.254/latest/meta-data/', 'http://[::1]:8171/', 'http://localhost:8171/', 'http://2130706433:8172/', 'http://10.0.0.1/', 'http://192.168.0.1/', 'file:///etc/passwd', 'gopher://127.0.0.1:6379/', 'http://user:pw@127.0.0.1:8171/', '//127.0.0.1:8172/rec/y'] as $target) {
        $r = SafeHttp::get($base . '/redirect?to=' . rawurlencode($target));
        T::ok(!$r['ok'] && $r['error'] !== null && $r['body'] === '', 'redirección hacia ' . $target . ' bloqueada (' . $r['error'] . ')');
    }
    T::ok(!is_file($dir . '/count_x') && !is_file($dir . '/count_y'), 'el destino bloqueado nunca recibió la petición');
    // Redirección encadenada: público -> permitido -> prohibido
    $r = SafeHttp::get($base . '/redirect?to=' . rawurlencode($base . '/redirect?to=' . rawurlencode('http://127.0.0.1:8172/rec/z')));
    T::ok(!$r['ok'] && !is_file($dir . '/count_z'), 'cada salto se valida de nuevo');
    Config::set('allow_private_http', true);

    $r = SafeHttp::get($base . '/big');
    T::ok(!$r['ok'] && $r['body'] === '' && strpos((string) $r['error'], '2 MB') !== false, 'respuesta de 4 MB rechazada por tamaño');
    $r = SafeHttp::get($base . '/big', ['max_bytes' => 5 * 1048576]);
    T::ok(!$r['ok'] && strpos((string) $r['error'], '2 MB') !== false, 'el límite no se puede subir por encima de 2 MB');
    $r = SafeHttp::get($base . '/gzbomb');
    T::ok(($noCurl ? true : (!$r['ok'] && strpos((string) $r['error'], '2 MB') !== false)), 'bomba gzip limitada tras descomprimir' . ($noCurl ? ' (el respaldo no pide gzip)' : ''));
    $t0 = microtime(true);
    $r = SafeHttp::get($base . '/slow?s=12', ['timeout' => 2]);
    $el = microtime(true) - $t0;
    T::ok(!$r['ok'] && $el < 4 && strpos((string) $r['error'], 'tardó demasiado') !== false, 'tiempo de espera de 2 s respetado (' . round($el, 1) . ' s)');
}
Config::set('safehttp_no_curl', false);
$t0 = microtime(true);
$r = SafeHttp::get($base . '/slow?s=15', ['timeout' => 120]);
$el = microtime(true) - $t0;
T::ok(!$r['ok'] && $el >= 9 && $el < 12.5, 'el límite máximo es 10 s aunque se pida más (' . round($el, 1) . ' s)');

T::section('Conexión fijada a la IP validada y otros');
Config::set('allow_private_http', true);
if ($hostResolvesLoopback) {
    $r = SafeHttp::get('http://runsc:8171/rec/host_header');
    $log = SaHelper::jsonl($dir . '/log_host_header.jsonl');
    T::ok($r['ok'] && ($log[0]['headers']['host'] ?? '') === 'runsc:8171', 'con nombre de host, la cabecera Host conserva el nombre original');
}
$r = SafeHttp::get('http://127.0.0.1:1/');
T::ok(!$r['ok'] && strpos((string) $r['error'], 'No pudimos conectarnos') !== false, 'puerto cerrado: mensaje amable');
$r = SafeHttp::get('http://nombre-que-no-existe-xyz.invalid/');
T::ok(!$r['ok'] && strpos((string) $r['error'], 'No encontramos') !== false, 'sitio inexistente: mensaje amable');
$r = SafeHttp::get($base . '/rec/hdr', ['headers' => ['X-Uno' => 'a', "X-Dos: b\r\nX-Inyectada: c"]]);
$log = SaHelper::jsonl($dir . '/log_hdr.jsonl');
T::ok(($log[0]['headers']['x-uno'] ?? '') === 'a' && !isset($log[0]['headers']['x-inyectada']), 'las cabeceras se pasan sin permitir inyección de líneas');
Config::set('allow_private_http', false);
$r = SafeHttp::get('http://127.0.0.1:8171/rec/nunca');
T::ok(!$r['ok'] && $r['status'] === 0 && !is_file($dir . '/count_nunca'), 'get() sin la excepción no llega a conectar con una IP privada');
$r = SafeHttp::post('http://169.254.169.254/latest/', 'x', []);
T::ok(!$r['ok'] && $r['status'] === 0, 'post() también está protegido');

SaHelper::stopAll();
T::done();
