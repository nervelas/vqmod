<?php
if (!defined('ABSPATH')) {
	exit;
}

/** Fuentes críticas por estilo (precarga de los archivos latin). */
function servicom_preload_fonts()
{
	$map = array(
		1 => array('cormorant-latin-500-700.woff2', 'manrope-latin-400-700.woff2'),
		2 => array('fraunces-latin-500-700.woff2', 'inter-latin-400-700.woff2'),
		3 => array('sora-latin-500-700.woff2', 'nunito-latin-400-700.woff2'),
		4 => array('playfair-latin-500-700.woff2', 'lato-latin-400.woff2'),
		5 => array('dmserif-latin-400.woff2', 'dmsans-latin-400-700.woff2'),
	);
	return $map[servicom_style()];
}

add_action('wp_head', 'servicom_head_extras', 1);
function servicom_head_extras()
{
	echo '<script>document.documentElement.className+=" sc-js";</script>' . "\n";
	foreach (servicom_preload_fonts() as $f) {
		echo '<link rel="preload" href="' . esc_url(SERVICOM_URI . '/assets/fonts/' . $f) . '" as="font" type="font/woff2" crossorigin>' . "\n";
	}
}

add_action('wp_enqueue_scripts', 'servicom_enqueue', 9999);
function servicom_enqueue()
{
	$u = SERVICOM_URI . '/assets/';
	$d = SERVICOM_DIR . '/assets/';
	$ver = function ($rel) use ($d) {
		$t = @filemtime($d . $rel);
		return SERVICOM_VER . ($t ? '.' . $t : '');
	};
	$n = servicom_style();

	wp_enqueue_style('servicom-fonts', $u . 'fonts/fonts.css', array(), $ver('fonts/fonts.css'));
	wp_enqueue_style('servicom-base', $u . 'css/base.css', array('servicom-fonts'), $ver('css/base.css'));
	wp_enqueue_style('servicom-style', $u . "css/style-$n.css", array('servicom-base'), $ver("css/style-$n.css"));
	$last = 'servicom-style';
	if (class_exists('WooCommerce')) {
		wp_enqueue_style('servicom-woo', $u . 'css/woocommerce.css', array('servicom-style'), $ver('css/woocommerce.css'));
	}

	wp_enqueue_script('servicom', $u . 'js/theme.js', array(), $ver('js/theme.js'), array('in_footer' => true, 'strategy' => 'defer'));

	// Estilos que no se usan en el frente.
	foreach (array('wp-block-library', 'wp-block-library-theme', 'global-styles', 'classic-theme-styles') as $h) {
		wp_dequeue_style($h);
		wp_deregister_style($h);
	}
	wp_dequeue_script('wp-embed');
	wp_deregister_script('wp-embed');
}
