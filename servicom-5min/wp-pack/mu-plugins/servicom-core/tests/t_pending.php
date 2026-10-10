<?php
/** Pendientes: detecta qué falta, enlaza al campo exacto, desaparece al completarse y se puede descartar. */
require __DIR__ . '/lib.php';
wp_set_current_user(1);
$keys = ['telefono','whatsapp','correo','direccion','horario','mapa_url','facebook','instagram','tiktok','youtube','x','linkedin'];
$orig = []; foreach ($keys as $k) { $orig[$k] = get_theme_mod('sc_' . $k, null); set_theme_mod('sc_' . $k, ''); }
$origLogo = get_theme_mod('custom_logo', 0); set_theme_mod('custom_logo', 0);
$origSkip = get_option('sc_pending_skip', null); delete_option('sc_pending_skip');

section('Todo vacío');
$it = sc_pending_items(); $ks = array_column($it, 'key');
eq(count($it), 8, 'ocho pendientes');
ok(in_array('whatsapp', $ks, true) && in_array('logo', $ks, true) && in_array('redes', $ks, true), 'incluye WhatsApp, logo y redes');
$u = array_column($it, 'url', 'key');
ok(str_contains($u['whatsapp'], 'sc_edit=1') && str_contains($u['whatsapp'], 'sc_biz=whatsapp'), 'enlace directo al campo: ' . $u['whatsapp']);
ok(str_contains($u['logo'], 'sc_biz=logo'), 'enlace al logo');
ok(str_contains($u['redes'], 'sc_biz=facebook'), 'redes abre la primera red');
ob_start(); sc_pending_render_box(false); $h = ob_get_clean();
ok(str_contains($h, 'Falta completar 8 datos') && str_contains($h, 'Completar ahora') && str_contains($h, 'Importante'), 'la caja se muestra');
ob_start(); sc_ins_render(); $ins = ob_get_clean();
ok(str_contains($ins, 'sc-pend'), 'INSTRUCCIONES muestra la caja de pendientes');

section('Completar un dato lo quita');
set_theme_mod('sc_whatsapp', '50255551234'); set_theme_mod('sc_telefono', '2222-3333');
$ks = array_column(sc_pending_items(), 'key');
ok(!in_array('whatsapp', $ks, true) && !in_array('telefono', $ks, true), 'WhatsApp y teléfono ya no aparecen');
set_theme_mod('sc_instagram', '@miempresa');
ok(!in_array('redes', array_column(sc_pending_items(), 'key'), true), 'una red social basta para quitar «redes»');

section('Descartar');
update_option('sc_pending_skip', ['horario']);
ok(!in_array('horario', array_column(sc_pending_items(), 'key'), true), '«Este negocio no lo tiene» oculta el dato');
ok(in_array('horario', array_column(sc_pending_items(true), 'key'), true), 'pero sigue pudiendo listarse completo');

section('Todo completo');
foreach (['correo' => 'a@b.com', 'direccion' => 'Zona 1', 'mapa_url' => 'https://maps.example/x'] as $k => $v) { set_theme_mod('sc_' . $k, $v); }
set_theme_mod('custom_logo', 0);
eq(count(sc_pending_items()), 1, 'solo queda el logo');
ob_start(); sc_pending_render_box(true); $h = ob_get_clean();
ok(str_contains($h, 'sc-pend--compact'), 'versión compacta para el widget');

// Restaurar
foreach ($keys as $k) { if ($orig[$k] === null) { remove_theme_mod('sc_' . $k); } else { set_theme_mod('sc_' . $k, $orig[$k]); } }
set_theme_mod('custom_logo', $origLogo);
if ($origSkip === null) { delete_option('sc_pending_skip'); } else { update_option('sc_pending_skip', $origSkip); }
done();
