<?php
// Siembra datos de prueba en el sitio local: páginas, ajustes, usuario cliente.
$NO_WP = false;
require __DIR__ . '/lib.php';
global $wp_rewrite;
switch_theme(is_dir($ENV['site'] . '/wp-content/themes/servicom') ? 'servicom' : 't2a-classic');
update_option('permalink_structure', '/%postname%/');
$wp_rewrite->set_permalink_structure('/%postname%/');

$mk = function (string $title, string $slug, string $content) {
    return wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content]);
};
$sec = fn($t) => '<section class="sc-sec"><h2>' . $t . '</h2><p>Texto de ' . $t . '</p></section>';
$ids = [
    'home'      => $mk('Inicio', 'inicio', $sec('Bienvenida') . '<p>[sc_nombre] — [sc_telefono_link] [sc_whatsapp_link texto="Hablemos"]</p>'),
    'nosotros'  => $mk('Nosotros', 'nosotros', $sec('Quiénes somos')),
    'servicios' => $mk('Servicios', 'servicios', $sec('Servicios')),
    'galeria'   => $mk('Galería', 'galeria', $sec('Galería')),
    'contacto'  => $mk('Contacto', 'contacto', $sec('Contacto') . '[sc_direccion] [sc_horario] [sc_redes] [sc_mapa_link]'),
];
$svc = [1 => $mk('Asesoría legal', 'servicio-asesoria', $sec('Asesoría')), 2 => $mk('Contratos', 'servicio-contratos', $sec('Contratos'))];
update_option('show_on_front', 'page'); update_option('page_on_front', $ids['home']);
update_option('sc_page_ids', $ids); update_option('sc_service_pages', $svc);
update_option('sc_plan', 'info');
update_option('sc_preview_key', 'a1b2c3d4e5f60718293a4b5c6d7e8f90');
update_option('sc_pay_url', 'https://crear.servicom.test/vp/a1b2c3d4e5f60718293a4b5c6d7e8f90/pagar');
update_option('sc_edit_url', 'https://crear.servicom.test/vp/a1b2c3d4e5f60718293a4b5c6d7e8f90/editar');
update_option('sc_wa_servicom', '50212345678');
foreach (['nombre' => 'Bufete Pérez & Asociados', 'telefono' => '2222-3333', 'whatsapp' => '50255551234', 'whatsapp_msg' => 'Hola, quiero una consulta',
          'correo' => 'info@bufete.test', 'direccion' => "6a Avenida 10-20, zona 1\nGuatemala", 'horario' => 'Lunes a viernes 8:00-17:00',
          'facebook' => 'https://facebook.com/bufete', 'instagram' => 'https://www.instagram.com/bufete', 'style' => 2] as $k => $v) {
    set_theme_mod('sc_' . $k, $v);
}
sc_set_mode('preview');
$u = sc_create_client_user('cliente@bufete.test', 'Ana Cliente');
wp_set_password('Cliente-Test-123!', $u['user_id']);
file_put_contents($ENV['dir'] . '/seed.json', json_encode(['ids' => $ids, 'svc' => $svc, 'client' => $u['user_id'], 'key' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90']));
$wp_rewrite->flush_rules(false);
echo "Sembrado: " . count($ids) . " paginas, cliente {$u['user_id']}\n";
