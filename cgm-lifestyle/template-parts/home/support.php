<?php
/**
 * Sección Atención Personalizada (CTA WhatsApp).
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$whats = cgm_get_option( 'whatsapp_phone' );
$email = cgm_get_option( 'email' );
?>
<section id="atencion" class="cgm-section cgm-support">
	<div class="cgm-container cgm-support__inner reveal">
		<h2 class="cgm-section-title"><?php echo esc_html( cgm_get_option( 'support_title', 'Atención Personalizada' ) ); ?></h2>
		<?php if ( cgm_get_option( 'support_sub' ) ) : ?>
			<p class="cgm-support__lead"><?php echo esc_html( cgm_get_option( 'support_sub' ) ); ?></p>
		<?php endif; ?>
		<div class="cgm-rich cgm-support__body"><?php echo cgm_format_text( cgm_get_option( 'support_body' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>

		<div class="cgm-support__actions">
			<a class="cgm-btn cgm-btn--whatsapp cgm-btn--lg" href="<?php echo esc_url( cgm_whatsapp_url() ); ?>" target="_blank" rel="noopener nofollow">
				<?php echo cgm_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo esc_html( cgm_get_option( 'support_cta', 'Contactar a un Asesor' ) ); ?>
			</a>
			<div class="cgm-support__contacts">
				<?php if ( $whats ) : ?>
					<span><?php echo cgm_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $whats ); ?></span>
				<?php endif; ?>
				<?php if ( $email ) : ?>
					<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo cgm_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $email ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
