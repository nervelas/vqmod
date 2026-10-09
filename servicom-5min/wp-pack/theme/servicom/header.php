<?php
if (!defined('ABSPATH')) {
	exit;
}
$sc_wa   = servicom_wa_url();
$sc_tel  = servicom_tel_href(servicom_biz('telefono'));
$sc_telv = servicom_biz('telefono');
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="sc-skip" href="#content">Saltar al contenido</a>

<header class="sc-header" id="sc-header">
	<div class="sc-header__bar sc-wrap">
		<?php echo servicom_brand('sc-header__brand'); // phpcs:ignore WordPress.Security.EscapeOutput ?>

		<nav class="sc-nav" id="sc-nav" aria-label="Menú principal">
			<div class="sc-nav__head">
				<span class="sc-nav__title">Menú</span>
				<button type="button" class="sc-nav__close" aria-label="Cerrar menú"><?php echo servicom_icon('close', 24); // phpcs:ignore ?></button>
			</div>
			<?php
			wp_nav_menu(array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'sc-menu',
				'depth'          => 3,
				'walker'         => new Servicom_Walker_Nav(),
				'fallback_cb'    => 'servicom_fallback_menu',
			));
			?>
			<?php if ($sc_wa || $sc_tel) : ?>
			<div class="sc-nav__foot">
				<?php if ($sc_wa) : ?>
					<a class="sc-btn sc-btn--primary" href="<?php echo esc_url($sc_wa); ?>" target="_blank" rel="noopener"><?php echo servicom_icon('whatsapp', 20); // phpcs:ignore ?><span>WhatsApp</span></a>
				<?php endif; ?>
				<?php if ($sc_tel) : ?>
					<a class="sc-btn sc-btn--ghost" href="<?php echo esc_attr($sc_tel); ?>"><?php echo servicom_icon('phone', 20); // phpcs:ignore ?><span>Llamar</span></a>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</nav>

		<div class="sc-header__actions">
			<?php echo servicom_cart_link(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php if ($sc_wa) : ?>
				<a class="sc-btn sc-btn--primary sc-btn--sm sc-header__cta" href="<?php echo esc_url($sc_wa); ?>" target="_blank" rel="noopener"><?php echo servicom_icon('whatsapp', 18); // phpcs:ignore ?><span>WhatsApp</span></a>
			<?php elseif ($sc_tel) : ?>
				<a class="sc-btn sc-btn--primary sc-btn--sm sc-header__cta" href="<?php echo esc_attr($sc_tel); ?>"><?php echo servicom_icon('phone', 18); // phpcs:ignore ?><span>Llamar</span></a>
			<?php endif; ?>
			<button type="button" class="sc-burger" aria-label="Abrir menú" aria-expanded="false" aria-controls="sc-nav"><?php echo servicom_icon('menu', 26); // phpcs:ignore ?></button>
		</div>
	</div>
</header>
<div class="sc-backdrop" aria-hidden="true"></div>
