<?php
/**
 * Sección Combos: arma tu combo ideal (descuento automático).
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$discount = (float) cgm_get_option( 'combo_discount', 0 );
$enabled  = (int) cgm_get_option( 'combo_enabled', 0 );
$shop     = class_exists( 'WooCommerce' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' );
?>
<section id="combos" class="cgm-section cgm-combos">
	<div class="cgm-container cgm-combos__inner reveal">
		<div class="cgm-combos__text">
			<span class="cgm-eyebrow"><?php esc_html_e( 'Combos', 'cgm-lifestyle' ); ?></span>
			<h2 class="cgm-section-title"><?php echo esc_html( cgm_get_option( 'combo_title', 'Arma tu Combo Ideal' ) ); ?></h2>
			<div class="cgm-rich"><?php echo cgm_format_text( cgm_get_option( 'combo_body' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php if ( $enabled && $discount > 0 ) : ?>
				<p class="cgm-combos__badge"><?php echo esc_html( sprintf( __( 'Ahorra %s%% automáticamente al combinar termo + pachón.', 'cgm-lifestyle' ), rtrim( rtrim( (string) $discount, '0' ), '.' ) ) ); ?></p>
			<?php endif; ?>
			<a class="cgm-btn cgm-btn--primary" href="<?php echo esc_url( $shop ); ?>"><?php echo esc_html( cgm_get_option( 'combo_cta', 'Crear mi Combo' ) ); ?></a>
		</div>
		<ul class="cgm-combos__steps">
			<li><span>1</span><?php esc_html_e( 'Elige cualquier termo', 'cgm-lifestyle' ); ?></li>
			<li><span>2</span><?php esc_html_e( 'Agrega cualquier pachón', 'cgm-lifestyle' ); ?></li>
			<li><span>3</span><?php esc_html_e( 'Combina colores', 'cgm-lifestyle' ); ?></li>
			<li><span>4</span><?php esc_html_e( 'El descuento se aplica solo', 'cgm-lifestyle' ); ?></li>
		</ul>
	</div>
</section>
