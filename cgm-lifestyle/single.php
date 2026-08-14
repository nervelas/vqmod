<?php
/**
 * Plantilla de entrada individual.
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
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'cgm-page-article' ); ?>>
				<header class="cgm-page__header">
					<h1 class="cgm-page__title"><?php the_title(); ?></h1>
					<p class="cgm-post-meta"><?php echo esc_html( get_the_date() ); ?></p>
				</header>
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="cgm-post-hero"><?php the_post_thumbnail( 'large' ); ?></div>
				<?php endif; ?>
				<div class="cgm-page__content cgm-rich">
					<?php
					the_content();
					wp_link_pages();
					?>
				</div>
			</article>
			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
		endwhile;
		?>
	</main>
</div>
<?php
get_footer();
