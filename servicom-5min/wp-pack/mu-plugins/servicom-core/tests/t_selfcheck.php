<?php
require __DIR__ . '/lib.php';
$U = $ENV['url']; $seed = json_decode(file_get_contents($ENV['dir'] . '/seed.json'), true);
update_option('sc_qa_sslverify', true);
section('Sitio sano');
$r = sc_selfcheck();
ok(isset($r['ok'], $r['problemas'], $r['urls']), 'estructura {ok, problemas, urls}');
ok(count($r['urls']) >= 7, 'URLs listadas: ' . count($r['urls']));
ok($r['ok'] && $r['problemas'] === [], 'sin problemas', json_encode($r['problemas'], JSON_UNESCAPED_UNICODE));

section('Con problemas provocados');
$home = (int) $seed['ids']['home'];
$orig = get_post_field('post_content', $home);
wp_update_post(['ID' => $home, 'post_content' => $orig
  . '<img src="' . $U . '/wp-content/uploads/no-existe.jpg" alt="x">'
  . '<img src="' . $U . '/wp-includes/images/blank.gif" alt="ok">'
  . '<div class="sc-sec sc-sec--alt"><div class="sc-grid">   </div></div>'
  . '<p><a href="' . $U . '/pagina-que-no-existe/">roto</a> <a href="' . $U . '/nosotros/">bueno</a></p>'
  . '<p>Warning: Undefined variable $zz in /var/www/x.php on line 12</p>']);
add_filter('sc_qa_urls', function ($u) use ($U) { $u[] = $U . '/ruta-404-xyz/'; return $u; });
define('ELEMENTOR_VERSION', '3.99.0');
update_post_meta($seed['ids']['nosotros'], '_elementor_edit_mode', 'builder');
$r = sc_selfcheck();
$tipos = array_column($r['problemas'], 'tipo');
ok(!$r['ok'], 'ok=false');
foreach (['http', 'imagen_rota', 'seccion_vacia', 'enlace_roto', 'error_php', 'css_elementor'] as $t) { ok(in_array($t, $tipos, true), "detecta $t"); }
foreach ($r['problemas'] as $p) { ok(isset($p['url'], $p['tipo'], $p['detalle']), "{$p['tipo']}: {$p['detalle']}"); }
$imgs = array_filter($r['problemas'], fn($p) => $p['tipo'] === 'imagen_rota');
eq(count($imgs), 1, 'solo la imagen realmente rota (blank.gif OK)');
// CSS de Elementor presente => deja de reportar
$up = wp_upload_dir(); wp_mkdir_p($up['basedir'] . '/elementor/css');
file_put_contents($up['basedir'] . '/elementor/css/post-' . $seed['ids']['nosotros'] . '.css', '.x{}');
$r2 = sc_selfcheck(); ok(!in_array('css_elementor', array_column($r2['problemas'], 'tipo'), true), 'con post-N.css existente no reporta css_elementor');
unlink($up['basedir'] . '/elementor/css/post-' . $seed['ids']['nosotros'] . '.css'); delete_post_meta($seed['ids']['nosotros'], '_elementor_edit_mode');
wp_update_post(['ID' => $home, 'post_content' => $orig]);
section('Restaurado');
remove_all_filters('sc_qa_urls');
$r3 = sc_selfcheck(); ok($r3['ok'], 'vuelve a estar sano', json_encode($r3['problemas'], JSON_UNESCAPED_UNICODE));
done();
