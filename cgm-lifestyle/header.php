<?php
/**
 * Header del tema.
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Saltar al contenido', 'cgm-lifestyle' ); ?></a>

<?php if ( cgm_get_option( 'topbar_enabled', 1 ) ) : ?>
<div class="cgm-topbar">
	<div class="cgm-container cgm-topbar__inner">
		<ul class="cgm-topbar__list">
			<?php
			foreach ( array( 'topbar_1', 'topbar_2', 'topbar_3', 'topbar_4' ) as $k ) {
				$txt = cgm_get_option( $k );
				if ( $txt ) {
					echo '<li>' . esc_html( $txt ) . '</li>';
				}
			}
			?>
		</ul>
		<div class="cgm-topbar__social">
			<?php foreach ( cgm_social_links() as $net => $url ) : ?>
				<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( ucfirst( $net ) ); ?>"><?php echo cgm_icon( $net ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			<?php endforeach; ?>
		</div>
	</div>
</div>
<?php endif; ?>

<header id="masthead" class="cgm-header" data-sticky>
	<div class="cgm-container cgm-header__inner">
		<div class="cgm-header__left">
			<button class="cgm-burger" aria-expanded="false" aria-controls="cgm-mobile-nav" aria-label="<?php esc_attr_e( 'Abrir menú', 'cgm-lifestyle' ); ?>">
				<?php echo cgm_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>
		</div>

		<div class="cgm-header__logo">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="cgm-logo" rel="home" aria-label="<?php echo esc_attr( cgm_get_option( 'brand_name', 'CGM' ) ); ?>">
				<?php echo cgm_logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</a>
		</div>

		<nav class="cgm-nav" aria-label="<?php esc_attr_e( 'Menú principal', 'cgm-lifestyle' ); ?>">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'cgm-menu',
						'depth'          => 2,
						'fallback_cb'    => false,
					)
				);
			} else {
				cgm_fallback_menu();
			}
			?>
		</nav>

		<div class="cgm-header__actions">
			<button class="cgm-icon-btn cgm-search-toggle" aria-expanded="false" aria-controls="cgm-search-panel" aria-label="<?php esc_attr_e( 'Buscar', 'cgm-lifestyle' ); ?>">
				<?php echo cgm_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>

			<?php if ( class_exists( 'WooCommerce' ) ) : ?>
				<a class="cgm-icon-btn" href="<?php echo esc_url( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ); ?>" aria-label="<?php esc_attr_e( 'Mi cuenta', 'cgm-lifestyle' ); ?>">
					<?php echo cgm_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
				<a class="cgm-icon-btn cgm-cart-link" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'Carrito', 'cgm-lifestyle' ); ?>">
					<?php echo cgm_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="cgm-cart-count" data-count="<?php echo esc_attr( cgm_cart_count() ); ?>"><?php echo esc_html( cgm_cart_count() ); ?></span>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<div id="cgm-search-panel" class="cgm-search-panel" hidden>
		<div class="cgm-container">
			<?php
			if ( class_exists( 'WooCommerce' ) ) {
				echo get_product_search_form( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				get_search_form();
			}
			?>
		</div>
	</div>
</header>

<!-- Menú móvil -->
<div id="cgm-mobile-nav" class="cgm-mobile-nav" aria-hidden="true">
	<div class="cgm-mobile-nav__head">
		<span class="cgm-mobile-nav__logo"><?php echo cgm_logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<button class="cgm-mobile-close" aria-label="<?php esc_attr_e( 'Cerrar menú', 'cgm-lifestyle' ); ?>"><?php echo cgm_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
	</div>
	<?php
	if ( has_nav_menu( 'primary' ) ) {
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'cgm-mobile-menu',
				'depth'          => 2,
				'fallback_cb'    => false,
			)
		);
	} else {
		cgm_fallback_menu( 'cgm-mobile-menu' );
	}
	?>
	<a class="cgm-btn cgm-btn--whatsapp cgm-mobile-cta" href="<?php echo esc_url( cgm_whatsapp_url() ); ?>" target="_blank" rel="noopener nofollow">
		<?php echo cgm_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo esc_html( cgm_get_option( 'support_cta', 'Contactar a un Asesor' ) ); ?>
	</a>
</div>
<div class="cgm-overlay" hidden></div>

<div id="content" class="site-content">
