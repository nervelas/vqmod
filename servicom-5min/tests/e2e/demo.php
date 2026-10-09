<?php
declare(strict_types=1);
/** Demos desde el panel, sección «Ejemplos» del portal y enlaces de la barra de vista previa. */
require __DIR__ . '/lib.php';
reset_limits();
$adm = admin_client();
$r = $adm->page('/admin/demos'); t_ok($r['status'] === 200 && str_contains($r['body'], 'Crear demo'), 'pantalla de demos');
foreach ([['info', 'abogado', 1], ['tienda', 'ropa', 4]] as [$plan, $rubro, $estilo]) {
    $r = $adm->form('/admin/demos', ['plan' => $plan, 'rubro' => $rubro, 'estilo' => $estilo]);
    t_ok($r['status'] === 302, "demo $plan/$rubro iniciada", (string) $r['status']);
    $id = (int) mysql_val('SELECT MAX(id) FROM s5test.s5_orders');
    for ($i = 0; $i < 60; $i++) { $b = $adm->req('GET', "/admin/construir/$id"); $j = $b['json'] ?? []; if (($j['estado'] ?? '') !== 'construyendo') { break; } usleep(700000); }
    $st = mysql_val("SELECT status FROM s5test.s5_orders WHERE id=$id");
    t_ok($st === 'publicada', "demo $plan lista y publicada", (string) $st);
    $slug = mysql_val("SELECT slug FROM s5test.s5_orders WHERE id=$id");
    $pub = new Client('http://127.0.0.1:8200', "$slug.servicom.test:8200"); $h = $pub->req('GET', '/');
    t_ok($h['status'] === 200 && !str_contains($h['body'], 'sc-preview'), "demo $plan pública sin barra ni clave", (string) $h['status']);
    t_ok(str_contains($h['headers'] . $h['body'], 'noindex'), "demo $plan con noindex");
}
$home = (new Client())->page('/');
t_ok(str_contains($home['body'], 'Ejemplos') || str_contains($home['body'], 'ejemplos'), 'sección Ejemplos visible con demos');
t_ok((bool) preg_match('#href="http://[a-z0-9-]+\.servicom\.test:8200#', $home['body']), 'enlaces a las webs demo en el portal');
echo "== Enlaces de la barra de vista previa\n";
$s = build_site(['nombre' => 'Barra Prueba', 'servicios' => 1, 'fotos' => 0, 'logo' => 0]);
$key = mysql_val("SELECT preview_key FROM s5test.s5_orders WHERE id={$s['id']}");
$c = new Client();
$r = $c->req('GET', "/vp/$key/pagar"); t_ok($r['status'] === 302 && str_contains($r['headers'], "/pago/{$s['token']}"), 'Aprobar y pagar → página de pago');
$r = $c->req('GET', "/vp/$key/editar"); t_ok($r['status'] === 302 && str_contains($r['headers'], "/continuar/{$s['token']}"), 'Editar datos → wizard');
$r = $c->req('GET', '/vp/' . str_repeat('0', 32) . '/pagar'); t_ok($r['status'] === 410, 'clave falsa → enlace no disponible');
$r = $c->page("/pago/{$s['token']}"); t_ok($r['status'] === 200 && str_contains($r['body'], 'Banco de Prueba') && str_contains($r['body'], '000-111222-3'), 'página de pago muestra datos bancarios y total');
$pub = new Client('http://127.0.0.1:8200', "{$s['slug']}.servicom.test:8200"); $pub->req('GET', "/?scpk=$key"); $h = $pub->req('GET', '/');
t_ok(str_contains($h['body'], "/vp/$key/pagar") && str_contains($h['body'], "/vp/$key/editar"), 'la barra enlaza a /vp/<clave>/pagar y /editar');
t_ok(!str_contains($h['body'], $s['token']), 'el token del borrador NO aparece en la web de vista previa');
echo "\nDemos: " . ($GLOBALS['__t_n'] - $GLOBALS['__t_fail']) . "/" . $GLOBALS['__t_n'] . " OK\n";
exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0);
