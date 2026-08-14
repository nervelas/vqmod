<?php
/**
 * Misión, Visión y Filosofía.
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mv = array(
	'mission' => array( 'icon' => 'target', 'title' => cgm_get_option( 'mission_title', 'Misión' ), 'sub' => cgm_get_option( 'mission_sub' ), 'body' => cgm_get_option( 'mission_body' ) ),
	'vision'  => array( 'icon' => 'eye', 'title' => cgm_get_option( 'vision_title', 'Visión' ), 'sub' => cgm_get_option( 'vision_sub' ), 'body' => cgm_get_option( 'vision_body' ) ),
);

$svg = array(
	'target' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.4" fill="currentColor"/></svg>',
	'eye'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>',
	'heart'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.8 8.6c0 5-8.8 10.4-8.8 10.4S3.2 13.6 3.2 8.6A4.6 4.6 0 0 1 12 6.9a4.6 4.6 0 0 1 8.8 1.7z"/></svg>',
);
?>
<section id="mision-vision" class="cgm-section cgm-mvp">
	<div class="cgm-container">
		<div class="cgm-mvp__grid">
			<?php foreach ( $mv as $item ) : ?>
				<article class="cgm-mvp__card reveal">
					<span class="cgm-mvp__icon"><?php echo $svg[ $item['icon'] ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h2 class="cgm-mvp__title"><?php echo esc_html( $item['title'] ); ?></h2>
					<?php if ( $item['sub'] ) : ?>
						<p class="cgm-mvp__sub"><?php echo esc_html( $item['sub'] ); ?></p>
					<?php endif; ?>
					<div class="cgm-rich"><?php echo cgm_format_text( $item['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				</article>
			<?php endforeach; ?>
		</div>

		<article class="cgm-philosophy reveal">
			<span class="cgm-mvp__icon"><?php echo $svg['heart']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<h2 class="cgm-mvp__title"><?php echo esc_html( cgm_get_option( 'philosophy_title', 'Filosofía' ) ); ?></h2>
			<?php if ( cgm_get_option( 'philosophy_sub' ) ) : ?>
				<p class="cgm-mvp__sub"><?php echo esc_html( cgm_get_option( 'philosophy_sub' ) ); ?></p>
			<?php endif; ?>
			<div class="cgm-rich cgm-philosophy__body"><?php echo cgm_format_text( cgm_get_option( 'philosophy_body' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</article>
	</div>
</section>
