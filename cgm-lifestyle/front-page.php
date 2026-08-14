<?php
/**
 * Portada / Inicio de CGM.
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main cgm-home">
	<?php
	get_template_part( 'template-parts/home/hero' );
	get_template_part( 'template-parts/home/badges' );
	get_template_part( 'template-parts/home/about' );
	get_template_part( 'template-parts/home/mvp' ); // Misión, Visión, Filosofía.
	get_template_part( 'template-parts/home/products' );
	get_template_part( 'template-parts/home/combos' );
	get_template_part( 'template-parts/home/custom' ); // Personalizados.
	get_template_part( 'template-parts/home/accessories' );
	get_template_part( 'template-parts/home/trust' ); // Envíos / Cambios / Pago / Responsable.
	get_template_part( 'template-parts/home/support' );
	?>
</main>
<?php
get_footer();
