<?php
/**
 * Tema hijo AGROINCO — rediseño "Industrial de élite" + funciones del sitio.
 * Solo capa visual: no altera contenido, URLs ni SEO.
 */
defined( 'ABSPATH' ) || exit;

define( 'AGRO_WA_NUM', '50240769228' );          // WhatsApp 4076 9228 (GT +502)
define( 'AGRO_TEL', '25068100' );                // PBX 2506 8100
define( 'AGRO_MAIL', 'info@agroinco.com' );
define( 'AGRO_VER', '1.0.0' );

/* ---------- Estilos y scripts ---------- */
add_action( 'wp_enqueue_scripts', function () {
	$parent = get_template_directory_uri();
	$child  = get_stylesheet_directory_uri();
	// Bundle único: fuentes + estilo del hijo + sistema de diseño (menos peticiones críticas)
	wp_enqueue_style( 'agro-bundle', $child . '/assets/agro-bundle.min.css', array( 'oceanwp-style' ), AGRO_VER );
	wp_enqueue_script( 'agro-design', $child . '/assets/agro-design.min.js', array(), AGRO_VER, true );
}, 20 );

/* preconnect para Google Fonts */


/* defer del JS propio */
add_filter( 'script_loader_tag', function ( $tag, $handle ) {
	return 'agro-design' === $handle ? str_replace( ' src=', ' defer src=', $tag ) : $tag;
}, 10, 2 );

/* ---------- [agro_menu] — sustituto del nav-menu de Elementor Pro ---------- */
add_shortcode( 'agro_menu', function ( $atts ) {
	$atts = shortcode_atts( array( 'menu' => 'botones-principales' ), $atts, 'agro_menu' );
	return wp_nav_menu( array(
		'menu' => $atts['menu'], 'container' => 'nav', 'container_class' => 'agro-inline-menu',
		'fallback_cb' => '__return_empty_string', 'echo' => false,
	) );
} );

/* ---------- Iconos SVG técnicos ---------- */
function agro_svg( $name ) {
	$p = 'stroke-width="1.7" fill="none" stroke-linecap="round" stroke-linejoin="round"';
	$icons = array(
		'sello'    => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4.5"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/>',
		'plancha'  => '<rect x="3" y="6" width="18" height="4" rx="1"/><rect x="3" y="14" width="18" height="4" rx="1"/>',
		'estopa'   => '<path d="M4 8c4-4 12 4 16 0M4 12c4-4 12 4 16 0M4 16c4-4 12 4 16 0"/>',
		'hule'     => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M4 12h16M12 4v16"/>',
		'oring'    => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="5"/>',
		'fibra'    => '<path d="M3 12h18M12 3v18M5.6 5.6l12.8 12.8M18.4 5.6L5.6 18.4"/>',
		'textil'   => '<path d="M4 4h16v16H4z"/><path d="M8 4v16M12 4v16M16 4v16M4 8h16M4 12h16M4 16h16"/>',
		'junta'    => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/><circle cx="5" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="7" cy="7" r="1"/><circle cx="17" cy="7" r="1"/><circle cx="7" cy="17" r="1"/><circle cx="17" cy="17" r="1"/>',
		'ptfe'     => '<path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z"/><path d="M12 3v9l8 4.5M12 12L4 16.5"/>',
		'fabrica'  => '<path d="M3 21V9l6 4V9l6 4V4h6v17z"/><path d="M7 17h2M12 17h2M17 17h2"/>',
		'gota'     => '<path d="M12 3s6 7 6 11a6 6 0 0 1-12 0c0-4 6-11 6-11z"/>',
		'energia'  => '<path d="M13 2L4 14h6l-1 8 9-12h-6z"/>',
		'alimento' => '<path d="M5 3v7a3 3 0 0 0 6 0V3M8 3v18M17 3c-2 0-3 5-3 8h3v10"/>',
		'quimica'  => '<path d="M10 3h4M12 3v6l6 9a2 2 0 0 1-1.7 3H7.7A2 2 0 0 1 6 18l6-9z"/>',
		'papel'    => '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/>',
		'azucar'   => '<rect x="4" y="10" width="7" height="7" rx="1"/><rect x="13" y="7" width="7" height="7" rx="1"/>',
		'bomba'    => '<circle cx="10" cy="14" r="6"/><path d="M10 8V4h8v6h-4"/>',
		'llave'    => '<path d="M14 7a4 4 0 1 0-6.9 2.8L3 14v4l4-1 4.2-4.1A4 4 0 0 0 14 7z"/><circle cx="17" cy="17" r="4"/>',
		'chat'     => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 21l1.9-5.4A8 8 0 1 1 21 12z"/>',
		'cotiza'   => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
		'entrega'  => '<path d="M3 7h11v10H3zM14 10h4l3 3v4h-7z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>',
	);
	return isset( $icons[ $name ] )
		? '<svg viewBox="0 0 24 24" ' . $p . ' aria-hidden="true">' . $icons[ $name ] . '</svg>' : '';
}

/* ---------- Botón flotante de WhatsApp ---------- */
add_action( 'wp_footer', function () {
	echo '<a class="agro-wa-float" href="https://wa.me/' . AGRO_WA_NUM
		. '?text=' . rawurlencode( 'Hola AGROINCO, quiero información sobre sus productos.' )
		. '" target="_blank" rel="noopener" aria-label="Escríbenos por WhatsApp">'
		. '<svg viewBox="0 0 32 32"><path d="M16 3C9.4 3 4 8.4 4 15c0 2.1.6 4.2 1.6 6L4 29l8.2-1.5c1.2.6 2.5.9 3.8.9 6.6 0 12-5.4 12-12S22.6 3 16 3zm0 21.8c-1.2 0-2.4-.3-3.5-.8l-.6-.3-4.9.9 1-4.7-.4-.6c-.9-1.5-1.4-3.2-1.4-4.9 0-5.4 4.4-9.8 9.8-9.8s9.8 4.4 9.8 9.8-4.4 9.4-9.8 9.4zm5.4-7.1c-.3-.2-1.7-.9-2-1s-.5-.2-.7.2-.8 1-.9 1.2-.3.2-.6.1c-1.7-.9-2.9-1.6-4-3.6-.3-.5.3-.5.8-1.6.1-.2 0-.4 0-.6s-.7-1.6-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.6.1-.9.4-.3.3-1.1 1.1-1.1 2.7s1.2 3.2 1.3 3.4c.2.2 2.3 3.5 5.6 4.9 2.1.9 2.9 1 3.9.8.6-.1 1.7-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.2-.3-.3-.6-.4z"/></svg></a>';
} );

/* ---------- WooCommerce: código visible + Cotizar por WhatsApp ---------- */
function agro_wa_product_link( $product ) {
	$msg = 'Hola AGROINCO, quiero cotizar: ' . wp_strip_all_tags( $product->get_name() );
	if ( $product->get_sku() ) $msg .= ' (código ' . $product->get_sku() . ')';
	return 'https://wa.me/' . AGRO_WA_NUM . '?text=' . rawurlencode( $msg );
}
$agro_wa_svg = '<svg viewBox="0 0 32 32" fill="currentColor"><path d="M16 3C9.4 3 4 8.4 4 15c0 2.1.6 4.2 1.6 6L4 29l8.2-1.5c1.2.6 2.5.9 3.8.9 6.6 0 12-5.4 12-12S22.6 3 16 3zm5.4 14.7c-.3-.2-1.7-.9-2-1s-.5-.2-.7.2-.8 1-.9 1.2-.3.2-.6.1c-1.7-.9-2.9-1.6-4-3.6-.3-.5.3-.5.8-1.6.1-.2 0-.4 0-.6s-.7-1.6-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.6.1-.9.4-.3.3-1.1 1.1-1.1 2.7s1.2 3.2 1.3 3.4c.2.2 2.3 3.5 5.6 4.9 2.1.9 2.9 1 3.9.8.6-.1 1.7-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.2-.3-.3-.6-.4z"/></svg>';

/* En tarjetas del catálogo */
add_action( 'woocommerce_after_shop_loop_item', function () use ( $agro_wa_svg ) {
	global $product;
	if ( ! $product ) return;
	if ( $product->get_sku() ) {
		echo '<span class="agro-sku">Cód. ' . esc_html( $product->get_sku() ) . '</span>';
	}
	echo '<a class="agro-wa-btn" href="' . esc_url( agro_wa_product_link( $product ) )
		. '" target="_blank" rel="noopener">' . $agro_wa_svg . 'Cotizar por WhatsApp</a>';
}, 15 );

/* En la ficha del producto (los productos usan plantilla Elementor: se anexa al contenido) */
add_filter( 'the_content', function ( $content ) use ( $agro_wa_svg ) {
	if ( ! is_singular( 'product' ) || ! in_the_loop() || ! is_main_query() ) return $content;
	$product = wc_get_product( get_the_ID() );
	if ( ! $product ) return $content;
	$extra = '<div class="agro-product-cta agro-center">';
	if ( $product->get_sku() ) {
		$extra .= '<div class="agro-sku-line">Código: ' . esc_html( $product->get_sku() ) . '</div>';
	}
	$extra .= '<a class="agro-wa-btn" href="' . esc_url( agro_wa_product_link( $product ) )
		. '" target="_blank" rel="noopener">' . $agro_wa_svg . 'Cotizar por WhatsApp</a></div>';
	return $content . $extra;
}, 30 );

/* ---------- Secciones informativas agregadas en INICIO (aditivas; no tocan contenido) ---------- */
add_action( 'ocean_before_footer', function () {
	if ( ! is_front_page() ) return;
	$cats = array(
		array( 'sello',   'SELLOS MECÁNICOS', 'John Crane y más', 'sellos-mecanicos' ),
		array( 'plancha', 'PLANCHAS', 'Klinger, láminas para sellado', 'planchas' ),
		array( 'estopa',  'ESTOPAS', 'Empaquetaduras trenzadas', 'estopas' ),
		array( 'hule',    'ELASTÓMEROS (HULES)', 'En placa y/o rollo', 'elastomeros-hules-en-placa-y-o-rollo' ),
		array( 'oring',   'O-RINGS', 'Medidas milimétricas y pulgadas', 'o-rings' ),
		array( 'ptfe',    'PRODUCTOS DE PTFE', 'Teflón industrial', 'productos-de-ptfe-teflon' ),
		array( 'fibra',   'FIBRA DE VIDRIO', 'Telas, cintas y cordones', 'productos-de-fibra-de-vidrio' ),
		array( 'textil',  'TEXTILES AISLANTES', 'Alta temperatura', 'textiles-aislantes' ),
		array( 'junta',   'JUNTAS A MEDIDA', 'Fabricación especializada', 'fabricacion-de-juntas-a-medida' ),
	);
	$inds = array(
		array( 'azucar',  'Ingenios azucareros' ), array( 'alimento', 'Alimentos y bebidas' ),
		array( 'quimica', 'Química y farmacéutica' ), array( 'papel', 'Pulpa y papel' ),
		array( 'energia', 'Energía' ), array( 'gota', 'Agua y saneamiento' ),
		array( 'bomba',   'Bombas y equipos' ), array( 'fabrica', 'Manufactura' ),
	);
	?>
	<section class="agro-sec agro-sec--white" aria-label="Categorías de productos">
	  <div class="agro-wrap agro-center">
	    <span class="agro-kicker">Catálogo técnico</span>
	    <h2 class="agro-h2">Soluciones para control y sellado de fluidos</h2>
	    <p class="agro-sub">Nueve líneas de producto para mantenimiento industrial, disponibles para cotización inmediata.</p>
	    <div class="agro-cats">
	      <?php foreach ( $cats as $c ) : ?>
	      <a class="agro-cat" href="<?php echo esc_url( home_url( '/categoria-producto/' . $c[3] . '/' ) ); ?>">
	        <?php echo agro_svg( $c[0] ); ?><span><b><?php echo esc_html( $c[1] ); ?></b><span><?php echo esc_html( $c[2] ); ?></span></span>
	      </a>
	      <?php endforeach; ?>
	    </div>
	  </div>
	</section>

	<section class="agro-sec agro-sec--dark" aria-label="Trayectoria">
	  <div class="agro-wrap agro-center">
	    <span class="agro-kicker">Desde 1976</span>
	    <h2 class="agro-h2">Pioneros en repuestos industriales en Guatemala</h2>
	    <p class="agro-sub">Casi cinco décadas sirviendo a la industria con productos que ahorran energía y optimizan tiempo.</p>
	    <div class="agro-stats">
	      <div class="agro-stat"><b data-count="49" data-suffix="+">0</b><span>Años de experiencia</span></div>
	      <div class="agro-stat"><b data-count="9" data-suffix="">0</b><span>Líneas de producto</span></div>
	      <div class="agro-stat"><b data-count="45" data-suffix="+">0</b><span>Productos en catálogo</span></div>
	      <div class="agro-stat"><b data-count="100" data-suffix="%">0</b><span>Enfoque industrial</span></div>
	    </div>
	  </div>
	</section>

	<section class="agro-sec agro-sec--white" aria-label="Marcas que distribuimos">
	  <div class="agro-wrap agro-center">
	    <span class="agro-kicker">Distribuidores de</span>
	    <div class="agro-brands">
	      <span class="agro-brand"><?php echo agro_svg( 'sello' ); ?>John Crane</span>
	      <span class="agro-brand"><?php echo agro_svg( 'plancha' ); ?>Klinger</span>
	    </div>
	  </div>
	</section>

	<section class="agro-sec" aria-label="Industrias que atendemos">
	  <div class="agro-wrap agro-center">
	    <span class="agro-kicker">Industrias que atendemos</span>
	    <h2 class="agro-h2">Donde hay fluidos, estamos nosotros</h2>
	    <div class="agro-inds">
	      <?php foreach ( $inds as $i ) : ?>
	      <div class="agro-ind"><?php echo agro_svg( $i[0] ); ?><?php echo esc_html( $i[1] ); ?></div>
	      <?php endforeach; ?>
	    </div>
	  </div>
	</section>

	<section class="agro-sec agro-sec--white" aria-label="Proceso de cotización">
	  <div class="agro-wrap agro-center">
	    <span class="agro-kicker">Así de simple</span>
	    <h2 class="agro-h2">Cotizar con nosotros toma minutos</h2>
	    <div class="agro-steps">
	      <div class="agro-step"><?php echo agro_svg( 'chat' ); ?><b>Cuéntenos su necesidad</b><p>Escríbanos por WhatsApp, llámenos al PBX o envíe un correo con el producto o aplicación que necesita.</p></div>
	      <div class="agro-step"><?php echo agro_svg( 'cotiza' ); ?><b>Reciba su cotización</b><p>Nuestro equipo técnico le asesora y envía una cotización clara con el material adecuado.</p></div>
	      <div class="agro-step"><?php echo agro_svg( 'entrega' ); ?><b>Retire o reciba su pedido</b><p>Conveniente ubicación con parqueo en zona 9, o coordinamos la entrega de su pedido.</p></div>
	    </div>
	  </div>
	</section>

	<section class="agro-sec agro-sec--dark agro-center" aria-label="Contacto directo">
	  <div class="agro-wrap">
	    <h2 class="agro-h2">¿Listo para cotizar?</h2>
	    <p class="agro-sub">Atención directa de especialistas en control y sellado de fluidos.</p>
	    <div class="agro-ctas">
	      <a class="agro-cta agro-cta--tel" href="tel:+502<?php echo AGRO_TEL; ?>"><?php echo agro_svg( 'llave' ); ?>PBX: 2506 8100</a>
	      <a class="agro-cta agro-cta--wa" href="https://wa.me/<?php echo AGRO_WA_NUM; ?>?text=<?php echo rawurlencode( 'Hola AGROINCO, quiero una cotización.' ); ?>" target="_blank" rel="noopener"><?php echo agro_svg( 'chat' ); ?>WhatsApp: 4076 9228</a>
	      <a class="agro-cta agro-cta--mail" href="mailto:<?php echo AGRO_MAIL; ?>"><?php echo agro_svg( 'papel' ); ?><?php echo AGRO_MAIL; ?></a>
	    </div>
	  </div>
	</section>
	<?php
} );

/* ---------- Rendimiento: retirar CSS/JS que la página no usa ---------- */
add_action( 'wp_enqueue_scripts', function () {
	// Fuentes Roboto/Roboto Slab del kit antiguo de Elementor (el diseño usa Space Grotesk/Inter)
	wp_dequeue_style( 'elementor-gf-local-roboto' );
	wp_dequeue_style( 'elementor-gf-local-robotoslab' );
	// Dashicons solo para usuarios conectados
	if ( ! is_user_logged_in() ) wp_dequeue_style( 'dashicons' );
	// El sitio no usa bloques de WooCommerce
	wp_dequeue_style( 'wc-blocks-style' );
	// Shim de Font Awesome 4 (los iconos usan FA5)
	wp_dequeue_style( 'font-awesome-4-shim' );
	wp_deregister_script( 'font-awesome-4-shim' );
}, 100 );

/* Micro-CSS de terceros: se integran inline para ahorrar peticiones criticas */
add_action( 'wp_enqueue_scripts', function () {
	$inline = array(
		'oceanwp-hamburgers' => get_template_directory() . '/assets/css/third/hamburgers/hamburgers.min.css',
		'oceanwp-3dx' => get_template_directory() . '/assets/css/third/hamburgers/types/3dx.css',
		'menu-icons-extra' => WP_PLUGIN_DIR . '/menu-icons/css/extra.min.css',
	);
	$css = '';
	foreach ( $inline as $h => $file ) {
		wp_dequeue_style( $h ); wp_deregister_style( $h );
		if ( is_readable( $file ) ) $css .= file_get_contents( $file );
	}
	if ( $css ) wp_add_inline_style( 'agro-bundle', $css );
}, 110 );

/* Algunos estilos se encolan durante el render: retirarlos justo antes de imprimir */
add_action( 'wp_print_styles', function () {
	foreach ( array( 'elementor-gf-local-roboto', 'elementor-gf-local-robotoslab', 'wc-blocks-style' ) as $h ) {
		wp_dequeue_style( $h ); wp_deregister_style( $h );
	}
	// Las fuentes se auto-alojan en el tema hijo: fuera cualquier hoja de fonts.googleapis
	global $wp_styles;
	foreach ( $wp_styles->queue as $h ) {
		$src = $wp_styles->registered[ $h ]->src ?? '';
		if ( strpos( $src, 'fonts.googleapis.com' ) !== false ) wp_dequeue_style( $h );
	}
}, 999 );
add_action( 'wp_footer', function () {
	foreach ( array( 'elementor-gf-local-roboto', 'elementor-gf-local-robotoslab' ) as $h ) {
		wp_dequeue_style( $h );
	}
}, 1 );

/* ---------- Rendimiento: precargas del hero (LCP) y primeras fuentes ---------- */
add_action( 'wp_head', function () {
	echo '<link rel="preload" as="font" type="font/woff2" crossorigin href="' . get_stylesheet_directory_uri() . '/assets/fonts/inter-56724408.woff2">' . "\n";
	if ( is_front_page() ) {
		echo '<link rel="preload" as="image" href="' . esc_url( content_url( '/uploads/2022/06/slide-1.jpg' ) ) . '" fetchpriority="high">' . "\n";
	}
}, 2 );

/* CSS de iconos y galería en carga diferida (no bloquean el primer render) */
add_filter( 'style_loader_tag', function ( $tag, $handle ) {
	$async = array( 'font-awesome', 'font-awesome-5-all', 'elementor-icons', 'elementor-icons-shared-0', 'simple-line-icons', 'photoswipe', 'photoswipe-default-skin', 'oceanwp-woo-star-font', 'oe-widgets-style', 'eael-general', 'elementor-icons' );
	if ( function_exists( 'is_woocommerce' ) && ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
		$async[] = 'oceanwp-woocommerce';
		$async[] = 'oceanwp-woo-mini-cart';
	}
	if ( in_array( $handle, $async, true ) && strpos( $tag, "media='print'" ) === false ) {
		$tag = str_replace( "media='all'", "media='print' onload=\"this.media='all'\"", $tag );
	}
	return $tag;
}, 20, 2 );

