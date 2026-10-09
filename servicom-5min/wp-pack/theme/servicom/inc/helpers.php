<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Dato del negocio. Fuente de verdad: sc_biz() del mu-plugin; si no existe,
 * theme_mod sc_<clave>.
 */
function servicom_biz($key, $default = '')
{
	if (function_exists('sc_biz')) {
		$v = sc_biz($key, $default);
	} else {
		$v = get_theme_mod('sc_' . $key, $default);
	}
	if (is_string($v)) {
		return trim($v);
	}
	if (is_int($v) || is_float($v)) {
		return (string) $v;
	}
	return is_scalar($v) ? (string) $v : $default;
}

function servicom_style()
{
	$n = (int) servicom_biz('style', 1);
	return ($n >= 1 && $n <= 5) ? $n : 1;
}

function servicom_name()
{
	$n = servicom_biz('nombre');
	return $n !== '' ? $n : get_bloginfo('name');
}

function servicom_digits($s)
{
	return preg_replace('/\D+/', '', (string) $s);
}

function servicom_tel_href($tel)
{
	$d = servicom_digits($tel);
	if ($d === '') {
		return '';
	}
	return 'tel:' . (str_starts_with(ltrim((string) $tel), '+') ? '+' : '') . $d;
}

function servicom_wa_url($msg = null)
{
	$d = servicom_digits(servicom_biz('whatsapp'));
	if ($d === '') {
		return '';
	}
	if ($msg === null) {
		$msg = servicom_biz('whatsapp_msg');
	}
	$url = 'https://wa.me/' . $d;
	if ($msg !== '') {
		$url .= '?text=' . rawurlencode($msg);
	}
	return $url;
}

function servicom_map_url()
{
	$u = servicom_biz('mapa_url');
	if ($u !== '' && preg_match('#^https?://#i', $u)) {
		return $u;
	}
	$a = servicom_biz('direccion');
	if ($a !== '') {
		return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(preg_replace('/\s+/', ' ', $a));
	}
	return '';
}

/** Interruptor 1/0 con valor por defecto "activado". */
function servicom_flag($key)
{
	return (string) servicom_biz($key, '1') !== '0';
}

function servicom_show_float_wa()
{
	return servicom_flag('float_wa') && servicom_wa_url() !== '';
}

function servicom_show_mobile_bar()
{
	if (!servicom_flag('mobile_bar')) {
		return false;
	}
	return servicom_tel_href(servicom_biz('telefono')) !== '' || servicom_wa_url() !== '' || servicom_map_url() !== '';
}

function servicom_credit()
{
	$c = servicom_biz('footer_credit');
	return $c !== '' ? $c : 'Sitio creado por Servicom';
}

/** ¿Hay tienda activa? Solo con WooCommerce y plan distinto de "info". */
function servicom_has_shop()
{
	return class_exists('WooCommerce') && get_option('sc_plan', 'tienda') !== 'info';
}

/** ¿La página está construida con Elementor? */
function servicom_is_builder($post_id = 0)
{
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if (!$post_id) {
		return false;
	}
	return get_post_meta($post_id, '_elementor_edit_mode', true) === 'builder';
}

/** Marca (logo o logotipo tipográfico). */
function servicom_brand($class = '')
{
	$name = servicom_name();
	$cls = 'sc-brand' . ($class ? ' ' . $class : '');
	$home = esc_url(home_url('/'));
	if (has_custom_logo()) {
		$id = (int) get_theme_mod('custom_logo');
		$img = wp_get_attachment_image($id, 'full', false, array(
			'class'    => 'sc-brand__img',
			'alt'      => $name,
			'loading'  => 'eager',
			'decoding' => 'async',
			'sizes'    => '180px',
		));
		if ($img) {
			return '<a class="' . esc_attr($cls) . ' sc-brand--logo" href="' . $home . '" rel="home" aria-label="' . esc_attr($name) . ' - Inicio">' . $img . '</a>';
		}
	}
	return '<a class="' . esc_attr($cls) . ' sc-brand--text" href="' . $home . '" rel="home" aria-label="' . esc_attr($name) . ' - Inicio"><span class="sc-brand__name">' . esc_html($name) . '</span></a>';
}

/** Lista de redes configuradas: clave => URL. */
function servicom_socials()
{
	$out = array();
	foreach (array('facebook', 'instagram', 'tiktok', 'youtube', 'x', 'linkedin') as $k) {
		$u = servicom_biz($k);
		if ($u !== '' && preg_match('#^https?://#i', $u)) {
			$out[$k] = $u;
		}
	}
	return $out;
}

function servicom_social_label($k)
{
	$l = array('facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'x' => 'X', 'linkedin' => 'LinkedIn');
	return isset($l[$k]) ? $l[$k] : ucfirst($k);
}

function servicom_socials_html($class = 'sc-social')
{
	$s = servicom_socials();
	if (!$s) {
		return '';
	}
	$h = '<ul class="' . esc_attr($class) . '">';
	foreach ($s as $k => $u) {
		$h .= '<li><a href="' . esc_url($u) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr(servicom_social_label($k)) . '">' . servicom_icon($k) . '</a></li>';
	}
	return $h . '</ul>';
}

/** Texto multilínea seguro. */
function servicom_multiline($s)
{
	return nl2br(esc_html($s));
}
