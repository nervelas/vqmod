<?php
if (!defined('ABSPATH')) {
	exit;
}
$tel   = servicom_biz('telefono');
$telh  = servicom_tel_href($tel);
$mail  = servicom_biz('correo');
$addr  = servicom_biz('direccion');
$hours = servicom_biz('horario');
$map   = servicom_map_url();
$wa    = servicom_wa_url();
$soc   = servicom_socials_html('sc-social');
$name  = servicom_name();
$has_contact = ($tel !== '' || $mail !== '' || $addr !== '');
?>
<footer class="sc-footer" id="sc-footer">
	<div class="sc-wrap sc-footer__grid">
		<div class="sc-footer__col sc-footer__brand">
			<?php echo servicom_brand('sc-footer__logo'); // phpcs:ignore ?>
			<?php echo $soc; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>

		<?php if ($has_contact) : ?>
		<div class="sc-footer__col">
			<h2 class="sc-footer__title">Contacto</h2>
			<ul class="sc-footer__list">
				<?php if ($tel !== '') : ?>
					<li><?php echo servicom_icon('phone', 18); // phpcs:ignore ?><?php if ($telh) : ?><a href="<?php echo esc_attr($telh); ?>"><?php echo esc_html($tel); ?></a><?php else : echo '<span>' . esc_html($tel) . '</span>'; endif; ?></li>
				<?php endif; ?>
				<?php if ($mail !== '' && is_email($mail)) : ?>
					<li><?php echo servicom_icon('mail', 18); // phpcs:ignore ?><a href="mailto:<?php echo esc_attr($mail); ?>"><?php echo esc_html($mail); ?></a></li>
				<?php endif; ?>
				<?php if ($addr !== '') : ?>
					<li><?php echo servicom_icon('pin', 18); // phpcs:ignore ?><?php if ($map) : ?><a href="<?php echo esc_url($map); ?>" target="_blank" rel="noopener"><?php echo servicom_multiline($addr); // phpcs:ignore ?></a><?php else : ?><span><?php echo servicom_multiline($addr); // phpcs:ignore ?></span><?php endif; ?></li>
				<?php endif; ?>
			</ul>
		</div>
		<?php endif; ?>

		<?php if ($hours !== '') : ?>
		<div class="sc-footer__col">
			<h2 class="sc-footer__title">Horario</h2>
			<ul class="sc-footer__list"><li><?php echo servicom_icon('clock', 18); // phpcs:ignore ?><span><?php echo servicom_multiline($hours); // phpcs:ignore ?></span></li></ul>
		</div>
		<?php endif; ?>

		<div class="sc-footer__col sc-footer__nav">
			<h2 class="sc-footer__title">Navegación</h2>
			<?php
			$sc_lx_fm = function_exists('sc_lx_footer_menu_html') ? sc_lx_footer_menu_html() : '';
			if ($sc_lx_fm !== '') {
				echo $sc_lx_fm; // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
			wp_nav_menu(array(
				'theme_location' => has_nav_menu('footer') ? 'footer' : 'primary',
				'container'      => false,
				'menu_class'     => 'sc-footer__menu',
				'depth'          => 1,
				'fallback_cb'    => false,
			));
			}
			?>
		</div>
	</div>
	<div class="sc-footer__bar">
		<div class="sc-wrap sc-footer__bar-in">
			<span class="sc-footer__copy">&copy; <?php echo esc_html(wp_date('Y')); ?> <?php echo esc_html($name); ?></span>
			<span class="sc-footer__credit"><?php echo esc_html(servicom_credit()); ?></span>
		</div>
	</div>
</footer>

<?php if (servicom_show_float_wa()) : ?>
<a class="sc-float-wa" href="<?php echo esc_url($wa); ?>" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp"><?php echo servicom_icon('whatsapp', 30); // phpcs:ignore ?></a>
<?php endif; ?>

<?php if (servicom_show_mobile_bar()) : ?>
<nav class="sc-bar" aria-label="Contacto rápido">
	<?php if ($telh) : ?><a class="sc-bar__btn" href="<?php echo esc_attr($telh); ?>"><?php echo servicom_icon('phone', 22); // phpcs:ignore ?><span>Llamar</span></a><?php endif; ?>
	<?php if ($wa) : ?><a class="sc-bar__btn sc-bar__btn--wa" href="<?php echo esc_url($wa); ?>" target="_blank" rel="noopener"><?php echo servicom_icon('whatsapp', 22); // phpcs:ignore ?><span>WhatsApp</span></a><?php endif; ?>
	<?php if ($map) : ?><a class="sc-bar__btn" href="<?php echo esc_url($map); ?>" target="_blank" rel="noopener"><?php echo servicom_icon('route', 22); // phpcs:ignore ?><span>Cómo llegar</span></a><?php endif; ?>
</nav>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
