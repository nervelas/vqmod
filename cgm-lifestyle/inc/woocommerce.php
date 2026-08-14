<?php
/**
 * Integración de WooCommerce en el tema (presentación).
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Contenedor del tema alrededor del contenido de WooCommerce. */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

add_action( 'woocommerce_before_main_content', 'cgm_woo_wrapper_start', 10 );
function cgm_woo_wrapper_start() {
	echo '<div class="cgm-container cgm-shop"><main id="primary" class="site-main">';
}
add_action( 'woocommerce_after_main_content', 'cgm_woo_wrapper_end', 10 );
function cgm_woo_wrapper_end() {
	echo '</main></div>';
}

/* Productos por fila y por página. */
add_filter( 'loop_shop_columns', function () { return 3; } );
add_filter( 'loop_shop_per_page', function () { return 12; }, 20 );

/* Miga de pan: separador de marca. */
add_filter( 'woocommerce_breadcrumb_defaults', function ( $defaults ) {
	$defaults['delimiter'] = ' <span class="cgm-crumb-sep">/</span> ';
	$defaults['wrap_before'] = '<nav class="woocommerce-breadcrumb cgm-breadcrumb" aria-label="' . esc_attr__( 'Ruta de navegación', 'cgm-lifestyle' ) . '">';
	return $defaults;
} );

/* Fragmento AJAX: actualiza el contador del carrito en el header. */
add_filter( 'woocommerce_add_to_cart_fragments', 'cgm_cart_count_fragment' );
function cgm_cart_count_fragment( $fragments ) {
	ob_start();
	$count = cgm_cart_count();
	?>
	<span class="cgm-cart-count" data-count="<?php echo esc_attr( $count ); ?>"><?php echo esc_html( $count ); ?></span>
	<?php
	$fragments['.cgm-cart-count'] = ob_get_clean();
	return $fragments;
}

/* Botón de ayuda por WhatsApp en la ficha de producto. */
add_action( 'woocommerce_after_add_to_cart_form', 'cgm_product_whatsapp_help', 20 );
function cgm_product_whatsapp_help() {
	$url = cgm_whatsapp_url();
	if ( ! $url || '#' === $url ) {
		return;
	}
	global $product;
	$name = $product ? $product->get_name() : '';
	echo '<a class="cgm-product-help" href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow">'
		. cgm_icon( 'whatsapp' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interno de confianza.
		. '<span>' . esc_html__( '¿Dudas sobre este producto? Escríbenos', 'cgm-lifestyle' ) . '</span></a>';
}

/* Ajusta el número de productos relacionados. */
add_filter( 'woocommerce_output_related_products_args', function ( $args ) {
	$args['posts_per_page'] = 3;
	$args['columns']        = 3;
	return $args;
} );

/* Tienda a ancho completo: quitamos la barra lateral por defecto de WooCommerce. */
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

/* Placeholder de imagen de producto: usa un marcador con la marca CGM. */
add_filter( 'woocommerce_placeholder_img_src', 'cgm_placeholder_img' );
function cgm_placeholder_img( $src ) {
	$custom = CGM_THEME_URI . '/assets/images/product-placeholder.svg';
	return $custom;
}
