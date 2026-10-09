<?php
require __DIR__ . '/lib.php';
section('Helpers sc_biz / sc_biz_all');
eq(sc_biz('nombre'), 'Bufete Pérez & Asociados', 'sc_biz nombre');
eq(sc_biz('sc_telefono'), '2222-3333', 'sc_biz acepta prefijo sc_');
eq(sc_biz('x_inexistente', 'def'), 'def', 'default del llamador');
eq(sc_biz('float_wa'), 1, 'float_wa por defecto = 1');
eq(sc_biz('style'), 2, 'style = 2');
eq(sc_biz('footer_credit'), 'Sitio creado por Servicom', 'footer_credit por defecto');
set_theme_mod('sc_footer_credit', ''); eq(sc_biz('footer_credit'), '', 'footer_credit vacío permitido'); remove_theme_mod('sc_footer_credit');
set_theme_mod('sc_float_wa', 0); eq(sc_biz('float_wa'), 0, 'toggle apagado = 0'); remove_theme_mod('sc_float_wa');
$all = sc_biz_all();
ok(isset($all['nombre'], $all['redes'], $all['logo_id'], $all['style']), 'sc_biz_all tiene claves base');
eq(array_keys($all['redes']), ['facebook', 'instagram'], 'redes con valor');

section('Shortcodes');
$s = fn($t) => do_shortcode($t);
eq($s('[sc_nombre]'), 'Bufete Pérez &amp; Asociados', 'sc_nombre escapado');
eq($s('[sc_telefono]'), '2222-3333', 'sc_telefono');
ok(str_ends_with($s('[sc_telefono_link]'), '>2222-3333</a>'), 'sc_telefono_link muestra el número');
ok(str_contains($s('[sc_telefono_link]'), 'href="tel:22223333"'), 'sc_telefono_link solo dígitos en href');
$wa = $s('[sc_whatsapp_link texto="Hablemos"]');
ok(str_contains($wa, 'https://wa.me/50255551234?text=Hola%2C%20quiero%20una%20consulta') && str_contains($wa, '>Hablemos<') && str_contains($wa, 'rel="noopener noreferrer"'), 'sc_whatsapp_link', $wa);
ok(str_contains($s('[sc_correo]'), 'mailto:info@bufete.test'), 'sc_correo');
ok(str_contains($s('[sc_direccion]'), '<br'), 'sc_direccion con salto de línea');
ok(str_contains($s('[sc_horario]'), 'Lunes a viernes'), 'sc_horario');
$r = $s('[sc_redes]');
ok(substr_count($r, '<li>') === 2 && str_contains($r, '<svg') && str_contains($r, 'rel="noopener noreferrer"') && str_contains($r, 'aria-label="Facebook'), 'sc_redes: 2 enlaces con SVG y aria-label', $r);
ok(str_contains($r, 'aria-hidden="true"'), 'SVG oculto a lectores');
ok(str_contains($s('[sc_mapa_link]'), 'google.com/maps/search'), 'sc_mapa_link usa la dirección si no hay URL');
set_theme_mod('sc_mapa_url', 'https://maps.app.goo.gl/abc'); ok(str_contains($s('[sc_mapa_link texto="Mapa"]'), 'href="https://maps.app.goo.gl/abc"'), 'sc_mapa_link con URL'); remove_theme_mod('sc_mapa_url');
eq($s('[sc_anio]'), wp_date('Y'), 'sc_anio');
set_theme_mod('sc_nombre', '<script>alert(1)</script>Neg'); ok(!str_contains($s('[sc_nombre]'), '<script'), 'XSS escapado en sc_nombre'); set_theme_mod('sc_nombre', 'Bufete Pérez & Asociados');
set_theme_mod('sc_telefono', ''); eq($s('[sc_telefono_link]'), '', 'sin teléfono -> vacío'); set_theme_mod('sc_telefono', '2222-3333');

section('Sanitizadores');
eq(sc_biz_sanitize('whatsapp', '+502 5555-1234'), '50255551234', 'whatsapp solo dígitos');
eq(sc_biz_sanitize('telefono', '<b>2222</b> ext 5'), '2222 5', 'teléfono limpia etiquetas y letras');
eq(sc_biz_sanitize('correo', 'no-es-correo'), '', 'correo inválido');
eq(sc_biz_sanitize('instagram', '@mi.negocio'), 'https://www.instagram.com/mi.negocio', 'instagram @usuario -> URL');
eq(sc_biz_sanitize('facebook', 'javascript:alert(1)'), '', 'javascript: rechazado');
eq(sc_biz_sanitize('mapa_url', 'ftp://x.com'), '', 'ftp rechazado');
eq(sc_biz_sanitize('style', 9), 1, 'style fuera de rango -> 1');
eq(sc_biz_sanitize('float_wa', 'on'), 1, 'toggle on');
eq(sc_biz_sanitize('float_wa', '0'), 0, 'toggle 0');

section('Personalizador');
require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
wp_set_current_user(1);
$wpc = new WP_Customize_Manager();
do_action('customize_register', $wpc);
foreach (['sc_business', 'sc_social', 'sc_float', 'sc_style_section'] as $sid) { ok((bool) $wpc->get_section($sid), "sección $sid registrada"); }
foreach (array_keys(sc_biz_fields()) as $k) {
    $st = $wpc->get_setting('sc_' . $k);
    ok($st && $st->type === 'theme_mod' && $st->transport === 'refresh' && $wpc->get_control('sc_' . $k), "setting+control sc_$k (theme_mod, refresh)");
}
eq(sc_biz_sections()['sc_business']['title'], 'Datos del negocio', 'título en español');
$url = sc_customizer_url('sc_business', admin_url('admin.php?page=sc-instrucciones'));
ok(str_contains(urldecode($url), 'autofocus[section]=sc_business') && str_contains($url, 'return='), 'URL de autofocus', $url);
// Recorte para cliente
wp_set_current_user((int) json_decode(file_get_contents($ENV['dir'] . '/seed.json'))->client);
$wpc2 = new WP_Customize_Manager(); do_action('customize_register', $wpc2);
ok(!$wpc2->get_section('custom_css'), 'cliente: sin CSS adicional');
ok(!$wpc2->get_panel('themes') && !$wpc2->get_section('themes'), 'cliente: sin Temas');
ok(!$wpc2->get_panel('widgets'), 'cliente: sin Widgets');
ok((bool) $wpc2->get_section('sc_business') && (bool) $wpc2->get_section('title_tagline'), 'cliente conserva Datos del negocio e Identidad');
wp_set_current_user(1);
$wpc3 = new WP_Customize_Manager(); do_action('customize_register', $wpc3);
ok((bool) $wpc3->get_section('custom_css'), 'admin conserva CSS adicional');
done();
