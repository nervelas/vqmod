<?php
/**
 * Footer del tema.
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$phone     = cgm_get_option( 'phone' );
$whats     = cgm_get_option( 'whatsapp_phone' );
$email     = cgm_get_option( 'email' );
$copyright = str_replace( '%year%', gmdate( 'Y' ), cgm_get_option( 'footer_copyright' ) );
?>
</div><!-- #content -->

<footer id="colophon" class="cgm-footer">
	<div class="cgm-container cgm-footer__grid">

		<div class="cgm-footer__brand">
			<span class="cgm-footer__logo"><?php echo cgm_logo_html( 'light' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<p><?php echo esc_html( cgm_get_option( 'footer_about' ) ); ?></p>
			<div class="cgm-footer__social">
				<?php foreach ( cgm_social_links() as $net => $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( ucfirst( $net ) ); ?>"><?php echo cgm_icon( $net ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="cgm-footer__col">
			<h3 class="widget-title"><?php esc_html_e( 'Explora', 'cgm-lifestyle' ); ?></h3>
			<?php
			if ( has_nav_menu( 'footer' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'cgm-footer__menu',
						'depth'          => 1,
						'fallback_cb'    => false,
					)
				);
			} else {
				echo '<ul class="cgm-footer__menu">';
				$links = array(
					'politicas-de-envio'   => __( 'Políticas de Envío', 'cgm-lifestyle' ),
					'cambios-garantizados' => __( 'Cambios Garantizados', 'cgm-lifestyle' ),
					'pago-seguro'          => __( 'Pago Seguro', 'cgm-lifestyle' ),
					'compra-responsable'   => __( 'Compra Responsable', 'cgm-lifestyle' ),
					'contacto'             => __( 'Contacto', 'cgm-lifestyle' ),
				);
				foreach ( $links as $slug => $label ) {
					$p = get_page_by_path( $slug );
					if ( $p ) {
						printf( '<li><a href="%s">%s</a></li>', esc_url( get_permalink( $p ) ), esc_html( $label ) );
					}
				}
				echo '</ul>';
			}
			?>
		</div>

		<div class="cgm-footer__col">
			<h3 class="widget-title"><?php esc_html_e( 'Contacto', 'cgm-lifestyle' ); ?></h3>
			<ul class="cgm-footer__contact">
				<?php if ( $whats ) : ?>
					<li><a href="<?php echo esc_url( cgm_whatsapp_url() ); ?>" target="_blank" rel="noopener nofollow"><?php echo cgm_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $whats ); ?></a></li>
				<?php endif; ?>
				<?php if ( $phone && $phone !== $whats ) : ?>
					<li><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo cgm_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $phone ); ?></a></li>
				<?php endif; ?>
				<?php if ( $email ) : ?>
					<li><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo cgm_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $email ); ?></a></li>
				<?php endif; ?>
			</ul>
		</div>

		<div class="cgm-footer__col">
			<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
				<?php dynamic_sidebar( 'footer-1' ); ?>
			<?php else : ?>
				<h3 class="widget-title"><?php esc_html_e( 'Atención', 'cgm-lifestyle' ); ?></h3>
				<p><?php echo esc_html( cgm_get_option( 'support_sub', 'Estamos aquí para ayudarte.' ) ); ?></p>
				<a class="cgm-btn cgm-btn--whatsapp" href="<?php echo esc_url( cgm_whatsapp_url() ); ?>" target="_blank" rel="noopener nofollow">
					<?php echo cgm_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo esc_html( cgm_get_option( 'support_cta', 'Contactar a un Asesor' ) ); ?>
				</a>
			<?php endif; ?>
		</div>

	</div>

	<div class="cgm-footer__bottom">
		<div class="cgm-container">
			<p><?php echo esc_html( $copyright ); ?></p>
		</div>
	</div>
</footer>

<a class="cgm-whatsapp-float" href="<?php echo esc_url( cgm_whatsapp_url() ); ?>" target="_blank" rel="noopener nofollow" aria-label="<?php esc_attr_e( 'Contactar por WhatsApp', 'cgm-lifestyle' ); ?>">
	<?php echo cgm_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</a>

<?php wp_footer(); ?>
</body>
</html>
