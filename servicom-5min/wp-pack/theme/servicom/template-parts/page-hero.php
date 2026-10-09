<?php
/** Cabecera de página interna. Args: title, sub (opcional), kicker (opcional). */
if (!defined('ABSPATH')) {
	exit;
}
$title  = isset($args['title']) ? $args['title'] : '';
$sub    = isset($args['sub']) ? $args['sub'] : '';
$kicker = isset($args['kicker']) ? $args['kicker'] : '';
?>
<section class="sc-page-hero">
	<div class="sc-wrap sc-page-hero__inner">
		<?php if ($kicker !== '') : ?><p class="sc-page-hero__kicker"><?php echo esc_html($kicker); ?></p><?php endif; ?>
		<h1 class="sc-page-hero__title"><?php echo esc_html($title); ?></h1>
		<?php if ($sub !== '') : ?><p class="sc-page-hero__sub"><?php echo esc_html($sub); ?></p><?php endif; ?>
	</div>
</section>
