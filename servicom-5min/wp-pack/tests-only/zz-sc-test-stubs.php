<?php
/**
 * SOLO PARA PRUEBAS - NO EMPAQUETAR.
 * Se instala en wp-content/mu-plugins del sitio de prueba con prefijo "zz-" para
 * cargar DESPUÉS de servicom-core.php. Todo está protegido con function_exists /
 * shortcode_exists: si el mu-plugin real (W2a) ya lo ofrece, no se toca.
 *  - carga las piezas de tiempo de ejecución del constructor si nadie lo hizo
 *  - registra un registro de correos (wp-content/sc-mail.log) en lugar de enviar
 */
if (!defined('ABSPATH')) {
	exit;
}
$sc_b = WPMU_PLUGIN_DIR . '/servicom-core/includes/builder/loader.php';
if (!defined('SC_BUILDER_LOADED') && is_readable($sc_b)) {
	require_once $sc_b;
}

// Correo: no se envía; se anota para poder verificar.
add_filter('pre_wp_mail', function ($r, $a) {
	@file_put_contents(WP_CONTENT_DIR . '/sc-mail.log', wp_json_encode(array('to' => $a['to'], 'subject' => $a['subject'], 'message' => $a['message'], 'headers' => $a['headers'])) . "\n", FILE_APPEND);
	return true;
}, 5, 2);

if (!function_exists('sc_biz')) {
	function sc_biz($key, $default = '')
	{
		$v = get_theme_mod('sc_' . $key, null);
		return ($v === null || $v === '') ? $default : $v;
	}
}
if (!function_exists('sc_set_mode')) {
	function sc_set_mode($m)
	{
		update_option('sc_mode', $m);
		return true;
	}
}
if (!function_exists('sc_create_client_user')) {
	function sc_create_client_user($email, $name = '')
	{
		$login = sanitize_user(strstr($email, '@', true) ?: 'cliente', true);
		$id = wp_insert_user(array('user_login' => $login . '_' . wp_rand(100, 999), 'user_email' => $email, 'display_name' => $name, 'role' => 'editor', 'user_pass' => wp_generate_password(24)));
		if (is_wp_error($id)) {
			return $id;
		}
		return array('user_id' => $id, 'set_password_url' => '[stub]');
	}
}
if (!function_exists('sc_replace_domain')) {
	function sc_replace_domain($from, $to)
	{
		update_option('home', str_replace($from, $to, get_option('home')));
		update_option('siteurl', str_replace($from, $to, get_option('siteurl')));
		return array('stub' => true);
	}
}
if (!function_exists('sc_selfcheck')) {
	function sc_selfcheck()
	{
		return array('stub' => true, 'urls' => array(home_url('/')), 'problems' => array());
	}
}
add_action('init', function () {
	$map = array(
		'sc_nombre' => function () {
			return esc_html(sc_biz('nombre'));
		},
		'sc_telefono' => function () {
			return esc_html(sc_biz('telefono'));
		},
		'sc_telefono_link' => function () {
			$t = sc_biz('telefono');
			return $t ? '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $t)) . '">' . esc_html($t) . '</a>' : '';
		},
		'sc_whatsapp_link' => function ($a) {
			$n = preg_replace('/\D/', '', sc_biz('whatsapp'));
			$a = shortcode_atts(array('texto' => 'WhatsApp'), $a);
			return $n ? '<a href="https://wa.me/' . $n . '">' . esc_html($a['texto']) . '</a>' : '';
		},
		'sc_correo' => function () {
			$c = sc_biz('correo');
			return $c ? '<a href="mailto:' . esc_attr($c) . '">' . esc_html($c) . '</a>' : '';
		},
		'sc_direccion' => function () {
			return nl2br(esc_html(sc_biz('direccion')));
		},
		'sc_horario' => function () {
			return nl2br(esc_html(sc_biz('horario')));
		},
		'sc_redes' => function () {
			$o = '';
			foreach (array('facebook', 'instagram', 'tiktok', 'youtube', 'x', 'linkedin') as $k) {
				if ($u = sc_biz($k)) {
					$o .= '<a href="' . esc_url($u) . '" rel="noopener">' . esc_html(ucfirst($k)) . '</a> ';
				}
			}
			return $o;
		},
		'sc_mapa_link' => function ($a) {
			$a = shortcode_atts(array('texto' => 'Mapa'), $a);
			$u = sc_biz('mapa_url');
			return $u ? '<a class="sc-link-map" href="' . esc_url($u) . '">' . esc_html($a['texto']) . '</a>' : '';
		},
		'sc_anio' => function () {
			return gmdate('Y');
		},
	);
	foreach ($map as $tag => $fn) {
		if (!shortcode_exists($tag)) {
			add_shortcode($tag, $fn);
		}
	}
}, 1);
