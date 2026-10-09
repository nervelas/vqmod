<?php
/**
 * Tema Servicom. Ligero, sin dependencias, compatible con PHP 8.0.
 * Los datos del negocio se leen con servicom_biz() (sc_biz() del mu-plugin
 * o, si no existe, theme_mod sc_<clave>).
 */
if (!defined('ABSPATH')) {
	exit;
}

define('SERVICOM_VER', '1.0.0');
define('SERVICOM_DIR', get_template_directory());
define('SERVICOM_URI', get_template_directory_uri());

require_once SERVICOM_DIR . '/inc/helpers.php';
require_once SERVICOM_DIR . '/inc/icons.php';
require_once SERVICOM_DIR . '/inc/enqueue.php';
require_once SERVICOM_DIR . '/inc/nav-walker.php';
require_once SERVICOM_DIR . '/inc/wc-support.php';

if (!isset($content_width)) {
	$content_width = 1200;
}

add_action('after_setup_theme', 'servicom_setup');
function servicom_setup()
{
	load_theme_textdomain('servicom', SERVICOM_DIR . '/languages');

	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');
	add_theme_support('custom-logo', array(
		'height'      => 120,
		'width'       => 360,
		'flex-height' => true,
		'flex-width'  => true,
	));
	add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets'));
	add_theme_support('responsive-embeds');
	add_theme_support('automatic-feed-links');

	register_nav_menus(array(
		'primary' => 'Menú principal',
		'footer'  => 'Menú del pie',
	));

	add_image_size('sc-card', 640, 480, true);
}

/** Cuerpo: clases de estilo y de elementos flotantes. */
add_filter('body_class', 'servicom_body_class');
function servicom_body_class($classes)
{
	$classes[] = 'sc-style-' . servicom_style();
	if (servicom_show_mobile_bar()) {
		$classes[] = 'sc-has-bar';
	}
	if (servicom_show_float_wa()) {
		$classes[] = 'sc-has-float';
	}
	return $classes;
}

/** Limpieza de cabecera: emoji, embeds y estilos de bloques (no se usan). */
add_action('init', 'servicom_cleanup');
function servicom_cleanup()
{
	remove_action('wp_head', 'print_emoji_detection_script', 7);
	remove_action('admin_print_scripts', 'print_emoji_detection_script');
	remove_action('wp_print_styles', 'print_emoji_styles');
	remove_action('admin_print_styles', 'print_emoji_styles');
	remove_filter('the_content_feed', 'wp_staticize_emoji');
	remove_filter('comment_text_rss', 'wp_staticize_emoji');
	remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
	add_filter('emoji_svg_url', '__return_false');
	remove_action('wp_head', 'wp_oembed_add_discovery_links');
	remove_action('wp_head', 'wp_oembed_add_host_js');
	remove_action('wp_head', 'rsd_link');
	remove_action('wp_head', 'wlwmanifest_link');
	remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
	remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
	remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
	remove_action('wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles');
}

add_filter('tiny_mce_plugins', function ($plugins) {
	return is_array($plugins) ? array_diff($plugins, array('wpemoji')) : array();
});
add_filter('wp_resource_hints', function ($urls, $type) {
	if ('dns-prefetch' === $type) {
		$urls = array_filter((array) $urls, function ($u) {
			return !(is_string($u) && str_contains($u, 's.w.org'));
		});
	}
	return $urls;
}, 10, 2);

/** Elementor: las fuentes las pone el tema (autoalojadas), nada de Google Fonts por CDN. */
add_filter('elementor/frontend/print_google_fonts', '__return_false');

/** Contenido de extractos más corto y sin "[...]". */
add_filter('excerpt_length', function () {
	return 28;
});
add_filter('excerpt_more', function () {
	return '…';
});

/** Menú de respaldo si no hay menú asignado: páginas de primer nivel. */
function servicom_fallback_menu()
{
	echo '<ul class="sc-menu">';
	wp_list_pages(array('title_li' => '', 'depth' => 1, 'sort_column' => 'menu_order,post_title'));
	echo '</ul>';
}

/** Cambia el texto de lectura "Leer más" y el marcado de paginación. */
add_filter('navigation_markup_template', function () {
	return '<nav class="navigation %1$s" aria-label="%4$s"><div class="nav-links">%3$s</div></nav>';
});
