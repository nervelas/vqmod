<?php
/**
 * Configuración del tema: soportes, menús, image sizes y assets.
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Soportes del tema.
 */
function cgm_theme_setup() {
	load_theme_textdomain( 'cgm-lifestyle', CGM_THEME_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 120,
			'width'       => 300,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	// WooCommerce.
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	register_nav_menus(
		array(
			'primary' => __( 'Menú principal', 'cgm-lifestyle' ),
			'footer'  => __( 'Menú del footer', 'cgm-lifestyle' ),
		)
	);

	// Tamaños de imagen responsive.
	add_image_size( 'cgm-hero', 1920, 900, true );
	add_image_size( 'cgm-hero-mobile', 800, 900, true );
	add_image_size( 'cgm-card', 640, 640, true );
}
add_action( 'after_setup_theme', 'cgm_theme_setup' );

/**
 * Ancho de contenido.
 */
function cgm_content_width() {
	$GLOBALS['content_width'] = 1200;
}
add_action( 'after_setup_theme', 'cgm_content_width', 0 );

/**
 * Áreas de widgets.
 */
function cgm_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'Footer', 'cgm-lifestyle' ),
			'id'            => 'footer-1',
			'description'   => __( 'Columna de widgets en el footer.', 'cgm-lifestyle' ),
			'before_widget' => '<div id="%1$s" class="widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);
	register_sidebar(
		array(
			'name'          => __( 'Tienda (lateral)', 'cgm-lifestyle' ),
			'id'            => 'shop-sidebar',
			'description'   => __( 'Se muestra en la tienda y el archivo de productos.', 'cgm-lifestyle' ),
			'before_widget' => '<div id="%1$s" class="widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);
}
add_action( 'widgets_init', 'cgm_widgets_init' );

/**
 * Encola estilos y scripts del front.
 */
function cgm_enqueue_assets() {
	wp_enqueue_style( 'cgm-fonts', 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap', array(), null );
	wp_enqueue_style( 'cgm-main', CGM_THEME_URI . '/assets/css/main.css', array(), CGM_THEME_VERSION );

	wp_enqueue_script( 'cgm-main', CGM_THEME_URI . '/assets/js/main.js', array(), CGM_THEME_VERSION, true );
	wp_localize_script(
		'cgm-main',
		'CGM',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'isCart'   => function_exists( 'is_cart' ) ? is_cart() : false,
		)
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'cgm_enqueue_assets' );

/**
 * Preconnect a Google Fonts para mejorar LCP.
 */
function cgm_resource_hints( $hints, $relation ) {
	if ( 'preconnect' === $relation ) {
		$hints[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $hints;
}
add_filter( 'wp_resource_hints', 'cgm_resource_hints', 10, 2 );

/**
 * Favicon administrable (si el usuario no ha definido el icono del sitio de WP).
 */
function cgm_custom_favicon() {
	if ( has_site_icon() ) {
		return;
	}
	if ( function_exists( 'cgm_get_image_url' ) ) {
		$favicon = cgm_get_image_url( 'favicon_id', 'full' );
		if ( $favicon ) {
			echo '<link rel="icon" href="' . esc_url( $favicon ) . '">' . "\n";
			echo '<link rel="apple-touch-icon" href="' . esc_url( $favicon ) . '">' . "\n";
		}
	}
}
add_action( 'wp_head', 'cgm_custom_favicon' );

/**
 * Clases útiles en el body.
 */
function cgm_body_classes( $classes ) {
	if ( is_front_page() ) {
		$classes[] = 'cgm-front';
	}
	$classes[] = 'cgm-theme';
	return $classes;
}
add_filter( 'body_class', 'cgm_body_classes' );

/**
 * Fallback de cgm_get_option cuando el plugin CGM Core no está activo,
 * para que el tema no se rompa.
 */
if ( ! function_exists( 'cgm_get_option' ) ) {
	/**
	 * Getter mínimo de reserva.
	 *
	 * @param string $key     Clave.
	 * @param mixed  $default Valor por defecto.
	 * @return mixed
	 */
	function cgm_get_option( $key, $default = '' ) {
		$opts = get_option( 'cgm_settings', array() );
		if ( is_array( $opts ) && isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) {
			return $opts[ $key ];
		}
		return $default;
	}
}

if ( ! function_exists( 'cgm_get_image_url' ) ) {
	function cgm_get_image_url( $key, $size = 'full', $fallback = '' ) {
		$id = absint( cgm_get_option( $key, 0 ) );
		if ( $id ) {
			$url = wp_get_attachment_image_url( $id, $size );
			if ( $url ) {
				return $url;
			}
		}
		return $fallback;
	}
}

if ( ! function_exists( 'cgm_format_text' ) ) {
	function cgm_format_text( $text ) {
		return wpautop( esc_html( (string) $text ) );
	}
}
