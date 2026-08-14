<?php
/**
 * Barra de badges de confianza (5 tarjetas administrables).
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$badges = array();
for ( $i = 1; $i <= 5; $i++ ) {
	$title = cgm_get_option( "badge_{$i}_title" );
	if ( ! $title ) {
		continue;
	}
	$badges[] = array(
		'icon'  => absint( cgm_get_option( "badge_{$i}_icon", 0 ) ),
		'title' => $title,
		'sub'   => cgm_get_option( "badge_{$i}_sub" ),
	);
}
if ( empty( $badges ) ) {
	return;
}
?>
<section class="cgm-badges" aria-label="<?php esc_attr_e( 'Beneficios', 'cgm-lifestyle' ); ?>">
	<div class="cgm-container cgm-badges__grid">
		<?php foreach ( $badges as $b ) : ?>
			<div class="cgm-badge reveal">
				<?php if ( $b['icon'] ) : ?>
					<div class="cgm-badge__icon">
						<?php echo wp_get_attachment_image( $b['icon'], array( 96, 96 ), false, array( 'alt' => $b['title'], 'loading' => 'lazy', 'decoding' => 'async' ) ); ?>
					</div>
				<?php endif; ?>
				<div class="cgm-badge__text">
					<h3 class="cgm-badge__title"><?php echo esc_html( $b['title'] ); ?></h3>
					<?php if ( $b['sub'] ) : ?>
						<p class="cgm-badge__sub"><?php echo esc_html( $b['sub'] ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>
