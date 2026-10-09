<?php
/**
 * Servicom builder: piezas que deben estar activas en el sitio publicado
 * (shortcodes propios y hooks de tienda). Se carga desde loader.php.
 */
if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('sc_builder_val')) {
	/** Valor de negocio: sc_biz() del mu-plugin o theme_mod sc_<clave>. */
	function sc_builder_val($key)
	{
		if (function_exists('sc_biz')) {
			return (string) sc_biz($key, '');
		}
		return (string) get_theme_mod('sc_' . $key, '');
	}
}

/** [sc_wa_btn texto="" mensaje="" tipo="boton|enlace"] enlace de WhatsApp con el número del Personalizador. */
add_shortcode('sc_wa_btn', function ($atts) {
	$a = shortcode_atts(array('texto' => '', 'mensaje' => '', 'tipo' => 'boton'), $atts, 'sc_wa_btn');
	$num = preg_replace('/\D+/', '', sc_builder_val('whatsapp'));
	if ($num === '') {
		return '';
	}
	$msg = trim((string) $a['mensaje']);
	if ($msg === '') {
		$msg = sc_builder_val('whatsapp_msg');
	}
	$url = 'https://wa.me/' . $num . ($msg !== '' ? '?text=' . rawurlencode($msg) : '');
	$txt = trim((string) $a['texto']);
	if ($txt === '') {
		$txt = ($a['tipo'] === 'enlace') ? '+' . $num : 'WhatsApp';
	}
	if ($a['tipo'] === 'enlace') {
		return '<a class="sc-link-wa" href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($txt) . '</a>';
	}
	return '<div class="elementor-button-wrapper"><a class="elementor-button elementor-button-link elementor-size-md sc-wa-link" href="' . esc_url($url)
		. '" target="_blank" rel="noopener noreferrer"><span class="elementor-button-content-wrapper"><span class="elementor-button-text">' . esc_html($txt) . '</span></span></a></div>';
});

/** [sc_product_search] buscador de productos (usa el formulario de WooCommerce si existe). */
add_shortcode('sc_product_search', function () {
	if (function_exists('get_product_search_form')) {
		return get_product_search_form(false);
	}
	$en = (strpos((string) get_locale(), 'en') === 0);
	return '<form role="search" method="get" class="woocommerce-product-search sc-product-search" action="' . esc_url(home_url('/')) . '">'
		. '<label class="screen-reader-text" for="sc-ps">' . ($en ? 'Search products' : 'Buscar productos') . '</label>'
		. '<input type="search" id="sc-ps" class="search-field" placeholder="' . ($en ? 'Search products…' : 'Buscar productos…') . '" value="' . esc_attr(get_search_query()) . '" name="s">'
		. '<button type="submit" class="sc-btn sc-btn--primary">' . ($en ? 'Search' : 'Buscar') . '</button>'
		. '<input type="hidden" name="post_type" value="product"></form>';
});

/** Nota de entrega visible en el checkout (texto en la opción sc_delivery_note). */
add_action('woocommerce_before_checkout_form', function () {
	$n = trim((string) get_option('sc_delivery_note', ''));
	if ($n !== '') {
		echo '<div class="sc-delivery-note">' . nl2br(esc_html($n)) . '</div>';
	}
}, 5);

/** Contact Form 7: campo honeypot propio (sc_website) tratado como spam si viene lleno. */
add_filter('wpcf7_spam', function ($spam) {
	if (!empty($_POST['sc_website'])) {
		return true;
	}
	return $spam;
});
