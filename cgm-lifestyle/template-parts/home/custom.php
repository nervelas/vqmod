<?php
/**
 * Sección Personalizados.
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Enlaza al producto/categoría de personalizados si existe.
$link = home_url( '/' );
$term = get_term_by( 'slug', 'personalizados', 'product_cat' );
if ( $term && ! is_wp_error( $term ) ) {
	$link = get_term_link( $term );
}
?>
<section id="personalizados" class="cgm-section cgm-custom">
	<div class="cgm-container cgm-custom__inner reveal">
		<span class="cgm-eyebrow"><?php esc_html_e( 'Personalizados', 'cgm-lifestyle' ); ?></span>
		<h2 class="cgm-section-title"><?php echo esc_html( cgm_get_option( 'custom_title', 'Hazlo único' ) ); ?></h2>
		<div class="cgm-rich cgm-custom__body"><?php echo cgm_format_text( cgm_get_option( 'custom_body' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<a class="cgm-btn cgm-btn--primary" href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( cgm_get_option( 'custom_cta', 'Personalizar mi Producto' ) ); ?></a>
	</div>
</section>
