<?php
/**
 * CGM Lifestyle - bootstrap del tema.
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CGM_THEME_VERSION', '1.0.0' );
define( 'CGM_THEME_DIR', get_template_directory() );
define( 'CGM_THEME_URI', get_template_directory_uri() );

require_once CGM_THEME_DIR . '/inc/theme-setup.php';
require_once CGM_THEME_DIR . '/inc/template-tags.php';

if ( class_exists( 'WooCommerce' ) ) {
	require_once CGM_THEME_DIR . '/inc/woocommerce.php';
}
