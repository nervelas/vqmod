<?php
/**
 * Plantilla principal de reserva (blog / archivos).
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="cgm-container cgm-page">
	<main id="primary" class="site-main">
		<?php if ( have_posts() ) : ?>
			<?php if ( is_home() && ! is_front_page() ) : ?>
				<header class="cgm-page__header"><h1 class="cgm-page__title"><?php single_post_title(); ?></h1></header>
			<?php elseif ( is_archive() ) : ?>
				<header class="cgm-page__header"><h1 class="cgm-page__title"><?php the_archive_title(); ?></h1><?php the_archive_description( '<div class="cgm-archive-desc">', '</div>' ); ?></header>
			<?php elseif ( is_search() ) : ?>
				<header class="cgm-page__header"><h1 class="cgm-page__title"><?php printf( esc_html__( 'Resultados para: %s', 'cgm-lifestyle' ), '<span>' . esc_html( get_search_query() ) . '</span>' ); ?></h1></header>
			<?php endif; ?>

			<div class="cgm-post-list">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article <?php post_class( 'cgm-post-card' ); ?>>
						<?php if ( has_post_thumbnail() ) : ?>
							<a class="cgm-post-card__media" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'cgm-card', array( 'loading' => 'lazy' ) ); ?></a>
						<?php endif; ?>
						<div class="cgm-post-card__body">
							<h2 class="cgm-post-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<div class="cgm-post-card__excerpt"><?php the_excerpt(); ?></div>
							<a class="cgm-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Leer más', 'cgm-lifestyle' ); ?> →</a>
						</div>
					</article>
					<?php
				endwhile;
				?>
			</div>

			<?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'No se encontró contenido.', 'cgm-lifestyle' ); ?></p>
			<?php get_search_form(); ?>
		<?php endif; ?>
	</main>
</div>
<?php
get_footer();
