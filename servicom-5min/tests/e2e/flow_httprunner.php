<?php
declare(strict_types=1);
/** Hosting sin proc_open: el constructor se ejecuta por HTTP firmado (loopback). Debe producir el mismo resultado. */
require __DIR__ . '/lib.php';
reset_limits();
$setRunner = function (string $runner) {
    as_www('php -r ' . escapeshellarg('define("S5_ROOT","/tmp/s5test/portal");require "/tmp/s5test/portal/app/bootstrap.php"; $h=S5\Services\Hosts::get(1); $c=$h["cfg"]; $c["runner"]="' . $runner . '"; S5\Services\Hosts::save(1,$h["name"],$h["kind"],$c,true);'));
};
$setRunner('http');
$s = build_site(['nombre' => 'Runner HTTP', 'servicios' => 3, 'productos' => 2]);
$setRunner('auto');
t_ok($s !== null && $s['slug'] !== '', 'construcción por HTTP firmado', json_encode($s));
$st = mysql_val("SELECT status FROM s5test.s5_orders WHERE id={$s['id']}");
t_ok($st === 'vista_lista', 'estado vista_lista (runner HTTP)', (string) $st);
$key = mysql_val("SELECT preview_key FROM s5test.s5_orders WHERE id={$s['id']}");
$c = new Client('http://127.0.0.1:8200', "{$s['slug']}.servicom.test:8200"); $c->req('GET', "/?scpk=$key"); $r = $c->req('GET', '/');
t_ok($r['status'] === 200 && str_contains($r['body'], 'Runner HTTP'), 'el sitio responde con su contenido');
t_ok(!is_file("/tmp/s5test/webs/{$s['slug']}/sc-provision.php"), 'script de aprovisionamiento eliminado');
$r = (new Client('http://127.0.0.1:8200', "{$s['slug']}.servicom.test:8200"))->req('GET', '/sc-provision.php?job=x&step=status&ts=' . time() . '&sig=00');
t_ok(in_array($r['status'], [403, 404], true), 'sc-provision.php inaccesible tras construir (' . $r['status'] . ')');
echo "\nRunner HTTP: " . ($GLOBALS['__t_n'] - $GLOBALS['__t_fail']) . "/" . $GLOBALS['__t_n'] . " OK\n";
exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0);
