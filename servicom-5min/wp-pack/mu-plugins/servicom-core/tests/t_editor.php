<?php
/**
 * Editor en el sitio (includes/editor.php): capacidad, REST sc/v1/edit|state|icons, validación, servicios, deshacer.
 * Ejecutar con el sitio de pruebas montado (tests/setup.sh) y el mu-plugin sincronizado (tests/sync.sh).
 */
require __DIR__ . '/lib.php';
$U = $ENV['url'];
$seed = json_decode(file_get_contents($ENV['dir'] . '/seed.json'), true);
$cid = (int) $seed['client'];

if (!function_exists('sc_icon_keys')) { // el tema de pruebas puede no traer los íconos: se usa el real del repositorio
    require_once dirname(__DIR__, 3) . '/theme/servicom/inc/luxe-icons.php';
}
if (!function_exists('sc_ed_site') || !function_exists('sc_design_build_palette')) { echo "FALTA includes/editor.php: ejecute tests/sync.sh\n"; exit(1); }

/* ---------------------------------------------------------------- utilidades */
function ed_call(array $ops, int $uid, string $method = 'POST', string $route = '/sc/v1/edit', ?string $raw = null): array
{
    wp_set_current_user($uid);
    $r = new WP_REST_Request($method, $route);
    if ($method === 'POST') { $r->set_header('content-type', 'application/json'); $r->set_body($raw ?? wp_json_encode(array('ops' => $ops))); }
    $res = rest_do_request($r);
    return array($res->get_status(), $res->get_data());
}
function ed(array $ops, int $uid = 1): array { return ed_call($ops, $uid); }
function site(): array { wp_cache_delete('sc_site', 'options'); wp_cache_delete('alloptions', 'options'); wp_cache_delete('notoptions', 'options'); return get_option('sc_site', array()); }
function mkimg(string $name = 'ed-test.png'): int
{
    $up = wp_upload_dir();
    $file = $up['path'] . '/' . wp_unique_filename($up['path'], $name);
    $im = imagecreatetruecolor(120, 80); imagepng($im, $file);
    $id = wp_insert_attachment(array('post_mime_type' => 'image/png', 'post_title' => 'ed-test', 'post_status' => 'inherit'), $file);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file));
    return (int) $id;
}

/* ---------------------------------------------------------------- escenario */
$orig = array(
    'site' => get_option('sc_site', null), 'map' => get_option('sc_service_pages', null), 'hist' => get_option('sc_site_history', null),
    'loc' => get_theme_mod('nav_menu_locations', null), 'blogname' => get_option('blogname'), 'logo' => get_theme_mod('custom_logo', null),
);
$modkeys = array('nombre', 'telefono', 'whatsapp', 'whatsapp_msg', 'correo', 'direccion', 'mapa_url', 'horario', 'facebook', 'instagram', 'tiktok', 'youtube', 'x', 'linkedin');
foreach ($modkeys as $k) { $orig['mod_' . $k] = get_theme_mod('sc_' . $k, null); }
$created = array();
$pageIds = get_option('sc_page_ids', array());
$servPage = (int) ($pageIds['servicios'] ?? 0);
if (!$servPage) { $servPage = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Servicios T', 'post_name' => 'servicios-t')); $created[] = $servPage; $pageIds['servicios'] = $servPage; update_option('sc_page_ids', $pageIds); }
$mkSvcPage = function (string $title, int $n) use ($servPage, &$created) {
    $id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_parent' => $servPage, 'meta_input' => array('_sc_luxe' => 'svc:' . $n)));
    $created[] = $id; return (int) $id;
};
$p0 = $mkSvcPage('Servicio Uno', 0); $p1 = $mkSvcPage('Servicio Dos', 1);
$btn = function ($t, $u) { return array('text' => $t, 'url' => $u); };
$fx = sc_design_default('abogado', 1, 7);
$fx_site = array(
    'v' => 1, 'design' => $fx, 'brand' => array('nombre' => 'Bufete Prueba', 'logo' => 0, 'servicios_label' => 'Servicios'),
    'pages' => array(
        'home' => array('sections' => array(
            array('id' => 'inicio-hero', 'type' => 'hero', 'on' => true, 'data' => array('eyebrow' => 'Despacho', 'title' => 'Título', 'sub' => 'Subtítulo', 'btn1' => $btn('Escríbanos', 'wa'), 'btn2' => $btn('Servicios', '#servicios'), 'img' => array('id' => 0, 'seed' => 5), 'slides' => array(), 'side' => array('id' => 0, 'seed' => 6), 'badge_icon' => 'crown')),
            array('id' => 'franja', 'type' => 'strip', 'on' => true, 'data' => array('items' => array('Uno', 'Dos', 'Tres'))),
            array('id' => 'nosotros', 'type' => 'about', 'on' => true, 'data' => array('side' => 'left', 'eyebrow' => 'N', 'title' => 'Quiénes', 'lead' => 'L', 'text' => 'Texto', 'points' => array(array('icon' => 'check', 'text' => 'P1')), 'img' => array('id' => 0, 'seed' => 7), 'seal_icon' => 'award', 'btn' => $btn('Más', 'page:nosotros'))),
            array('id' => 'servicios', 'type' => 'services', 'on' => true, 'data' => array('eyebrow' => '', 'title' => 'Servicios', 'lead' => '', 'limit' => 6, 'more' => 'Ver más', 'btn' => $btn('Todos', 'page:servicios'))),
            array('id' => 'valores', 'type' => 'values', 'on' => true, 'data' => array('eyebrow' => '', 'title' => 'Valores', 'lead' => '', 'items' => array(array('icon' => 'gem', 'title' => 'V1', 'text' => 'T1'), array('icon' => 'shield', 'title' => 'V2', 'text' => 'T2')))),
            array('id' => 'proceso', 'type' => 'process', 'on' => true, 'data' => array('eyebrow' => '', 'title' => 'Proceso', 'lead' => '', 'items' => array(array('title' => 'S1', 'text' => 'T1'), array('title' => 'S2', 'text' => 'T2')))),
            array('id' => 'galeria', 'type' => 'gallery', 'on' => true, 'data' => array('eyebrow' => '', 'title' => 'Galería', 'lead' => '', 'items' => array(array('id' => 0, 'seed' => 1)))),
            array('id' => 'preguntas', 'type' => 'faq', 'on' => true, 'data' => array('eyebrow' => '', 'title' => 'FAQ', 'lead' => '', 'items' => array(array('q' => 'P1', 'a' => 'R1'), array('q' => 'P2', 'a' => 'R2')))),
            array('id' => 'video', 'type' => 'video', 'on' => true, 'data' => array('eyebrow' => '', 'title' => 'Video', 'lead' => '', 'url' => 'https://youtu.be/dQw4w9WgXcQ')),
            array('id' => 'cta', 'type' => 'cta', 'on' => true, 'data' => array('eyebrow' => '', 'title' => 'CTA', 'text' => 'x', 'btn1' => $btn('Hablemos', 'wa'), 'btn2' => $btn('Llame', 'tel'), 'img' => array('id' => 0, 'seed' => 9))),
        )),
        'servicios' => array('sections' => array(array('id' => 'cabecera', 'type' => 'pagehero', 'on' => true, 'data' => array('eyebrow' => '', 'title' => 'Servicios', 'lead' => '', 'img' => array('id' => 0, 'seed' => 3)))))
    ),
    'services' => array(
        array('id' => 's1', 'nombre' => 'Servicio Uno', 'resumen' => 'r1', 'descripcion' => 'd1', 'icono' => 'scales', 'img' => array('id' => 0, 'seed' => 11), 'post' => $p0, 'origen' => 'form', 'on' => true),
        array('id' => 's2', 'nombre' => 'Servicio Dos', 'resumen' => 'r2', 'descripcion' => 'd2', 'icono' => 'gavel', 'img' => array('id' => 0, 'seed' => 12), 'post' => $p1, 'origen' => 'form', 'on' => true),
    ),
    'seo' => array('description' => 'Descripción SEO'), 'footer' => array('texto' => 'Pie', 'credit' => 'Créditos'),
);
update_option('sc_site', $fx_site, false);
update_option('sc_service_pages', array(0 => $p0, 1 => $p1));
delete_option('sc_site_history');
// menú principal de pruebas con «Servicios» como padre
require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
$menuId = wp_create_nav_menu('Menú editor T ' . wp_generate_password(4, false));
$locs = get_theme_mod('nav_menu_locations', array()); $locs = is_array($locs) ? $locs : array(); $locs['primary'] = $menuId; set_theme_mod('nav_menu_locations', $locs);
$mi = function (string $title, int $obj, int $parent, string $key) use ($menuId) {
    $i = wp_update_nav_menu_item($menuId, 0, array('menu-item-title' => $title, 'menu-item-object' => 'page', 'menu-item-object-id' => $obj, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-parent-id' => $parent));
    update_post_meta($i, '_sc_key', $key); return (int) $i;
};
$miServ = $mi('Servicios', $servPage, 0, 'item:primary:servicios');
$mi('Servicio Uno', $p0, $miServ, 'item:primary:svc:0'); $mi('Servicio Dos', $p1, $miServ, 'item:primary:svc:1');
function menu_titles(int $menu): array { $o = array(); foreach ((array) wp_get_nav_menu_items($menu) as $it) { $o[] = $it->title; } return $o; }
function hist(): int { return count((array) get_option('sc_site_history', array())); }
function sec(string $type, int $page = 0): int { foreach (site()['pages']['home']['sections'] as $i => $s) { if ($s['type'] === $type) { return $i; } } return -1; }
$H = 'pages.home.sections.';
$HERO = $H . '0.data.';

/* ---------------------------------------------------------------- capacidad y permisos */
section('Capacidad sc_edit_site');
ok(user_can($cid, 'sc_edit_site'), 'el rol cliente tiene sc_edit_site');
ok(user_can(1, 'sc_edit_site'), 'el administrador tiene sc_edit_site');
$sub = wp_insert_user(array('user_login' => 'ed_sub_' . wp_generate_password(5, false), 'user_pass' => wp_generate_password(20), 'user_email' => 'ed' . wp_generate_password(5, false) . '@ejemplo.test', 'role' => 'subscriber'));
ok(!user_can($sub, 'sc_edit_site'), 'un suscriptor NO tiene sc_edit_site');
ok(in_array('sc_edit_site', sc_client_caps(false), true), 'sc_client_caps() incluye sc_edit_site');
ok(get_role('sc_cliente')->has_cap('sc_edit_site'), 'el rol sc_cliente fue sincronizado con la capacidad');
[$s, $d] = ed(array(array('op' => 'text', 'path' => $HERO . 'title', 'value' => 'X')), 0);
ok($s === 401, 'visitante: POST /edit → 401', (string) $s);
[$s, $d] = ed(array(array('op' => 'text', 'path' => $HERO . 'title', 'value' => 'X')), $sub);
ok(in_array($s, array(401, 403), true), 'suscriptor: POST /edit → 401/403', (string) $s);
[$s] = ed_call(array(), 0, 'GET', '/sc/v1/state'); ok($s === 401, 'visitante: GET /state → 401');
[$s] = ed_call(array(), 0, 'GET', '/sc/v1/icons'); ok($s === 401, 'visitante: GET /icons → 401');
[$s] = ed_call(array(), $sub, 'GET', '/sc/v1/state'); ok(in_array($s, array(401, 403), true), 'suscriptor: GET /state denegado');
eq(site()['pages']['home']['sections'][0]['data']['title'], 'Título', 'los intentos denegados no cambiaron nada');

/* ---------------------------------------------------------------- texto */
section('Texto');
[$s, $d] = ed(array(array('op' => 'text', 'path' => $HERO . 'title', 'value' => '  Nuevo título  ')), $cid);
ok($s === 200 && $d['ok'] && $d['changed'] === array('text:' . $HERO . 'title'), 'cliente edita un texto', json_encode($d));
eq(site()['pages']['home']['sections'][0]['data']['title'], 'Nuevo título', 'texto recortado y guardado');
ok(isset($d['saved_at']) && isset($d['can_undo']), 'respuesta con saved_at y can_undo');
[$s, $d] = ed(array(array('op' => 'text', 'path' => $HERO . 'sub', 'value' => 'Hola <script>alert(1)</script><img src=x onerror=alert(2)> <b>mundo</b>')));
eq(site()['pages']['home']['sections'][0]['data']['sub'], 'Hola  mundo', 'se quitan etiquetas y scripts (solo texto plano)');
ed(array(array('op' => 'text', 'path' => $HERO . 'sub', 'value' => "Línea 1\r\nLínea 2")));
eq(site()['pages']['home']['sections'][0]['data']['sub'], "Línea 1\nLínea 2", 'conserva saltos de línea');
$bad = array(
    'v' => array('v', '2'), 'palette' => array('design.palette.bg', '#000'), 'design' => array('design.mood', 'light'), 'clave nueva' => array($HERO . 'inventado', 'x'), 'ruta rara' => array('../x', 'x'),
    'ruta con espacio' => array($HERO . 'ti tle', 'x'), 'ruta vacía' => array('', 'x'), 'tipo' => array($HERO . 'type', 'x'), 'id' => array($H . '0.id', 'x'), 'seed' => array('services.0.img.seed', '1'),
    'brand.logo' => array('brand.logo', '5'), 'post' => array('services.0.post', '1'), 'url' => array($HERO . 'btn1.url', 'x'), 'ícono como texto' => array($HERO . 'badge_icon', 'x'), 'lista entera' => array($H . '1.data.items', 'x'),
    'sección entera' => array($H . '0', 'x'), 'limit' => array($H . '3.data.limit', '2'), 'imagen como texto' => array($HERO . 'img', 'x'), 'texto de botón' => array($HERO . 'btn1.text', 'x'), 'lado' => array($H . '2.data.side', 'x'),
);
foreach ($bad as $k => $p) { [$s, $d] = ed(array(array('op' => 'text', 'path' => $p[0], 'value' => $p[1]))); ok($s === 400 && $d['ok'] === false && $d['msg'] !== '', "rechaza texto en «{$k}»", $s . ' ' . ($d['msg'] ?? '')); }
[$s, $d] = ed(array(array('op' => 'text', 'path' => $HERO . 'title', 'value' => str_repeat('a', 2001)))); ok($s === 400, 'máximo 2000 caracteres');
[$s, $d] = ed(array(array('op' => 'text', 'path' => $HERO . 'title', 'value' => str_repeat('a', 2000)))); ok($s === 200, '2000 caracteres exactos sí');
[$s, $d] = ed(array(array('op' => 'text', 'path' => $HERO . 'title', 'value' => array('x')))); ok($s === 400, 'valor que no es texto');
[$s, $d] = ed(array(array('op' => 'text', 'path' => $HERO . 'title'))); ok($s === 400, 'sin valor');
[$s, $d] = ed(array(array('op' => 'borrar_todo'))); ok($s === 400 && $d['msg'] !== '', 'operación desconocida');
[$s, $d] = ed(array('no es un objeto')); ok($s === 400, 'operación mal formada');
[$s, $d] = ed(array()); ok($s === 400, 'sin operaciones');
[$s, $d] = ed(array(), 1, 'POST', '/sc/v1/edit');
ed(array(array('op' => 'text', 'path' => 'seo.description', 'value' => 'SEO nuevo')), 1); eq(site()['seo']['description'], 'SEO nuevo', 'seo.description editable');
ed(array(array('op' => 'text', 'path' => 'footer.texto', 'value' => 'Pie nuevo'))); eq(site()['footer']['texto'], 'Pie nuevo', 'footer.texto editable');
section('Límites y atomicidad');
$before = serialize(site()); $h0 = hist();
[$s, $d] = ed(array_fill(0, 41, array('op' => 'text', 'path' => 'seo.description', 'value' => 'a')));
ok($s === 400 && stripos($d['msg'], 'demasiados') !== false, 'más de 40 operaciones: rechazado');
ed(array_fill(0, 40, array('op' => 'text', 'path' => 'seo.description', 'value' => 'cuarenta'))); eq(site()['seo']['description'], 'cuarenta', '40 operaciones: aceptado');
[$s, $d] = ed_call(array(), 1, 'POST', '/sc/v1/edit', wp_json_encode(array('ops' => array(array('op' => 'text', 'path' => 'seo.description', 'value' => 'a')), 'relleno' => str_repeat('x', SC_ED_MAX_BODY)))); ok($s === 413, 'cuerpo demasiado grande: 413', (string) $s);
$before = serialize(site()); $h0 = hist();
[$s, $d] = ed(array(array('op' => 'text', 'path' => $HERO . 'title', 'value' => 'Cambio parcial'), array('op' => 'icon', 'path' => $HERO . 'badge_icon', 'key' => 'no-existe')));
ok($s === 400 && $d['op'] === 1 && str_starts_with($d['msg'], 'Cambio 2:'), 'una operación inválida cancela todo el lote y dice cuál', $d['msg'] ?? '');
ok(serialize(site()) === $before && hist() === $h0, 'nada se guardó (atómico) y no hay historial nuevo');
[$s, $d] = ed(array(array('op' => 'text', 'path' => $HERO . 'title', 'value' => 'Igual')));
$t = site()['pages']['home']['sections'][0]['data']['title'];
[$s, $d] = ed(array(array('op' => 'text', 'path' => $HERO . 'title', 'value' => $t)));
ok($s === 200 && $d['msg'] === 'Sin cambios.' && $d['changed'] === array(), 'valor idéntico: «Sin cambios»');

/* ---------------------------------------------------------------- imágenes */
section('Imágenes');
$att = mkimg(); $created[] = $att;
$page = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'no imagen')); $created[] = $page;
$IMG = $HERO . 'img';
[$s, $d] = ed(array(array('op' => 'img', 'path' => $IMG, 'id' => $att)), $cid);
ok($s === 200 && site()['pages']['home']['sections'][0]['data']['img']['id'] === $att && site()['pages']['home']['sections'][0]['data']['img']['seed'] === 5, 'asigna un adjunto-imagen y conserva la semilla');
foreach (array('no existe' => 99999999, 'no es adjunto' => $page, 'negativo' => -3) as $k => $id) { [$s, $d] = ed(array(array('op' => 'img', 'path' => $IMG, 'id' => $id))); ok($s === 400 && $d['msg'] !== '', "rechaza imagen: $k"); }
[$s, $d] = ed(array(array('op' => 'img', 'path' => $IMG, 'id' => 'abc'))); ok($s === 400, 'rechaza id no numérico');
[$s, $d] = ed(array(array('op' => 'img', 'path' => $HERO . 'title', 'id' => $att))); ok($s === 400, 'una ruta que no es imagen se rechaza');
[$s, $d] = ed(array(array('op' => 'img', 'path' => 'design.palette', 'id' => $att))); ok($s === 400, 'img no toca design');
[$s, $d] = ed(array(array('op' => 'img', 'path' => $IMG, 'id' => 0))); ok($s === 200 && site()['pages']['home']['sections'][0]['data']['img']['id'] === 0, 'id=0 quita la foto (vuelve al arte)');
[$s, $d] = ed(array(array('op' => 'img', 'path' => 'services.1.img', 'id' => $att))); ok($s === 200 && site()['services'][1]['img']['id'] === $att, 'imagen de un servicio');
[$s, $d] = ed(array(array('op' => 'img', 'path' => $H . '2.data.img', 'id' => $att))); ok($s === 200, 'imagen de «Quiénes somos»');

/* ---------------------------------------------------------------- íconos */
section('Íconos');
$keys = sc_icon_keys();
[$s, $d] = ed(array(array('op' => 'icon', 'path' => $HERO . 'badge_icon', 'key' => 'clock')), $cid); ok($s === 200 && site()['pages']['home']['sections'][0]['data']['badge_icon'] === 'clock', 'cambia un ícono');
[$s, $d] = ed(array(array('op' => 'icon', 'path' => $H . '4.data.items.0.icon', 'key' => 'whatsapp'))); ok($s === 200 && site()['pages']['home']['sections'][4]['data']['items'][0]['icon'] === 'whatsapp', 'ícono de un valor');
[$s, $d] = ed(array(array('op' => 'icon', 'path' => 'services.0.icono', 'key' => 'gear'))); ok($s === 200 && site()['services'][0]['icono'] === 'gear', 'ícono de un servicio');
[$s, $d] = ed(array(array('op' => 'icon', 'path' => $H . '2.data.points.0.icon', 'key' => 'star'))); ok($s === 200, 'ícono de un punto');
foreach (array('inexistente', '<svg onload=alert(1)>', '', 'CLOCK ', '../x') as $k) { [$s, $d] = ed(array(array('op' => 'icon', 'path' => $HERO . 'badge_icon', 'key' => $k))); ok($s === 400, 'ícono inválido rechazado: ' . json_encode($k)); }
[$s, $d] = ed(array(array('op' => 'icon', 'path' => $HERO . 'title', 'key' => 'clock'))); ok($s === 400, 'icon solo en rutas de ícono');
ok(count(array_unique($keys)) === count($keys) && count($keys) > 100, 'el catálogo de íconos tiene claves únicas (' . count($keys) . ')');

/* ---------------------------------------------------------------- enlaces */
section('Enlaces y botones');
$L = $HERO . 'btn1';
foreach (array('wa', 'tel', 'mail', 'page:servicios', 'page:home', 'svc:1', '#contacto', '/servicios/', '/x?a=1#b', 'https://ejemplo.com/ruta?x=1', 'http://ejemplo.com', 'ejemplo.com' => 'https://ejemplo.com', 'www.ejemplo.com/a' => 'https://www.ejemplo.com/a') as $k => $v) {
    $in = is_int($k) ? $v : $k; $out = is_int($k) ? $v : $v;
    [$s, $d] = ed(array(array('op' => 'link', 'path' => $L, 'text' => 'Botón', 'url' => $in)), $cid);
    ok($s === 200 && site()['pages']['home']['sections'][0]['data']['btn1']['url'] === $out, 'enlace válido: ' . $in, $s . ' ' . ($d['msg'] ?? ''));
}
foreach (array('', ' ', 'javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html,hola', 'vbscript:x', '//malo.com/x', 'page:no_existe', 'page:../x', 'svc:99', 'svc:x', '#con espacio', '#<x>', 'ftp://x.com', 'mailto:a@b.com', 'tel:123', "/x\ny", '/a"b', 'https://a b.com', str_repeat('a', 600)) as $u) {
    $before = serialize(site());
    [$s, $d] = ed(array(array('op' => 'link', 'path' => $L, 'text' => 'Botón', 'url' => $u)));
    ok($s === 400 && serialize(site()) === $before, 'enlace rechazado: ' . json_encode(mb_substr($u, 0, 30)), $s . ' ' . ($d['msg'] ?? ''));
}
[$s, $d] = ed(array(array('op' => 'link', 'path' => $L, 'text' => '', 'url' => 'wa'))); ok($s === 400 && stripos($d['msg'], 'texto') !== false, 'el texto del botón es obligatorio');
[$s, $d] = ed(array(array('op' => 'link', 'path' => $L, 'text' => '<b>Hola</b><script>x</script>', 'url' => 'wa'))); eq(site()['pages']['home']['sections'][0]['data']['btn1']['text'], 'Hola', 'el texto del botón queda sin HTML');
[$s, $d] = ed(array(array('op' => 'link', 'path' => $HERO . 'title', 'text' => 'a', 'url' => 'wa'))); ok($s === 400, 'link solo en rutas {text,url}');
[$s, $d] = ed(array(array('op' => 'link', 'path' => $H . '9.data.btn2', 'text' => 'Llame ya', 'url' => 'tel'))); ok($s === 200 && $d['results'][0]['url'] === 'tel', 'botón de la llamada a la acción');
[$s, $d] = ed(array(array('op' => 'link', 'path' => $L, 'text' => 'Ver', 'url' => 'page:servicios'))); ok(function_exists('sc_lx_url') ? str_contains($d['results'][0]['href'], '/') : true, 'la respuesta incluye el href resuelto');

/* ---------------------------------------------------------------- secciones */
section('Secciones: mostrar/ocultar y mover');
$iFaq = sec('faq'); $iStrip = sec('strip');
[$s, $d] = ed(array(array('op' => 'sec_on', 'path' => $H . $iStrip, 'on' => false)), $cid); ok($s === 200 && site()['pages']['home']['sections'][$iStrip]['on'] === false, 'oculta una sección');
[$s, $d] = ed(array(array('op' => 'sec_on', 'path' => $H . $iStrip, 'on' => true))); ok($s === 200 && site()['pages']['home']['sections'][$iStrip]['on'] === true, 'la vuelve a mostrar');
[$s, $d] = ed(array(array('op' => 'sec_on', 'path' => $H . '99', 'on' => false))); ok($s === 400, 'sección inexistente');
[$s, $d] = ed(array(array('op' => 'sec_on', 'path' => $H . $iStrip . '.data', 'on' => false))); ok($s === 400, 'la ruta debe ser de una sección');
[$s, $d] = ed(array(array('op' => 'sec_on', 'path' => $H . $iStrip))); ok($s === 400, 'falta el valor on');
[$s, $d] = ed(array(array('op' => 'sec_on', 'path' => $H . $iStrip, 'on' => false, 'sid' => 'otra'))); ok($s === 400 && stripos($d['msg'], 'cambió') !== false, 'sid distinto: la página cambió');
$types0 = array_column(site()['pages']['home']['sections'], 'type');
[$s, $d] = ed(array(array('op' => 'sec_move', 'path' => $H . '1', 'dir' => 1, 'sid' => 'franja')));
$types1 = array_column(site()['pages']['home']['sections'], 'type'); ok($s === 200 && $types1[2] === 'strip' && $types1[1] === 'about', '↓ baja la sección un lugar', implode(',', $types1));
[$s, $d] = ed(array(array('op' => 'sec_move', 'path' => $H . '2', 'dir' => -1))); ok(array_column(site()['pages']['home']['sections'], 'type') === $types0, '↑ la devuelve');
[$s, $d] = ed(array(array('op' => 'sec_move', 'path' => $H . '0', 'dir' => -1))); ok($s === 400 && stripos($d['msg'], 'inicio') !== false, 'no sube más allá del inicio');
$last = count($types0) - 1; [$s, $d] = ed(array(array('op' => 'sec_move', 'path' => $H . $last, 'dir' => 1))); ok($s === 400 && stripos($d['msg'], 'final') !== false, 'no baja más allá del final');
[$s, $d] = ed(array(array('op' => 'sec_move', 'path' => $H . '1', 'to' => 4))); ok($s === 200 && array_column(site()['pages']['home']['sections'], 'type')[4] === 'strip', 'mover a un índice concreto');
ed(array(array('op' => 'sec_move', 'path' => $H . '4', 'to' => 1))); ok(array_column(site()['pages']['home']['sections'], 'type') === $types0, 'y regresa');
[$s, $d] = ed(array(array('op' => 'sec_move', 'path' => $H . '1', 'dir' => 0))); ok($s === 400, 'sin dirección');
[$s, $d] = ed(array(array('op' => 'sec_move', 'path' => 'pages.servicios.sections.0', 'dir' => 1))); ok($s === 400, 'una sola sección: no hay a dónde mover (solo dentro de su página)');
ok(count(site()['pages']['home']['sections']) === count($types0), 'ninguna sección se perdió ni se duplicó');

/* ---------------------------------------------------------------- listas */
section('Listas: agregar, eliminar y mover');
$LF = $H . $iFaq . '.data.items'; $nF = count(site()['pages']['home']['sections'][$iFaq]['data']['items']);
[$s, $d] = ed(array(array('op' => 'list_add', 'path' => $LF)), $cid);
$it = site()['pages']['home']['sections'][$iFaq]['data']['items'];
ok($s === 200 && count($it) === $nF + 1 && array_keys($it[$nF]) === array('q', 'a') && $d['results'][0]['focus'] === $LF . '.' . $nF . '.q', 'FAQ: agrega {q,a} y devuelve el foco', json_encode($d['results'][0] ?? null));
ed(array(array('op' => 'list_add', 'path' => $LF, 'at' => 0))); eq(site()['pages']['home']['sections'][$iFaq]['data']['items'][0]['q'], 'Nueva pregunta', 'inserta en una posición');
ed(array(array('op' => 'list_move', 'path' => $LF, 'index' => 0, 'dir' => 1))); eq(site()['pages']['home']['sections'][$iFaq]['data']['items'][1]['q'], 'Nueva pregunta', 'mover ítem hacia abajo');
ed(array(array('op' => 'list_move', 'path' => $LF, 'index' => 1, 'dir' => -1))); eq(site()['pages']['home']['sections'][$iFaq]['data']['items'][0]['q'], 'Nueva pregunta', 'mover ítem hacia arriba');
[$s, $d] = ed(array(array('op' => 'list_move', 'path' => $LF, 'index' => 0, 'dir' => -1))); ok($s === 400, 'no se mueve fuera de la lista');
ed(array(array('op' => 'list_del', 'path' => $LF, 'index' => 0))); ed(array(array('op' => 'list_del', 'path' => $LF, 'index' => $nF)));
eq(count(site()['pages']['home']['sections'][$iFaq]['data']['items']), $nF, 'eliminar vuelve al tamaño original');
[$s, $d] = ed(array(array('op' => 'list_del', 'path' => $LF, 'index' => 50))); ok($s === 400, 'eliminar un índice inexistente');
[$s, $d] = ed(array(array('op' => 'list_del', 'path' => $LF, 'index' => 'x'))); ok($s === 400, 'índice no numérico');
$LV = $H . sec('values') . '.data.items'; ed(array(array('op' => 'list_add', 'path' => $LV)));
$vi = site()['pages']['home']['sections'][sec('values')]['data']['items']; ok(array_keys(end($vi)) === array('icon', 'title', 'text') && in_array(end($vi)['icon'], $keys, true), 'values: {icon,title,text} con ícono válido');
$LP = $H . sec('process') . '.data.items'; ed(array(array('op' => 'list_add', 'path' => $LP)));
$pi = site()['pages']['home']['sections'][sec('process')]['data']['items']; ok(array_keys(end($pi)) === array('title', 'text'), 'process: {title,text}');
$LPt = $H . sec('about') . '.data.points'; ed(array(array('op' => 'list_add', 'path' => $LPt)));
$pt = site()['pages']['home']['sections'][sec('about')]['data']['points']; ok(array_keys(end($pt)) === array('icon', 'text') && end($pt)['icon'] === 'check', 'points: {icon,text}');
$LS = $H . $iStrip . '.data.items'; [$s, $d] = ed(array(array('op' => 'list_add', 'path' => $LS)));
$st = site()['pages']['home']['sections'][$iStrip]['data']['items']; ok($s === 200 && is_string(end($st)) && count($st) === 4 && $d['results'][0]['focus'] === $LS . '.3', 'strip: ítem de texto');
$LG = $H . sec('gallery') . '.data.items'; [$s, $d] = ed(array(array('op' => 'list_add', 'path' => $LG)));
$g = site()['pages']['home']['sections'][sec('gallery')]['data']['items']; ok(array_keys(end($g)) === array('id', 'seed') && end($g)['id'] === 0, 'gallery: {id:0,seed}');
[$s, $d] = ed(array(array('op' => 'list_add', 'path' => $LG, 'id' => $att))); $g = site()['pages']['home']['sections'][sec('gallery')]['data']['items']; ok($s === 200 && end($g)['id'] === $att, 'gallery: con una foto elegida');
[$s, $d] = ed(array(array('op' => 'list_add', 'path' => $LG, 'id' => $page))); ok($s === 400, 'gallery: la foto debe ser imagen');
[$s, $d] = ed(array(array('op' => 'list_add', 'path' => $H . '0.data.items'))); ok($s === 400, 'no hay lista de ítems en la portada');
[$s, $d] = ed(array(array('op' => 'list_add', 'path' => $H . '0.data.slides'))); ok($s === 400, 'solo listas con plantilla');
[$s, $d] = ed(array(array('op' => 'list_add', 'path' => 'services'))); ok($s === 400, 'las listas de servicios usan svc_*');
$OF = 'pages.home.sections.' . $iFaq . '.data.items';
for ($i = 0; $i < 40; $i++) { [$s] = ed(array(array('op' => 'list_add', 'path' => $OF))); if ($s !== 200) { break; } }
ok($s === 400 && count(site()['pages']['home']['sections'][$iFaq]['data']['items']) === 30, 'máximo de preguntas (30)');
while (count(site()['pages']['home']['sections'][$iFaq]['data']['items']) > 1) { ed(array(array('op' => 'list_del', 'path' => $OF, 'index' => 0))); }
[$s, $d] = ed(array(array('op' => 'list_del', 'path' => $OF, 'index' => 0))); ok($s === 400 && stripos($d['msg'], 'al menos') !== false, 'debe quedar al menos un elemento');

/* ---------------------------------------------------------------- servicios */
section('Servicios: agregar, duplicar, ocultar y eliminar');
$n0 = count(site()['services']); $titles0 = menu_titles($menuId);
[$s, $d] = ed(array(array('op' => 'svc_add', 'nombre' => '  Divorcios <b>express</b> ')), $cid);
ok($s === 200 && $d['results'][0]['n'] === $n0 && $d['results'][0]['focus'] === 'services.' . $n0 . '.nombre', 'agrega un servicio', json_encode($d));
$sv = site()['services'][$n0];
ok($sv['nombre'] === 'Divorcios express' && $sv['origen'] === 'form' && $sv['on'] === true && in_array($sv['icono'], $keys, true) && $sv['id'] === 's3' && $sv['img'] === array('id' => 0, 'seed' => $sv['img']['seed']), 'datos del servicio: nombre limpio, origen form, ícono válido, id s3', json_encode($sv));
$post = get_post($sv['post']);
ok($post && $post->post_type === 'page' && $post->post_status === 'publish' && (int) $post->post_parent === $servPage, 'página WordPress publicada y hija de Servicios');
eq(get_post_meta($sv['post'], '_sc_luxe', true), 'svc:' . $n0, 'meta _sc_luxe = svc:N');
ok($post->post_title === 'Divorcios express' && $post->post_name === 'divorcios-express', 'título y slug', $post->post_name);
eq(get_option('sc_service_pages')[$n0], (int) $sv['post'], 'sc_service_pages actualizada');
$items = (array) wp_get_nav_menu_items($menuId); $mine = null; foreach ($items as $it) { if ($it->title === 'Divorcios express') { $mine = $it; } }
ok($mine && (int) $mine->menu_item_parent === $miServ && (int) $mine->object_id === (int) $sv['post'], 'ítem de menú bajo «Servicios»');
ok(str_starts_with($d['results'][0]['url'], 'http'), 'la respuesta trae la URL de la página nueva');
[$s, $d] = ed(array(array('op' => 'svc_add', 'nombre' => 'Divorcios express')));
$sv2 = site()['services'][$n0 + 1]; ok($s === 200 && get_post($sv2['post'])->post_name !== 'divorcios-express' && str_starts_with(get_post($sv2['post'])->post_name, 'divorcios-express'), 'slug único para un nombre repetido', get_post($sv2['post'])->post_name);
// editar el nombre sincroniza página y menú
ed(array(array('op' => 'text', 'path' => 'services.' . $n0 . '.nombre', 'value' => 'Divorcios y custodia')));
eq(get_post($sv['post'])->post_title, 'Divorcios y custodia', 'renombrar el servicio renombra la página');
ok(in_array('Divorcios y custodia', menu_titles($menuId), true) && count(array_keys(menu_titles($menuId), 'Divorcios express', true)) === 1, 'y su entrada de menú (la otra queda igual)', implode('|', menu_titles($menuId)));
// duplicar
[$s, $d] = ed(array(array('op' => 'svc_dup', 'n' => 0)));
$cnt = count(site()['services']); $dup = site()['services'][$cnt - 1];
ok($s === 200 && $dup['nombre'] === 'Servicio Uno (copia)' && $dup['resumen'] === 'r1' && $dup['icono'] === 'gear' && $dup['post'] > 0 && $dup['post'] !== site()['services'][0]['post'], 'duplica con su propia página');
ok(get_post_meta($dup['post'], '_sc_luxe', true) === 'svc:' . ($cnt - 1), 'la copia apunta a su índice');
// ocultar / mostrar
$pid1 = (int) site()['services'][1]['post'];
[$s, $d] = ed(array(array('op' => 'svc_del', 'n' => 1)), $cid);
ok($s === 200 && site()['services'][1]['on'] === false && get_post_status($pid1) === 'trash', 'eliminar: on=false y la página va a la papelera');
ok(!in_array('Servicio Dos', menu_titles($menuId), true), 'y sale del menú');
eq(count(site()['services']), $cnt, 'los índices NO se reordenan ni se borran');
eq(site()['services'][2]['nombre'], 'Divorcios y custodia', 'los servicios posteriores conservan su índice');
[$s, $d] = ed(array(array('op' => 'svc_on', 'n' => 1, 'on' => true)));
ok($s === 200 && site()['services'][1]['on'] === true && get_post_status($pid1) === 'publish' && in_array('Servicio Dos', menu_titles($menuId), true), 'mostrar: restaura la página y el menú');
eq(count(array_keys(array_filter(menu_titles($menuId), function ($t) { return $t === 'Servicio Dos'; }))), 1, 'sin ítems de menú duplicados');
[$s, $d] = ed(array(array('op' => 'svc_del', 'n' => 99))); ok($s === 400, 'servicio inexistente');
[$s, $d] = ed(array(array('op' => 'svc_dup', 'n' => 'x'))); ok($s === 400, 'índice no válido');

/* ---------------------------------------------------------------- datos del negocio */
section('Datos del negocio y logo');
[$s, $d] = ed(array(array('op' => 'biz', 'values' => array('telefono' => ' 2255-9988 ', 'whatsapp' => '+502 5555-1234', 'correo' => 'Info@Ejemplo.COM', 'direccion' => "Zona 1\n<b>Guatemala</b>", 'horario' => 'L-V 8-17', 'facebook' => 'https://facebook.com/bufete', 'instagram' => '@bufete', 'mapa_url' => 'maps.google.com/x'))), $cid);
ok($s === 200, 'guarda varios datos a la vez', json_encode($d));
eq(get_theme_mod('sc_telefono'), '2255-9988', 'teléfono'); eq(get_theme_mod('sc_whatsapp'), '50255551234', 'WhatsApp solo dígitos'); eq(get_theme_mod('sc_correo'), 'Info@Ejemplo.COM', 'correo');
eq(get_theme_mod('sc_direccion'), "Zona 1\nGuatemala", 'dirección sin HTML'); eq(get_theme_mod('sc_instagram'), 'https://www.instagram.com/bufete', '@usuario → URL de la red'); eq(get_theme_mod('sc_mapa_url'), 'https://maps.google.com/x', 'URL con https');
foreach (array('correo' => 'no-es-correo', 'facebook' => 'javascript:alert(1)', 'mapa_url' => 'esto no es url') as $k => $v) { [$s, $d] = ed(array(array('op' => 'biz', 'values' => array($k => $v)))); ok($s === 400 && stripos($d['msg'], 'no es válido') !== false, "dato inválido «{$k}» rechazado con mensaje claro", $d['msg'] ?? ''); }
foreach (array('admin_email', 'blogname', '../x', 'float_wa', 'footer_credit', 'style') as $k) { [$s, $d] = ed(array(array('op' => 'biz', 'values' => array($k => 'x')))); ok($s === 400, "clave fuera de la lista blanca: $k"); }
[$s, $d] = ed(array(array('op' => 'biz', 'values' => array()))); ok($s === 400, 'sin datos');
[$s, $d] = ed(array(array('op' => 'biz', 'values' => array('nombre' => '   ')))); ok($s === 400 && stripos($d['msg'], 'vacío') !== false, 'el nombre no puede quedar vacío');
[$s, $d] = ed(array(array('op' => 'biz', 'values' => array('nombre' => 'Bufete <i>Nuevo</i> & Cía'))));
eq(get_theme_mod('sc_nombre'), 'Bufete Nuevo & Cía', 'nombre sin HTML'); eq(site()['brand']['nombre'], 'Bufete Nuevo & Cía', 'el nombre actualiza brand.nombre'); eq(html_entity_decode(get_option('blogname')), 'Bufete Nuevo & Cía', 'y el título del sitio');
ed(array(array('op' => 'biz', 'values' => array('youtube' => 'https://www.youtube.com/watch?v=abcdefghijk'))));
eq(site()['pages']['home']['sections'][sec('video')]['data']['url'], 'https://www.youtube.com/watch?v=abcdefghijk', 'YouTube actualiza la sección de video');
ed(array(array('op' => 'biz', 'values' => array('telefono' => '')))); eq(get_theme_mod('sc_telefono'), '', 'se puede vaciar un dato');
$logo = mkimg('logo-t.png'); $created[] = $logo;
[$s, $d] = ed(array(array('op' => 'logo', 'id' => $logo))); ok($s === 200 && (int) get_theme_mod('custom_logo') === $logo && site()['brand']['logo'] === $logo, 'logo: custom_logo y brand.logo');
[$s, $d] = ed(array(array('op' => 'logo', 'id' => $page))); ok($s === 400, 'el logo debe ser imagen');
[$s, $d] = ed(array(array('op' => 'logo', 'id' => 0))); ok($s === 200 && !get_theme_mod('custom_logo') && site()['brand']['logo'] === 0, 'quitar el logo');

/* ---------------------------------------------------------------- diseño */
section('Diseño: paleta con contraste AA');
$pal0 = site()['design']['palette'];
[$s, $d] = ed(array(array('op' => 'design', 'primary' => '#8A1C2B')), $cid);
$ds = site()['design']; ok($s === 200 && $ds['base']['primary'] === '#8a1c2b' && $ds['palette'] !== $pal0 && $ds['palette']['brand'] === '#8a1c2b', 'cambia el color de marca y recalcula la paleta');
ok(str_contains($d['results'][0]['css'], '--lx-primary'), 'devuelve el CSS recalculado');
$worst = function (array $p) { $m = 99; foreach (sc_ed_contrast_report($p) as $c) { $m = min($m, $c); } return $m; };
$colors = array('#8a1c2b', '#1d476b', '#0e6b4f', '#ffd700', '#00ffff', '#ff00ff', '#fafafa', '#101010', '#7a7a2b', '#ff7f50', '#4b0082', '#f5deb3', '#3cb371', '#b8860b', '#e6e6fa', '#0b3d2e', '#ffffe0', '#c71585', '#6495ed', '#2f4f4f', '#ff4500', '#808000', '#a0522d', '#90ee90');
$minAll = 99; $bad = 0;
foreach ($colors as $c) { foreach (array('dark', 'light') as $m) { foreach (array('', '#caa55e', '#ffffff', '#00ff00') as $a) {
    $r = array('op' => 'design', 'primary' => $c, 'mood' => $m); $r['accent'] = $a;
    [$s, $dd] = ed(array($r));
    if ($s === 400 && stripos($dd['msg'], 'intensidad') !== false) { continue; } // grises/blanco/negro se rechazan con aviso
    $w = $worst(site()['design']['palette']); $minAll = min($minAll, $w); if ($s !== 200 || $w < 4.5) { $bad++; echo "     (falla $c $m $a: $s $w)\n"; }
} } }
ok($bad === 0 && $minAll >= 4.5, 'todas las combinaciones de color/ambiente/acento cumplen AA (≥4.5)', 'mínimo ' . $minAll);
[$s, $d] = ed(array(array('op' => 'design', 'primary' => '#808080'))); ok($s === 400 && stripos($d['msg'], 'intensidad') !== false, 'un gris puro se rechaza con aviso claro');
[$s, $d] = ed(array(array('op' => 'design', 'primary' => '#ffffff'))); ok($s === 400, 'blanco rechazado');
foreach (array('rojo', '#12', '#gggggg', 'red', '<script>', '#12345678') as $c) { [$s, $d] = ed(array(array('op' => 'design', 'primary' => $c))); ok($s === 400 && stripos($d['msg'], 'color') !== false, 'color no válido: ' . $c); }
[$s, $d] = ed(array(array('op' => 'design', 'mood' => 'sepia'))); ok($s === 400, 'ambiente no válido');
ed(array(array('op' => 'design', 'primary' => '#1d476b', 'accent' => '', 'mood' => 'dark')));
[$s, $d] = ed(array(array('op' => 'design', 'mood' => 'light'))); eq(site()['design']['mood'], 'light', 'cambia a claro'); eq(site()['design']['base']['primary'], '#1d476b', 'conserva el color base al cambiar solo el ambiente');
[$s, $d] = ed(array(array('op' => 'design', 'head' => 'playfair', 'body' => 'lato'))); eq(site()['design']['fonts'], array('head' => 'playfair', 'body' => 'lato'), 'tipografías');
foreach (array('comic', '', 'inter') as $f) { [$s, $d] = ed(array(array('op' => 'design', 'head' => $f))); ok($s === 400, 'tipografía de títulos no válida: ' . json_encode($f)); }
[$s, $d] = ed(array(array('op' => 'design', 'body' => 'cormorant'))); ok($s === 400, 'una tipografía de títulos no sirve para el texto');
foreach (sc_ed_fonts() as $kind => $list) { foreach ($list as $k => $label) { ok(sc_design_font_stack($k, $kind) !== sc_design_font_stack('__x', $kind) || $k === array_key_first($list), "catálogo coherente con design.php: $kind/$k"); } }
$palNow = site()['design']['palette']; ed(array(array('op' => 'design', 'head' => 'sora'))); eq(site()['design']['palette'], $palNow, 'cambiar solo la letra no altera la paleta');
[$s, $d] = ed(array(array('op' => 'text', 'path' => 'design.fonts.head', 'value' => 'x'))); ok($s === 400, 'las fuentes no se editan como texto');

/* ---------------------------------------------------------------- historial y deshacer */
section('Historial y deshacer');
$fresh = sc_design_default('abogado', 1, 7);
update_option('sc_site', $fx_site, false); update_option('sc_service_pages', array(0 => $p0, 1 => $p1)); delete_option('sc_site_history');
foreach ($modkeys as $k) { remove_theme_mod('sc_' . $k); } remove_theme_mod('custom_logo');
// reponer el menú de servicios del escenario por si los pasos anteriores lo cambiaron
foreach (array(array($p0, 0, 'Servicio Uno'), array($p1, 1, 'Servicio Dos')) as $r) { if (!in_array($r[2], menu_titles($menuId), true)) { sc_ed_svc_menu_add($r[1], $r[0], $r[2]); } if (get_post_status($r[0]) !== 'publish') { wp_untrash_post($r[0]); wp_update_post(array('ID' => $r[0], 'post_status' => 'publish')); } }
[$s, $d] = ed_call(array(), 1, 'POST', '/sc/v1/edit', wp_json_encode(array('ops' => array(array('op' => 'undo')))));
ok($s === 400 && stripos($d['msg'], 'no hay cambios') !== false, 'deshacer sin historial: aviso claro');
ed(array(array('op' => 'text', 'path' => $HERO . 'title', 'value' => 'A'))); eq(hist(), 1, 'cada guardado crea una versión');
ed(array(array('op' => 'text', 'path' => $HERO . 'title', 'value' => 'B'))); eq(hist(), 1, 'ediciones seguidas del mismo texto se agrupan en una versión');
ed(array(array('op' => 'text', 'path' => $HERO . 'sub', 'value' => 'C'))); eq(hist(), 2, 'otro texto crea otra versión');
[$s, $d] = ed(array(array('op' => 'undo')), $cid);
ok($s === 200 && $d['ok'] && $d['can_undo'] === 1 && site()['pages']['home']['sections'][0]['data']['sub'] === 'Subtítulo' && site()['pages']['home']['sections'][0]['data']['title'] === 'B', 'deshacer restaura la versión anterior');
[$s, $d] = ed(array(array('op' => 'undo'))); ok($s === 200 && site()['pages']['home']['sections'][0]['data']['title'] === 'Título', 'deshacer otra vez llega al original (agrupado)');
[$s, $d] = ed(array(array('op' => 'undo'), array('op' => 'text', 'path' => $HERO . 'title', 'value' => 'Z'))); ok($s === 400, 'deshacer debe ir solo');
// datos del negocio y servicios se deshacen también
ed(array(array('op' => 'biz', 'values' => array('telefono' => '1111-2222'))));
ed(array(array('op' => 'undo'))); eq(get_theme_mod('sc_telefono', 'nulo'), 'nulo', 'deshacer revierte los datos del negocio (theme_mod)');
$nS = count(site()['services']); $mt = menu_titles($menuId);
ed(array(array('op' => 'svc_add', 'nombre' => 'Para deshacer'))); $newPost = (int) site()['services'][$nS]['post'];
ok(get_post_status($newPost) === 'publish' && in_array('Para deshacer', menu_titles($menuId), true), 'servicio agregado (antes de deshacer)');
ed(array(array('op' => 'undo')));
ok(count(site()['services']) === $nS && get_post_status($newPost) === 'trash' && !in_array('Para deshacer', menu_titles($menuId), true) && !isset(get_option('sc_service_pages')[$nS]), 'deshacer «agregar servicio»: sin servicio, página a la papelera y fuera del menú');
ed(array(array('op' => 'svc_del', 'n' => 0))); ok(get_post_status($p0) === 'trash', 'servicio eliminado (antes de deshacer)');
ed(array(array('op' => 'undo'))); ok(site()['services'][0]['on'] === true && get_post_status($p0) === 'publish' && in_array('Servicio Uno', menu_titles($menuId), true), 'deshacer «eliminar servicio»: vuelve la página y el menú');
ed(array(array('op' => 'design', 'primary' => '#0e6b4f'))); $palG = site()['design']['palette']['bg']; ed(array(array('op' => 'undo'))); ok(site()['design']['palette']['bg'] !== $palG, 'deshacer revierte el diseño');
// límite del historial
delete_option('sc_site_history');
for ($i = 0; $i < 30; $i++) { ed(array(array('op' => 'text', 'path' => $H . '1.data.items.0', 'value' => 'v' . $i)));  ed(array(array('op' => 'text', 'path' => 'seo.description', 'value' => 's' . $i))); }
eq(hist(), SC_ED_HISTORY, 'el historial guarda solo las últimas ' . SC_ED_HISTORY . ' versiones');
$opt = $GLOBALS['wpdb']->get_row($GLOBALS['wpdb']->prepare("SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s", 'sc_site_history'));
ok($opt && in_array($opt->autoload, array('no', 'off', 'auto-off'), true), 'sc_site_history no es autoload', $opt->autoload ?? '?');
$opt = $GLOBALS['wpdb']->get_row($GLOBALS['wpdb']->prepare("SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s", 'sc_site'));
ok($opt && in_array($opt->autoload, array('no', 'off', 'auto-off'), true), 'sc_site se guarda sin autoload', $opt->autoload ?? '?');

/* ---------------------------------------------------------------- /state e /icons */
section('GET state e icons');
[$s, $d] = ed_call(array(), $cid, 'GET', '/sc/v1/state');
ok($s === 200 && $d['ok'] && count($d['pages']) === 2 && $d['pages'][0]['key'] === 'home' && count($d['pages'][0]['sections']) === count(site()['pages']['home']['sections']), 'state: páginas y secciones');
ok(isset($d['pages'][0]['sections'][0]['label']) && $d['pages'][0]['sections'][0]['label'] !== '' && isset($d['services'][0]['nombre']) && is_array($d['design']['catalog']['head']) && isset($d['biz']['telefono']), 'state: etiquetas, servicios, catálogo de fuentes y datos');
ok(isset($d['links'][$HERO . 'btn1']) && isset($d['tokens'][0]['token']), 'state: mapa de enlaces y destinos');
$js = wp_json_encode($d); ok(!preg_match('/user_pass|user_login|session|nonce|password|secret|token_key/i', str_replace('"tokens"', '', $js)), 'state no expone datos sensibles');
[$s, $d] = ed_call(array(), 1, 'GET', '/sc/v1/icons');
ok($s === 200 && count($d) === count(sc_icon_keys()) && isset($d[0]['key'], $d[0]['label'], $d[0]['svg']) && str_starts_with($d[0]['svg'], '<svg'), 'icons: [{key,label,svg}] de todo el catálogo (' . count($d) . ')');
ok(!preg_match('/<script|onload=|onerror=/i', wp_json_encode($d)), 'los SVG no traen scripts');

/* ---------------------------------------------------------------- HTTP: carga condicional de la barra */
section('Frontal: la barra solo existe para quien puede editar');
update_option('sc_site', $fx_site, false); if (function_exists('sc_site_reload')) { sc_site_reload(); }
if (function_exists('sc_lx_active')) {
    $pg = get_option('page_on_front'); update_option('sc_mode', 'published');
    $anon = http($U . '/');
    ok($anon['code'] === 200 && !str_contains($anon['body'], 'SC_ED') && !str_contains($anon['body'], 'editor.js') && !str_contains($anon['body'], 'sc-ed-'), 'visitante: ni editor.js ni configuración ni clases', (string) $anon['code']);
    $jc = login_jar('cliente@bufete.test', 'Cliente-Test-123!', 'edcli');
    $pgc = http($U . '/', array('jar' => $jc));
    ok(str_contains($pgc['body'], 'editor.js') && str_contains($pgc['body'], 'window.SC_ED') && !str_contains($pgc['body'], 'media-editor'), 'cliente: carga editor.js sin la mediateca fuera del modo edición');
    $pge = http($U . '/?sc_edit=1', array('jar' => $jc));
    ok(str_contains($pge['body'], 'editor.css') && str_contains($pge['body'], 'media-editor') && preg_match('/"editing":true/', $pge['body']), 'cliente en ?sc_edit=1: editor.css, mediateca y editing=true');
    ok(str_contains(strtolower($pge['raw_headers']), 'no-cache') || str_contains(strtolower($pge['raw_headers']), 'no-store'), 'modo edición: sin caché');
    preg_match('/"nonce":"([a-f0-9]+)"/', $pge['body'], $m); $nonce = $m[1] ?? '';
    ok($nonce !== '', 'el nonce REST viaja en la configuración');
    $api = $U . '/?rest_route=/sc/v1/edit';
    $body = wp_json_encode(array('ops' => array(array('op' => 'text', 'path' => $HERO . 'title', 'value' => 'Por HTTP'))));
    $r = http($api, array('post' => $body, 'headers' => array('Content-Type: application/json')));
    ok($r['code'] === 401, 'HTTP anónimo → 401', (string) $r['code']);
    $r = http($api, array('post' => $body, 'jar' => $jc, 'headers' => array('Content-Type: application/json')));
    ok(in_array($r['code'], array(401, 403), true), 'HTTP con cookie pero sin nonce → 401/403', (string) $r['code']);
    $r = http($api, array('post' => $body, 'jar' => $jc, 'headers' => array('Content-Type: application/json', 'X-WP-Nonce: ' . $nonce)));
    ok($r['code'] === 200 && json_decode($r['body'], true)['ok'] === true && site()['pages']['home']['sections'][0]['data']['title'] === 'Por HTTP', 'HTTP con cookie + nonce → 200 y guarda', $r['code'] . ' ' . substr($r['body'], 0, 120));
    $r = http($U . '/?rest_route=/sc/v1/state', array('jar' => $jc, 'headers' => array('X-WP-Nonce: ' . $nonce)));
    ok($r['code'] === 200 && json_decode($r['body'], true)['ok'] === true, 'GET state por HTTP');
    sc_set_mode('preview');
} else {
    echo "  (omitido: el tema del sitio de pruebas no trae el renderizador LUXE)\n";
}

/* ---------------------------------------------------------------- limpieza */
section('Limpieza');
foreach ($created as $id) { wp_delete_post((int) $id, true); }
foreach ((array) get_posts(array('post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_sc_luxe', 'fields' => 'ids')) as $id) { if (str_starts_with((string) get_post_meta($id, '_sc_luxe', true), 'svc:')) { wp_delete_post((int) $id, true); } }
wp_delete_nav_menu($menuId);
if ($orig['site'] === null) { delete_option('sc_site'); } else { update_option('sc_site', $orig['site'], false); }
if ($orig['map'] === null) { delete_option('sc_service_pages'); } else { update_option('sc_service_pages', $orig['map']); }
if ($orig['hist'] === null) { delete_option('sc_site_history'); } else { update_option('sc_site_history', $orig['hist'], false); }
if ($orig['loc'] === null) { remove_theme_mod('nav_menu_locations'); } else { set_theme_mod('nav_menu_locations', $orig['loc']); }
foreach ($modkeys as $k) { if ($orig['mod_' . $k] === null) { remove_theme_mod('sc_' . $k); } else { set_theme_mod('sc_' . $k, $orig['mod_' . $k]); } }
if ($orig['logo'] === null) { remove_theme_mod('custom_logo'); } else { set_theme_mod('custom_logo', $orig['logo']); }
update_option('blogname', $orig['blogname']);
require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($sub);
ok(true, 'datos de prueba eliminados y opciones restauradas');
done();
