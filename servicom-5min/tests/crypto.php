<?php
declare(strict_types=1);
/** Cifrado de secretos sin sodium (AES-256-GCM/OpenSSL) y compatibilidad con valores antiguos de sodium. */
define('S5_ROOT', dirname(__DIR__));
require_once S5_ROOT . '/app/Core/Config.php';
require_once S5_ROOT . '/app/Core/Crypto.php';
use S5\Core\Config; use S5\Core\Crypto;
$n = 0; $f = 0;
function ok(bool $c, string $m): void { global $n, $f; $n++; if (!$c) { $f++; echo "FAIL $m\n"; } else { echo "PASS $m\n"; } }
Config::override(['secret_key' => Crypto::newKey()]);
ok(strlen(base64_decode(Config::get('secret_key'))) === 32, 'llave de 32 bytes sin sodium');
ok(Crypto::available(), 'AES-256-GCM disponible');
foreach (['', 'a', 'token-cPanel-ABC123', str_repeat('x', 5000), "ñandú 😀 \0 raro", 'sk-ant-api03-' . bin2hex(random_bytes(40))] as $i => $p) {
    $c = Crypto::encrypt($p);
    ok(str_starts_with($c, 'enc2:') && (strlen($p) < 6 || !str_contains(base64_decode(substr($c, 5)), $p)) && Crypto::decrypt($c) === $p, "ida y vuelta #$i");
}
ok(Crypto::encrypt('igual') !== Crypto::encrypt('igual'), 'IV aleatorio: dos cifrados distintos');
$c = Crypto::encrypt('secreto'); $raw = base64_decode(substr($c, 5));
$t = $raw; $t[20] = chr(ord($t[20]) ^ 1); ok(Crypto::decrypt('enc2:' . base64_encode($t)) === '', 'texto alterado → rechazado (autenticado)');
$t = $raw; $t[14] = chr(ord($t[14]) ^ 1); ok(Crypto::decrypt('enc2:' . base64_encode($t)) === '', 'etiqueta alterada → rechazado');
ok(Crypto::decrypt('enc2:' . base64_encode(substr($raw, 0, 20))) === '', 'truncado → vacío sin errores');
ok(Crypto::decrypt('enc2:%%%') === '' && Crypto::decrypt('basura') === '' && Crypto::decrypt('') === '', 'basura → vacío sin errores');
$other = Crypto::encrypt('x'); Config::override(['secret_key' => Crypto::newKey()]); ok(Crypto::decrypt($other) === '', 'otra llave → no descifra');
Config::override(['secret_key' => 'corta']); $e = false; try { Crypto::encrypt('x'); } catch (\RuntimeException $x) { $e = true; } ok($e, 'llave inválida → error claro');
Config::override(['secret_key' => Crypto::newKey()]);
if (function_exists('sodium_crypto_secretbox')) {
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); $legacy = 'enc1:' . base64_encode($nonce . sodium_crypto_secretbox('antiguo', $nonce, base64_decode(Config::get('secret_key'))));
    ok(Crypto::decrypt($legacy) === 'antiguo', 'compatibilidad: valores antiguos de sodium se leen');
} else {
    ok(Crypto::decrypt('enc1:AAAA') === '', 'sin sodium: valores antiguos no se descifran (y no hay errores)');
    echo "INFO sodium NO está cargado en este PHP\n";
}
ok(strlen(Crypto::sign('dato')) === 64, 'firma HMAC sin sodium');
echo "\nCrypto: " . ($n - $f) . "/$n OK (PHP " . PHP_VERSION . ", sodium " . (extension_loaded('sodium') ? 'sí' : 'no') . ")\n";
exit($f ? 1 : 0);
