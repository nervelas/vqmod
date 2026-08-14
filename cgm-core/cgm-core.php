<?php
/**
 * Plugin Name:       CGM Core
 * Plugin URI:        https://cgmlifestyle.com
 * Description:       Funcionalidad central de CGM – Crea Grandes Momentos: opciones globales administrables, slides del hero, combos con descuento, personalización de productos, SEO/Schema e importador de contenido inicial. Diseñado para trabajar junto al tema CGM Lifestyle y WooCommerce.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            CGM Lifestyle
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cgm
 * Domain Path:       /languages
 *
 * @package CGM_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No acceso directo.
}

define( 'CGM_CORE_VERSION', '1.0.0' );
define( 'CGM_CORE_FILE', __FILE__ );
define( 'CGM_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CGM_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'CGM_CORE_OPTION', 'cgm_settings' );

require_once CGM_CORE_DIR . 'includes/helpers.php';
require_once CGM_CORE_DIR . 'includes/class-cgm-settings.php';
require_once CGM_CORE_DIR . 'includes/class-cgm-slides.php';
require_once CGM_CORE_DIR . 'includes/class-cgm-woo.php';
require_once CGM_CORE_DIR . 'includes/class-cgm-seo.php';
require_once CGM_CORE_DIR . 'includes/class-cgm-importer.php';

if ( is_admin() ) {
	require_once CGM_CORE_DIR . 'admin/class-cgm-admin.php';
}

/**
 * Arranque del plugin.
 */
function cgm_core_bootstrap() {
	load_plugin_textdomain( 'cgm', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	CGM_Settings::instance();
	CGM_Slides::instance();
	CGM_SEO::instance();
	CGM_Importer::instance();

	// Funcionalidad que depende de WooCommerce.
	if ( class_exists( 'WooCommerce' ) ) {
		CGM_Woo::instance();
	}

	if ( is_admin() ) {
		CGM_Admin::instance();
	}
}
add_action( 'plugins_loaded', 'cgm_core_bootstrap' );

/**
 * Aviso si WooCommerce no está activo (la tienda lo requiere).
 */
function cgm_core_admin_notices() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	if ( ! class_exists( 'WooCommerce' ) ) {
		echo '<div class="notice notice-warning"><p><strong>CGM Core:</strong> ' .
			esc_html__( 'WooCommerce no está activo. Instálalo y actívalo para habilitar la tienda, el carrito, los combos y la personalización de productos.', 'cgm' ) .
			'</p></div>';
	}
}
add_action( 'admin_notices', 'cgm_core_admin_notices' );

/**
 * Activación: registra CPT + reglas de reescritura y guarda flag de bienvenida.
 */
function cgm_core_activate() {
	require_once CGM_CORE_DIR . 'includes/class-cgm-slides.php';
	CGM_Slides::register_cpt();
	flush_rewrite_rules();
	if ( false === get_option( 'cgm_core_installed' ) ) {
		add_option( 'cgm_core_installed', CGM_CORE_VERSION );
	}
}
register_activation_hook( __FILE__, 'cgm_core_activate' );

/**
 * Desactivación: limpia reglas de reescritura.
 */
function cgm_core_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'cgm_core_deactivate' );
