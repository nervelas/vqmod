<?php
/** Inicio: página estática (Elementor o contenido) o, si no hay, entradas recientes. */
if (!defined('ABSPATH')) {
	exit;
}
if (is_page()) {
	get_header();
	while (have_posts()) :
		the_post();
		if (function_exists('sc_lx_page_key') && sc_lx_page_key() !== '') :
			sc_render_page(sc_lx_page_key());
		elseif (servicom_is_builder()) : ?>
<main id="content" class="sc-main sc-main--builder">
	<?php the_content(); ?>
</main>
		<?php else : ?>
<main id="content" class="sc-main">
	<section class="sc-page-hero sc-page-hero--home"><div class="sc-wrap sc-page-hero__inner">
		<h1 class="sc-page-hero__title"><?php echo esc_html(servicom_name()); ?></h1>
	</div></section>
	<div class="sc-sec"><div class="sc-wrap"><div class="entry-content sc-prose"><?php the_content(); ?></div></div></div>
</main>
		<?php endif;
	endwhile;
	get_footer();
	return;
}
require get_theme_file_path('index.php');
