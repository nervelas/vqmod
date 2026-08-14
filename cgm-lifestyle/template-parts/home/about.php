<?php
/**
 * Sección ¿Quiénes Somos?
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section id="quienes-somos" class="cgm-section cgm-about">
	<div class="cgm-container">
		<?php cgm_section_header( cgm_get_option( 'about_title', '¿Quiénes Somos?' ), cgm_get_option( 'about_sub' ) ); ?>
		<div class="cgm-about__inner reveal">
			<?php if ( cgm_get_option( 'about_heading' ) ) : ?>
				<h3 class="cgm-about__concept"><?php echo esc_html( cgm_get_option( 'about_heading' ) ); ?></h3>
			<?php endif; ?>
			<div class="cgm-about__body cgm-rich">
				<?php echo cgm_format_text( cgm_get_option( 'about_body' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cgm_format_text escapa. ?>
			</div>
		</div>
	</div>
</section>
