<?php
/**
 * Mini-biblioteca de pruebas (sin dependencias). Cada t_*.php hace require de esto.
 * Carga WordPress del sitio de pruebas (ver setup.sh) y ofrece helpers.
 */
if (PHP_SAPI !== 'cli') { exit; }
$GLOBALS['T'] = ['pass' => 0, 'fail' => 0, 'name' => basename($_SERVER['argv'][0] ?? 't')];
$ENV = require (getenv('T2A_ENV') ?: '/tmp/t2a/env.php');
$_SERVER['HTTP_HOST'] = parse_url($ENV['url'], PHP_URL_HOST) . ':' . parse_url($ENV['url'], PHP_URL_PORT);
$_SERVER['SERVER_NAME'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
if (empty($NO_WP)) { require $ENV['site'] . '/wp-load.php'; }

function ok($cond, string $msg, string $extra = ''): void {
    if ($cond) { $GLOBALS['T']['pass']++; echo "  ok   $msg\n"; }
    else { $GLOBALS['T']['fail']++; echo "  FAIL $msg" . ($extra !== '' ? "  -> $extra" : '') . "\n"; }
}
function eq($a, $b, string $msg): void { ok($a === $b, $msg, 'esperado ' . var_export($b, true) . ' obtenido ' . var_export($a, true)); }
function section(string $s): void { echo "\n== $s\n"; }
function done(): void {
    $T = $GLOBALS['T'];
    echo "\n{$T['name']}: {$T['pass']} ok, {$T['fail']} fallos\n";
    exit($T['fail'] ? 1 : 0);
}
/** Petición HTTP al sitio. Devuelve ['code','headers'(array minúsculas),'body','raw_headers']. */
function http(string $url, array $o = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => !empty($o['follow']),
        CURLOPT_TIMEOUT => 30, CURLOPT_USERAGENT => 'sc-test',
    ]);
    if (!empty($o['method']) && $o['method'] === 'HEAD') { curl_setopt($ch, CURLOPT_NOBODY, true); }
    if (isset($o['post'])) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($o['post']) ? http_build_query($o['post']) : $o['post']); }
    if (!empty($o['cookie'])) { curl_setopt($ch, CURLOPT_COOKIE, $o['cookie']); }
    if (!empty($o['jar'])) { curl_setopt($ch, CURLOPT_COOKIEJAR, $o['jar']); curl_setopt($ch, CURLOPT_COOKIEFILE, $o['jar']); }
    if (!empty($o['headers'])) { curl_setopt($ch, CURLOPT_HTTPHEADER, $o['headers']); }
    $r = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $raw = substr((string) $r, 0, $hs); $body = substr((string) $r, $hs);
    $h = [];
    foreach (explode("\r\n", $raw) as $line) {
        if (strpos($line, ':') !== false) { [$k, $v] = explode(':', $line, 2); $h[strtolower(trim($k))] = trim($v); }
    }
    return ['code' => $code, 'headers' => $h, 'body' => $body, 'raw_headers' => $raw];
}
/** Inicia sesión por HTTP; devuelve la ruta del jar de cookies. */
function login_jar(string $user, string $pass, string $tag = 'x'): string {
    global $ENV;
    $jar = $ENV['dir'] . "/jar_$tag.txt"; @unlink($jar);
    http($ENV['url'] . '/wp-login.php', ['jar' => $jar]);
    $r = http($ENV['url'] . '/wp-login.php', ['jar' => $jar, 'cookie' => 'wordpress_test_cookie=WP%20Cookie%20check', 'post' => [
        'log' => $user, 'pwd' => $pass, 'wp-submit' => 'Acceder', 'redirect_to' => $ENV['url'] . '/wp-admin/', 'testcookie' => '1']]);
    return $jar;
}
