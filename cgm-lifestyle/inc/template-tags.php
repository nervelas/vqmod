<?php
/**
 * Funciones de plantilla reutilizables.
 *
 * @package CGM_Lifestyle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Devuelve el HTML del logotipo (imagen administrable o SVG por defecto).
 *
 * @param string $variant 'default' o 'light'.
 * @return string
 */
function cgm_logo_html( $variant = 'default' ) {
	$brand = cgm_get_option( 'brand_name', 'CGM' );
	$key   = ( 'light' === $variant && cgm_get_option( 'logo_light_id' ) ) ? 'logo_light_id' : 'logo_id';
	$id    = absint( cgm_get_option( $key, 0 ) );

	if ( $id ) {
		$img = wp_get_attachment_image( $id, 'full', false, array( 'class' => 'cgm-logo__img', 'alt' => $brand ) );
		if ( $img ) {
			return $img;
		}
	}
	// SVG por defecto (usa currentColor para adaptarse al contexto).
	$svg = CGM_THEME_DIR . '/assets/images/logo-cgm.svg';
	if ( file_exists( $svg ) ) {
		$markup = file_get_contents( $svg ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return '<span class="cgm-logo__svg" aria-hidden="false">' . $markup . '</span>';
	}
	return '<span class="cgm-logo__text">' . esc_html( $brand ) . '</span>';
}

/**
 * Enlace principal de WhatsApp.
 *
 * @return string
 */
function cgm_whatsapp_url() {
	$url = cgm_get_option( 'whatsapp_link' );
	return $url ? $url : '#';
}

/**
 * Redes sociales disponibles.
 *
 * @return array clave => url
 */
function cgm_social_links() {
	$map = array(
		'facebook'  => cgm_get_option( 'facebook' ),
		'instagram' => cgm_get_option( 'instagram' ),
		'tiktok'    => cgm_get_option( 'tiktok' ),
	);
	return array_filter( $map );
}

/**
 * Iconos SVG inline (line icons, currentColor).
 *
 * @param string $name Nombre del icono.
 * @return string
 */
function cgm_icon( $name ) {
	$icons = array(
		'search'   => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
		'user'     => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
		'cart'     => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
		'menu'     => '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
		'close'    => '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
		'whatsapp' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M17.5 14.4c-.3-.15-1.7-.85-2-.95-.26-.1-.45-.15-.65.15-.2.3-.75.95-.9 1.15-.16.2-.33.22-.62.07-1.7-.85-2.8-1.52-3.92-3.44-.3-.5.3-.47.85-1.56.1-.2.05-.37-.02-.52-.08-.15-.65-1.57-.9-2.15-.24-.57-.48-.5-.65-.5h-.55c-.2 0-.5.07-.77.37s-1 1-1 2.42 1.03 2.8 1.17 3c.15.2 2.02 3.08 4.9 4.32 1.82.78 2.53.85 3.44.72.55-.08 1.7-.7 1.94-1.36.24-.67.24-1.24.17-1.36-.07-.13-.26-.2-.55-.35zM12 2a10 10 0 0 0-8.6 15.05L2 22l5.05-1.32A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.18-1.14l-.3-.18-3 .78.8-2.92-.2-.3A8.2 8.2 0 1 1 12 20.2z"/></svg>',
		'facebook' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12z"/></svg>',
		'instagram'=> '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><line x1="17.5" y1="6.5" x2="17.5" y2="6.5"/></svg>',
		'tiktok'   => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M16.6 5.82A4.28 4.28 0 0 1 15.5 3h-3v13.4a2.6 2.6 0 1 1-2.6-2.6c.27 0 .53.04.78.12v-3.1a5.7 5.7 0 0 0-.78-.06 5.68 5.68 0 1 0 5.68 5.68V8.9a7.3 7.3 0 0 0 4.02 1.2V7.1a4.28 4.28 0 0 1-3-1.28z"/></svg>',
		'mail'     => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>',
		'phone'    => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.6a2 2 0 0 1-.4 2.1L8 9.6a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.5c.8.3 1.7.5 2.6.6a2 2 0 0 1 1.7 2z"/></svg>',
	);
	return $icons[ $name ] ?? '';
}

/**
 * Encabezado de sección estándar.
 *
 * @param string $title    Título.
 * @param string $subtitle Subtítulo.
 * @param string $tag      Etiqueta del título (h2/h3...).
 */
function cgm_section_header( $title, $subtitle = '', $tag = 'h2' ) {
	if ( ! $title && ! $subtitle ) {
		return;
	}
	echo '<div class="cgm-section-head reveal">';
	if ( $title ) {
		printf( '<%1$s class="cgm-section-title">%2$s</%1$s>', esc_html( $tag ), esc_html( $title ) );
	}
	if ( $subtitle ) {
		echo '<p class="cgm-section-sub">' . esc_html( $subtitle ) . '</p>';
	}
	echo '</div>';
}

/**
 * URL de la tienda de forma segura (o inicio si no está configurada).
 *
 * @return string
 */
function cgm_shop_url() {
	if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_id' ) ) {
		$id = wc_get_page_id( 'shop' );
		if ( $id > 0 ) {
			$url = get_permalink( $id );
			if ( $url ) {
				return $url;
			}
		}
	}
	return home_url( '/' );
}

/**
 * URL de "Mi cuenta" de forma segura (vacío si no está configurada).
 *
 * @return string
 */
function cgm_account_url() {
	if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_id' ) ) {
		$id = wc_get_page_id( 'myaccount' );
		if ( $id > 0 ) {
			$url = get_permalink( $id );
			if ( $url ) {
				return $url;
			}
		}
	}
	return '';
}

/**
 * Menú de reserva cuando no hay menú asignado a la ubicación "primary".
 *
 * @param string $class Clase de la lista.
 */
function cgm_fallback_menu( $class = 'cgm-menu' ) {
	$items = array(
		home_url( '/' ) => __( 'Inicio', 'cgm-lifestyle' ),
	);
	$about = get_page_by_path( 'sobre-nosotros' );
	if ( $about ) {
		$items[ get_permalink( $about ) ] = __( 'Sobre Nosotros', 'cgm-lifestyle' );
	}
	if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_id' ) ) {
		$shop = wc_get_page_id( 'shop' );
		if ( $shop > 0 ) {
			$items[ get_permalink( $shop ) ] = __( 'Nuestros Productos', 'cgm-lifestyle' );
		}
	}
	$contact = get_page_by_path( 'contacto' );
	if ( $contact ) {
		$items[ get_permalink( $contact ) ] = __( 'Contacto', 'cgm-lifestyle' );
	}

	echo '<ul class="' . esc_attr( $class ) . '">';
	foreach ( $items as $url => $label ) {
		printf( '<li class="menu-item"><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $label ) );
	}
	echo '</ul>';
}

/**
 * Contador de items del carrito (para el header).
 *
 * @return int
 */
function cgm_cart_count() {
	if ( function_exists( 'WC' ) && WC()->cart ) {
		return (int) WC()->cart->get_cart_contents_count();
	}
	return 0;
}
