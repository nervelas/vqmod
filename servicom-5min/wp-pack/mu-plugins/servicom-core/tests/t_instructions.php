<?php
require __DIR__ . '/lib.php';
$U = $ENV['url']; $seed = json_decode(file_get_contents($ENV['dir'] . '/seed.json'), true);
wp_set_current_user(1);

/** Valida una URL del admin: post.php?post=N existe; customize autofocus apunta a sección real. */
function check_url(string $url, WP_Customize_Manager $wpc): array {
    $errs = [];
    if (!str_starts_with($url, admin_url()) && !str_starts_with($url, 'https://wa.me/') && !str_starts_with($url, home_url('/')) && !str_starts_with($url, wp_login_url()) && !str_starts_with($url, wp_lostpassword_url())) { $errs[] = 'fuera del sitio: ' . $url; }
    $q = []; parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
    $path = (string) parse_url($url, PHP_URL_PATH);
    if (str_ends_with($path, 'post.php')) {
        $p = get_post((int) ($q['post'] ?? 0));
        if (!$p) { $errs[] = 'post inexistente: ' . $url; }
        elseif (!in_array($q['action'] ?? '', ['edit', 'elementor'], true)) { $errs[] = 'acción rara: ' . $url; }
    }
    if (str_ends_with($path, 'customize.php') && isset($q['autofocus']['section'])) {
        $sid = $q['autofocus']['section'];
        if (!$wpc->get_section($sid) && !$wpc->get_panel($sid)) { $errs[] = "sección de Personalizador inexistente: $sid"; }
    }
    return $errs;
}
require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
$wpc = new WP_Customize_Manager(); do_action('customize_register', $wpc);

section('sc_instruction_links (sin Elementor)');
$L = sc_instruction_links();
eq(array_keys($L['pages']), ['home', 'nosotros', 'servicios', 'galeria', 'contacto'], 'páginas de sc_page_ids');
foreach ($L['pages'] as $k => $p) {
    eq($p['url'], admin_url('post.php?post=' . $seed['ids'][$k] . '&action=edit'), "página $k -> editor clásico con ID real");
}
eq(count($L['services']), 2, '2 páginas de servicio');
foreach ($L['services'] as $i => $s) { eq($s['url'], admin_url('post.php?post=' . $seed['svc'][$i] . '&action=edit'), "servicio $i -> ID real"); }
$bad = [];
$flat = function (array $a) use (&$flat) { $o = []; foreach ($a as $k => $v) { if (is_array($v)) { $o = array_merge($o, isset($v['url']) ? [$v['url']] : $flat($v)); } elseif (is_string($v) && preg_match('#^https?://#', $v)) { $o[] = $v; } } return $o; };
foreach ($flat($L) as $u) { $bad = array_merge($bad, check_url($u, $wpc)); }
ok(!$bad, 'todas las URLs válidas (posts existen, secciones existen)', implode('; ', $bad));
ok(str_contains(urldecode($L['customizer_business']), 'autofocus[section]=sc_business') && str_contains($L['customizer_business'], 'return='), 'Personalizador con autofocus y return');
foreach (['customizer_business' => 'sc_business', 'customizer_social' => 'sc_social', 'customizer_float' => 'sc_float', 'customizer_style' => 'sc_style_section'] as $k => $sec) { ok(str_contains(urldecode($L[$k]), "autofocus[section]=$sec"), "$k -> $sec"); }
ok(str_ends_with($L['menus'], 'nav-menus.php') && str_ends_with($L['media'], 'upload.php') && str_ends_with($L['profile'], 'profile.php'), 'menús, medios y perfil');
eq($L['kit'], $L['customizer_style'], 'sin Elementor: colores -> estilo visual del Personalizador');
ok(str_starts_with($L['wa_servicom'], 'https://wa.me/50212345678?text=') && str_contains(urldecode($L['wa_servicom']), 'Bufete Pérez & Asociados'), 'WhatsApp de soporte con el nombre del sitio');

section('Con Elementor activo (simulado: constante)');
define('ELEMENTOR_VERSION', '3.99.0');
$kit = wp_insert_post(['post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Kit', 'post_content' => '']);
update_option('elementor_active_kit', $kit);
$L2 = sc_instruction_links();
eq($L2['pages']['home']['url'], admin_url('post.php?post=' . $seed['ids']['home'] . '&action=elementor'), 'páginas -> post.php?post=ID&action=elementor');
eq($L2['kit'], admin_url('post.php?post=' . $kit . '&action=elementor'), 'colores/tipografías -> editar el Kit');
$bad = []; foreach ($flat($L2) as $u) { $bad = array_merge($bad, check_url($u, $wpc)); } ok(!$bad, 'URLs válidas con Elementor', implode('; ', $bad));
wp_delete_post($kit, true); delete_option('elementor_active_kit');
$L3 = sc_instruction_links(); eq($L3['kit'], $L3['customizer_style'], 'kit inexistente -> fallback al Personalizador');

section('Tarjetas');
$cards = sc_instruction_cards();
$ids = array_column($cards, 'id');
foreach (['textos', 'titulos', 'imagenes', 'iconos', 'botones', 'banner', 'servicios', 'galeria', 'menu', 'logo', 'colores', 'encabezado', 'pie', 'contacto', 'redes', 'formulario', 'mapa', 'video', 'password'] as $need) { ok(in_array($need, $ids, true), "tarjeta $need"); }
ok(!array_intersect(['productos', 'pedidos', 'pagos', 'banco', 'precios', 'stock', 'categorias', 'cuentas'], $ids), 'plan info: sin tarjetas de tienda');
$errs = [];
foreach ($cards as $c) {
    $n = count($c['steps']);
    if ($n < 2 || $n > 4) { $errs[] = "{$c['id']} tiene $n pasos"; }
    if (empty($c['buttons'])) { $errs[] = "{$c['id']} sin botón"; }
    foreach ($c['buttons'] as $b) { $errs = array_merge($errs, check_url($b['url'], $wpc)); if (trim($b['label']) === '') { $errs[] = "{$c['id']} botón sin etiqueta"; } }
    if (trim($c['title']) === '') { $errs[] = 'sin título'; }
}
ok(!$errs, 'cada tarjeta: 2–4 pasos, botón con URL real', implode('; ', $errs));
update_option('sc_plan', 'tienda');
$ids2 = array_column(sc_instruction_cards(), 'id');
foreach (['productos', 'categorias', 'precios', 'stock', 'pedidos', 'pagos', 'banco', 'cuentas'] as $need) { ok(in_array($need, $ids2, true), "plan tienda: tarjeta $need"); }
$pay = null; foreach (sc_instruction_cards() as $c) { if ($c['id'] === 'banco') { $pay = $c['buttons'][0]['url']; } }
eq($pay, admin_url('admin.php?page=wc-settings&tab=checkout&section=bacs'), 'datos bancarios -> wc-settings checkout bacs');
update_option('sc_plan', 'info');
add_filter('sc_instruction_cards', function ($c) { $c[] = ['id' => 'extra', 'group' => 'contenido', 'icon' => 'star', 'title' => 'Extra', 'steps' => ['a', 'b'], 'buttons' => [['label' => 'x', 'url' => admin_url()]]]; return $c; });
ok(in_array('extra', array_column(sc_instruction_cards(), 'id'), true), 'filtro sc_instruction_cards extensible');

section('Pantalla (HTTP, cliente)');
$jc = login_jar('cliente@bufete.test', 'Cliente-Test-123!', 'cli');
$r = http($U . '/wp-admin/admin.php?page=sc-instrucciones', ['jar' => $jc]);
eq($r['code'], 200, 'INSTRUCCIONES 200');
$b = $r['body'];
foreach (['Hola, Bufete Pérez &amp; Asociados', '¿Qué desea cambiar?', 'id="sc-ins-q"', '2 días hábiles', 'wa.me/50212345678', 'Cambiar mi contraseña', 'instructions.css', 'instructions.js'] as $needle) { ok(str_contains($b, $needle), "contiene: $needle"); }
ok(substr_count($b, 'class="sc-ins__card"') >= 19, 'tarjetas renderizadas: ' . substr_count($b, 'class="sc-ins__card"'));
ok(!str_contains($b, '<script>alert'), 'sin scripts inesperados');
preg_match('/href=[\x27"]([^\x27"]*instructions\.css[^\x27"]*)[\x27"]/', $b, $m); $css = http(html_entity_decode($m[1] ?? ''));
eq($css['code'], 200, 'instructions.css se sirve (200)');
preg_match('/src="([^"]*instructions\.js[^"]*)"/', $b, $m); $js = http(html_entity_decode($m[1] ?? ''));
eq($js['code'], 200, 'instructions.js se sirve (200)');
$r = http($U . '/wp-admin/admin.php?page=sc-instrucciones&sc_aviso=1', ['jar' => $jc]);
ok(str_contains($r['body'], 'no está disponible en su cuenta'), 'aviso al ser redirigido');
done();
