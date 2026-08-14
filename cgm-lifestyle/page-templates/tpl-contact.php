<?php
/**
 * Template Name: CGM · Contacto
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$whats = cgm_get_option( 'whatsapp_phone' );
$phone = cgm_get_option( 'phone' );
$email = cgm_get_option( 'email' );
?>
<main id="primary" class="site-main cgm-contact-page">
	<div class="cgm-page-hero">
		<div class="cgm-container">
			<h1 class="cgm-page-hero__title"><?php the_title(); ?></h1>
			<p class="cgm-page-hero__sub"><?php echo esc_html( cgm_get_option( 'support_sub', 'Estamos aquí para ayudarte.' ) ); ?></p>
		</div>
	</div>

	<div class="cgm-container cgm-contact">
		<div class="cgm-contact__grid">
			<div class="cgm-contact__info">
				<?php
				while ( have_posts() ) :
					the_post();
					if ( trim( get_the_content() ) ) {
						echo '<div class="cgm-rich">';
						the_content();
						echo '</div>';
					}
				endwhile;
				?>
				<div class="cgm-rich"><?php echo cgm_format_text( cgm_get_option( 'support_body' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			</div>

			<aside class="cgm-contact__card">
				<h2><?php esc_html_e( 'Contáctanos', 'cgm-lifestyle' ); ?></h2>
				<ul class="cgm-contact__list">
					<?php if ( $whats ) : ?>
						<li><a href="<?php echo esc_url( cgm_whatsapp_url() ); ?>" target="_blank" rel="noopener nofollow"><?php echo cgm_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span><?php echo esc_html( $whats ); ?></span></a></li>
					<?php endif; ?>
					<?php if ( $phone && $phone !== $whats ) : ?>
						<li><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo cgm_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span><?php echo esc_html( $phone ); ?></span></a></li>
					<?php endif; ?>
					<?php if ( $email ) : ?>
						<li><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo cgm_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span><?php echo esc_html( $email ); ?></span></a></li>
					<?php endif; ?>
				</ul>
				<a class="cgm-btn cgm-btn--whatsapp cgm-btn--block" href="<?php echo esc_url( cgm_whatsapp_url() ); ?>" target="_blank" rel="noopener nofollow">
					<?php echo cgm_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo esc_html( cgm_get_option( 'support_cta', 'Contactar a un Asesor' ) ); ?>
				</a>
				<div class="cgm-contact__social">
					<?php foreach ( cgm_social_links() as $net => $url ) : ?>
						<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( ucfirst( $net ) ); ?>"><?php echo cgm_icon( $net ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
					<?php endforeach; ?>
				</div>
			</aside>
		</div>
	</div>
</main>
<?php
get_footer();
