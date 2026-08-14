<?php
/**
 * Plantilla de comentarios.
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="cgm-comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="cgm-comments__title">
			<?php
			$count = get_comments_number();
			printf(
				esc_html( _n( '%s comentario', '%s comentarios', $count, 'cgm-lifestyle' ) ),
				esc_html( number_format_i18n( $count ) )
			);
			?>
		</h2>
		<ol class="cgm-comments__list">
			<?php
			wp_list_comments(
				array(
					'style'      => 'ol',
					'short_ping' => true,
					'avatar_size'=> 48,
				)
			);
			?>
		</ol>
		<?php the_comments_pagination(); ?>
	<?php endif; ?>

	<?php comment_form(); ?>
</section>
