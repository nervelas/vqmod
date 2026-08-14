<?php
/**
 * Sección Nuestros Productos: categorías + productos destacados.
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section id="productos" class="cgm-section cgm-products">
	<div class="cgm-container">
		<?php cgm_section_header( cgm_get_option( 'products_title', 'NUESTROS PRODUCTOS' ), cgm_get_option( 'products_sub' ) ); ?>

		<?php if ( cgm_get_option( 'products_intro' ) ) : ?>
			<div class="cgm-products__intro cgm-rich reveal"><?php echo cgm_format_text( cgm_get_option( 'products_intro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<?php endif; ?>

		<?php
		if ( ! class_exists( 'WooCommerce' ) ) {
			echo '<p class="cgm-notice">' . esc_html__( 'Activa WooCommerce para mostrar la tienda y las categorías.', 'cgm-lifestyle' ) . '</p>';
			echo '</div></section>';
			return;
		}

		// Orden preferido de categorías según el documento.
		$order_slugs = array( 'pachones', 'termos', 'kids', 'combos', 'personalizados', 'accesorios' );
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'exclude'    => array( absint( get_option( 'default_product_cat', 0 ) ) ),
			)
		);
		$by_slug = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$by_slug[ $t->slug ] = $t;
			}
		}
		$ordered = array();
		foreach ( $order_slugs as $slug ) {
			if ( isset( $by_slug[ $slug ] ) ) {
				$ordered[] = $by_slug[ $slug ];
				unset( $by_slug[ $slug ] );
			}
		}
		$ordered = array_merge( $ordered, array_values( $by_slug ) );
		?>

		<?php if ( ! empty( $ordered ) ) : ?>
			<div class="cgm-cat-grid">
				<?php foreach ( $ordered as $term ) : ?>
					<?php
					$thumb_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
					$img      = $thumb_id ? wp_get_attachment_image( $thumb_id, 'cgm-card', false, array( 'alt' => $term->name, 'loading' => 'lazy', 'decoding' => 'async' ) ) : '';
					?>
					<a class="cgm-cat-card reveal" href="<?php echo esc_url( get_term_link( $term ) ); ?>">
						<span class="cgm-cat-card__media">
							<?php echo $img ? $img : '<img src="' . esc_url( CGM_THEME_URI . '/assets/images/product-placeholder.svg' ) . '" alt="' . esc_attr( $term->name ) . '" loading="lazy">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</span>
						<span class="cgm-cat-card__name"><?php echo esc_html( $term->name ); ?></span>
						<span class="cgm-cat-card__count"><?php echo esc_html( sprintf( _n( '%d producto', '%d productos', $term->count, 'cgm-lifestyle' ), $term->count ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php
		// Productos destacados / recientes.
		$loop = new WP_Query(
			array(
				'post_type'           => 'product',
				'posts_per_page'      => 8,
				'post_status'         => 'publish',
				'ignore_sticky_posts' => true,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'tax_query'           => array(
					array(
						'taxonomy' => 'product_visibility',
						'field'    => 'name',
						'terms'    => 'exclude-from-catalog',
						'operator' => 'NOT IN',
					),
				),
			)
		);
		if ( $loop->have_posts() ) :
			?>
			<div class="cgm-featured">
				<h3 class="cgm-featured__title"><?php esc_html_e( 'Recién llegados', 'cgm-lifestyle' ); ?></h3>
				<ul class="products cgm-product-grid columns-4">
					<?php
					while ( $loop->have_posts() ) :
						$loop->the_post();
						wc_get_template_part( 'content', 'product' );
					endwhile;
					?>
				</ul>
			</div>
			<?php
		endif;
		wp_reset_postdata();
		?>

		<div class="cgm-products__cta reveal">
			<a class="cgm-btn cgm-btn--primary" href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>"><?php esc_html_e( 'Ver toda la tienda', 'cgm-lifestyle' ); ?></a>
		</div>
	</div>
</section>
