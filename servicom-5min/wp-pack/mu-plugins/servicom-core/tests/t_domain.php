<?php
require __DIR__ . '/lib.php';
global $wpdb;
$old = 'viejo-test.servicom.gt'; $new = 'nuevo-dominio.com.gt';
section('Preparar datos con el dominio viejo');
$el = [['id' => 'a1', 'elType' => 'container', 'settings' => ['background_image' => ['url' => "https://$old/wp-content/uploads/2026/01/banner.webp", 'id' => 5], 'link' => ['url' => "http://www.$old/contacto/"]], 'elements' => [['widgetType' => 'image', 'settings' => ['image' => ['url' => "https://$old/wp-content/uploads/a.jpg"]]]]]];
$json = wp_json_encode($el); // Con barras escapadas: https:\/\/
ok(str_contains($json, "https:\\/\\/$old"), 'JSON de prueba tiene barras escapadas');
$pid = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Dom test', 'post_name' => 'dom-test', 'post_content' => "<img src=\"https://$old/wp-content/uploads/x.jpg\"> <a href=\"http://$old/\">hola</a> correo info@$old y //$old/cdn y $old",
    'guid' => "https://$old/?page_id=999"]);
$wpdb->update($wpdb->posts, ['guid' => "https://$old/?page_id=$pid"], ['ID' => $pid]);
update_post_meta($pid, '_elementor_data', wp_slash($json));
$ser = ['logo' => "https://$old/logo.png", 'nested' => ['a' => "visita https://www.$old/x ñandú áéí", 'n' => 5, 'obj' => (object) ['u' => "http://$old/o"]], 'k ' . $old => 1, 'lista' => ["https://$old/1", "https://$old/2"]];
update_post_meta($pid, '_serial_test', $ser);
update_post_meta($pid, '_json_in_ser', ['raw' => $json]);
update_option('t2a_opt_ser', $ser); update_option('t2a_opt_plain', "https://$old/a");
update_option('widget_text', [2 => ['title' => 't', 'text' => "<a href=\"https://$old/w\">w</a>"], '_multiwidget' => 1]);
set_theme_mod('t2a_logo_url', "https://$old/wp-content/uploads/logo.png");
update_user_meta(1, 't2a_um', ['site' => "https://$old/perfil"]);
update_option('t2a_urlenc', 'u=' . rawurlencode("https://$old/p?q=1"));
update_option('t2a_other', "https://otro-$old.com/x y https://$old.evil.com/y https://x$old/z");   // NO deben tocarse
update_option('t2a_noop', "https://ejemplo.org/x");
// Archivo CSS de Elementor de prueba
$up = wp_upload_dir(); wp_mkdir_p($up['basedir'] . '/elementor/css'); file_put_contents($up['basedir'] . '/elementor/css/post-' . $pid . '.css', ".a{background:url(https://$old/x.jpg)}");
update_post_meta($pid, '_elementor_css', ['status' => 'file']);

section('sc_replace_domain');
$res = sc_replace_domain("https://$old", "https://$new");
ok($res['ok'], 'ok=true', json_encode($res));
ok($res['filas'] >= 8 && $res['reemplazos'] >= 15, 'conteos: filas=' . $res['filas'] . ' reemplazos=' . $res['reemplazos']);
eq($res['restantes'], [], 'no quedan restos del dominio viejo');
ok(isset($res['tablas'][$wpdb->postmeta], $res['tablas'][$wpdb->options], $res['tablas'][$wpdb->posts], $res['tablas'][$wpdb->usermeta]), 'tablas reportadas: ' . implode(',', array_keys($res['tablas'])));
$p = get_post($pid);
ok(str_contains($p->post_content, "https://$new/wp-content/uploads/x.jpg") && str_contains($p->post_content, "href=\"https://$new/\""), 'post_content: http->https y dominio nuevo');
ok(str_contains($p->post_content, "info@$new") && str_contains($p->post_content, "//$new/cdn") && !str_contains($p->post_content, $old), 'correo, protocolo-relativo y texto plano reemplazados');
eq($wpdb->get_var("SELECT guid FROM {$wpdb->posts} WHERE ID=$pid"), "https://$old/?page_id=$pid", 'guid NO se toca');
$raw = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_elementor_data'", $pid));
ok(str_contains($raw, "https:\\/\\/$new\\/wp-content\\/uploads\\/2026\\/01\\/banner.webp") && !str_contains($raw, $old), '_elementor_data: barras escapadas conservadas, sin dominio viejo');
$dec = json_decode($raw, true);
ok(is_array($dec) && $dec[0]['settings']['background_image']['url'] === "https://$new/wp-content/uploads/2026/01/banner.webp" && $dec[0]['settings']['link']['url'] === "https://$new/contacto/", 'JSON sigue siendo válido y con URLs nuevas (www y http normalizados)');
eq($dec[0]['settings']['background_image']['id'], 5, 'datos no relacionados intactos');
$s = get_post_meta($pid, '_serial_test', true);
ok(is_array($s) && $s['logo'] === "https://$new/logo.png" && $s['nested']['a'] === "visita https://$new/x ñandú áéí" && $s['nested']['n'] === 5 && $s['nested']['obj'] instanceof stdClass && $s['nested']['obj']->u === "https://$new/o" && isset($s['k ' . $new]) && $s['lista'][1] === "https://$new/2", 'serializado: valores, claves, objetos y UTF-8 con longitudes correctas');
$rawS = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_serial_test'", $pid));
ok(is_array(@unserialize($rawS)), 'unserialize del valor crudo funciona');
$js = get_post_meta($pid, '_json_in_ser', true); ok(is_array(json_decode($js['raw'], true)) && !str_contains($js['raw'], $old), 'JSON dentro de serializado');
eq(get_option('t2a_opt_ser')['logo'], "https://$new/logo.png", 'opción serializada');
eq(get_option('t2a_opt_plain'), "https://$new/a", 'opción plana');
eq(get_option('widget_text')[2]['text'], "<a href=\"https://$new/w\">w</a>", 'widget');
eq(get_theme_mod('t2a_logo_url'), "https://$new/wp-content/uploads/logo.png", 'theme_mod');
eq(get_user_meta(1, 't2a_um', true)['site'], "https://$new/perfil", 'usermeta');
eq(get_option('t2a_urlenc'), 'u=' . rawurlencode("https://$new/p?q=1"), 'URL codificada');
eq(get_option('t2a_other'), "https://otro-$old.com/x y https://$old.evil.com/y https://x$old/z", 'dominios parecidos NO se tocan');
eq(get_option('t2a_noop'), "https://ejemplo.org/x", 'otros valores intactos');
ok(!file_exists($up['basedir'] . '/elementor/css/post-' . $pid . '.css') && !get_post_meta($pid, '_elementor_css', true), 'CSS de Elementor limpiado/regenerable (' . $res['css'] . ')');
section('Idempotencia y casos límite');
$res2 = sc_replace_domain("https://$old", "https://$new");
ok($res2['ok'] && $res2['reemplazos'] === 0 && $res2['filas'] === 0, 'segunda ejecución: 0 cambios');
$res3 = sc_replace_domain($new, $new); ok($res3['ok'] && $res3['reemplazos'] === 0, 'from == to: no hace nada');
update_option('t2a_sub', "https://api.$new/x https://$new/y");
$r4 = sc_replace_domain($new, "www.$new"); $r5 = sc_replace_domain($new, "www.$new");
ok($r5['reemplazos'] === 0 && $r5['ok'], 'to contiene a from: segunda pasada sin cambios');
eq(get_option('t2a_sub'), "https://api.$new/x https://www.$new/y", 'subdominio api.* intacto, raíz -> www');
ok(!sc_replace_domain('', 'x.com')['ok'] && !sc_replace_domain('a b', 'x.com')['ok'], 'entradas inválidas rechazadas');
$r6 = sc_replace_domain("http://www.$new", "https://$new"); // cambio solo de esquema/www
eq(get_option('t2a_sub'), "https://api.$new/x https://$new/y", 'www -> sin www y esquema');
// Datos serializados corruptos (longitud errónea): no deben romper nada
$wpdb->insert($wpdb->options, ['option_name' => 't2a_corrupt', 'option_value' => 'a:1:{s:1:"u";s:99:"https://' . $new . '/x";}', 'autoload' => 'no']);
$r7 = sc_replace_domain($new, 'final.example.org');
ok($r7['ok'] || $r7['errores'] === [], 'serializado corrupto: sin errores fatales', json_encode($r7['errores'] + $r7['restantes']));
ok(!str_contains((string) $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='t2a_corrupt'"), $new), 'serializado corrupto también reemplazado');
// limpiar
foreach (['t2a_opt_ser', 't2a_opt_plain', 't2a_urlenc', 't2a_other', 't2a_noop', 't2a_sub', 't2a_corrupt'] as $o) { delete_option($o); }
delete_option('widget_text'); remove_theme_mod('t2a_logo_url'); delete_user_meta(1, 't2a_um'); wp_delete_post($pid, true);
done();
