<?php
/**
 * Hero / carrusel principal. Slides administrables (CPT cgm_slide).
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$slides = class_exists( 'CGM_Slides' ) ? CGM_Slides::get_active_slides() : array();

if ( empty( $slides ) ) {
	// Fallback: hero simple con el concepto de marca (nunca queda vacío).
	?>
	<section class="cgm-hero cgm-hero--empty">
		<div class="cgm-container cgm-hero__fallback">
			<p class="cgm-hero__sub"><?php echo esc_html( cgm_get_option( 'brand_tagline', 'Crea Grandes Momentos' ) ); ?></p>
			<h1 class="cgm-hero__title"><?php esc_html_e( 'Termos, pachones y accesorios para acompañarte', 'cgm-lifestyle' ); ?></h1>
			<?php if ( class_exists( 'WooCommerce' ) ) : ?>
				<a class="cgm-btn cgm-btn--primary" href="<?php echo esc_url( cgm_shop_url() ); ?>"><?php esc_html_e( 'Comprar ahora', 'cgm-lifestyle' ); ?></a>
			<?php endif; ?>
		</div>
	</section>
	<?php
	return;
}

$multiple = count( $slides ) > 1;
?>
<section class="cgm-hero" aria-roledescription="carousel" aria-label="<?php esc_attr_e( 'Destacados', 'cgm-lifestyle' ); ?>" data-autoplay="6000">
	<div class="cgm-hero__track">
		<?php foreach ( $slides as $index => $slide ) : ?>
			<?php
			$img_id     = $slide['image_id'];
			$img_mob_id = $slide['image_mobile_id'] ? $slide['image_mobile_id'] : $img_id;
			$is_first   = ( 0 === $index );
			?>
			<div class="cgm-slide<?php echo $is_first ? ' is-active' : ''; ?>" role="group" aria-roledescription="slide" aria-label="<?php echo esc_attr( sprintf( __( '%1$d de %2$d', 'cgm-lifestyle' ), $index + 1, count( $slides ) ) ); ?>"<?php echo $is_first ? '' : ' aria-hidden="true"'; ?>>
				<?php if ( $img_id ) : ?>
					<picture class="cgm-slide__media">
						<?php if ( $img_mob_id && $img_mob_id !== $img_id ) : ?>
							<source media="(max-width: 768px)" srcset="<?php echo esc_url( wp_get_attachment_image_url( $img_mob_id, 'cgm-hero-mobile' ) ); ?>">
						<?php endif; ?>
						<?php
						echo wp_get_attachment_image(
							$img_id,
							'cgm-hero',
							false,
							array(
								'class'         => 'cgm-slide__img',
								'loading'       => $is_first ? 'eager' : 'lazy',
								'fetchpriority' => $is_first ? 'high' : 'low',
								'decoding'      => 'async',
								'alt'           => $slide['heading'] ? $slide['heading'] : get_the_title( $slide['id'] ),
							)
						);
						?>
					</picture>
				<?php endif; ?>

				<div class="cgm-slide__overlay"></div>
				<div class="cgm-container cgm-slide__content">
					<?php if ( $slide['subtitle'] ) : ?>
						<p class="cgm-slide__sub"><?php echo esc_html( $slide['subtitle'] ); ?></p>
					<?php endif; ?>
					<?php
					if ( $slide['heading'] ) {
						$tag = $is_first ? 'h1' : 'h2';
						printf( '<%1$s class="cgm-slide__title">%2$s</%1$s>', esc_html( $tag ), esc_html( $slide['heading'] ) );
					}
					?>
					<?php if ( $slide['description'] ) : ?>
						<p class="cgm-slide__desc"><?php echo esc_html( $slide['description'] ); ?></p>
					<?php endif; ?>
					<?php if ( $slide['cta_text'] && $slide['cta_url'] ) : ?>
						<a class="cgm-btn cgm-btn--primary" href="<?php echo esc_url( $slide['cta_url'] ); ?>"><?php echo esc_html( $slide['cta_text'] ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $multiple ) : ?>
		<button class="cgm-hero__nav cgm-hero__nav--prev" aria-label="<?php esc_attr_e( 'Anterior', 'cgm-lifestyle' ); ?>">‹</button>
		<button class="cgm-hero__nav cgm-hero__nav--next" aria-label="<?php esc_attr_e( 'Siguiente', 'cgm-lifestyle' ); ?>">›</button>
		<div class="cgm-hero__dots" role="tablist">
			<?php foreach ( $slides as $i => $s ) : ?>
				<button class="cgm-hero__dot<?php echo 0 === $i ? ' is-active' : ''; ?>" role="tab" aria-label="<?php echo esc_attr( sprintf( __( 'Ir al slide %d', 'cgm-lifestyle' ), $i + 1 ) ); ?>" data-index="<?php echo esc_attr( $i ); ?>"></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
