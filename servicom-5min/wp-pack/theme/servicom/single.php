<?php
if (!defined('ABSPATH')) {
	exit;
}
get_header();
while (have_posts()) :
	the_post();
	if (servicom_is_builder()) : ?>
<main id="content" class="sc-main sc-main--builder"><?php the_content(); ?></main>
	<?php else :
		get_template_part('template-parts/page-hero', null, array('title' => get_the_title(), 'kicker' => get_the_date()));
		?>
<main id="content" class="sc-main">
	<article <?php post_class('sc-sec sc-single'); ?>>
		<div class="sc-wrap sc-wrap--narrow">
			<?php if (has_post_thumbnail()) : ?>
				<div class="sc-single__media"><?php the_post_thumbnail('large', array('alt' => '')); ?></div>
			<?php endif; ?>
			<div class="entry-content sc-prose"><?php the_content(); ?><?php wp_link_pages(); ?></div>
			<nav class="sc-postnav" aria-label="Más entradas">
				<span><?php previous_post_link('%link', '&larr; %title'); ?></span>
				<span><?php next_post_link('%link', '%title &rarr;'); ?></span>
			</nav>
		</div>
	</article>
</main>
	<?php endif;
endwhile;
get_footer();
