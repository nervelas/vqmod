<?php
if (!defined('ABSPATH')) {
	exit;
}
get_header();
?>
<main id="content" class="sc-main">
	<section class="sc-sec sc-404">
		<div class="sc-wrap sc-404__in">
			<p class="sc-404__code">404</p>
			<h1 class="sc-404__title">No encontramos esta página</h1>
			<p class="sc-404__text">Es posible que el enlace haya cambiado. Puede buscar o volver al inicio.</p>
			<div class="sc-search-box"><?php get_search_form(); ?></div>
			<a class="sc-btn sc-btn--primary" href="<?php echo esc_url(home_url('/')); ?>">Volver al inicio</a>
		</div>
	</section>
</main>
<?php get_footer();
