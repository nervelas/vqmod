<?php
/** Tarjeta de entrada (archivo, búsqueda, inicio de blog). */
if (!defined('ABSPATH')) {
	exit;
}
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('sc-card sc-card--post sc-reveal'); ?>>
	<?php if (has_post_thumbnail()) : ?>
		<a class="sc-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail('sc-card', array('loading' => 'lazy', 'alt' => '')); ?></a>
	<?php endif; ?>
	<div class="sc-card__body">
		<p class="sc-card__meta"><?php echo esc_html(get_the_date()); ?></p>
		<h2 class="sc-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<div class="sc-card__text"><?php echo esc_html(wp_strip_all_tags(get_the_excerpt())); ?></div>
		<a class="sc-card__more" href="<?php the_permalink(); ?>">Leer más <?php echo servicom_icon('arrow', 16); // phpcs:ignore ?></a>
	</div>
</article>
