<?php
/**
 * Servicom Core - Pendientes por completar.
 *
 * Después de crear la web desde la presentación del cliente puede faltar algún dato (teléfono, WhatsApp, correo…).
 * Este módulo lo detecta, lo muestra en el panel (menú PENDIENTES, aviso del Escritorio, widget y cabecera de
 * INSTRUCCIONES) y cada elemento lleva un enlace directo al editor, con el campo exacto ya abierto.
 */

defined( 'ABSPATH' ) || exit;

/** Datos que pueden faltar: clave => [etiqueta, nivel, por qué importa, ejemplo]. */
function sc_pending_defs(): array {
	return array(
		'logo'      => array( 'Logo de su negocio', 'important', 'Aparece en el encabezado, el pie y la pestaña del navegador.', '' ),
		'telefono'  => array( 'Teléfono', 'important', 'Activa el botón flotante «Llamar» y se muestra en contacto y pie.', 'Ejemplo: 2222-3333' ),
		'whatsapp'  => array( 'WhatsApp', 'important', 'Activa el botón flotante de WhatsApp y los botones «Escríbanos».', 'Solo números con código de país. Ejemplo: 50255551234' ),
		'correo'    => array( 'Correo de contacto', 'important', 'Activa el botón flotante de correo y recibe los mensajes del formulario.', 'Ejemplo: info@sudominio.com' ),
		'direccion' => array( 'Dirección', 'important', 'Se muestra en contacto y pie para que lo encuentren.', '' ),
		'horario'   => array( 'Horario de atención', 'tip', 'Los clientes saben cuándo pueden visitarle o llamarle.', 'Ejemplo: Lunes a viernes, 8:00 a 17:00' ),
		'mapa_url'  => array( 'Enlace de Google Maps', 'tip', 'Muestra el botón «Cómo llegar».', 'En Google Maps toque Compartir y copie el enlace.' ),
		'redes'     => array( 'Redes sociales', 'tip', 'Facebook, Instagram, etc. aparecen como iconos en el pie.', 'Pegue el enlace de su perfil.' ),
	);
}

function sc_pending_skipped(): array {
	$s = get_option( 'sc_pending_skip', array() );
	return is_array( $s ) ? array_map( 'strval', $s ) : array();
}

/** Enlace directo al editor con el campo exacto abierto. */
function sc_pending_url( string $key ): string {
	$field = ( 'redes' === $key ) ? 'facebook' : $key;
	return add_query_arg( array( 'sc_edit' => 1, 'sc_biz' => $field ), home_url( '/' ) );
}

/** Lista de pendientes: cada uno [key,label,level,why,hint,url]. */
function sc_pending_items( bool $include_skipped = false ): array {
	if ( ! function_exists( 'sc_biz' ) ) {
		return array();
	}
	$skip = $include_skipped ? array() : sc_pending_skipped();
	$out  = array();
	foreach ( sc_pending_defs() as $key => $d ) {
		if ( in_array( $key, $skip, true ) ) {
			continue;
		}
		if ( 'logo' === $key ) {
			$has = (int) get_theme_mod( 'custom_logo', 0 ) > 0;
		} elseif ( 'redes' === $key ) {
			$has = false;
			foreach ( array( 'facebook', 'instagram', 'tiktok', 'youtube', 'x', 'linkedin' ) as $n ) {
				if ( '' !== trim( (string) sc_biz( $n, '' ) ) ) {
					$has = true;
					break;
				}
			}
		} else {
			$has = '' !== trim( (string) sc_biz( $key, '' ) );
		}
		if ( ! $has ) {
			$out[] = array( 'key' => $key, 'label' => $d[0], 'level' => $d[1], 'why' => $d[2], 'hint' => $d[3], 'url' => sc_pending_url( $key ) );
		}
	}
	return $out;
}

function sc_pending_count(): int {
	return count( sc_pending_items() );
}

/** Caja con la lista; $compact para el widget. */
function sc_pending_render_box( bool $compact = false ): void {
	$items = sc_pending_items();
	echo '<section class="sc-pend' . ( $compact ? ' sc-pend--compact' : '' ) . '" aria-label="Pendientes por completar">';
	if ( ! $items ) {
		echo '<p class="sc-pend__ok"><strong>¡Todo listo!</strong> Su sitio tiene todos los datos importantes.</p></section>';
		return;
	}
	echo '<h2 class="sc-pend__t">Falta completar ' . (int) count( $items ) . ( 1 === count( $items ) ? ' dato' : ' datos' ) . '</h2>';
	echo '<p class="sc-pend__lead">Su web ya está creada. Estos datos no venían en su archivo: pulse el botón y lo llevamos al lugar exacto para escribirlo.</p><ul class="sc-pend__list">';
	foreach ( $items as $it ) {
		echo '<li class="sc-pend__it sc-pend__it--' . esc_attr( $it['level'] ) . '"><div class="sc-pend__tx"><strong>' . esc_html( $it['label'] ) . '</strong>';
		echo ( 'important' === $it['level'] ? ' <span class="sc-pend__tag">Importante</span>' : ' <span class="sc-pend__tag sc-pend__tag--tip">Recomendado</span>' );
		if ( ! $compact ) {
			echo '<small>' . esc_html( $it['why'] . ( '' !== $it['hint'] ? ' ' . $it['hint'] : '' ) ) . '</small>';
		}
		echo '</div><div class="sc-pend__act"><a class="button button-primary" href="' . esc_url( $it['url'] ) . '">Completar ahora</a>';
		if ( 'tip' === $it['level'] || ! $compact ) {
			$u = wp_nonce_url( admin_url( 'admin-post.php?action=sc_pending_skip&k=' . rawurlencode( $it['key'] ) ), 'sc_pending_skip_' . $it['key'] );
			echo ' <a class="sc-pend__skip" href="' . esc_url( $u ) . '">Este negocio no lo tiene</a>';
		}
		echo '</div></li>';
	}
	echo '</ul></section>';
}

add_action( 'admin_post_sc_pending_skip', function (): void {
	$k = isset( $_GET['k'] ) ? sanitize_key( (string) $_GET['k'] ) : ''; // phpcs:ignore
	if ( ! current_user_can( 'edit_pages' ) || ! isset( sc_pending_defs()[ $k ] ) || ! wp_verify_nonce( (string) ( $_GET['_wpnonce'] ?? '' ), 'sc_pending_skip_' . $k ) ) { // phpcs:ignore
		wp_die( esc_html( 'No se pudo completar la acción.' ) );
	}
	$s   = sc_pending_skipped();
	$s[] = $k;
	update_option( 'sc_pending_skip', array_values( array_unique( $s ) ), false );
	wp_safe_redirect( admin_url( 'admin.php?page=sc-pendientes' ) );
	exit;
} );

add_action( 'admin_menu', function (): void {
	$n = sc_pending_count();
	if ( $n < 1 ) {
		return;
	}
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#f0b849" d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm1 12H9v-2h2zm0-3H9V6h2z"/></svg>';
	add_menu_page( 'Pendientes', 'PENDIENTES <span class="awaiting-mod">' . (int) $n . '</span>', 'edit_pages', 'sc-pendientes', 'sc_pending_page', 'data:image/svg+xml;base64,' . base64_encode( $svg ), 1.4 );
}, 2 );

function sc_pending_page(): void {
	echo '<div class="wrap sc-pend-wrap"><h1 class="screen-reader-text">Pendientes</h1>';
	sc_pending_render_box( false );
	echo '</div>';
	sc_pending_css();
}

function sc_pending_css(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	echo '<style>.sc-pend{background:#fff;border:1px solid #e6dfcf;border-left:4px solid #c9a45c;border-radius:12px;padding:18px 22px;margin:16px 0;max-width:980px}.sc-pend__t{margin:0 0 4px;font-size:20px}.sc-pend__lead{margin:0 0 12px;color:#50575e}.sc-pend__list{margin:0;padding:0;list-style:none}.sc-pend__it{display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:12px 0;border-top:1px solid #f0ece2}.sc-pend__tx small{display:block;color:#646970;margin-top:3px;max-width:560px}.sc-pend__tag{display:inline-block;margin-left:6px;padding:1px 8px;border-radius:99px;background:#fdecc8;color:#7a5200;font-size:11px;font-weight:600}.sc-pend__tag--tip{background:#e8eef7;color:#2c4a7c}.sc-pend__skip{margin-left:10px;font-size:12px;color:#787c82}.sc-pend__ok{margin:0;color:#1a6b3a}.sc-pend--compact{box-shadow:none;border:0;padding:0;margin:0}.sc-pend--compact .sc-pend__t{font-size:15px}.sc-pend--compact .sc-pend__it{padding:8px 0}#adminmenu .toplevel_page_sc-pendientes{background:rgba(240,184,73,.12)}</style>';
}

add_action( 'admin_head', function (): void {
	if ( sc_pending_count() > 0 ) {
		sc_pending_css();
	}
} );

// Aviso en el Escritorio y en INSTRUCCIONES.
add_action( 'admin_notices', function (): void {
	$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $s || ! in_array( $s->id, array( 'dashboard', 'toplevel_page_sc-instrucciones' ), true ) || ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$n = sc_pending_count();
	if ( $n < 1 ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>Faltan ' . (int) $n . ( 1 === $n ? ' dato' : ' datos' ) . ' por completar en su web.</strong> <a href="' . esc_url( admin_url( 'admin.php?page=sc-pendientes' ) ) . '">Ver qué falta y completarlo</a></p></div>';
} );

add_action( 'wp_dashboard_setup', function (): void {
	if ( current_user_can( 'edit_pages' ) && sc_pending_count() > 0 ) {
		wp_add_dashboard_widget( 'sc_pending_widget', 'Pendientes por completar', function () { sc_pending_render_box( true ); } );
	}
} );
