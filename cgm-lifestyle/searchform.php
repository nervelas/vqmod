<?php
/**
 * Formulario de búsqueda.
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$unique = wp_unique_id( 'cgm-search-' );
?>
<form role="search" method="get" class="cgm-searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $unique ); ?>"><?php esc_html_e( 'Buscar:', 'cgm-lifestyle' ); ?></label>
	<input type="search" id="<?php echo esc_attr( $unique ); ?>" class="cgm-searchform__input" placeholder="<?php esc_attr_e( 'Buscar productos…', 'cgm-lifestyle' ); ?>" value="<?php echo get_search_query(); ?>" name="s">
	<button type="submit" class="cgm-searchform__btn" aria-label="<?php esc_attr_e( 'Buscar', 'cgm-lifestyle' ); ?>"><?php echo cgm_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
</form>
