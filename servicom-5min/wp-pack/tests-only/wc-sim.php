<?php
/**
 * ############################################################################
 *  SOLO PARA PRUEBAS - NO EMPAQUETAR - NO ES WOOCOMMERCE
 * ----------------------------------------------------------------------------
 *  Simulador mínimo de WooCommerce para el sandbox: CPT product, taxonomía
 *  product_cat, shortcodes [products] [product_categories] [woocommerce_cart]
 *  [woocommerce_checkout] [woocommerce_my_account], get_product_search_form(),
 *  y una "página de tienda" que añade el listado tras el contenido (como el
 *  archivo real). Marcado con WC_SIM y la clase WooCommerce (vacía).
 *  NO define WC_Product_Simple: el constructor usa su ruta de respaldo (posts/meta).
 * ############################################################################
 */
if (!defined('ABSPATH')) {
	exit;
}
define('WC_SIM', true);
if (!defined('WC_VERSION')) {
	define('WC_VERSION', '0.0-sim');
}
if (!class_exists('WooCommerce', false)) {
	class WooCommerce
	{
	}
}

add_action('init', function () {
	register_post_type('product', array(
		'label' => 'Productos (sim)', 'public' => true, 'has_archive' => 'tienda-sim', 'rewrite' => array('slug' => 'producto'),
		'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
	));
	register_taxonomy('product_cat', 'product', array('label' => 'Categorías de producto (sim)', 'hierarchical' => true, 'public' => true, 'rewrite' => array('slug' => 'categoria-producto')));
}, 5);

if (!function_exists('wc_get_page_id')) {
	function wc_get_page_id($p)
	{
		$p = ($p === 'account') ? 'myaccount' : $p;
		return (int) get_option('woocommerce_' . $p . '_page_id', 0);
	}
	function get_woocommerce_currency_symbol()
	{
		return get_option('woocommerce_currency', 'GTQ') === 'GTQ' ? 'Q' : '$';
	}
	function is_shop()
	{
		return is_page() && (int) get_queried_object_id() === wc_get_page_id('shop');
	}
	function is_cart()
	{
		return is_page() && (int) get_queried_object_id() === wc_get_page_id('cart');
	}
	function is_checkout()
	{
		return is_page() && (int) get_queried_object_id() === wc_get_page_id('checkout');
	}
	function is_account_page()
	{
		return is_page() && (int) get_queried_object_id() === wc_get_page_id('myaccount');
	}
	function is_woocommerce()
	{
		return is_shop() || is_cart() || is_checkout() || is_account_page();
	}
	function get_product_search_form($echo = true)
	{
		$f = '<form role="search" method="get" class="woocommerce-product-search" action="' . esc_url(home_url('/')) . '"><label class="screen-reader-text" for="woocommerce-product-search-field-0">Buscar:</label>'
			. '<input type="search" id="woocommerce-product-search-field-0" class="search-field" placeholder="Buscar productos&hellip;" value="' . esc_attr(get_search_query()) . '" name="s" />'
			. '<button type="submit" value="Buscar">Buscar</button><input type="hidden" name="post_type" value="product" /></form>';
		if ($echo) {
			echo $f;
			return '';
		}
		return $f;
	}
}

function sc_wcsim_products($atts = array())
{
	$a = shortcode_atts(array('limit' => 12, 'columns' => 4, 'orderby' => 'date', 'order' => 'DESC'), (array) $atts);
	$q = new WP_Query(array('post_type' => 'product', 'posts_per_page' => (int) $a['limit'], 'orderby' => $a['orderby'], 'order' => $a['order'], 'no_found_rows' => true));
	$o = '<div class="woocommerce columns-' . (int) $a['columns'] . '"><ul class="products columns-' . (int) $a['columns'] . '">';
	while ($q->have_posts()) {
		$q->the_post();
		$id = get_the_ID();
		$price = get_post_meta($id, '_price', true);
		$o .= '<li class="product type-product post-' . $id . ' status-publish instock"><a href="' . esc_url(get_permalink()) . '" class="woocommerce-LoopProduct-link woocommerce-loop-product__link">'
			. (has_post_thumbnail() ? get_the_post_thumbnail($id, 'medium_large', array('class' => 'attachment-woocommerce_thumbnail size-woocommerce_thumbnail')) : '<img class="woocommerce-placeholder" alt="" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7">')
			. '<h2 class="woocommerce-loop-product__title">' . esc_html(get_the_title()) . '</h2>'
			. '<span class="price"><span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">' . get_woocommerce_currency_symbol() . '</span>' . esc_html(number_format((float) $price, 2)) . '</bdi></span></span></a>'
			. '<a href="#" class="button product_type_simple add_to_cart_button">Añadir al carrito</a></li>';
	}
	wp_reset_postdata();
	return $o . '</ul></div>';
}
add_shortcode('products', 'sc_wcsim_products');
add_shortcode('product_categories', function () {
	$o = '<div class="woocommerce columns-4"><ul class="products columns-4">';
	foreach (get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false)) as $t) {
		if (is_wp_error($t)) {
			continue;
		}
		$o .= '<li class="product-category product"><a href="#"><h2 class="woocommerce-loop-category__title">' . esc_html($t->name) . ' <mark class="count">(' . (int) $t->count . ')</mark></h2></a></li>';
	}
	return $o . '</ul></div>';
});
add_shortcode('woocommerce_cart', function () {
	return '<div class="woocommerce"><p class="cart-empty woocommerce-info">Su carrito está vacío. [SIM]</p></div>';
});
add_shortcode('woocommerce_checkout', function () {
	ob_start();
	do_action('woocommerce_before_checkout_form', null);
	return '<div class="woocommerce">' . ob_get_clean() . '<form class="woocommerce-checkout checkout"><h3>Detalles de facturación [SIM]</h3></form></div>';
});
add_shortcode('woocommerce_my_account', function () {
	return '<div class="woocommerce"><div class="woocommerce-MyAccount-content"><p>Acceso / registro [SIM]</p></div></div>';
});

/** La página de tienda real muestra el listado de productos tras el contenido de la página. */
add_filter('the_content', function ($c) {
	if (is_shop() && in_the_loop()) {
		$c .= '[product_categories]' . '[products limit="100"]';
		return do_shortcode($c);
	}
	return $c;
}, 12);
