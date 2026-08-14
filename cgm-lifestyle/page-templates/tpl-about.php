<?php
/**
 * Template Name: CGM · Sobre Nosotros
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main cgm-about-page">
	<div class="cgm-page-hero">
		<div class="cgm-container">
			<h1 class="cgm-page-hero__title"><?php echo esc_html( cgm_get_option( 'about_title', '¿Quiénes Somos?' ) ); ?></h1>
			<?php if ( cgm_get_option( 'about_sub' ) ) : ?>
				<p class="cgm-page-hero__sub"><?php echo esc_html( cgm_get_option( 'about_sub' ) ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<?php
	// Contenido propio de la página si el editor añadió algo.
	while ( have_posts() ) :
		the_post();
		if ( trim( get_the_content() ) ) :
			?>
			<div class="cgm-container cgm-page__content cgm-rich"><?php the_content(); ?></div>
			<?php
		endif;
	endwhile;

	get_template_part( 'template-parts/home/about' );
	get_template_part( 'template-parts/home/mvp' );
	get_template_part( 'template-parts/home/support' );
	?>
</main>
<?php
get_footer();
