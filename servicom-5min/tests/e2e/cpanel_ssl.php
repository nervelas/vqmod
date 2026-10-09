<?php
declare(strict_types=1);
/** Conexión a cPanel por «localhost» con certificado que no coincide (el caso real del hosting) y verificación activa para otros hosts. Requiere Apache SSL :8443. */
define('S5_ROOT', dirname(__DIR__, 2));
foreach (['Core/Config', 'Core/Crypto', 'Provision/ProvisionException', 'Provision/CpanelApi', 'Provision/CpanelHttpApi'] as $f) { require_once S5_ROOT . "/app/$f.php"; }
require_once __DIR__ . '/lib.php';
putenv('NO_PROXY=*'); putenv('no_proxy=*'); putenv('HTTPS_PROXY'); putenv('https_proxy');
use S5\Provision\CpanelHttpApi;
$a = new CpanelHttpApi(['host' => 'localhost', 'port' => 8443, 'user' => 'u', 'token' => 'T']);
$r = $a->ping();
t_ok(!str_contains((string) $r['message'], 'SSL'), 'localhost: ya no falla por certificado', (string) $r['message']);
$a = new CpanelHttpApi(['host' => '127.0.0.1', 'port' => 8443, 'user' => 'u', 'token' => 'T']);
$r = $a->ping(); t_ok(!str_contains((string) $r['message'], 'SSL'), '127.0.0.1: tampoco', (string) $r['message']);
$v = function (string $h): bool { $o = new CpanelHttpApi(['host' => $h, 'port' => 2083, 'user' => 'u', 'token' => 'T']); $p = (new ReflectionClass($o))->getProperty('verify'); $p->setAccessible(true); return (bool) $p->getValue($o); };
t_ok($v('panelx1.com') === true && $v('servicom.gt') === true, 'hosts reales: la verificación del certificado sigue activa');
t_ok($v('localhost') === false && $v('LOCALHOST') === false && $v('127.0.0.1') === false, 'solo bucle local la desactiva');
echo "\ncPanel SSL: " . ($GLOBALS['__t_n'] - $GLOBALS['__t_fail']) . "/" . $GLOBALS['__t_n'] . " OK\n";
exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0);
