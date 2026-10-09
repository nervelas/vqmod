<?php
declare(strict_types=1);
/** Flujo completo de punta a punta: formulario → vista previa → pago → aprobación → publicación. */
require __DIR__ . '/lib.php';

$opts = getopt('', ['plan::', 'rubro::', 'estilo::', 'servicios::', 'productos::', 'logo::', 'fotos::', 'idioma::', 'largo::', 'nombre::', 'keep::', 'skip-publish::']);
$plan = $opts['plan'] ?? 'info';
$rubro = $opts['rubro'] ?? 'abogado';
$estilo = (int) ($opts['estilo'] ?? 1);
$nServ = (int) ($opts['servicios'] ?? 3);
$nProd = (int) ($opts['productos'] ?? 4);
$logo = ($opts['logo'] ?? '1') === '1';
$fotos = ($opts['fotos'] ?? '1') === '1';
$idioma = $opts['idioma'] ?? 'es';
$largo = $opts['largo'] ?? 'normal';
$nombre = $opts['nombre'] ?? 'Bufete Ñandú & Asociados';
$tmp = sys_get_temp_dir() . '/s5e2e'; @mkdir($tmp, 0777, true);

$c = new Client();
$c->page('/crear');
$r = $c->api('POST', '/api/borrador', ['plan' => $plan]);
t_ok(!empty($r['json']['token']), 'borrador creado'); $tok = $r['json']['token'];

$mk = function (int $n, string $pre) use ($largo): string {
    return match ($largo) { 'corto' => 'X', 'largo' => $pre . ' ' . str_repeat('muy largo texto sin espacios ', 12) . str_repeat('A', 80), 'emoji' => $pre . ' 😀🚀 ñ á é í ó ú ü <b>x</b> & " \'', default => $pre . ' ' . $n };
};
$ids = ['logo' => null, 'banner' => [], 'galeria' => []];
if ($logo) { $u = $c->upload($tok, 'logo', mk_img("$tmp/logo.jpg", 400, 400, 3)); t_ok(!empty($u['json']['id']), 'logo subido', json_encode($u['json'])); $ids['logo'] = $u['json']['id'] ?? null; }
$srv = [];
for ($i = 1; $i <= $nServ; $i++) {
    $foto = null;
    if ($fotos && $i % 2 === 1) { $u = $c->upload($tok, 'foto', mk_img("$tmp/s$i.jpg", 1000, 700, $i), 'image/jpeg'); $foto = $u['json']['id'] ?? null; }
    $srv[] = ['nombre' => $mk($i, 'Servicio'), 'descripcion' => $mk($i, 'Descripción del servicio'), 'foto' => $foto];
}
if ($fotos) { for ($i = 1; $i <= 2; $i++) { $u = $c->upload($tok, 'foto', mk_img("$tmp/b$i.jpg", 1600, 900, 10 + $i)); if (!empty($u['json']['id'])) { $ids['banner'][] = $u['json']['id']; } } $u = $c->upload($tok, 'foto', mk_img("$tmp/g1.jpg", 800, 800, 40)); if (!empty($u['json']['id'])) { $ids['galeria'][] = $u['json']['id']; } }
$data = [
    'plan' => $plan,
    'negocio' => ['nombre' => $largo === 'corto' ? 'Z' : ($largo === 'largo' ? str_repeat('Nombre largo ', 8) : $nombre), 'rubro' => $rubro, 'idioma' => $idioma, 'estilo' => $estilo, 'logo' => $ids['logo']],
    'dominio' => ['tiene' => false, 'deseado' => 'minegocio.com'],
    'correos' => ['info', 'ventas'], 'correo_contacto' => 'cliente@example.com',
    'contenido' => ['frase' => 'Su tranquilidad, nuestra prioridad', 'apoyo' => 'Atención cercana', 'banner' => $ids['banner'], 'quienes' => 'Somos un equipo comprometido con nuestros clientes.', 'servicios' => $srv, 'galeria' => $ids['galeria'], 'youtube' => ''],
    'contacto' => ['whatsapp' => '50255551234', 'whatsapp_msg' => 'Hola, quisiera información', 'telefono' => '2222-3333', 'direccion' => '5a avenida 10-20 zona 1, Guatemala', 'mapa_url' => 'https://maps.google.com/?q=guatemala', 'horario' => 'Lunes a viernes 8:00 a 17:00', 'redes' => ['facebook' => 'https://facebook.com/ejemplo', 'instagram' => 'https://instagram.com/ejemplo']],
];
if ($plan === 'tienda') {
    $prods = [];
    for ($i = 1; $i <= $nProd; $i++) {
        $f = null; if ($fotos && $i % 3 === 1) { $u = $c->upload($tok, 'foto', mk_img("$tmp/p$i.jpg", 800, 800, 60 + $i)); $f = $u['json']['id'] ?? null; }
        $prods[] = ['nombre' => $mk($i, 'Producto'), 'categoria' => $i % 2 ? 'Camisas' : 'Pantalones', 'precio' => 25.5 * $i, 'descripcion' => $mk($i, 'Descripción producto'), 'foto' => $f, 'stock' => $i % 5];
    }
    $data['tienda'] = ['categorias' => [['nombre' => 'Ropa', 'padre' => ''], ['nombre' => 'Camisas', 'padre' => 'Ropa'], ['nombre' => 'Pantalones', 'padre' => 'Ropa']], 'productos' => $prods, 'umbral_stock' => 2, 'correo_alertas' => 'alertas@example.com', 'correo_pedidos' => 'pedidos@example.com', 'banco' => ['banco' => 'Banrural', 'numero' => '123-456', 'titular' => 'Cliente SA', 'tipo' => 'Monetaria'], 'contra_entrega' => true, 'nota_entrega' => 'Entregamos en la capital.'];
}
$r = $c->api('POST', "/api/borrador/$tok/guardar", ['data' => $data]);
t_ok(!empty($r['json']['ok']), 'datos guardados', json_encode($r['json']));
$r = $c->api('GET', "/api/borrador/$tok");
t_ok(($r['json']['data']['negocio']['rubro'] ?? '') === $rubro, 'datos recuperables');

sleep(2); // tiempo mínimo de llenado
$r = $c->api('POST', "/api/borrador/$tok/crear", ['t0' => (time() - 60) * 1000]);
t_ok(!empty($r['json']['ok']), 'construcción iniciada', json_encode($r['json']));
$t0 = time(); $last = null; $url = null;
while (time() - $t0 < 600) {
    $r = $c->api('GET', "/api/borrador/$tok/construccion");
    $j = $r['json'] ?? [];
    $last = $j;
    if (($j['estado'] ?? '') !== 'construyendo') { break; }
    usleep(500000);
}
t_ok(($last['estado'] ?? '') === 'lista', 'vista previa lista (estado=' . ($last['estado'] ?? '?') . ')', json_encode($last));
$url = $last['url'] ?? null;
echo "  vista previa: $url (" . (time() - $t0) . " s)\n";
if (($last['estado'] ?? '') !== 'lista') { exit(1); }
// la vista previa responde con la clave y está protegida sin ella
$u = parse_url($url);
$site = new Client('http://127.0.0.1:8200', $u['host'] . ':8200');
$r = $site->req('GET', '/');
t_ok($r['status'] === 403, 'sin clave: vista previa privada 403', (string) $r['status']);
$r = $site->req('GET', '/?scpk=' . ($u['query'] ? substr($u['query'], 5) : ''));
$r2 = $site->req('GET', '/');
t_ok($r2['status'] === 200, 'con clave: 200', (string) $r2['status']);
t_ok(!preg_match('/(Warning|Notice|Fatal error|Deprecated):/', $r2['body']), 'sin mensajes PHP');
t_ok(str_contains($r2['body'], 'Aprobar') || str_contains($r2['body'], 'sc-preview'), 'barra de vista previa presente');
t_ok(str_contains($r2['headers'], 'noindex') || str_contains($r2['body'], 'noindex'), 'noindex presente');
file_put_contents("$tmp/last_url.txt", $url);
if (isset($opts['skip-publish'])) { echo "OK (sin publicar)\n"; exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0); }

// pago
$u = $c->upload($tok, 'comprobante', mk_img("$tmp/pago.jpg", 600, 800, 77));
t_ok(!empty($u['json']['id']), 'comprobante subido', json_encode($u['json']));
$r = $c->api('POST', "/api/borrador/$tok/pagar", ['nombre_nit' => 'Cliente SA / 1234567-8', 'comprobante' => $u['json']['id']]);
t_ok(($r['json']['estado'] ?? '') === 'pago_revisar', 'pago enviado', json_encode($r['json']));

// admin aprueba
$a = new Client();
$a->page('/admin/login');
$r = $a->form('/admin/login', ['email' => 'dueno@servicom.test', 'password' => 'Contrasena-Segura-123']);
t_ok($r['status'] === 302, 'login admin', (string) $r['status']);
$oid = (int) mysql_val("SELECT id FROM s5test.s5_orders WHERE token='$tok'");
$p = $a->page("/admin/pedido/$oid");
t_ok($p['status'] === 200 && str_contains($p['body'], 'Pago por revisar'), 'admin ve el pago por revisar');
$r = $a->form("/admin/pedido/$oid/accion", ['accion' => 'aprobar']);
$st = mysql_val("SELECT status FROM s5test.s5_orders WHERE id=$oid");
t_ok($st === 'publicada', 'pedido publicado (estado=' . $st . ')');
$pub = new Client('http://127.0.0.1:8200', $u0 = parse_url($url)['host'] . ':8200');
$r = $pub->req('GET', '/');
t_ok($r['status'] === 200 && !str_contains($r['body'], 'sc-preview-bar'), 'sitio público sin barra', (string) $r['status']);
t_ok(!str_contains($r['body'] . $r['headers'], 'noindex'), 'sin noindex tras publicar');
$mails = glob('/tmp/s5test/mail/*.json') ?: [];
$subjects = array_map(fn($f) => json_decode((string) file_get_contents($f), true)['subject'] ?? '', $mails);
t_ok((bool) array_filter($subjects, fn($s) => str_contains($s, 'publicada')), 'correo de publicación al cliente');
$orig = mysql_val("SELECT COUNT(*) FROM s5test.s5_files WHERE order_id=$oid AND kind IN ('presentacion','presentacion_img','foto','logo')");
t_ok($orig === '0', 'archivos subidos purgados al publicar');
echo "\nResultado: " . ($GLOBALS['__t_n'] - $GLOBALS['__t_fail']) . "/" . $GLOBALS['__t_n'] . " OK\n";
exit(($GLOBALS['__t_fail'] ?? 0) ? 1 : 0);
