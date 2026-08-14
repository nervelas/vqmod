<?php
/**
 * Sección Accesorios.
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$term = get_term_by( 'slug', 'accesorios', 'product_cat' );
$link = ( $term && ! is_wp_error( $term ) ) ? get_term_link( $term ) : home_url( '/' );
?>
<section id="accesorios" class="cgm-section cgm-accessories">
	<div class="cgm-container cgm-accessories__inner reveal">
		<div>
			<span class="cgm-eyebrow"><?php esc_html_e( 'Accesorios', 'cgm-lifestyle' ); ?></span>
			<h2 class="cgm-section-title"><?php echo esc_html( cgm_get_option( 'acc_title', 'Completa tu experiencia' ) ); ?></h2>
			<div class="cgm-rich"><?php echo cgm_format_text( cgm_get_option( 'acc_body' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<a class="cgm-btn cgm-btn--outline" href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'Ver accesorios', 'cgm-lifestyle' ); ?></a>
		</div>
	</div>
</section>
