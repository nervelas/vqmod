<?php
if (!defined('ABSPATH')) {
	exit;
}
get_header();
get_template_part('template-parts/page-hero', null, array(
	'title' => 'Resultados de la búsqueda',
	'sub'   => get_search_query() !== '' ? '“' . get_search_query() . '”' : '',
));
?>
<main id="content" class="sc-main">
	<div class="sc-sec">
		<div class="sc-wrap">
			<div class="sc-search-box"><?php get_search_form(); ?></div>
			<?php if (have_posts()) : ?>
				<div class="sc-grid sc-grid--posts">
					<?php while (have_posts()) : the_post(); get_template_part('template-parts/content-card'); endwhile; ?>
				</div>
				<?php the_posts_pagination(array('mid_size' => 1, 'prev_text' => '&larr;', 'next_text' => '&rarr;', 'screen_reader_text' => 'Navegación de resultados')); ?>
			<?php else : ?>
				<p class="sc-empty">No encontramos resultados. Pruebe con otras palabras.</p>
			<?php endif; ?>
		</div>
	</div>
</main>
<?php get_footer();
