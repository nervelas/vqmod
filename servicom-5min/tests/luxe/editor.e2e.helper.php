<?php
/**
 * Ayudante de la prueba E2E del editor (tests/luxe/editor.e2e.js). Se ejecuta con el usuario del sitio:
 *   runuser -u www-data -- php editor.e2e.helper.php <orden> [args]
 * Órdenes: login <admin|cliente> · snapshot <archivo> · restore <archivo> · info · png <archivo>
 * Solo toca el sitio de prueba (slug bufete-nandu-asociados) y deja todo como estaba con `restore`.
 * No hay "modo de prueba" dentro del plugin: la sesión se crea aquí, con las mismas funciones que WordPress.
 */
if (PHP_SAPI !== 'cli') { exit; }
$SITE = getenv('SC_E2E_SITE') ?: '/tmp/s5test/webs/bufete-nandu-asociados';
$HOST = getenv('SC_E2E_HOST') ?: 'bufete-nandu-asociados.servicom.test:8200';
$_SERVER['HTTP_HOST'] = $HOST; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require $SITE . '/wp-load.php';

$cmd = $argv[1] ?? '';
function out($v) { echo json_encode($v, JSON_UNESCAPED_UNICODE); exit(0); }

if ($cmd === 'login') {
    $role = $argv[2] ?? 'admin';
    $login = $role === 'cliente' ? 'sc_e2e_cliente' : 'sc_e2e_admin';
    $u = get_user_by('login', $login);
    if (!$u) {
        $id = wp_insert_user(array('user_login' => $login, 'user_pass' => wp_generate_password(24), 'user_email' => $login . '@example.test', 'role' => $role === 'cliente' ? 'sc_cliente' : 'administrator'));
        $u = get_userdata($id);
    }
    $exp = time() + 6 * HOUR_IN_SECONDS;
    $tok = WP_Session_Tokens::get_instance($u->ID)->create($exp);
    out(array('name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie($u->ID, $exp, 'logged_in', $tok), 'auth_name' => AUTH_COOKIE, 'auth_value' => wp_generate_auth_cookie($u->ID, $exp, 'auth', $tok), 'uid' => $u->ID, 'sc_edit_site' => user_can($u, 'sc_edit_site')));
}

if ($cmd === 'info') {
    $s = get_option('sc_site', array());
    $svc = array();
    foreach ((array) ($s['services'] ?? array()) as $n => $x) {
        $pid = (int) ($x['post'] ?? 0);
        $svc[] = array('n' => $n, 'nombre' => $x['nombre'] ?? '', 'on' => !empty($x['on']), 'post' => $pid, 'status' => $pid ? get_post_status($pid) : '', 'title' => $pid ? get_the_title($pid) : '', 'meta' => $pid ? get_post_meta($pid, '_sc_luxe', true) : '', 'parent' => $pid ? (int) wp_get_post_parent_id($pid) : 0);
    }
    $menu = array();
    $locs = get_theme_mod('nav_menu_locations', array());
    foreach ((array) wp_get_nav_menu_items((int) ($locs['primary'] ?? 0)) as $it) { $menu[] = array('title' => $it->title, 'parent' => (int) $it->menu_item_parent, 'obj' => (int) $it->object_id); }
    out(array('site' => $s, 'svc' => $svc, 'menu' => $menu, 'map' => get_option('sc_service_pages'), 'pageids' => get_option('sc_page_ids'), 'history' => count((array) get_option('sc_site_history', array())), 'preview_key' => get_option('sc_preview_key'), 'tel' => get_theme_mod('sc_telefono', ''), 'logo' => (int) get_theme_mod('custom_logo', 0), 'blogname' => get_option('blogname')));
}

if ($cmd === 'snapshot') {
    global $wpdb;
    $mods = array();
    foreach (array('nombre', 'telefono', 'whatsapp', 'whatsapp_msg', 'correo', 'direccion', 'mapa_url', 'horario', 'facebook', 'instagram', 'tiktok', 'youtube', 'x', 'linkedin') as $k) { $mods['sc_' . $k] = get_theme_mod('sc_' . $k, null); }
    $mods['custom_logo'] = get_theme_mod('custom_logo', null);
    $snap = array(
        'site' => get_option('sc_site'), 'svcmap' => get_option('sc_service_pages'), 'mods' => $mods, 'blogname' => get_option('blogname'),
        'max_post' => (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}"), 'history' => get_option('sc_site_history', null),
        'pages' => $wpdb->get_results("SELECT ID, post_status, post_title FROM {$wpdb->posts} WHERE post_type = 'page'", ARRAY_A),
    );
    file_put_contents($argv[2], serialize($snap));
    out(array('ok' => true, 'max_post' => $snap['max_post']));
}

if ($cmd === 'restore') {
    global $wpdb;
    $snap = unserialize((string) file_get_contents($argv[2]));
    update_option('sc_site', $snap['site'], false);
    update_option('sc_service_pages', $snap['svcmap']);
    foreach ($snap['mods'] as $k => $v) { if ($v === null) { remove_theme_mod($k); } else { set_theme_mod($k, $v); } }
    update_option('blogname', $snap['blogname']);
    if ($snap['history'] === null) { delete_option('sc_site_history'); } else { update_option('sc_site_history', $snap['history'], false); }
    // quitar todo lo creado durante la prueba (páginas, ítems de menú, adjuntos)
    $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID > %d", $snap['max_post']));
    foreach ($ids as $id) { wp_delete_post((int) $id, true); }
    // páginas originales: mismo estado que antes
    foreach ($snap['pages'] as $p) {
        $cur = get_post_status((int) $p['ID']);
        if ($cur && $cur !== $p['post_status']) { if ($cur === 'trash') { wp_untrash_post((int) $p['ID']); } wp_update_post(array('ID' => (int) $p['ID'], 'post_status' => $p['post_status'], 'post_title' => wp_slash($p['post_title']))); }
    }
    // ítems de menú de servicios originales: que existan
    foreach ((array) $snap['site']['services'] as $n => $sv) {
        if (!empty($sv['on']) && !empty($sv['post'])) { sc_ed_svc_menu_add((int) $n, (int) $sv['post'], (string) $sv['nombre']); }
    }
    sc_site_reload();
    out(array('ok' => true, 'deleted' => count($ids)));
}

if ($cmd === 'png') {
    $im = imagecreatetruecolor(900, 600);
    for ($y = 0; $y < 600; $y++) { $c = imagecolorallocate($im, 30 + (int) ($y / 4), 70, 160 - (int) ($y / 6)); imageline($im, 0, $y, 899, $y, $c); }
    imagefilledellipse($im, 450, 300, 360, 360, imagecolorallocate($im, 214, 180, 90));
    imagepng($im, $argv[2]);
    out(array('ok' => true));
}
out(array('error' => 'orden desconocida'));
