<?php
if (!defined('ABSPATH')) {
	exit;
}
get_header();
get_template_part('template-parts/page-hero', null, array(
	'title' => wp_strip_all_tags(get_the_archive_title()),
	'sub'   => wp_strip_all_tags(get_the_archive_description()),
));
?>
<main id="content" class="sc-main">
	<div class="sc-sec">
		<div class="sc-wrap">
			<?php if (have_posts()) : ?>
				<div class="sc-grid sc-grid--posts">
					<?php while (have_posts()) : the_post(); get_template_part('template-parts/content-card'); endwhile; ?>
				</div>
				<?php the_posts_pagination(array('mid_size' => 1, 'prev_text' => '&larr;', 'next_text' => '&rarr;', 'screen_reader_text' => 'Navegación de entradas')); ?>
			<?php else : ?>
				<p class="sc-empty">No hay entradas en esta sección.</p>
			<?php endif; ?>
		</div>
	</div>
</main>
<?php get_footer();
