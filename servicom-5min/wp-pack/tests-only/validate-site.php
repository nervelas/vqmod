<?php
/**
 * SOLO PARA PRUEBAS. Valida un sitio construido: php validate-site.php <siteDir> [--expect-products=N] [--expect-services=N]
 * Comprueba _elementor_data (JSON válido, ids únicos de 7 hex, elType/widgetType permitidos, sin html),
 * duplicados por _sc_key, conteos y opciones clave. Sale con 1 si hay problemas.
 */
$dir = rtrim($argv[1] ?? '', '/');
define('SC_PROVISIONING', true);
define('WP_USE_THEMES', false);
chdir($dir);
require $dir . '/wp-load.php';
$opt = array();
foreach (array_slice($argv, 2) as $a) {
	if (preg_match('/^--([a-z-]+)=(.*)$/', $a, $m)) {
		$opt[$m[1]] = $m[2];
	}
}
$bad = array();
$info = array();
$pages = get_posts(array('post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_elementor_edit_mode', 'meta_value' => 'builder'));
$allowed = SC_El::ALLOWED_WIDGETS;
foreach ($pages as $p) {
	$raw = get_post_meta($p->ID, '_elementor_data', true);
	$d = json_decode((string) $raw, true);
	if (!is_array($d) || !$d) {
		$bad[] = "page {$p->ID} {$p->post_name}: JSON inválido/vacío";
		continue;
	}
	$errs = SC_El::validate($d);
	foreach ($errs as $e) {
		$bad[] = "page {$p->ID} {$p->post_name}: $e";
	}
	if (stripos($raw, '"widgetType":"html"') !== false) {
		$bad[] = "page {$p->ID}: widget html";
	}
	foreach (array('_elementor_edit_mode' => 'builder', '_elementor_template_type' => 'wp-page') as $k => $v) {
		if (get_post_meta($p->ID, $k, true) !== $v) {
			$bad[] = "page {$p->ID}: meta $k";
		}
	}
	// los datos del negocio nunca van en claro: no debe aparecer el teléfono/correo literal
	foreach (array('2222-3333', 'contacto@example.test', '50255550000') as $lit) {
		if (strpos($raw, $lit) !== false) {
			$bad[] = "page {$p->ID}: dato de negocio literal '$lit' (debe ir por shortcode)";
		}
	}
	$info['pages'][] = $p->post_name;
}
global $wpdb;
$dups = $wpdb->get_results("SELECT meta_value k, COUNT(*) c FROM {$wpdb->postmeta} WHERE meta_key='_sc_key' GROUP BY meta_value HAVING c>1");
foreach ($dups as $d) {
	$bad[] = "duplicado _sc_key {$d->k} x{$d->c}";
}
$tdups = $wpdb->get_results("SELECT meta_value k, COUNT(*) c FROM {$wpdb->termmeta} WHERE meta_key='_sc_key' GROUP BY meta_value HAVING c>1");
foreach ($tdups as $d) {
	$bad[] = "duplicado término _sc_key {$d->k}";
}
$cnt = function ($type) use ($wpdb) {
	return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s AND post_status NOT IN ('trash','auto-draft')", $type));
};
$info['pages_n'] = $cnt('page');
$info['attachments'] = $cnt('attachment');
$info['products'] = $cnt('product');
$info['menu_items'] = $cnt('nav_menu_item');
$info['kits'] = $cnt('elementor_library');
if ($cnt('post') > 0) {
	$bad[] = 'quedan entradas de blog (demo)';
}
if ($info['kits'] !== 1) {
	$bad[] = 'kits=' . $info['kits'];
}
if (isset($opt['expect-products']) && $info['products'] !== (int) $opt['expect-products']) {
	$bad[] = "productos {$info['products']} != {$opt['expect-products']}";
}
if (isset($opt['expect-services'])) {
	$svc = count((array) get_option('sc_service_pages', array()));
	if ($svc !== (int) $opt['expect-services']) {
		$bad[] = "páginas de servicio $svc != {$opt['expect-services']}";
	}
}
foreach (array('sc_plan', 'sc_mode', 'sc_preview_key', 'sc_page_ids') as $o) {
	if (get_option($o) === false || get_option($o) === '') {
		$bad[] = "opción $o vacía";
	}
}
if (get_option('show_on_front') !== 'page' || !get_option('page_on_front')) {
	$bad[] = 'portada estática no configurada';
}
if (get_option('blog_public') !== '0' && get_option('sc_mode') !== 'published') {
	$bad[] = 'blog_public != 0';
}
if (get_option('permalink_structure') !== '/%postname%/') {
	$bad[] = 'permalinks';
}
if (get_option('stylesheet') !== 'servicom') {
	$bad[] = 'tema';
}
$locs = get_theme_mod('nav_menu_locations');
if (empty($locs['primary'])) {
	$bad[] = 'menú primary sin asignar';
}
$u = get_user_by('login', 'duenio_sitio');
if (!$u || user_can($u, 'manage_options') === false) {
	$bad[] = 'usuario admin';
}
if (get_user_by('login', 'admin')) {
	$bad[] = 'existe usuario admin';
}
$log = WP_CONTENT_DIR . '/debug.log';
if (is_file($log) && filesize($log) > 0) {
	$bad[] = 'debug.log no vacío: ' . substr(trim(preg_replace('/\s+/', ' ', file_get_contents($log, false, null, 0, 600))), 0, 400);
}
echo json_encode(array('ok' => !$bad, 'info' => $info, 'problems' => $bad), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
exit($bad ? 1 : 0);
