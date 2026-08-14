<?php
/**
 * Plantilla 404.
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="cgm-container cgm-page cgm-404">
	<main id="primary" class="site-main">
		<h1 class="cgm-404__code">404</h1>
		<h2 class="cgm-404__title"><?php esc_html_e( 'Página no encontrada', 'cgm-lifestyle' ); ?></h2>
		<p><?php esc_html_e( 'Lo sentimos, la página que buscas no existe o fue movida. Sigue creando grandes momentos con nuestra colección.', 'cgm-lifestyle' ); ?></p>
		<div class="cgm-404__actions">
			<a class="cgm-btn cgm-btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Volver al inicio', 'cgm-lifestyle' ); ?></a>
			<?php if ( class_exists( 'WooCommerce' ) ) : ?>
				<a class="cgm-btn cgm-btn--outline" href="<?php echo esc_url( cgm_shop_url() ); ?>"><?php esc_html_e( 'Ir a la tienda', 'cgm-lifestyle' ); ?></a>
			<?php endif; ?>
		</div>
		<div class="cgm-404__search"><?php get_search_form(); ?></div>
	</main>
</div>
<?php
get_footer();
