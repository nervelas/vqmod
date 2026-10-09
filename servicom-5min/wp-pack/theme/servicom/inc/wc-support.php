<?php
if (!defined('ABSPATH')) {
	exit;
}

add_action('after_setup_theme', 'servicom_wc_setup');
function servicom_wc_setup()
{
	add_theme_support('woocommerce');
	add_theme_support('wc-product-gallery-zoom');
	add_theme_support('wc-product-gallery-lightbox');
	add_theme_support('wc-product-gallery-slider');
}

if (!class_exists('WooCommerce')) {
	return;
}

add_action('init', 'servicom_wc_layout', 20);
function servicom_wc_layout()
{
	remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
	remove_action('woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10);
	remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);
	add_action('woocommerce_before_main_content', 'servicom_wc_hero', 5);
	add_action('woocommerce_before_main_content', 'servicom_wc_open', 10);
	add_action('woocommerce_after_main_content', 'servicom_wc_close', 10);
}

add_filter('woocommerce_show_page_title', '__return_false');
add_filter('loop_shop_columns', function () {
	return 3;
});
add_filter('loop_shop_per_page', function () {
	return 12;
});
add_filter('woocommerce_output_related_products_args', function ($args) {
	$args['posts_per_page'] = 3;
	$args['columns'] = 3;
	return $args;
});
add_filter('woocommerce_upsell_display_args', function ($args) {
	$args['posts_per_page'] = 3;
	$args['columns'] = 3;
	return $args;
});

/** Cabecera de tienda/categoría con título y buscador de productos. */
function servicom_wc_hero()
{
	if (!(function_exists('is_shop') && (is_shop() || is_product_taxonomy()))) {
		return;
	}
	echo '<section class="sc-page-hero sc-page-hero--shop"><div class="sc-wrap sc-page-hero__inner">';
	echo '<h1 class="sc-page-hero__title">' . esc_html(woocommerce_page_title(false)) . '</h1>';
	echo '<div class="sc-shop-search">';
	if (function_exists('get_product_search_form')) {
		get_product_search_form();
	}
	echo '</div></div></section>';
}

function servicom_wc_open()
{
	echo '<main id="content" class="sc-main sc-woo"><div class="sc-sec sc-sec--woo"><div class="sc-wrap">';
}

function servicom_wc_close()
{
	echo '</div></div></main>';
}

/** Enlace del carrito (con contador) para el encabezado. */
function servicom_cart_link()
{
	if (!servicom_has_shop() || !function_exists('wc_get_cart_url')) {
		return '';
	}
	$count = (function_exists('WC') && WC()->cart) ? (int) WC()->cart->get_cart_contents_count() : 0;
	$label = $count === 1 ? 'Ver carrito, 1 artículo' : 'Ver carrito, ' . $count . ' artículos';
	return '<a class="sc-cart" href="' . esc_url(wc_get_cart_url()) . '" aria-label="' . esc_attr($label) . '">'
		. servicom_icon('cart', 22)
		. '<span class="sc-cart__count' . ($count ? '' : ' is-empty') . '">' . (int) $count . '</span></a>';
}

add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
	$fragments['a.sc-cart'] = servicom_cart_link();
	return $fragments;
});

/** Cuerpo: marca páginas de WooCommerce con clase propia. */
add_filter('body_class', function ($c) {
	if (function_exists('is_woocommerce') && (is_woocommerce() || is_cart() || is_checkout() || is_account_page())) {
		$c[] = 'sc-is-woo';
	}
	return $c;
});
