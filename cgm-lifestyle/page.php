<?php
/**
 * Plantilla de página estándar.
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
				</header>
				<div class="cgm-page__content cgm-rich">
					<?php the_content(); ?>
				</div>
			</article>
			<?php
		endwhile;
		?>
	</main>
</div>
<?php
get_footer();
