<?php
/**
 * Páginas. Con Elementor: contenido a ancho completo, sin duplicar títulos.
 * Sin Elementor: cabecera sc-page-hero generada por el tema.
 */
if (!defined('ABSPATH')) {
	exit;
}
get_header();
while (have_posts()) :
	the_post();
	if (servicom_is_builder()) : ?>
<main id="content" class="sc-main sc-main--builder">
	<?php the_content(); ?>
</main>
	<?php else :
		get_template_part('template-parts/page-hero', null, array('title' => get_the_title()));
		$woo = function_exists('is_woocommerce') && (is_cart() || is_checkout() || is_account_page());
		?>
<main id="content" class="sc-main">
	<div class="sc-sec">
		<div class="sc-wrap">
			<div class="entry-content sc-prose<?php echo $woo ? ' sc-prose--woo' : ''; ?>">
				<?php the_content(); ?>
				<?php wp_link_pages(); ?>
			</div>
		</div>
	</div>
</main>
	<?php endif;
endwhile;
get_footer();
