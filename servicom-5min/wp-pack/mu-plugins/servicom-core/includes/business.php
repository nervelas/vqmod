<?php
/**
 * Servicom Core - Datos del negocio (fuente de verdad).
 *
 * Personalizador, helpers sc_biz() / sc_biz_all() y shortcodes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Saneadores
 * ---------------------------------------------------------------------- */

function sc_san_text( $v ): string {
	return mb_substr( sanitize_text_field( (string) $v ), 0, 160 );
}

function sc_san_msg( $v ): string {
	return mb_substr( sanitize_text_field( (string) $v ), 0, 300 );
}

function sc_san_multiline( $v ): string {
	return mb_substr( sanitize_textarea_field( (string) $v ), 0, 400 );
}

function sc_san_phone( $v ): string {
	$v = preg_replace( '/[^0-9+\-\s().]/', '', (string) $v );
	$v = preg_replace( '/\s+/', ' ', (string) $v );
	return mb_substr( trim( (string) $v ), 0, 30 );
}

function sc_san_digits( $v ): string {
	return mb_substr( preg_replace( '/\D+/', '', (string) $v ), 0, 20 );
}

function sc_san_email( $v ): string {
	$v = sanitize_email( (string) $v );
	return is_email( $v ) ? $v : '';
}

function sc_san_url( $v ): string {
	$v = trim( (string) $v );
	if ( '' === $v ) {
		return '';
	}
	if ( ! preg_match( '#^https?://#i', $v ) ) {
		if ( preg_match( '#^[a-z0-9.-]+\.[a-z]{2,}(/.*)?$#i', $v ) ) {
			$v = 'https://' . $v;
		} else {
			return '';
		}
	}
	return esc_url_raw( $v, array( 'http', 'https' ) );
}

/** Acepta URL completa o solo el usuario (@usuario) de cada red. */
function sc_san_social( $v, string $net = '' ): string {
	$v = trim( (string) $v );
	if ( '' === $v ) {
		return '';
	}
	$bases = array(
		'facebook'  => 'https://www.facebook.com/',
		'instagram' => 'https://www.instagram.com/',
		'tiktok'    => 'https://www.tiktok.com/@',
		'youtube'   => 'https://www.youtube.com/@',
		'x'         => 'https://x.com/',
		'linkedin'  => 'https://www.linkedin.com/company/',
	);
	if ( isset( $bases[ $net ] ) && preg_match( '/^@?[A-Za-z0-9._-]{2,60}$/', $v ) ) {
		return $bases[ $net ] . ltrim( $v, '@' );
	}
	return sc_san_url( $v );
}

function sc_san_toggle( $v ): int {
	return ( true === $v || 1 === $v || '1' === $v || 'true' === $v || 'on' === $v ) ? 1 : 0;
}

function sc_san_style( $v ): int {
	$v = (int) $v;
	return ( $v >= 1 && $v <= 5 ) ? $v : 1;
}

/* -------------------------------------------------------------------------
 * Definición de campos
 * ---------------------------------------------------------------------- */

function sc_style_names(): array {
	return (array) apply_filters(
		'sc_style_names',
		array(
			1 => 'Clásico elegante',
			2 => 'Moderno y limpio',
			3 => 'Vibrante y atrevido',
			4 => 'Cálido y cercano',
			5 => 'Oscuro profesional',
		)
	);
}

function sc_biz_sections(): array {
	return array(
		'sc_business'      => array(
			'title'       => 'Datos del negocio',
			'description' => 'Escribe aquí una sola vez los datos de tu negocio. Se actualizan solos en el encabezado, el pie, el botón de WhatsApp y la página de contacto.',
			'priority'    => 20,
		),
		'sc_social'        => array(
			'title'       => 'Redes sociales',
			'description' => 'Pega el enlace de tu perfil (o solo tu usuario). Las que dejes vacías no se muestran.',
			'priority'    => 21,
		),
		'sc_float'         => array(
			'title'       => 'Botones y pie de página',
			'description' => 'Activa o desactiva los botones que acompañan al visitante y el texto del pie.',
			'priority'    => 22,
		),
		'sc_style_section' => array(
			'title'       => 'Estilo visual',
			'description' => 'Elige el aspecto general de tu sitio. El contenido no se pierde al cambiarlo.',
			'priority'    => 23,
		),
	);
}

/**
 * Campos: clave sin prefijo => definición. El theme_mod real es "sc_<clave>".
 */
function sc_biz_fields(): array {
	static $fields = null;
	if ( null !== $fields ) {
		return $fields;
	}
	$fields = array(
		'nombre'         => array( 'section' => 'sc_business', 'label' => 'Nombre del negocio', 'type' => 'text', 'default' => '', 'san' => 'sc_san_text', 'desc' => 'Tal como quieres que aparezca en el sitio.' ),
		'telefono'       => array( 'section' => 'sc_business', 'label' => 'Teléfono', 'type' => 'text', 'default' => '', 'san' => 'sc_san_phone', 'desc' => 'Ejemplo: 2222-3333. Se puede tocar para llamar desde el celular.', 'attrs' => array( 'inputmode' => 'tel', 'autocomplete' => 'off' ) ),
		'whatsapp'       => array( 'section' => 'sc_business', 'label' => 'WhatsApp (con código de país)', 'type' => 'text', 'default' => '', 'san' => 'sc_san_digits', 'desc' => 'Solo números, con código de país. Ejemplo: 50255551234.', 'attrs' => array( 'inputmode' => 'numeric' ) ),
		'whatsapp_msg'   => array( 'section' => 'sc_business', 'label' => 'Mensaje inicial de WhatsApp', 'type' => 'text', 'default' => '', 'san' => 'sc_san_msg', 'desc' => 'Texto que aparece escrito cuando un cliente te escribe.' ),
		'correo'         => array( 'section' => 'sc_business', 'label' => 'Correo de contacto', 'type' => 'email', 'default' => '', 'san' => 'sc_san_email', 'desc' => 'Donde quieres recibir los mensajes.' ),
		'direccion'      => array( 'section' => 'sc_business', 'label' => 'Dirección', 'type' => 'textarea', 'default' => '', 'san' => 'sc_san_multiline', 'desc' => 'Puedes usar varias líneas.' ),
		'mapa_url'       => array( 'section' => 'sc_business', 'label' => 'Enlace de Google Maps', 'type' => 'url', 'default' => '', 'san' => 'sc_san_url', 'desc' => 'En Google Maps busca tu negocio, toca Compartir y copia el enlace.' ),
		'horario'        => array( 'section' => 'sc_business', 'label' => 'Horario de atención', 'type' => 'textarea', 'default' => '', 'san' => 'sc_san_multiline', 'desc' => 'Ejemplo: Lunes a viernes, 8:00 a 17:00.' ),
		'facebook'       => array( 'section' => 'sc_social', 'label' => 'Facebook', 'type' => 'url', 'default' => '', 'net' => 'facebook', 'desc' => 'Enlace de tu página.' ),
		'instagram'      => array( 'section' => 'sc_social', 'label' => 'Instagram', 'type' => 'text', 'default' => '', 'net' => 'instagram', 'desc' => 'Enlace o @usuario.' ),
		'tiktok'         => array( 'section' => 'sc_social', 'label' => 'TikTok', 'type' => 'text', 'default' => '', 'net' => 'tiktok', 'desc' => 'Enlace o @usuario.' ),
		'youtube'        => array( 'section' => 'sc_social', 'label' => 'YouTube', 'type' => 'url', 'default' => '', 'net' => 'youtube', 'desc' => 'Enlace de tu canal.' ),
		'x'              => array( 'section' => 'sc_social', 'label' => 'X (Twitter)', 'type' => 'text', 'default' => '', 'net' => 'x', 'desc' => 'Enlace o @usuario.' ),
		'linkedin'       => array( 'section' => 'sc_social', 'label' => 'LinkedIn', 'type' => 'url', 'default' => '', 'net' => 'linkedin', 'desc' => 'Enlace de tu perfil o empresa.' ),
		'float_wa'       => array( 'section' => 'sc_float', 'label' => 'Botón flotante de WhatsApp', 'type' => 'checkbox', 'default' => 1, 'san' => 'sc_san_toggle', 'desc' => 'Un botón verde siempre visible para escribirte.' ),
		'mobile_bar'     => array( 'section' => 'sc_float', 'label' => 'Barra inferior en celulares', 'type' => 'checkbox', 'default' => 1, 'san' => 'sc_san_toggle', 'desc' => 'Botones de Llamar y WhatsApp abajo en el celular.' ),
		'footer_credit'  => array( 'section' => 'sc_float', 'label' => 'Créditos del pie de página', 'type' => 'text', 'default' => 'Sitio creado por Servicom', 'san' => 'sc_san_text', 'empty_ok' => true, 'desc' => 'Texto pequeño al final de todas las páginas.' ),
		'style'          => array( 'section' => 'sc_style_section', 'label' => 'Estilo del sitio', 'type' => 'radio', 'default' => 1, 'san' => 'sc_san_style', 'desc' => '' ),
	);
	return $fields;
}

function sc_biz_sanitize( string $key, $value ) {
	$f = sc_biz_fields();
	if ( ! isset( $f[ $key ] ) ) {
		return $value;
	}
	if ( isset( $f[ $key ]['net'] ) ) {
		return sc_san_social( $value, $f[ $key ]['net'] );
	}
	return call_user_func( $f[ $key ]['san'], $value );
}

/* -------------------------------------------------------------------------
 * Helpers públicos
 * ---------------------------------------------------------------------- */

/**
 * Valor de un dato del negocio. Acepta "telefono" o "sc_telefono".
 */
function sc_biz( string $key, $default = '' ) {
	$key = ( 0 === strpos( $key, 'sc_' ) ) ? substr( $key, 3 ) : $key;
	$f   = sc_biz_fields();
	$def = ( func_num_args() > 1 ) ? $default : ( $f[ $key ]['default'] ?? '' );
	$v   = get_theme_mod( 'sc_' . $key, null );

	if ( isset( $f[ $key ]['type'] ) && 'checkbox' === $f[ $key ]['type'] ) {
		if ( null === $v || '' === $v ) {
			return (int) $def;
		}
		return (int) (bool) $v;
	}
	if ( 'style' === $key ) {
		return ( null === $v || '' === $v ) ? sc_san_style( $def ) : sc_san_style( $v );
	}
	if ( null === $v || false === $v ) {
		return $def;
	}
	if ( '' === $v ) {
		return ! empty( $f[ $key ]['empty_ok'] ) ? '' : $def;
	}
	return is_string( $v ) ? $v : $def;
}

/**
 * Todos los datos (claves sin prefijo) + logo + redes con valor.
 */
function sc_biz_all(): array {
	$out = array();
	foreach ( array_keys( sc_biz_fields() ) as $k ) {
		$out[ $k ] = sc_biz( $k );
	}
	if ( '' === $out['nombre'] ) {
		$out['nombre'] = (string) get_bloginfo( 'name' );
	}
	$out['redes']    = sc_biz_social();
	$logo_id         = (int) get_theme_mod( 'custom_logo', 0 );
	$out['logo_id']  = $logo_id;
	$out['logo_url'] = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'full' ) : '';
	return $out;
}

/** Redes con valor: red => URL. */
function sc_biz_social(): array {
	$r = array();
	foreach ( array( 'facebook', 'instagram', 'tiktok', 'youtube', 'x', 'linkedin' ) as $n ) {
		$u = (string) sc_biz( $n );
		if ( '' !== $u ) {
			$r[ $n ] = $u;
		}
	}
	return $r;
}

function sc_biz_name(): string {
	$n = (string) sc_biz( 'nombre' );
	return '' !== $n ? $n : (string) get_bloginfo( 'name' );
}

function sc_whatsapp_url( ?string $msg = null ): string {
	$n = (string) sc_biz( 'whatsapp' );
	if ( '' === $n ) {
		return '';
	}
	$msg = ( null === $msg ) ? (string) sc_biz( 'whatsapp_msg' ) : $msg;
	$url = 'https://wa.me/' . $n;
	if ( '' !== $msg ) {
		$url .= '?text=' . rawurlencode( $msg );
	}
	return $url;
}

function sc_tel_href(): string {
	$t = preg_replace( '/[^0-9+]/', '', (string) sc_biz( 'telefono' ) );
	return $t ? 'tel:' . $t : '';
}

function sc_maps_url(): string {
	$u = (string) sc_biz( 'mapa_url' );
	if ( '' !== $u ) {
		return $u;
	}
	$a = trim( preg_replace( '/\s+/', ' ', (string) sc_biz( 'direccion' ) ) );
	return '' !== $a ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $a ) : '';
}

function sc_social_label( string $n ): string {
	$l = array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'x' => 'X', 'linkedin' => 'LinkedIn' );
	return $l[ $n ] ?? ucfirst( $n );
}

function sc_social_icon_svg( string $n ): string {
	$p = array(
		'facebook'  => '<path fill="currentColor" d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H7v4h3v8h4v-8h3l1-4h-4V8.500c0-.3.200-.5.500-.5z"/>',
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.500" cy="6.500" r="1.300" fill="currentColor"/>',
		'tiktok'    => '<path fill="currentColor" d="M16 3c.3 2.300 1.700 3.900 4 4.100v3.100c-1.500 0-2.900-.5-4-1.300v6.200a5.100 5.100 0 1 1-5.100-5.100c.3 0 .6 0 .9.100v3.200a2 2 0 1 0 1.100 1.800V3z"/>',
		'youtube'   => '<path fill="currentColor" d="M21.600 7.200a2.500 2.500 0 0 0-1.800-1.800C18.200 5 12 5 12 5s-6.200 0-7.800.4A2.500 2.500 0 0 0 2.400 7.200C2 8.800 2 12 2 12s0 3.200.4 4.800a2.500 2.500 0 0 0 1.800 1.800C5.800 19 12 19 12 19s6.200 0 7.800-.4a2.500 2.500 0 0 0 1.800-1.800C22 15.200 22 12 22 12s0-3.200-.4-4.800zM10 15V9l5.200 3z"/>',
		'x'         => '<path fill="none" stroke="currentColor" stroke-width="2.400" stroke-linecap="round" d="M4.500 4.500l15 15M19.500 4.500l-15 15"/>',
		'linkedin'  => '<path fill="currentColor" d="M4 9h4v11H4zM6 3.500a2.300 2.300 0 1 1 0 4.600 2.300 2.300 0 0 1 0-4.600zM10 9h3.800v1.600c.6-1 1.900-1.900 3.800-1.900 3.600 0 4.400 2.300 4.400 5.300V20h-4v-5.200c0-1.300 0-2.800-1.800-2.800s-2.200 1.300-2.200 2.700V20h-4z"/>',
	);
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">' . ( $p[ $n ] ?? '' ) . '</svg>';
}

/* -------------------------------------------------------------------------
 * Personalizador
 * ---------------------------------------------------------------------- */

function sc_customizer_url( string $section = 'sc_business', string $return = '' ): string {
	$args = array( 'autofocus[section]' => $section );
	if ( '' !== $return ) {
		$args['return'] = $return;
	}
	return add_query_arg( $args, admin_url( 'customize.php' ) );
}

add_action( 'customize_register', 'sc_biz_customize_register', 20 );
function sc_biz_customize_register( $wp_customize ): void {
	foreach ( sc_biz_sections() as $id => $s ) {
		$wp_customize->add_section(
			$id,
			array(
				'title'       => $s['title'],
				'description' => $s['description'],
				'priority'    => $s['priority'],
				'capability'  => 'edit_theme_options',
			)
		);
	}

	foreach ( sc_biz_fields() as $key => $f ) {
		$id = 'sc_' . $key;
		if ( isset( $f['net'] ) ) {
			$net = $f['net'];
			$san = static function ( $v ) use ( $net ) {
				return sc_san_social( $v, $net );
			};
		} else {
			$san = $f['san'];
		}
		$wp_customize->add_setting(
			$id,
			array(
				'type'              => 'theme_mod',
				'default'           => $f['default'],
				'transport'         => 'refresh',
				'capability'        => 'edit_theme_options',
				'sanitize_callback' => $san,
			)
		);
		$args = array(
			'section'     => $f['section'],
			'label'       => $f['label'],
			'description' => $f['desc'],
			'settings'    => $id,
		);
		if ( 'radio' === $f['type'] ) {
			$args['type']    = 'radio';
			$args['choices'] = sc_style_names();
			$args['description'] = 'Elige el aspecto general de tu sitio.';
		} else {
			$args['type'] = $f['type'];
			if ( ! empty( $f['attrs'] ) ) {
				$args['input_attrs'] = $f['attrs'];
			}
		}
		$wp_customize->add_control( $id, $args );
	}
}

/** Oculta del Personalizador lo peligroso para el cliente. */
add_action( 'customize_register', 'sc_biz_customize_trim', 9999 );
function sc_biz_customize_trim( $wp_customize ): void {
	if ( ! function_exists( 'sc_is_client' ) || ! sc_is_client() ) {
		return;
	}
	$wp_customize->remove_section( 'custom_css' );
	$wp_customize->remove_section( 'themes' );
	$wp_customize->remove_panel( 'themes' );
	$wp_customize->remove_section( 'static_front_page' );
}

// Widgets: el componente se desactiva por completo para el cliente.
add_filter( 'customize_loaded_components', 'sc_biz_customize_components' );
function sc_biz_customize_components( $components ) {
	if ( function_exists( 'sc_is_client' ) && sc_is_client() ) {
		$components = array_values( array_diff( (array) $components, array( 'widgets' ) ) );
	}
	return $components;
}

add_action( 'customize_controls_print_styles', 'sc_biz_customize_css' );
function sc_biz_customize_css(): void {
	if ( ! function_exists( 'sc_is_client' ) || ! sc_is_client() ) {
		return;
	}
	echo '<style id="sc-customize-trim">#customize-theme-controls .customize-pane-parent #accordion-section-themes,#accordion-panel-widgets,#accordion-section-custom_css,#customize-save-button-wrapper+.customize-help-toggle{display:none!important}</style>';
}

/* -------------------------------------------------------------------------
 * Shortcodes
 * ---------------------------------------------------------------------- */

add_action( 'init', 'sc_biz_register_shortcodes' );
function sc_biz_register_shortcodes(): void {
	$map = array(
		'sc_nombre'         => 'sc_sc_nombre',
		'sc_telefono'       => 'sc_sc_telefono',
		'sc_telefono_link'  => 'sc_sc_telefono_link',
		'sc_whatsapp_link'  => 'sc_sc_whatsapp_link',
		'sc_correo'         => 'sc_sc_correo',
		'sc_direccion'      => 'sc_sc_direccion',
		'sc_horario'        => 'sc_sc_horario',
		'sc_redes'          => 'sc_sc_redes',
		'sc_mapa_link'      => 'sc_sc_mapa_link',
		'sc_anio'           => 'sc_sc_anio',
	);
	foreach ( $map as $tag => $cb ) {
		add_shortcode( $tag, $cb );
	}
}

function sc_sc_nombre(): string {
	return esc_html( sc_biz_name() );
}

function sc_sc_telefono(): string {
	return esc_html( (string) sc_biz( 'telefono' ) );
}

function sc_sc_telefono_link( $atts = array() ): string {
	$href = sc_tel_href();
	if ( '' === $href ) {
		return '';
	}
	$atts = shortcode_atts( array( 'texto' => '', 'class' => '' ), (array) $atts, 'sc_telefono_link' );
	$txt  = '' !== $atts['texto'] ? $atts['texto'] : (string) sc_biz( 'telefono' );
	$cls  = '' !== $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
	return '<a' . $cls . ' href="' . esc_url( $href, array( 'tel' ) ) . '">' . esc_html( $txt ) . '</a>';
}

function sc_sc_whatsapp_link( $atts = array() ): string {
	$atts = shortcode_atts( array( 'texto' => '', 'class' => '', 'mensaje' => '' ), (array) $atts, 'sc_whatsapp_link' );
	$url  = sc_whatsapp_url( '' !== $atts['mensaje'] ? $atts['mensaje'] : null );
	if ( '' === $url ) {
		return '';
	}
	$txt = '' !== $atts['texto'] ? $atts['texto'] : 'Escríbanos por WhatsApp';
	$cls = '' !== $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
	return '<a' . $cls . ' href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $txt ) . '</a>';
}

function sc_sc_correo(): string {
	$m = (string) sc_biz( 'correo' );
	if ( '' === $m ) {
		return '';
	}
	return '<a href="' . esc_url( 'mailto:' . $m, array( 'mailto' ) ) . '">' . esc_html( $m ) . '</a>';
}

function sc_sc_direccion(): string {
	return nl2br( esc_html( (string) sc_biz( 'direccion' ) ), false );
}

function sc_sc_horario(): string {
	return nl2br( esc_html( (string) sc_biz( 'horario' ) ), false );
}

function sc_sc_mapa_link( $atts = array() ): string {
	$atts = shortcode_atts( array( 'texto' => '', 'class' => '' ), (array) $atts, 'sc_mapa_link' );
	$url  = sc_maps_url();
	if ( '' === $url ) {
		return '';
	}
	$txt = '' !== $atts['texto'] ? $atts['texto'] : 'Ver en Google Maps';
	$cls = '' !== $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
	return '<a' . $cls . ' href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $txt ) . '</a>';
}

function sc_sc_redes( $atts = array() ): string {
	$redes = sc_biz_social();
	if ( ! $redes ) {
		return '';
	}
	$atts = shortcode_atts( array( 'class' => '' ), (array) $atts, 'sc_redes' );
	$cls  = 'sc-redes' . ( '' !== $atts['class'] ? ' ' . $atts['class'] : '' );
	$out  = '<ul class="' . esc_attr( $cls ) . '">';
	foreach ( $redes as $n => $url ) {
		$label = sc_social_label( $n );
		$out  .= '<li><a class="sc-redes__link sc-redes__link--' . esc_attr( $n ) . '" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $label . ' (se abre en otra pestaña)' ) . '">'
			. sc_social_icon_svg( $n ) . '</a></li>';
	}
	return $out . '</ul>';
}

function sc_sc_anio(): string {
	return esc_html( wp_date( 'Y' ) );
}

add_action( 'wp_enqueue_scripts', 'sc_biz_enqueue_front' );
function sc_biz_enqueue_front(): void {
	if ( defined( 'SC_CORE_URL' ) && SC_CORE_URL ) {
		wp_enqueue_style( 'servicom-core-front', SC_CORE_URL . '/assets/front.css', array(), SC_CORE_VERSION );
	}
}
