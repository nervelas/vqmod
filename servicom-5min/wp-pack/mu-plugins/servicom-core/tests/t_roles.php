<?php
require __DIR__ . '/lib.php';
$U = $ENV['url']; $seed = json_decode(file_get_contents($ENV['dir'] . '/seed.json'), true);
$cid = (int) $seed['client'];

section('Rol sc_cliente');
$role = get_role('sc_cliente');
ok($role !== null, 'rol existe');
eq(wp_roles()->get_names()['sc_cliente'] ?? '', 'Cliente de Servicom', 'nombre del rol');
$must = ['read', 'upload_files', 'edit_theme_options', 'edit_pages', 'edit_others_pages', 'edit_published_pages', 'publish_pages', 'delete_pages', 'edit_posts', 'edit_others_posts', 'publish_posts', 'edit_published_posts', 'customize', 'sc_edit_site'];
foreach ($must as $c) { ok(user_can($cid, $c), "cliente PUEDE $c"); }
$never = ['manage_options', 'install_plugins', 'activate_plugins', 'edit_plugins', 'delete_plugins', 'update_plugins', 'install_themes', 'switch_themes', 'edit_themes', 'delete_themes', 'update_themes', 'edit_files', 'update_core', 'unfiltered_html', 'unfiltered_upload', 'edit_users', 'create_users', 'delete_users', 'list_users', 'promote_users', 'remove_users', 'import', 'export', 'edit_css', 'manage_network', 'view_woocommerce_reports', 'update_languages'];
foreach ($never as $c) { ok(!user_can($cid, $c), "cliente NO puede $c"); }
ok(user_can(1, 'manage_options') && user_can(1, 'edit_css') && user_can(1, 'activate_plugins'), 'admin técnico conserva todo');
$woo = sc_client_caps(true); $nowoo = sc_client_caps(false);
foreach (['manage_woocommerce', 'edit_products', 'edit_others_products', 'publish_products', 'read_shop_order', 'edit_shop_orders', 'assign_product_terms'] as $c) { ok(in_array($c, $woo, true) && !in_array($c, $nowoo, true), "caps Woo: $c solo con WooCommerce"); }
ok(!in_array('view_woocommerce_reports', $woo, true) && !in_array('manage_options', $woo, true), 'caps Woo sin informes ni manage_options');
ok(sc_is_client($cid) && !sc_is_client(1), 'sc_is_client');
ok(user_can(1, 'sc_edit_site'), 'el administrador también puede editar el sitio (sc_edit_site)');
ok(in_array('sc_edit_site', sc_client_caps(false), true) && in_array('sc_edit_site', sc_client_caps(true), true), 'sc_edit_site forma parte de las capacidades del cliente');

section('sc_create_client_user');
$mails = 0; add_filter('pre_wp_mail', function () use (&$mails) { $mails++; return true; });
$res = sc_create_client_user('nuevo.cliente@ejemplo.test', 'Nuevo Cliente');
ok($res['user_id'] > 0 && $res['reset_url'] !== '', 'crea usuario y devuelve reset_url', json_encode($res));
eq($mails, 0, 'no se envía ningún correo');
$u = get_userdata($res['user_id']);
ok($u && in_array('sc_cliente', $u->roles, true), 'rol sc_cliente asignado');
eq($u->display_name, 'Nuevo Cliente', 'nombre mostrado');
ok(strlen($u->user_pass) > 30, 'tiene contraseña aleatoria fuerte (hash)');
parse_str((string) parse_url($res['reset_url'], PHP_URL_QUERY), $q);
eq($q['action'] ?? '', 'rp', 'reset_url action=rp');
ok(str_starts_with($res['reset_url'], $U . '/wp-login.php?'), 'reset_url apunta a wp-login.php del sitio', $res['reset_url']);
$chk = check_password_reset_key($q['key'], $q['login']);
ok($chk instanceof WP_User && $chk->ID === $res['user_id'], 'la clave de reset es válida');
ok(get_user_meta($res['user_id'], 'sc_client', true) == 1 && (int) get_option('sc_client_user_id') === $res['user_id'], 'sitio marcado con el cliente');
$jar = $ENV['dir'] . '/jar_reset.txt'; @unlink($jar);
$rr = http($res['reset_url'], ['jar' => $jar, 'follow' => true]);
ok($rr['code'] === 200 && str_contains($rr['body'], 'name="pass1"'), 'abrir reset_url muestra el formulario de nueva contraseña');
$again = sc_create_client_user('nuevo.cliente@ejemplo.test', 'Nuevo Cliente');
eq($again['user_id'], $res['user_id'], 'idempotente: mismo usuario');
$adm = sc_create_client_user('admin@servicom.test', 'X');
ok($adm['user_id'] === 0 && !empty($adm['error']), 'no degrada a un administrador');
ok(sc_create_client_user('malo', 'X')['user_id'] === 0, 'correo inválido rechazado');
require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($res['user_id']); update_option('sc_client_user_id', $cid);

section('Panel del cliente (HTTP)');
$jc = login_jar('cliente@bufete.test', 'Cliente-Test-123!', 'cli');
$lg = http($U . '/wp-login.php', ['jar' => $jar . 'x', 'cookie' => 'wordpress_test_cookie=WP%20Cookie%20check', 'post' => ['log' => 'cliente@bufete.test', 'pwd' => 'Cliente-Test-123!', 'redirect_to' => $U . '/wp-admin/', 'testcookie' => '1']]);
ok(in_array($lg['code'], [302], true) && str_contains($lg['headers']['location'] ?? '', 'sc-instrucciones'), 'login del cliente redirige a INSTRUCCIONES', $lg['headers']['location'] ?? '');
$blocked = ['/wp-admin/', '/wp-admin/index.php', '/wp-admin/plugins.php', '/wp-admin/plugin-install.php', '/wp-admin/plugin-editor.php', '/wp-admin/themes.php', '/wp-admin/theme-editor.php', '/wp-admin/theme-install.php', '/wp-admin/widgets.php', '/wp-admin/tools.php', '/wp-admin/import.php', '/wp-admin/export.php', '/wp-admin/options-general.php', '/wp-admin/options-permalink.php', '/wp-admin/users.php', '/wp-admin/user-new.php', '/wp-admin/update-core.php', '/wp-admin/edit-comments.php', '/wp-admin/admin.php?page=elementor', '/wp-admin/admin.php?page=wc-status', '/wp-admin/admin.php?page=wc-addons', '/wp-admin/admin.php?page=wc-settings&tab=general', '/wp-admin/edit.php?post_type=elementor_library', '/wp-admin/site-health.php', '/wp-admin/edit-tags.php?taxonomy=post_tag'];
foreach ($blocked as $p) {
    $r = http($U . $p, ['jar' => $jc]);
    ok($r['code'] === 302 && str_contains($r['headers']['location'] ?? '', 'page=sc-instrucciones'), "bloqueada $p -> INSTRUCCIONES", $r['code'] . ' ' . ($r['headers']['location'] ?? ''));
}
$pg = $seed['ids']['home'];
$allowed = ['/wp-admin/admin.php?page=sc-instrucciones', '/wp-admin/profile.php', '/wp-admin/edit.php?post_type=page', '/wp-admin/edit.php', '/wp-admin/upload.php', '/wp-admin/nav-menus.php', '/wp-admin/customize.php', "/wp-admin/post.php?post=$pg&action=edit", '/wp-admin/post-new.php?post_type=page', '/wp-admin/media-new.php'];
foreach ($allowed as $p) {
    $r = http($U . $p, ['jar' => $jc]);
    $loc = $r['headers']['location'] ?? '';
    ok($r['code'] === 200 || ($r['code'] === 302 && str_contains($loc, 'nav-menus.php')), "permitida $p", (string) $r['code'] . ' ' . $loc);
}
$r = http($U . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=bacs', ['jar' => $jc]);
ok(!str_contains($r['headers']['location'] ?? '', 'sc-instrucciones'), 'wc-settings tab=checkout no se redirige (Woo ausente: la pantalla no existe)', (string) $r['code']);
$r = http($U . '/wp-admin/admin-ajax.php?action=heartbeat', ['jar' => $jc, 'post' => 'action=heartbeat']);
ok($r['code'] !== 302, 'admin-ajax no se redirige');

section('Menú lateral del cliente');
$m = http($U . '/wp-admin/profile.php', ['jar' => $jc])['body'];
preg_match('/<ul[^>]*id="adminmenu".*?<\/ul>\s*<\/div>/s', $m, $mm); $menu = $mm[0] ?? $m;
ok(str_contains($menu, 'INSTRUCCIONES'), 'menú: INSTRUCCIONES presente');
ok(strpos($menu, 'sc-instrucciones') < strpos($menu, 'edit.php?post_type=page'), 'INSTRUCCIONES es el primer ítem');
foreach (['plugins.php', 'tools.php', 'options-general.php', 'users.php', 'theme-editor.php', 'widgets.php', 'edit-comments.php', 'update-core.php', 'elementor'] as $bad) { ok(!str_contains($menu, "href=\"$bad") && !str_contains($menu, "page=$bad"), "menú sin $bad"); }
ok(!preg_match('/href=.themes\.php/', $menu), 'menú sin Temas');
ok(str_contains($menu, 'nav-menus.php') && str_contains($menu, 'customize.php'), 'Apariencia: Menús y Personalizar');
ok(str_contains($menu, 'profile.php'), 'menú: Perfil');
ok(str_contains($menu, 'upload.php') && str_contains($menu, 'post_type=page'), 'menú: Medios y Páginas');
$adm = login_jar('admin_user', 'Adm1n-Test-Pass!', 'adm');
$ma = http($U . '/wp-admin/plugins.php', ['jar' => $adm]);
eq($ma['code'], 200, 'admin técnico entra a plugins.php');
$mad = http($U . '/wp-admin/options-general.php', ['jar' => $adm]);
eq($mad['code'], 200, 'admin técnico entra a Ajustes');
ok(preg_match('/id="adminmenu".*?INSTRUCCIONES/s', $ma['body']) === 1, 'admin también ve INSTRUCCIONES');

section('Barra de administración y escritorio');
$fe = http($U . '/', ['jar' => $jc])['body'];
ok(str_contains($fe, 'wp-admin-bar-my-account'), 'barra: Mi cuenta');
foreach (['wp-admin-bar-new-content', 'wp-admin-bar-comments', 'wp-admin-bar-wp-logo', 'wp-admin-bar-updates'] as $bad) { ok(!str_contains($fe, 'id="' . $bad . '"'), "barra sin $bad"); }
ok(str_contains($fe, 'wp-admin-bar-sc-instrucciones'), 'barra: enlace Cómo editar mi web');
$fa = http($U . '/', ['jar' => $adm])['body'];
ok(str_contains($fa, 'wp-admin-bar-new-content') || str_contains($fa, 'wp-admin-bar-comments'), 'admin conserva su barra completa');
done();
