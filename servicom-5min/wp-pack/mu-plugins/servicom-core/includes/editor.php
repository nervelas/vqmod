<?php
/**
 * Servicom Core - Editor en el sitio (LUXE, contrato §6).
 *
 * - Capacidad `sc_edit_site` (rol cliente y administradores).
 * - REST `sc/v1/edit` (POST), `sc/v1/state` (GET) y `sc/v1/icons` (GET).
 * - Encola la barra y el modo edición (assets/editor.js y editor.css) solo para quien puede editar.
 *
 * La opción `sc_site` (modelo de contenido) solo se modifica con operaciones atómicas validadas
 * por tipo y por lista blanca de rutas: nunca se escribe HTML y nunca se crean claves nuevas.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SC_ED_CAP      = 'sc_edit_site';
const SC_ED_MAX_OPS  = 40;
const SC_ED_MAX_BODY = 262144;
const SC_ED_HISTORY  = 25;
const SC_ED_MAX_TEXT = 2000;

final class SC_Ed_Error extends Exception {
}

/* -------------------------------------------------------------------------
 * Capacidad
 * ---------------------------------------------------------------------- */

add_filter( 'sc_client_caps', 'sc_ed_client_caps' );
function sc_ed_client_caps( $caps ) {
	$caps   = (array) $caps;
	$caps[] = SC_ED_CAP;
	return array_values( array_unique( $caps ) );
}

// Los administradores (manage_options) siempre pueden editar.
add_filter( 'user_has_cap', 'sc_ed_admin_cap', 10, 4 );
function sc_ed_admin_cap( $allcaps, $caps, $args, $user ) {
	if ( ! empty( $allcaps['manage_options'] ) ) {
		$allcaps[ SC_ED_CAP ] = true;
	}
	return $allcaps;
}

function sc_ed_can(): bool {
	return is_user_logged_in() && ( current_user_can( SC_ED_CAP ) || current_user_can( 'manage_options' ) );
}

/* -------------------------------------------------------------------------
 * Catálogos
 * ---------------------------------------------------------------------- */

function sc_ed_fonts(): array {
	return array(
		'head' => array(
			'cormorant' => 'Cormorant Garamond',
			'playfair'  => 'Playfair Display',
			'fraunces'  => 'Fraunces',
			'dmserif'   => 'DM Serif Display',
			'sora'      => 'Sora',
		),
		'body' => array(
			'inter'   => 'Inter',
			'manrope' => 'Manrope',
			'dmsans'  => 'DM Sans',
			'lato'    => 'Lato',
			'nunito'  => 'Nunito Sans',
		),
	);
}

function sc_ed_biz_keys(): array {
	return array(
		'nombre'       => 'Nombre del negocio',
		'telefono'     => 'Teléfono',
		'whatsapp'     => 'WhatsApp',
		'whatsapp_msg' => 'Mensaje de WhatsApp',
		'correo'       => 'Correo',
		'direccion'    => 'Dirección',
		'mapa_url'     => 'Enlace de Google Maps',
		'horario'      => 'Horario',
		'facebook'     => 'Facebook',
		'instagram'    => 'Instagram',
		'tiktok'       => 'TikTok',
		'youtube'      => 'YouTube',
		'x'            => 'X (Twitter)',
		'linkedin'     => 'LinkedIn',
	);
}

function sc_ed_section_label( string $type ): string {
	if ( function_exists( 'sc_lx_section_label' ) ) {
		return (string) sc_lx_section_label( $type );
	}
	return $type;
}

/* -------------------------------------------------------------------------
 * Rutas del modelo
 * ---------------------------------------------------------------------- */

function sc_ed_site(): array {
	$o = get_option( 'sc_site', array() );
	return is_array( $o ) ? $o : array();
}

function sc_ed_parts( $path ): array {
	if ( ! is_string( $path ) || '' === $path || strlen( $path ) > 160 || ! preg_match( '/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)*$/', $path ) ) {
		throw new SC_Ed_Error( 'La ruta del elemento no es válida.' );
	}
	return explode( '.', $path );
}

/** @return array{0:bool,1:mixed} */
function sc_ed_find( array $a, array $parts ): array {
	$cur = $a;
	foreach ( $parts as $p ) {
		if ( is_array( $cur ) && array_key_exists( $p, $cur ) ) {
			$cur = $cur[ $p ];
		} else {
			return array( false, null );
		}
	}
	return array( true, $cur );
}

function sc_ed_put( array &$a, array $parts, $val ): void {
	$ref = &$a;
	foreach ( $parts as $p ) {
		$ref = &$ref[ $p ];
	}
	$ref = $val;
	unset( $ref );
}

/** Zona editable de una ruta: page|service|seo|footer|brand o '' (prohibida). */
function sc_ed_zone( array $parts ): string {
	$n = count( $parts );
	if ( 'pages' === $parts[0] ) {
		if ( $n >= 6 && 'sections' === $parts[2] && ctype_digit( $parts[3] ) && 'data' === $parts[4] ) {
			return 'page';
		}
		return '';
	}
	if ( 'services' === $parts[0] ) {
		if ( 3 === $n && ctype_digit( $parts[1] ) && in_array( $parts[2], array( 'nombre', 'resumen', 'descripcion', 'icono', 'img' ), true ) ) {
			return 'service';
		}
		return '';
	}
	if ( 2 === $n && 'seo' === $parts[0] && 'description' === $parts[1] ) {
		return 'seo';
	}
	if ( 2 === $n && 'footer' === $parts[0] && in_array( $parts[1], array( 'texto', 'credit' ), true ) ) {
		return 'footer';
	}
	if ( 2 === $n && 'brand' === $parts[0] && in_array( $parts[1], array( 'servicios_label', 'eyebrow' ), true ) ) {
		return 'brand';
	}
	return '';
}

function sc_ed_clean_text( string $v ): string {
	$v = str_replace( array( "\r\n", "\r" ), "\n", $v );
	$v = sanitize_textarea_field( $v );
	return trim( $v );
}

function sc_ed_attachment_ok( int $id ): bool {
	return $id > 0 && 'attachment' === get_post_type( $id ) && wp_attachment_is_image( $id );
}

/* -------------------------------------------------------------------------
 * Operaciones sobre contenido
 * ---------------------------------------------------------------------- */

function sc_ed_op_text( array &$site, array $op ): array {
	$parts = sc_ed_parts( $op['path'] ?? '' );
	if ( '' === sc_ed_zone( $parts ) ) {
		throw new SC_Ed_Error( 'Ese texto no se puede editar.' );
	}
	$leaf = end( $parts );
	if ( in_array( $leaf, array( 'id', 'seed', 'on', 'type', 'post', 'origen', 'url', 'icon', 'icono', 'seal_icon', 'badge_icon', 'limit', 'v' ), true ) ) {
		throw new SC_Ed_Error( 'Ese valor no es un texto editable.' );
	}
	list( $found, $cur ) = sc_ed_find( $site, $parts );
	if ( ! $found || ! is_string( $cur ) ) {
		throw new SC_Ed_Error( 'Ese texto ya no existe en la página. Recargue e intente de nuevo.' );
	}
	$v = $op['value'] ?? null;
	if ( ! is_string( $v ) ) {
		throw new SC_Ed_Error( 'El texto no es válido.' );
	}
	if ( mb_strlen( $v ) > SC_ED_MAX_TEXT * 2 ) {
		throw new SC_Ed_Error( 'El texto es demasiado largo (máximo ' . SC_ED_MAX_TEXT . ' caracteres).' );
	}
	$v = sc_ed_clean_text( $v );
	if ( mb_strlen( $v ) > SC_ED_MAX_TEXT ) {
		throw new SC_Ed_Error( 'El texto es demasiado largo (máximo ' . SC_ED_MAX_TEXT . ' caracteres).' );
	}
	sc_ed_put( $site, $parts, $v );
	return array( 'op' => 'text', 'path' => implode( '.', $parts ), 'value' => $v );
}

function sc_ed_op_img( array &$site, array $op ): array {
	$parts = sc_ed_parts( $op['path'] ?? '' );
	$zone  = sc_ed_zone( $parts );
	if ( '' === $zone ) {
		throw new SC_Ed_Error( 'Esa imagen no se puede cambiar.' );
	}
	list( $found, $cur ) = sc_ed_find( $site, $parts );
	if ( ! $found || ! is_array( $cur ) || ! array_key_exists( 'id', $cur ) ) {
		throw new SC_Ed_Error( 'Ese espacio de imagen ya no existe. Recargue e intente de nuevo.' );
	}
	$id = isset( $op['id'] ) && is_numeric( $op['id'] ) ? (int) $op['id'] : -1;
	if ( $id < 0 ) {
		throw new SC_Ed_Error( 'La imagen elegida no es válida.' );
	}
	if ( $id > 0 && ! sc_ed_attachment_ok( $id ) ) {
		throw new SC_Ed_Error( 'La imagen elegida no existe o no es una imagen.' );
	}
	$cur['id'] = $id;
	if ( ! isset( $cur['seed'] ) ) {
		$cur['seed'] = abs( crc32( implode( '.', $parts ) ) ) % 9000 + 11;
	}
	sc_ed_put( $site, $parts, $cur );
	return array( 'op' => 'img', 'path' => implode( '.', $parts ), 'id' => $id );
}

function sc_ed_op_icon( array &$site, array $op ): array {
	$parts = sc_ed_parts( $op['path'] ?? '' );
	$leaf  = end( $parts );
	if ( '' === sc_ed_zone( $parts ) || ! in_array( $leaf, array( 'icon', 'icono', 'seal_icon', 'badge_icon' ), true ) ) {
		throw new SC_Ed_Error( 'Ese ícono no se puede cambiar.' );
	}
	list( $found, $cur ) = sc_ed_find( $site, $parts );
	if ( ! $found || ! is_string( $cur ) ) {
		throw new SC_Ed_Error( 'Ese ícono ya no existe. Recargue e intente de nuevo.' );
	}
	$key = $op['key'] ?? '';
	if ( ! function_exists( 'sc_icon_keys' ) || ! is_string( $key ) || ! in_array( $key, sc_icon_keys(), true ) ) {
		throw new SC_Ed_Error( 'Ese ícono no está disponible.' );
	}
	sc_ed_put( $site, $parts, $key );
	return array( 'op' => 'icon', 'path' => implode( '.', $parts ), 'key' => $key );
}

/** Valida un destino de enlace y lo devuelve normalizado. */
function sc_ed_clean_url( $u, array $site ): string {
	$u = is_string( $u ) ? trim( $u ) : '';
	if ( '' === $u ) {
		throw new SC_Ed_Error( 'Elija o escriba el destino del enlace.' );
	}
	if ( mb_strlen( $u ) > 500 ) {
		throw new SC_Ed_Error( 'El destino del enlace es demasiado largo.' );
	}
	if ( in_array( $u, array( 'wa', 'tel', 'mail' ), true ) ) {
		return $u;
	}
	if ( preg_match( '/^page:([a-z0-9_]+)$/', $u, $m ) ) {
		$ids = get_option( 'sc_page_ids', array() );
		if ( ( is_array( $ids ) && ! empty( $ids[ $m[1] ] ) ) || isset( $site['pages'][ $m[1] ] ) ) {
			return $u;
		}
		throw new SC_Ed_Error( 'Esa página del sitio no existe.' );
	}
	if ( preg_match( '/^svc:(\d{1,3})$/', $u, $m ) ) {
		if ( isset( $site['services'][ (int) $m[1] ] ) ) {
			return $u;
		}
		throw new SC_Ed_Error( 'Ese servicio no existe.' );
	}
	if ( preg_match( '/^#[A-Za-z0-9_-]{1,60}$/', $u ) ) {
		return $u;
	}
	if ( '/' === $u[0] ) {
		if ( 0 === strpos( $u, '//' ) || preg_match( '/[\s<>"\'\\\\\x00-\x1f]/', $u ) ) {
			throw new SC_Ed_Error( 'La ruta del enlace no es válida.' );
		}
		return $u;
	}
	$full = $u;
	if ( ! preg_match( '#^https?://#i', $full ) && preg_match( '#^(www\.)?[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}(/\S*)?$#i', $full ) ) {
		$full = 'https://' . $full;
	}
	if ( preg_match( '#^https?://#i', $full ) && ! preg_match( '/[\s"<>\\\\]/', $full ) ) {
		$c = esc_url_raw( $full, array( 'http', 'https' ) );
		if ( '' !== $c ) {
			return $c;
		}
	}
	throw new SC_Ed_Error( 'El destino no es válido. Use una dirección que empiece con https://, WhatsApp, un teléfono o una página del sitio.' );
}

function sc_ed_op_link( array &$site, array $op ): array {
	$parts = sc_ed_parts( $op['path'] ?? '' );
	if ( 'page' !== sc_ed_zone( $parts ) ) {
		throw new SC_Ed_Error( 'Ese enlace no se puede cambiar.' );
	}
	list( $found, $cur ) = sc_ed_find( $site, $parts );
	if ( ! $found || ! is_array( $cur ) || ! array_key_exists( 'text', $cur ) || ! array_key_exists( 'url', $cur ) ) {
		throw new SC_Ed_Error( 'Ese enlace ya no existe. Recargue e intente de nuevo.' );
	}
	$text = isset( $op['text'] ) && is_string( $op['text'] ) ? trim( sanitize_text_field( $op['text'] ) ) : '';
	if ( '' === $text ) {
		throw new SC_Ed_Error( 'Escriba el texto del botón.' );
	}
	if ( mb_strlen( $text ) > 120 ) {
		throw new SC_Ed_Error( 'El texto del botón es demasiado largo (máximo 120 caracteres).' );
	}
	$url = sc_ed_clean_url( $op['url'] ?? '', $site );
	sc_ed_put( $site, $parts, array( 'text' => $text, 'url' => $url ) );
	$href = function_exists( 'sc_lx_url' ) ? (string) sc_lx_url( $url ) : '';
	return array( 'op' => 'link', 'path' => implode( '.', $parts ), 'text' => $text, 'url' => $url, 'href' => $href );
}

/* ---- secciones ---- */

/** @return array{0:string,1:int} */
function sc_ed_sec_ref( array $site, $path, $sid = null ): array {
	$p = sc_ed_parts( $path );
	if ( 4 !== count( $p ) || 'pages' !== $p[0] || 'sections' !== $p[2] || ! ctype_digit( $p[3] ) ) {
		throw new SC_Ed_Error( 'Esa sección no es válida.' );
	}
	$i = (int) $p[3];
	if ( ! isset( $site['pages'][ $p[1] ]['sections'][ $i ] ) || ! is_array( $site['pages'][ $p[1] ]['sections'][ $i ] ) ) {
		throw new SC_Ed_Error( 'Esa sección ya no existe. Recargue e intente de nuevo.' );
	}
	if ( is_string( $sid ) && '' !== $sid && (string) ( $site['pages'][ $p[1] ]['sections'][ $i ]['id'] ?? '' ) !== $sid ) {
		throw new SC_Ed_Error( 'La página cambió mientras la editaba. Recargue e intente de nuevo.' );
	}
	return array( $p[1], $i );
}

function sc_ed_op_sec_on( array &$site, array $op ): array {
	list( $k, $i ) = sc_ed_sec_ref( $site, $op['path'] ?? '', $op['sid'] ?? null );
	if ( ! array_key_exists( 'on', $op ) ) {
		throw new SC_Ed_Error( 'Indique si la sección se muestra u oculta.' );
	}
	$on = ( true === $op['on'] || 1 === $op['on'] || '1' === $op['on'] || 'true' === $op['on'] );
	$site['pages'][ $k ]['sections'][ $i ]['on'] = $on;
	return array( 'op' => 'sec_on', 'path' => "pages.$k.sections.$i", 'on' => $on );
}

function sc_ed_op_sec_move( array &$site, array $op ): array {
	list( $k, $i ) = sc_ed_sec_ref( $site, $op['path'] ?? '', $op['sid'] ?? null );
	$list = $site['pages'][ $k ]['sections'];
	$n    = count( $list );
	if ( isset( $op['to'] ) && is_numeric( $op['to'] ) ) {
		$j = (int) $op['to'];
	} else {
		$dir = isset( $op['dir'] ) ? (int) $op['dir'] : 0;
		if ( 0 === $dir ) {
			throw new SC_Ed_Error( 'Indique hacia dónde mover la sección.' );
		}
		$j = $i + ( $dir > 0 ? 1 : -1 );
	}
	if ( $j < 0 || $j >= $n ) {
		throw new SC_Ed_Error( $j < 0 ? 'Esa sección ya está al inicio de la página.' : 'Esa sección ya está al final de la página.' );
	}
	if ( $j !== $i ) {
		$item = array_splice( $list, $i, 1 );
		array_splice( $list, $j, 0, $item );
		$site['pages'][ $k ]['sections'] = array_values( $list );
	}
	return array( 'op' => 'sec_move', 'path' => "pages.$k.sections.$i", 'to' => $j );
}

/* ---- listas ---- */

function sc_ed_list_info( array $site, $path ): array {
	$p = sc_ed_parts( $path );
	if ( 6 !== count( $p ) || 'page' !== sc_ed_zone( $p ) || ! in_array( $p[5], array( 'items', 'points' ), true ) ) {
		throw new SC_Ed_Error( 'Esa lista no se puede modificar.' );
	}
	$type = (string) ( $site['pages'][ $p[1] ]['sections'][ (int) $p[3] ]['type'] ?? '' );
	$key  = $type . ':' . $p[5];
	$defs = sc_ed_list_defs();
	if ( ! isset( $defs[ $key ] ) ) {
		throw new SC_Ed_Error( 'Esa lista no se puede modificar.' );
	}
	list( $found, $list ) = sc_ed_find( $site, $p );
	if ( ! $found || ! is_array( $list ) ) {
		throw new SC_Ed_Error( 'Esa lista ya no existe. Recargue e intente de nuevo.' );
	}
	return array( $p, $key, array_values( $list ), $defs[ $key ] );
}

function sc_ed_list_defs(): array {
	return array(
		'faq:items'      => array( 'max' => 30, 'min' => 1, 'focus' => 'q' ),
		'values:items'   => array( 'max' => 12, 'min' => 1, 'focus' => 'title' ),
		'process:items'  => array( 'max' => 10, 'min' => 1, 'focus' => 'title' ),
		'about:points'   => array( 'max' => 10, 'min' => 0, 'focus' => 'text' ),
		'strip:items'    => array( 'max' => 20, 'min' => 1, 'focus' => '' ),
		'gallery:items'  => array( 'max' => 60, 'min' => 0, 'focus' => '' ),
	);
}

function sc_ed_list_template( string $key ) {
	switch ( $key ) {
		case 'faq:items':
			return array( 'q' => 'Nueva pregunta', 'a' => 'Escriba aquí la respuesta.' );
		case 'values:items':
			return array( 'icon' => 'gem', 'title' => 'Nuevo punto', 'text' => 'Describa aquí este punto.' );
		case 'process:items':
			return array( 'title' => 'Nuevo paso', 'text' => 'Describa aquí este paso.' );
		case 'about:points':
			return array( 'icon' => 'check', 'text' => 'Nuevo punto' );
		case 'strip:items':
			return 'Nueva palabra';
		case 'gallery:items':
			return array( 'id' => 0, 'seed' => abs( crc32( uniqid( '', true ) ) ) % 9000 + 11 );
	}
	return null;
}

function sc_ed_op_list_add( array &$site, array $op ): array {
	list( $p, $key, $list, $def ) = sc_ed_list_info( $site, $op['path'] ?? '' );
	if ( count( $list ) >= $def['max'] ) {
		throw new SC_Ed_Error( 'Ya llegó al máximo de elementos permitidos en esta lista (' . $def['max'] . ').' );
	}
	$item = sc_ed_list_template( $key );
	if ( 'gallery:items' === $key && isset( $op['id'] ) && is_numeric( $op['id'] ) && (int) $op['id'] > 0 ) {
		if ( ! sc_ed_attachment_ok( (int) $op['id'] ) ) {
			throw new SC_Ed_Error( 'La imagen elegida no existe o no es una imagen.' );
		}
		$item['id'] = (int) $op['id'];
	}
	$at = isset( $op['at'] ) && is_numeric( $op['at'] ) ? max( 0, min( count( $list ), (int) $op['at'] ) ) : count( $list );
	array_splice( $list, $at, 0, array( $item ) );
	sc_ed_put( $site, $p, array_values( $list ) );
	$focus = '';
	if ( '' !== $def['focus'] ) {
		$focus = implode( '.', $p ) . '.' . $at . '.' . $def['focus'];
	} elseif ( 'strip:items' === $key ) {
		$focus = implode( '.', $p ) . '.' . $at;
	}
	return array( 'op' => 'list_add', 'path' => implode( '.', $p ), 'index' => $at, 'focus' => $focus );
}

function sc_ed_op_list_del( array &$site, array $op ): array {
	list( $p, $key, $list, $def ) = sc_ed_list_info( $site, $op['path'] ?? '' );
	$i = isset( $op['index'] ) && is_numeric( $op['index'] ) ? (int) $op['index'] : -1;
	if ( $i < 0 || $i >= count( $list ) ) {
		throw new SC_Ed_Error( 'Ese elemento ya no existe. Recargue e intente de nuevo.' );
	}
	if ( count( $list ) <= $def['min'] ) {
		throw new SC_Ed_Error( 'Debe quedar al menos un elemento en esta lista. Si no lo quiere, oculte la sección completa.' );
	}
	array_splice( $list, $i, 1 );
	sc_ed_put( $site, $p, array_values( $list ) );
	return array( 'op' => 'list_del', 'path' => implode( '.', $p ), 'index' => $i );
}

function sc_ed_op_list_move( array &$site, array $op ): array {
	list( $p, $key, $list, $def ) = sc_ed_list_info( $site, $op['path'] ?? '' );
	$i   = isset( $op['index'] ) && is_numeric( $op['index'] ) ? (int) $op['index'] : -1;
	$dir = isset( $op['dir'] ) ? (int) $op['dir'] : 0;
	if ( $i < 0 || $i >= count( $list ) || 0 === $dir ) {
		throw new SC_Ed_Error( 'Ese elemento ya no existe. Recargue e intente de nuevo.' );
	}
	$j = $i + ( $dir > 0 ? 1 : -1 );
	if ( $j < 0 || $j >= count( $list ) ) {
		throw new SC_Ed_Error( $j < 0 ? 'Ese elemento ya está al inicio.' : 'Ese elemento ya está al final.' );
	}
	$tmp       = $list[ $i ];
	$list[ $i ] = $list[ $j ];
	$list[ $j ] = $tmp;
	sc_ed_put( $site, $p, array_values( $list ) );
	return array( 'op' => 'list_move', 'path' => implode( '.', $p ), 'index' => $i, 'to' => $j );
}

/* ---- servicios ---- */

function sc_ed_svc_idx( array $site, $n ): int {
	$n = is_numeric( $n ) ? (int) $n : -1;
	if ( $n < 0 || ! isset( $site['services'][ $n ] ) || ! is_array( $site['services'][ $n ] ) ) {
		throw new SC_Ed_Error( 'Ese servicio ya no existe. Recargue e intente de nuevo.' );
	}
	return $n;
}

function sc_ed_primary_menu(): int {
	$locs = get_theme_mod( 'nav_menu_locations', array() );
	return ( is_array( $locs ) && ! empty( $locs['primary'] ) ) ? (int) $locs['primary'] : 0;
}

function sc_ed_svc_menu_items( int $n, int $pid ): array {
	$menu = sc_ed_primary_menu();
	$out  = array();
	if ( ! $menu ) {
		return $out;
	}
	foreach ( (array) wp_get_nav_menu_items( $menu, array( 'post_status' => 'any' ) ) as $it ) {
		if ( 'item:primary:svc:' . $n === get_post_meta( $it->ID, '_sc_key', true ) || ( $pid && 'page' === $it->object && (int) $it->object_id === $pid ) ) {
			$out[] = (int) $it->ID;
		}
	}
	return $out;
}

function sc_ed_svc_menu_add( int $n, int $pid, string $title ): void {
	$menu = sc_ed_primary_menu();
	if ( ! $menu || ! $pid || sc_ed_svc_menu_items( $n, $pid ) ) {
		return;
	}
	if ( file_exists( ABSPATH . 'wp-admin/includes/nav-menu.php' ) ) {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
	}
	$ids    = get_option( 'sc_page_ids', array() );
	$sid    = ( is_array( $ids ) && ! empty( $ids['servicios'] ) ) ? (int) $ids['servicios'] : 0;
	$parent = 0;
	foreach ( (array) wp_get_nav_menu_items( $menu, array( 'post_status' => 'any' ) ) as $it ) {
		if ( 'item:primary:servicios' === get_post_meta( $it->ID, '_sc_key', true ) || ( $sid && 'page' === $it->object && (int) $it->object_id === $sid && ! (int) $it->menu_item_parent ) ) {
			$parent = (int) $it->ID;
			break;
		}
	}
	$item = wp_update_nav_menu_item(
		$menu,
		0,
		array(
			'menu-item-title'     => mb_substr( $title, 0, 60 ),
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $pid,
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
			'menu-item-parent-id' => $parent,
		)
	);
	if ( ! is_wp_error( $item ) ) {
		update_post_meta( (int) $item, '_sc_key', 'item:primary:svc:' . $n );
	}
}

function sc_ed_svc_menu_remove( int $n, int $pid ): void {
	foreach ( sc_ed_svc_menu_items( $n, $pid ) as $id ) {
		wp_delete_post( $id, true );
	}
}

function sc_ed_svc_page_create( array $sv, int $n ): int {
	$ids    = get_option( 'sc_page_ids', array() );
	$parent = ( is_array( $ids ) && ! empty( $ids['servicios'] ) ) ? (int) $ids['servicios'] : 0;
	$name   = (string) ( $sv['nombre'] ?? '' );
	$slug   = trim( (string) preg_replace( '/%[0-9a-f]{2}/i', '', sanitize_title( remove_accents( mb_substr( $name, 0, 70 ) ) ) ), '-' );
	if ( '' === $slug ) {
		$slug = 'servicio-' . ( $n + 1 );
	}
	$id = wp_insert_post(
		wp_slash(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => $name,
				'post_name'      => $slug,
				'post_parent'    => $parent,
				'menu_order'     => $n,
				'post_content'   => '<!-- Servicom: esta página se edita con «Editar mi web» -->',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'meta_input'     => array( '_sc_luxe' => 'svc:' . $n, '_sc_key' => 'page:svc:' . $n ),
			)
		),
		true
	);
	if ( is_wp_error( $id ) || ! $id ) {
		throw new SC_Ed_Error( 'No se pudo crear la página del servicio.' );
	}
	return (int) $id;
}

/** Deja el servicio visible: página publicada y entrada en el menú. */
function sc_ed_svc_publish( array &$site, int $n ): int {
	$sv  = $site['services'][ $n ];
	$map = get_option( 'sc_service_pages', array() );
	$map = is_array( $map ) ? $map : array();
	$pid = (int) ( $map[ $n ] ?? ( $sv['post'] ?? 0 ) );
	$post = $pid ? get_post( $pid ) : null;
	if ( $post && 'page' === $post->post_type ) {
		if ( 'trash' === $post->post_status ) {
			wp_untrash_post( $pid );
		}
		if ( 'publish' !== get_post_status( $pid ) ) {
			wp_update_post( array( 'ID' => $pid, 'post_status' => 'publish' ) );
		}
	} else {
		$pid = sc_ed_svc_page_create( $sv, $n );
	}
	$map[ $n ] = $pid;
	update_option( 'sc_service_pages', $map );
	$site['services'][ $n ]['post'] = $pid;
	sc_ed_svc_menu_add( $n, $pid, (string) ( $sv['nombre'] ?? '' ) );
	return $pid;
}

/** Oculta el servicio: página a la papelera y fuera del menú (el índice se conserva). */
function sc_ed_svc_unpublish( array $site, int $n, int $pid = 0 ): void {
	$map = get_option( 'sc_service_pages', array() );
	$pid = $pid ? $pid : (int) ( ( is_array( $map ) ? ( $map[ $n ] ?? 0 ) : 0 ) ?: ( $site['services'][ $n ]['post'] ?? 0 ) );
	sc_ed_svc_menu_remove( $n, $pid );
	if ( $pid && get_post( $pid ) && 'trash' !== get_post_status( $pid ) ) {
		wp_trash_post( $pid );
	}
}

function sc_ed_op_svc_add( array &$site, array $op, array &$fx ): array {
	$list = isset( $site['services'] ) && is_array( $site['services'] ) ? $site['services'] : array();
	if ( count( $list ) >= 40 ) {
		throw new SC_Ed_Error( 'Ya llegó al máximo de 40 servicios.' );
	}
	$nombre = isset( $op['nombre'] ) && is_string( $op['nombre'] ) ? trim( sanitize_text_field( $op['nombre'] ) ) : '';
	$nombre = '' !== $nombre ? mb_substr( $nombre, 0, 80 ) : 'Nuevo servicio';
	$n      = count( $list );
	$max    = 0;
	foreach ( $list as $s ) {
		if ( is_array( $s ) && preg_match( '/^s(\d+)$/', (string) ( $s['id'] ?? '' ), $m ) ) {
			$max = max( $max, (int) $m[1] );
		}
	}
	$icon = function_exists( 'sc_icon_for' ) ? sc_icon_for( $nombre, '' ) : 'star';
	if ( function_exists( 'sc_icon_keys' ) && ! in_array( $icon, sc_icon_keys(), true ) ) {
		$icon = 'star';
	}
	$site['services'][ $n ] = array(
		'id'          => 's' . ( $max + 1 ),
		'nombre'      => $nombre,
		'resumen'     => 'Describa brevemente este servicio.',
		'descripcion' => 'Cuéntele a sus clientes en qué consiste este servicio, qué incluye y por qué elegirlo.',
		'icono'       => $icon,
		'img'         => array( 'id' => 0, 'seed' => abs( crc32( uniqid( '', true ) ) ) % 9000 + 11 ),
		'post'        => 0,
		'origen'      => 'form',
		'on'          => true,
	);
	$fx[] = function ( array &$site ) use ( $n ) {
		sc_ed_svc_publish( $site, $n );
	};
	return array( 'op' => 'svc_add', 'n' => $n, 'focus' => 'services.' . $n . '.nombre' );
}

function sc_ed_op_svc_dup( array &$site, array $op, array &$fx ): array {
	$from = sc_ed_svc_idx( $site, $op['n'] ?? -1 );
	if ( count( $site['services'] ) >= 40 ) {
		throw new SC_Ed_Error( 'Ya llegó al máximo de 40 servicios.' );
	}
	$src = $site['services'][ $from ];
	$n   = count( $site['services'] );
	$max = 0;
	foreach ( $site['services'] as $s ) {
		if ( is_array( $s ) && preg_match( '/^s(\d+)$/', (string) ( $s['id'] ?? '' ), $m ) ) {
			$max = max( $max, (int) $m[1] );
		}
	}
	$copy           = $src;
	$copy['id']     = 's' . ( $max + 1 );
	$copy['nombre'] = mb_substr( trim( (string) ( $src['nombre'] ?? '' ) ) . ' (copia)', 0, 80 );
	$copy['post']   = 0;
	$copy['origen'] = 'form';
	$copy['on']     = true;
	$site['services'][ $n ] = $copy;
	$fx[] = function ( array &$site ) use ( $n ) {
		sc_ed_svc_publish( $site, $n );
	};
	return array( 'op' => 'svc_dup', 'n' => $n, 'from' => $from, 'focus' => 'services.' . $n . '.nombre' );
}

function sc_ed_op_svc_on( array &$site, array $op, array &$fx ): array {
	$n  = sc_ed_svc_idx( $site, $op['n'] ?? -1 );
	$on = ! empty( $op['on'] ) && 'false' !== $op['on'] && '0' !== $op['on'];
	if ( (bool) ( $site['services'][ $n ]['on'] ?? false ) === $on ) {
		return array( 'op' => 'svc_on', 'n' => $n, 'on' => $on );
	}
	$site['services'][ $n ]['on'] = $on;
	if ( $on ) {
		$fx[] = function ( array &$site ) use ( $n ) {
			sc_ed_svc_publish( $site, $n );
		};
	} else {
		$fx[] = function ( array &$site ) use ( $n ) {
			sc_ed_svc_unpublish( $site, $n );
		};
	}
	return array( 'op' => 'svc_on', 'n' => $n, 'on' => $on );
}

function sc_ed_op_svc_del( array &$site, array $op, array &$fx ): array {
	$op['on'] = false;
	$r        = sc_ed_op_svc_on( $site, $op, $fx );
	$r['op']  = 'svc_del';
	return $r;
}

/* ---- datos del negocio y logo ---- */

function sc_ed_biz_clean( string $key, string $v ): string {
	if ( function_exists( 'sc_biz_sanitize' ) ) {
		return (string) sc_biz_sanitize( $key, $v );
	}
	return mb_substr( sanitize_text_field( $v ), 0, 300 );
}

function sc_ed_op_biz( array &$site, array $op, array &$fx ): array {
	$vals = isset( $op['values'] ) && is_array( $op['values'] ) ? $op['values'] : array();
	if ( ! $vals ) {
		throw new SC_Ed_Error( 'No hay datos para guardar.' );
	}
	$keys  = sc_ed_biz_keys();
	$clean = array();
	foreach ( $vals as $k => $v ) {
		if ( ! is_string( $k ) || ! isset( $keys[ $k ] ) ) {
			throw new SC_Ed_Error( 'Ese dato del negocio no se puede editar.' );
		}
		if ( ! is_string( $v ) && ! is_numeric( $v ) ) {
			throw new SC_Ed_Error( 'El valor de «' . $keys[ $k ] . '» no es válido.' );
		}
		$v = trim( (string) $v );
		if ( mb_strlen( $v ) > 500 ) {
			throw new SC_Ed_Error( 'El valor de «' . $keys[ $k ] . '» es demasiado largo.' );
		}
		$c = sc_ed_biz_clean( $k, $v );
		if ( '' !== $v && '' === $c ) {
			throw new SC_Ed_Error( 'El valor de «' . $keys[ $k ] . '» no es válido. Revíselo e intente de nuevo.' );
		}
		if ( 'nombre' === $k && '' === $c ) {
			throw new SC_Ed_Error( 'El nombre del negocio no puede quedar vacío.' );
		}
		$clean[ $k ] = $c;
	}
	if ( isset( $clean['nombre'] ) ) {
		$site['brand']['nombre'] = $clean['nombre'];
	}
	if ( isset( $clean['youtube'] ) ) {
		foreach ( (array) ( $site['pages'] ?? array() ) as $pk => $page ) {
			foreach ( (array) ( $page['sections'] ?? array() ) as $si => $sec ) {
				if ( is_array( $sec ) && 'video' === ( $sec['type'] ?? '' ) && isset( $sec['data']['url'] ) ) {
					$site['pages'][ $pk ]['sections'][ $si ]['data']['url'] = $clean['youtube'];
				}
			}
		}
	}
	$fx[] = function ( array &$site ) use ( $clean ) {
		foreach ( $clean as $k => $v ) {
			set_theme_mod( 'sc_' . $k, $v );
		}
		if ( isset( $clean['nombre'] ) && get_option( 'blogname' ) !== $clean['nombre'] ) {
			update_option( 'blogname', $clean['nombre'] );
		}
	};
	return array( 'op' => 'biz', 'keys' => array_keys( $clean ) );
}

function sc_ed_op_logo( array &$site, array $op, array &$fx ): array {
	$id = isset( $op['id'] ) && is_numeric( $op['id'] ) ? (int) $op['id'] : -1;
	if ( $id < 0 ) {
		throw new SC_Ed_Error( 'El logo elegido no es válido.' );
	}
	if ( $id > 0 && ! sc_ed_attachment_ok( $id ) ) {
		throw new SC_Ed_Error( 'El logo elegido no existe o no es una imagen.' );
	}
	$site['brand']['logo'] = $id;
	$fx[] = function ( array &$site ) use ( $id ) {
		if ( $id > 0 ) {
			set_theme_mod( 'custom_logo', $id );
		} else {
			remove_theme_mod( 'custom_logo' );
		}
	};
	return array( 'op' => 'logo', 'id' => $id );
}

/* ---- diseño ---- */

function sc_ed_hex( $v ): string {
	$v = is_string( $v ) ? trim( $v ) : '';
	if ( preg_match( '/^#?([0-9a-fA-F]{3})$/', $v, $m ) ) {
		$h = $m[1];
		$v = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
	} elseif ( preg_match( '/^#?([0-9a-fA-F]{6})$/', $v, $m ) ) {
		$v = $m[1];
	} else {
		return '';
	}
	return '#' . strtolower( $v );
}

/** Pares (texto, fondo) que deben cumplir AA (4.5). */
function sc_ed_contrast_pairs( array $p ): array {
	return array(
		'ink' => array( 'ink', 'bg' ), 'ink2' => array( 'ink', 'bg2' ), 'ink3' => array( 'ink', 'surface' ),
		'muted' => array( 'muted', 'bg' ), 'muted2' => array( 'muted', 'surface' ),
		'primary' => array( 'primary', 'bg' ), 'accent' => array( 'accent', 'bg' ),
		'primary_ink' => array( 'primary_ink', 'primary' ), 'accent_ink' => array( 'accent_ink', 'accent' ),
		'dark_ink' => array( 'dark_ink', 'dark' ), 'primary_dark' => array( 'primary_dark', 'dark' ), 'accent_dark' => array( 'accent_dark', 'dark' ),
	);
}

function sc_ed_contrast_report( array $p ): array {
	$out = array();
	foreach ( sc_ed_contrast_pairs( $p ) as $name => $pair ) {
		if ( isset( $p[ $pair[0] ], $p[ $pair[1] ] ) ) {
			$out[ $name ] = round( sc_contrast( $p[ $pair[0] ], $p[ $pair[1] ] ), 2 );
		}
	}
	return $out;
}

/** Garantiza contraste AA en la paleta recalculada. */
function sc_ed_fix_palette( array $p ): array {
	foreach ( array( 'ink', 'muted', 'primary', 'accent' ) as $k ) {
		$p[ $k ] = sc_ensure_contrast( $p[ $k ], $p['bg'], 4.5 );
	}
	$p['ink']   = sc_ensure_contrast( $p['ink'], $p['surface'], 4.5 );
	$p['ink']   = sc_ensure_contrast( $p['ink'], $p['bg2'], 4.5 );
	$p['muted'] = sc_ensure_contrast( $p['muted'], $p['surface'], 4.5 );
	$p['muted'] = sc_ensure_contrast( $p['muted'], $p['bg2'], 4.5 );
	$p['primary_ink'] = sc_best_ink( $p['primary'], '#ffffff', '#0b0b0f' );
	$p['accent_ink']  = sc_best_ink( $p['accent'], '#ffffff', '#0b0b0f' );
	$p['dark_ink']    = sc_ensure_contrast( $p['dark_ink'], $p['dark'], 4.5 );
	$p['primary_dark'] = sc_ensure_contrast( $p['primary_dark'], $p['dark'], 4.5 );
	$p['accent_dark']  = sc_ensure_contrast( $p['accent_dark'], $p['dark'], 4.5 );
	return $p;
}

function sc_ed_op_design( array &$site, array $op ): array {
	if ( ! function_exists( 'sc_design_build_palette' ) || ! function_exists( 'sc_design_css' ) ) {
		throw new SC_Ed_Error( 'El motor de diseño no está disponible.' );
	}
	$d = isset( $site['design'] ) && is_array( $site['design'] ) ? $site['design'] : array();
	if ( empty( $d['palette'] ) || ! is_array( $d['palette'] ) ) {
		throw new SC_Ed_Error( 'Esta web no tiene diseño editable.' );
	}
	$pal  = $d['palette'];
	$base = isset( $d['base'] ) && is_array( $d['base'] ) ? $d['base'] : array();
	$primary = sc_ed_hex( $base['primary'] ?? '' );
	if ( '' === $primary ) {
		$primary = sc_ed_hex( $pal['brand'] ?? ( $pal['primary'] ?? '#3b3f8f' ) );
	}
	$accent = array_key_exists( 'accent', $base ) ? sc_ed_hex( $base['accent'] ) : sc_ed_hex( $pal['accent'] ?? '' );
	$mood   = in_array( $d['mood'] ?? '', array( 'dark', 'light' ), true ) ? $d['mood'] : 'dark';
	$rebuild = false;

	if ( isset( $op['primary'] ) ) {
		$c = sc_ed_hex( $op['primary'] );
		if ( '' === $c ) {
			throw new SC_Ed_Error( 'El color principal no es válido. Use un color como #1d476b.' );
		}
		$hsl = sc_hex2hsl( $c );
		if ( $hsl[1] < 0.1 ) {
			throw new SC_Ed_Error( 'Elija un color con algo más de intensidad (evite el gris, el blanco y el negro).' );
		}
		$primary = $c;
		$rebuild = true;
	}
	if ( array_key_exists( 'accent', $op ) ) {
		if ( '' === $op['accent'] || null === $op['accent'] ) {
			$accent = '';
		} else {
			$c = sc_ed_hex( $op['accent'] );
			if ( '' === $c ) {
				throw new SC_Ed_Error( 'El color de acento no es válido. Use un color como #caa55e.' );
			}
			$accent = $c;
		}
		$rebuild = true;
	}
	if ( isset( $op['mood'] ) ) {
		if ( ! in_array( $op['mood'], array( 'dark', 'light' ), true ) ) {
			throw new SC_Ed_Error( 'El ambiente debe ser claro u oscuro.' );
		}
		$mood    = $op['mood'];
		$rebuild = true;
	}
	$fonts = isset( $d['fonts'] ) && is_array( $d['fonts'] ) ? $d['fonts'] : array();
	$cat   = sc_ed_fonts();
	foreach ( array( 'head', 'body' ) as $f ) {
		if ( isset( $op[ $f ] ) ) {
			if ( ! is_string( $op[ $f ] ) || ! isset( $cat[ $f ][ $op[ $f ] ] ) ) {
				throw new SC_Ed_Error( 'Esa tipografía no está disponible.' );
			}
			$fonts[ $f ] = $op[ $f ];
		}
	}
	if ( $rebuild ) {
		$pal = sc_ed_fix_palette( sc_design_build_palette( $primary, $accent, $mood ) );
		$d['palette'] = $pal;
		$d['base']    = array( 'primary' => $primary, 'accent' => $accent );
		$d['mood']    = $mood;
	}
	$d['fonts']     = $fonts;
	$site['design'] = $d;
	return array(
		'op'       => 'design',
		'mood'     => $d['mood'] ?? $mood,
		'contrast' => sc_ed_contrast_report( $d['palette'] ),
		'css'      => sc_design_css( $d ),
		'bg'       => $d['palette']['bg'] ?? '',
	);
}

/* -------------------------------------------------------------------------
 * Historial y deshacer
 * ---------------------------------------------------------------------- */

function sc_ed_mods_snapshot(): array {
	$o = array();
	foreach ( array_keys( sc_ed_biz_keys() ) as $k ) {
		$o[ 'sc_' . $k ] = get_theme_mod( 'sc_' . $k, null );
	}
	$o['custom_logo'] = get_theme_mod( 'custom_logo', null );
	return $o;
}

function sc_ed_history(): array {
	$h = get_option( 'sc_site_history', array() );
	return is_array( $h ) ? $h : array();
}

function sc_ed_history_push( array $old, string $label, string $coalesce = '' ): void {
	$h    = sc_ed_history();
	$uid  = get_current_user_id();
	$last = $h ? end( $h ) : null;
	if ( '' !== $coalesce && is_array( $last ) && ( $last['k'] ?? '' ) === $coalesce && (int) ( $last['u'] ?? 0 ) === $uid && time() - (int) ( $last['t'] ?? 0 ) < 120 ) {
		$h[ count( $h ) - 1 ]['t'] = time();
		update_option( 'sc_site_history', $h, false );
		return;
	}
	$h[] = array(
		't'    => time(),
		'u'    => $uid,
		'l'    => $label,
		'k'    => $coalesce,
		'site' => $old,
		'mods' => sc_ed_mods_snapshot(),
		'svc'  => get_option( 'sc_service_pages', array() ),
	);
	if ( count( $h ) > SC_ED_HISTORY ) {
		$h = array_slice( $h, -SC_ED_HISTORY );
	}
	update_option( 'sc_site_history', array_values( $h ), false );
}

/** Sincroniza páginas WordPress y menú con el estado de los servicios. */
function sc_ed_svc_reconcile( array $restored, array $current, array $oldmap ): void {
	$map = get_option( 'sc_service_pages', array() );
	$map = is_array( $map ) ? $map : array();
	$cs  = isset( $current['services'] ) && is_array( $current['services'] ) ? $current['services'] : array();
	$rs  = isset( $restored['services'] ) && is_array( $restored['services'] ) ? $restored['services'] : array();
	foreach ( $cs as $n => $sv ) {
		if ( ! isset( $rs[ $n ] ) ) {
			$pid = (int) ( $oldmap[ $n ] ?? ( $sv['post'] ?? 0 ) );
			sc_ed_svc_unpublish( $current, (int) $n, $pid );
		}
	}
	foreach ( $rs as $n => $sv ) {
		if ( ! is_array( $sv ) ) {
			continue;
		}
		if ( ! empty( $sv['on'] ) ) {
			sc_ed_svc_publish( $restored, (int) $n );
		} else {
			sc_ed_svc_unpublish( $restored, (int) $n );
		}
	}
	update_option( 'sc_site', $restored, false );
}

function sc_ed_after_save( array $old, array $new ): void {
	$os = isset( $old['services'] ) && is_array( $old['services'] ) ? $old['services'] : array();
	$ns = isset( $new['services'] ) && is_array( $new['services'] ) ? $new['services'] : array();
	foreach ( $ns as $n => $sv ) {
		if ( ! is_array( $sv ) || ! isset( $os[ $n ] ) || ( $os[ $n ]['nombre'] ?? '' ) === ( $sv['nombre'] ?? '' ) ) {
			continue;
		}
		$pid = (int) ( $sv['post'] ?? 0 );
		if ( $pid && get_post( $pid ) ) {
			wp_update_post( wp_slash( array( 'ID' => $pid, 'post_title' => (string) $sv['nombre'] ) ) );
		}
		foreach ( sc_ed_svc_menu_items( (int) $n, $pid ) as $mid ) {
			wp_update_post( wp_slash( array( 'ID' => $mid, 'post_title' => mb_substr( (string) $sv['nombre'], 0, 60 ) ) ) );
		}
	}
}

function sc_ed_do_undo(): array {
	$h = sc_ed_history();
	if ( ! $h ) {
		throw new SC_Ed_Error( 'No hay cambios para deshacer.' );
	}
	$e = array_pop( $h );
	if ( ! is_array( $e ) || ! isset( $e['site'] ) || ! is_array( $e['site'] ) ) {
		update_option( 'sc_site_history', array_values( $h ), false );
		throw new SC_Ed_Error( 'No se pudo deshacer ese cambio.' );
	}
	$cur    = sc_ed_site();
	$oldmap = get_option( 'sc_service_pages', array() );
	$oldmap = is_array( $oldmap ) ? $oldmap : array();
	foreach ( (array) ( $e['mods'] ?? array() ) as $k => $v ) {
		if ( ! is_string( $k ) || ! preg_match( '/^(sc_[a-z_]+|custom_logo)$/', $k ) ) {
			continue;
		}
		if ( null === $v ) {
			remove_theme_mod( $k );
		} else {
			set_theme_mod( $k, $v );
		}
	}
	if ( isset( $e['svc'] ) && is_array( $e['svc'] ) ) {
		update_option( 'sc_service_pages', $e['svc'] );
	}
	sc_ed_svc_reconcile( $e['site'], $cur, $oldmap );
	$restored = sc_ed_site();
	sc_ed_after_save( $cur, $restored );
	if ( isset( $restored['brand']['nombre'] ) && '' !== $restored['brand']['nombre'] && get_option( 'blogname' ) !== $restored['brand']['nombre'] ) {
		update_option( 'blogname', $restored['brand']['nombre'] );
	}
	update_option( 'sc_site_history', array_values( $h ), false );
	if ( function_exists( 'sc_site_reload' ) ) {
		sc_site_reload();
	}
	return array( 'changed' => array( 'undo' ), 'can_undo' => count( $h ) );
}

/* -------------------------------------------------------------------------
 * REST
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', 'sc_ed_register_routes' );
function sc_ed_register_routes(): void {
	register_rest_route(
		'sc/v1',
		'/edit',
		array(
			'methods'             => 'POST',
			'callback'            => 'sc_ed_rest_edit',
			'permission_callback' => 'sc_ed_rest_permission',
		)
	);
	register_rest_route(
		'sc/v1',
		'/state',
		array(
			'methods'             => 'GET',
			'callback'            => 'sc_ed_rest_state',
			'permission_callback' => 'sc_ed_rest_permission',
		)
	);
	register_rest_route(
		'sc/v1',
		'/icons',
		array(
			'methods'             => 'GET',
			'callback'            => 'sc_ed_rest_icons',
			'permission_callback' => 'sc_ed_rest_permission',
		)
	);
}

function sc_ed_rest_permission() {
	if ( sc_ed_can() ) {
		return true;
	}
	return new WP_Error( 'rest_forbidden', 'No tiene permiso para editar esta web.', array( 'status' => rest_authorization_required_code() ) );
}

function sc_ed_fail( string $msg, int $status = 400, array $extra = array() ): WP_REST_Response {
	return new WP_REST_Response( array_merge( array( 'ok' => false, 'msg' => $msg ), $extra ), $status );
}

function sc_ed_audit( string $action, array $data ): void {
	do_action( 'sc_editor_audit', $action, $data, get_current_user_id() );
}

function sc_ed_apply( array &$site, $op, array &$fx ): array {
	if ( ! is_array( $op ) || empty( $op['op'] ) || ! is_string( $op['op'] ) ) {
		throw new SC_Ed_Error( 'Operación no válida.' );
	}
	switch ( $op['op'] ) {
		case 'text':
			return sc_ed_op_text( $site, $op );
		case 'img':
			return sc_ed_op_img( $site, $op );
		case 'icon':
			return sc_ed_op_icon( $site, $op );
		case 'link':
			return sc_ed_op_link( $site, $op );
		case 'sec_on':
			return sc_ed_op_sec_on( $site, $op );
		case 'sec_move':
			return sc_ed_op_sec_move( $site, $op );
		case 'list_add':
			return sc_ed_op_list_add( $site, $op );
		case 'list_del':
			return sc_ed_op_list_del( $site, $op );
		case 'list_move':
			return sc_ed_op_list_move( $site, $op );
		case 'svc_add':
			return sc_ed_op_svc_add( $site, $op, $fx );
		case 'svc_dup':
			return sc_ed_op_svc_dup( $site, $op, $fx );
		case 'svc_on':
			return sc_ed_op_svc_on( $site, $op, $fx );
		case 'svc_del':
			return sc_ed_op_svc_del( $site, $op, $fx );
		case 'biz':
			return sc_ed_op_biz( $site, $op, $fx );
		case 'logo':
			return sc_ed_op_logo( $site, $op, $fx );
		case 'design':
			return sc_ed_op_design( $site, $op );
	}
	throw new SC_Ed_Error( 'Esa operación no existe.' );
}

function sc_ed_saved_stamp(): array {
	return array( 'saved_at' => gmdate( 'c' ), 'saved_label' => wp_date( 'g:i a' ) );
}

function sc_ed_rest_edit( WP_REST_Request $req ) {
	$raw = (string) $req->get_body();
	if ( strlen( $raw ) > SC_ED_MAX_BODY ) {
		return sc_ed_fail( 'La petición es demasiado grande.', 413 );
	}
	$data = $req->get_json_params();
	$ops  = is_array( $data ) && isset( $data['ops'] ) && is_array( $data['ops'] ) ? array_values( $data['ops'] ) : array();
	if ( ! $ops ) {
		return sc_ed_fail( 'No hay cambios para guardar.' );
	}
	if ( count( $ops ) > SC_ED_MAX_OPS ) {
		return sc_ed_fail( 'Son demasiados cambios a la vez (máximo ' . SC_ED_MAX_OPS . ').' );
	}

	// Deshacer: solo y sin otras operaciones.
	if ( is_array( $ops[0] ) && isset( $ops[0]['op'] ) && 'undo' === $ops[0]['op'] ) {
		if ( count( $ops ) > 1 ) {
			return sc_ed_fail( 'Deshacer debe enviarse solo.' );
		}
		try {
			$r = sc_ed_do_undo();
		} catch ( SC_Ed_Error $e ) {
			return sc_ed_fail( $e->getMessage() );
		}
		sc_ed_audit( 'undo', array() );
		return new WP_REST_Response( array_merge( array( 'ok' => true, 'msg' => 'Se deshizo el último cambio.', 'reload' => true, 'results' => array() ), sc_ed_saved_stamp(), $r ), 200 );
	}

	$old = sc_ed_site();
	if ( empty( $old['v'] ) || empty( $old['pages'] ) ) {
		return sc_ed_fail( 'Esta web todavía no se puede editar desde el sitio.', 409 );
	}
	$site    = $old;
	$fx      = array();
	$results = array();
	foreach ( $ops as $i => $op ) {
		try {
			$results[] = sc_ed_apply( $site, $op, $fx );
		} catch ( SC_Ed_Error $e ) {
			return sc_ed_fail( ( count( $ops ) > 1 ? 'Cambio ' . ( $i + 1 ) . ': ' : '' ) . $e->getMessage(), 400, array( 'op' => $i ) );
		}
	}
	$changed = array();
	foreach ( $results as $r ) {
		$changed[] = $r['op'] . ( isset( $r['path'] ) ? ':' . $r['path'] : ( isset( $r['n'] ) ? ':' . $r['n'] : '' ) );
	}
	if ( $site === $old && ! $fx ) {
		return new WP_REST_Response( array_merge( array( 'ok' => true, 'msg' => 'Sin cambios.', 'changed' => array(), 'results' => $results, 'can_undo' => count( sc_ed_history() ) ), sc_ed_saved_stamp() ), 200 );
	}

	// Historial (antes de aplicar nada).
	$coalesce = '';
	if ( 1 === count( $results ) && 'text' === $results[0]['op'] ) {
		$coalesce = 'text:' . $results[0]['path'];
	}
	sc_ed_history_push( $old, implode( ',', $changed ), $coalesce );

	try {
		foreach ( $fx as $f ) {
			$f( $site );
		}
	} catch ( Throwable $e ) {
		return sc_ed_fail( 'No se pudo completar el cambio: ' . $e->getMessage(), 500 );
	}
	update_option( 'sc_site', $site, false );
	if ( function_exists( 'sc_site_reload' ) ) {
		sc_site_reload();
	}
	sc_ed_after_save( $old, $site );
	sc_ed_audit( 'edit', array( 'changed' => $changed ) );

	return new WP_REST_Response(
		array_merge(
			array(
				'ok'       => true,
				'msg'      => 'Cambios guardados.',
				'changed'  => $changed,
				'results'  => $results,
				'can_undo' => count( sc_ed_history() ),
			),
			sc_ed_saved_stamp()
		),
		200
	);
}

function sc_ed_collect_links( $node, string $path, array &$out ): void {
	if ( ! is_array( $node ) ) {
		return;
	}
	if ( array_key_exists( 'text', $node ) && array_key_exists( 'url', $node ) && 2 === count( $node ) && is_string( $node['text'] ) && is_string( $node['url'] ) ) {
		$out[ $path ] = array( 'text' => $node['text'], 'url' => $node['url'] );
		return;
	}
	foreach ( $node as $k => $v ) {
		if ( is_array( $v ) ) {
			sc_ed_collect_links( $v, $path . '.' . $k, $out );
		}
	}
}

function sc_ed_biz_values(): array {
	$o = array();
	foreach ( array_keys( sc_ed_biz_keys() ) as $k ) {
		$o[ $k ] = function_exists( 'sc_biz' ) ? (string) sc_biz( $k, '' ) : (string) get_theme_mod( 'sc_' . $k, '' );
	}
	if ( '' === $o['nombre'] ) {
		$o['nombre'] = (string) get_bloginfo( 'name' );
	}
	return $o;
}

function sc_ed_rest_state() {
	$site = sc_ed_site();
	$ids  = get_option( 'sc_page_ids', array() );
	$ids  = is_array( $ids ) ? $ids : array();
	$pages = array();
	$links = array();
	foreach ( (array) ( $site['pages'] ?? array() ) as $k => $p ) {
		$secs = array();
		foreach ( (array) ( $p['sections'] ?? array() ) as $i => $s ) {
			if ( ! is_array( $s ) ) {
				continue;
			}
			$secs[] = array(
				'i'     => (int) $i,
				'path'  => "pages.$k.sections.$i",
				'id'    => (string) ( $s['id'] ?? '' ),
				'type'  => (string) ( $s['type'] ?? '' ),
				'label' => sc_ed_section_label( (string) ( $s['type'] ?? '' ) ),
				'on'    => ! empty( $s['on'] ),
			);
			sc_ed_collect_links( $s['data'] ?? array(), "pages.$k.sections.$i.data", $links );
		}
		$pid     = (int) ( $ids[ $k ] ?? 0 );
		$pages[] = array(
			'key'      => (string) $k,
			'title'    => $pid ? html_entity_decode( get_the_title( $pid ), ENT_QUOTES, 'UTF-8' ) : ucfirst( (string) $k ),
			'url'      => $pid ? (string) get_permalink( $pid ) : home_url( '/' ),
			'sections' => $secs,
		);
	}
	$services = array();
	foreach ( (array) ( $site['services'] ?? array() ) as $n => $s ) {
		if ( ! is_array( $s ) ) {
			continue;
		}
		$services[] = array(
			'n'      => (int) $n,
			'nombre' => (string) ( $s['nombre'] ?? '' ),
			'icono'  => (string) ( $s['icono'] ?? '' ),
			'on'     => ! empty( $s['on'] ),
			'url'    => ! empty( $s['post'] ) && 'publish' === get_post_status( (int) $s['post'] ) ? (string) get_permalink( (int) $s['post'] ) : '',
		);
	}
	$design = isset( $site['design'] ) && is_array( $site['design'] ) ? $site['design'] : array();
	$pal    = isset( $design['palette'] ) && is_array( $design['palette'] ) ? $design['palette'] : array();
	$base   = isset( $design['base'] ) && is_array( $design['base'] ) ? $design['base'] : array();
	$fonts  = array( 'head' => array(), 'body' => array() );
	foreach ( sc_ed_fonts() as $kind => $list ) {
		foreach ( $list as $key => $label ) {
			$fonts[ $kind ][] = array(
				'key'   => $key,
				'label' => $label,
				'stack' => function_exists( 'sc_design_font_stack' ) ? sc_design_font_stack( $key, $kind ) : '',
			);
		}
	}
	$logo_id = (int) get_theme_mod( 'custom_logo', 0 );
	$tokens  = array();
	foreach ( $pages as $p ) {
		$tokens[] = array( 'token' => 'page:' . $p['key'], 'label' => $p['title'] );
	}
	return new WP_REST_Response(
		array(
			'ok'       => true,
			'pages'    => $pages,
			'services' => $services,
			'links'    => $links,
			'tokens'   => $tokens,
			'design'   => array(
				'mood'    => (string) ( $design['mood'] ?? 'dark' ),
				'fonts'   => (array) ( $design['fonts'] ?? array() ),
				'primary' => (string) ( ( $base['primary'] ?? '' ) ?: ( $pal['brand'] ?? ( $pal['primary'] ?? '#3b3f8f' ) ) ),
				'accent'  => (string) ( array_key_exists( 'accent', $base ) ? $base['accent'] : ( $pal['accent'] ?? '' ) ),
				'auto'    => array_key_exists( 'accent', $base ) ? '' === $base['accent'] : false,
				'palette' => $pal,
				'catalog' => $fonts,
			),
			'biz'      => sc_ed_biz_values(),
			'logo'     => array( 'id' => $logo_id, 'url' => $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '' ),
			'can_undo' => count( sc_ed_history() ),
		),
		200
	);
}

function sc_ed_rest_icons() {
	$out = array();
	if ( function_exists( 'sc_icon_keys' ) && function_exists( 'sc_icon' ) ) {
		foreach ( sc_icon_keys() as $k ) {
			$out[] = array(
				'key'   => $k,
				'label' => function_exists( 'sc_icon_label' ) ? sc_icon_label( $k ) : $k,
				'svg'   => sc_icon( $k ),
			);
		}
	}
	return new WP_REST_Response( $out, 200 );
}

/* -------------------------------------------------------------------------
 * Frontal: barra y modo edición
 * ---------------------------------------------------------------------- */

function sc_ed_is_editing(): bool {
	return isset( $_GET['sc_edit'] ) && '' !== (string) $_GET['sc_edit'] && '0' !== (string) $_GET['sc_edit']; // phpcs:ignore WordPress.Security.NonceVerification
}

function sc_ed_front_active(): bool {
	if ( is_admin() || is_feed() || is_embed() || ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) ) {
		return false;
	}
	if ( isset( $_GET['sc_nobar'] ) || isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return false;
	}
	return sc_ed_can() && function_exists( 'sc_lx_active' ) && sc_lx_active();
}

add_action( 'send_headers', 'sc_ed_send_headers' );
function sc_ed_send_headers(): void {
	if ( ! is_admin() && sc_ed_is_editing() && sc_ed_can() ) {
		nocache_headers();
	}
}

add_action( 'wp_enqueue_scripts', 'sc_ed_enqueue', 10020 );
function sc_ed_enqueue(): void {
	if ( ! sc_ed_front_active() ) {
		return;
	}
	$editing = sc_ed_is_editing();
	$dir     = SC_CORE_DIR . '/assets/';
	$ver     = function ( string $f ) use ( $dir ) {
		$t = @filemtime( $dir . $f );
		return SC_CORE_VERSION . ( $t ? '.' . $t : '' );
	};
	wp_enqueue_style( 'sc-editor', SC_CORE_URL . '/assets/editor.css', array(), $ver( 'editor.css' ) );
	if ( $editing ) {
		wp_add_inline_style( 'sc-editor', 'html:not(#sc-ed){margin-top:56px!important}html:not(#sc-ed){scroll-padding-top:76px}#wpadminbar{display:none!important}body.sc-ed-on .sc-header{top:56px!important}' );
		wp_enqueue_media();
	}
	$pid = (int) get_queried_object_id();
	$key = $pid ? (string) get_post_meta( $pid, '_sc_luxe', true ) : '';
	$cfg = array(
		'rest'    => esc_url_raw( rest_url( 'sc/v1/' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
		'editing' => $editing,
		'panel'   => admin_url( '/' ),
		'instr'   => function_exists( 'sc_instructions_url' ) ? sc_instructions_url() : admin_url( '/' ),
		'home'    => home_url( '/' ),
		'url'     => $pid ? (string) get_permalink( $pid ) : home_url( '/' ),
		'page'    => $key,
		'admin'   => current_user_can( 'manage_options' ),
		'name'    => (string) get_bloginfo( 'name' ),
		'v'       => SC_CORE_VERSION,
	);
	wp_enqueue_script( 'sc-editor', SC_CORE_URL . '/assets/editor.js', $editing ? array( 'media-editor' ) : array(), $ver( 'editor.js' ), true );
	wp_add_inline_script( 'sc-editor', 'window.SC_ED=' . wp_json_encode( $cfg ) . ';', 'before' );
}

add_filter( 'body_class', 'sc_ed_body_class' );
function sc_ed_body_class( $c ) {
	if ( sc_ed_front_active() ) {
		$c[] = 'sc-ed-on';
		if ( sc_ed_is_editing() ) {
			$c[] = 'sc-ed-editing';
		}
	}
	return $c;
}
