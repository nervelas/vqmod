<?php
/**
 * Sección de confianza: Envíos, Cambios, Pago Seguro (con logos) y Compra Responsable.
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_url = function ( $slug ) {
	$p = get_page_by_path( $slug );
	return $p ? get_permalink( $p ) : '';
};

$blocks = array(
	array(
		'id'    => 'envios',
		'title' => cgm_get_option( 'shipping_title', 'Políticas de Envío' ),
		'body'  => cgm_get_option( 'shipping_body' ),
		'url'   => $page_url( 'politicas-de-envio' ),
	),
	array(
		'id'    => 'cambios',
		'title' => cgm_get_option( 'returns_title', 'Cambios Garantizados' ),
		'body'  => cgm_get_option( 'returns_body' ),
		'url'   => $page_url( 'cambios-garantizados' ),
	),
	array(
		'id'    => 'responsable',
		'title' => cgm_get_option( 'responsible_title', 'Compra Responsable' ),
		'body'  => cgm_get_option( 'responsible_body' ),
		'url'   => $page_url( 'compra-responsable' ),
	),
);
?>
<section id="confianza" class="cgm-section cgm-trust">
	<div class="cgm-container">
		<div class="cgm-trust__grid">
			<?php foreach ( $blocks as $b ) : ?>
				<article class="cgm-trust__card reveal">
					<h2 class="cgm-trust__title"><?php echo esc_html( $b['title'] ); ?></h2>
					<div class="cgm-rich cgm-trust__body"><?php echo cgm_format_text( $b['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<?php if ( $b['url'] ) : ?>
						<a class="cgm-link" href="<?php echo esc_url( $b['url'] ); ?>"><?php esc_html_e( 'Leer más', 'cgm-lifestyle' ); ?> →</a>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>

			<article class="cgm-trust__card cgm-trust__payment reveal">
				<h2 class="cgm-trust__title"><?php echo esc_html( cgm_get_option( 'payment_title', 'Pago Seguro' ) ); ?></h2>
				<?php if ( cgm_get_option( 'payment_sub' ) ) : ?>
					<p class="cgm-trust__sub"><?php echo esc_html( cgm_get_option( 'payment_sub' ) ); ?></p>
				<?php endif; ?>
				<div class="cgm-rich cgm-trust__body"><?php echo cgm_format_text( cgm_get_option( 'payment_body' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>

				<div class="cgm-paylogos" aria-label="<?php esc_attr_e( 'Métodos de pago aceptados', 'cgm-lifestyle' ); ?>">
					<?php if ( cgm_get_option( 'pay_visa' ) ) : ?>
						<span class="cgm-paylogo cgm-paylogo--visa">VISA</span>
					<?php endif; ?>
					<?php if ( cgm_get_option( 'pay_mastercard' ) ) : ?>
						<span class="cgm-paylogo cgm-paylogo--mc"><span class="mc-c1"></span><span class="mc-c2"></span>Mastercard</span>
					<?php endif; ?>
					<?php if ( cgm_get_option( 'pay_visalink' ) ) : ?>
						<span class="cgm-paylogo cgm-paylogo--visalink">VisaLink</span>
					<?php endif; ?>
					<?php if ( cgm_get_option( 'pay_deposit' ) ) : ?>
						<span class="cgm-paylogo cgm-paylogo--deposit"><?php esc_html_e( 'Depósito bancario', 'cgm-lifestyle' ); ?></span>
					<?php endif; ?>
				</div>
				<p class="cgm-paylogos__note"><?php esc_html_e( 'Los métodos de pago se procesan de forma segura al finalizar tu compra.', 'cgm-lifestyle' ); ?></p>
			</article>
		</div>
	</div>
</section>
