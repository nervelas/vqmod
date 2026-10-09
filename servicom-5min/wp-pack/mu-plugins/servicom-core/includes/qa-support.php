<?php
/**
 * Servicom Core - Autochequeo interno del sitio (lo usa el paso "qa" del portal).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** URLs públicas a revisar (únicas, mismo sitio). */
function sc_qa_urls(): array {
	$urls = array( home_url( '/' ) );
	foreach ( (array) get_option( 'sc_page_ids', array() ) as $id ) {
		$id = (int) $id;
		if ( $id && 'publish' === get_post_status( $id ) ) {
			$urls[] = (string) get_permalink( $id );
		}
	}
	foreach ( (array) get_option( 'sc_service_pages', array() ) as $id ) {
		$id = (int) $id;
		if ( $id && 'publish' === get_post_status( $id ) ) {
			$urls[] = (string) get_permalink( $id );
		}
	}
	if ( function_exists( 'wc_get_page_id' ) ) {
		foreach ( array( 'shop', 'cart', 'checkout' ) as $p ) {
			$id = (int) wc_get_page_id( $p );
			if ( $id > 0 && 'publish' === get_post_status( $id ) ) {
				$urls[] = (string) get_permalink( $id );
			}
		}
	}
	if ( post_type_exists( 'product' ) ) {
		$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 3, 'fields' => 'ids' ) );
		foreach ( $ids as $id ) {
			$urls[] = (string) get_permalink( (int) $id );
		}
	}
	$urls = array_values( array_unique( array_filter( $urls ) ) );
	return (array) apply_filters( 'sc_qa_urls', $urls );
}

function sc_qa_http( string $method, string $url, string $key ): array {
	$args = array(
		'timeout'     => 25,
		'redirection' => 3,
		'sslverify'   => (bool) get_option( 'sc_qa_sslverify', true ),
		'user-agent'  => 'ServicomSelfCheck/1.0',
		'headers'     => array(),
	);
	if ( '' !== $key ) {
		$args['headers']['Cookie'] = 'sc_pk=' . $key;
	}
	$resp = ( 'HEAD' === $method ) ? wp_remote_head( $url, $args ) : wp_remote_get( $url, $args );
	if ( is_wp_error( $resp ) ) {
		return array( 'code' => 0, 'body' => '', 'error' => $resp->get_error_message() );
	}
	return array(
		'code'  => (int) wp_remote_retrieve_response_code( $resp ),
		'ctype' => strtolower( (string) wp_remote_retrieve_header( $resp, 'content-type' ) ),
		'body'  => (string) wp_remote_retrieve_body( $resp ),
		'error' => '',
	);
}

/** HEAD con respaldo a GET (algunos servidores responden mal a HEAD). */
function sc_qa_probe( string $url, string $key ): int {
	$r = sc_qa_http( 'HEAD', $url, $key );
	if ( $r['code'] >= 400 || 0 === $r['code'] ) {
		$r = sc_qa_http( 'GET', $url, $key );
	}
	return $r['code'];
}

/** Código "efectivo" de un archivo multimedia: un 200 que no es imagen/video (p. ej. redirección a una página) cuenta como roto. */
function sc_qa_probe_media( string $url, string $key ): int {
	$r = sc_qa_http( 'HEAD', $url, $key );
	if ( 200 !== $r['code'] || ! preg_match( '#^(image|video|audio|application/octet)#', $r['ctype'] ) ) {
		$r = sc_qa_http( 'GET', $url, $key );
	}
	if ( 200 === $r['code'] && ! preg_match( '#^(image|video|audio|application/octet)#', $r['ctype'] ) ) {
		return 404;
	}
	return $r['code'];
}

function sc_qa_same_site( string $url ): bool {
	$h  = wp_parse_url( home_url(), PHP_URL_HOST );
	$uh = wp_parse_url( $url, PHP_URL_HOST );
	return ! $uh || strtolower( (string) $uh ) === strtolower( (string) $h );
}

function sc_qa_abs( string $href, string $base ): string {
	$href = trim( html_entity_decode( $href, ENT_QUOTES | ENT_HTML5 ) );
	if ( '' === $href ) {
		return '';
	}
	if ( 0 === strpos( $href, '//' ) ) {
		return ( wp_parse_url( home_url(), PHP_URL_SCHEME ) ?: 'https' ) . ':' . $href;
	}
	if ( preg_match( '#^https?://#i', $href ) ) {
		return $href;
	}
	if ( preg_match( '#^(?:mailto|tel|javascript|data|sms|whatsapp):#i', $href ) || '#' === $href[0] ) {
		return '';
	}
	$origin = preg_replace( '#^(https?://[^/]+).*#i', '$1', $base );
	if ( '/' === $href[0] ) {
		return $origin . $href;
	}
	return rtrim( preg_replace( '#[?\#].*$#', '', $base ), '/' ) . '/' . $href;
}

function sc_qa_html_scan( string $html, string $url, array &$probs ): array {
	$found = array( 'imgs' => array(), 'links' => array() );
	// Errores de PHP en la salida.
	$sig = array(
		'/(?:<b>)?(?:Warning|Notice|Deprecated)(?:<\/b>)?:\s[^\n]{0,400}?\bon line\b/u',
		'/(?:<b>)?(?:Fatal error|Parse error)(?:<\/b>)?:/u',
		'/Stack trace:/u',
	);
	foreach ( $sig as $re ) {
		if ( preg_match( $re, $html, $m ) ) {
			$probs[] = array( 'url' => $url, 'tipo' => 'error_php', 'detalle' => mb_substr( trim( wp_strip_all_tags( $m[0] ) ), 0, 200 ) );
		}
	}
	if ( '' === trim( $html ) ) {
		return $found;
	}
	$dom = new DOMDocument();
	$prev = libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	$xp = new DOMXPath( $dom );

	foreach ( $xp->query( '//img' ) as $img ) {
		$src = trim( (string) ( $img->getAttribute( 'src' ) ?: $img->getAttribute( 'data-src' ) ?: $img->getAttribute( 'data-lazy-src' ) ) );
		if ( '' === $src || '#' === $src ) {
			$probs[] = array( 'url' => $url, 'tipo' => 'imagen_rota', 'detalle' => 'Imagen sin dirección (src vacío).' );
			continue;
		}
		if ( 0 === stripos( $src, 'data:' ) ) {
			continue;
		}
		$abs = sc_qa_abs( $src, $url );
		if ( '' !== $abs ) {
			$found['imgs'][ $abs ] = true;
		}
	}
	foreach ( $xp->query( '//a[@href]' ) as $a ) {
		$abs = sc_qa_abs( (string) $a->getAttribute( 'href' ), $url );
		if ( '' !== $abs ) {
			$found['links'][ strtok( $abs, '#' ) ] = true;
		}
	}
	// Secciones vacías.
	$q = '//*[contains(concat(" ",normalize-space(@class)," ")," sc-sec ")]';
	foreach ( $xp->query( $q ) as $sec ) {
		$text = trim( preg_replace( '/\s+/u', ' ', $sec->textContent ) );
		$has  = $xp->query( './/img|.//svg|.//video|.//iframe|.//picture|.//canvas|.//form|.//input|.//audio|.//object|.//embed', $sec )->length > 0;
		if ( '' === $text && ! $has ) {
			$cls = trim( (string) $sec->getAttribute( 'class' ) );
			$probs[] = array( 'url' => $url, 'tipo' => 'seccion_vacia', 'detalle' => 'Sección sin texto ni imagen (' . mb_substr( $cls, 0, 80 ) . ').' );
		}
	}
	return $found;
}

/**
 * Autochequeo del sitio.
 *
 * @return array{ok:bool,problemas:array<int,array{url:string,tipo:string,detalle:string}>,urls:array}
 */
function sc_selfcheck(): array {
	@set_time_limit( 300 ); // phpcs:ignore
	$key    = (string) get_option( 'sc_preview_key', '' );
	$urls   = sc_qa_urls();
	$probs  = array();
	$imgs   = array();
	$links  = array();
	$elem   = ( defined( 'ELEMENTOR_VERSION' ) || did_action( 'elementor/loaded' ) ) && 'internal' !== get_option( 'elementor_css_print_method', 'external' );
	$up     = wp_upload_dir( null, false );
	$page_for = array();

	foreach ( $urls as $url ) {
		$r = sc_qa_http( 'GET', $url, $key );
		if ( 0 === $r['code'] ) {
			$probs[] = array( 'url' => $url, 'tipo' => 'peticion', 'detalle' => 'No se pudo cargar: ' . $r['error'] );
			continue;
		}
		if ( 200 !== $r['code'] ) {
			$probs[] = array( 'url' => $url, 'tipo' => 'http', 'detalle' => 'Respuesta HTTP ' . $r['code'] . ' (se esperaba 200).' );
		}
		$f = sc_qa_html_scan( $r['body'], $url, $probs );
		foreach ( array_keys( $f['imgs'] ) as $i ) {
			$imgs[ $i ] = $url;
		}
		foreach ( array_keys( $f['links'] ) as $l ) {
			$links[ $l ] = $url;
		}
		$page_for[ $url ] = url_to_postid( $url );
	}

	// Imágenes del mismo sitio.
	foreach ( $imgs as $img => $page ) {
		if ( ! sc_qa_same_site( $img ) ) {
			continue;
		}
		$code = sc_qa_probe_media( $img, $key );
		if ( 200 !== $code ) {
			$probs[] = array( 'url' => $page, 'tipo' => 'imagen_rota', 'detalle' => $img . ' -> HTTP ' . $code );
		}
	}

	// Enlaces internos.
	$checked = 0;
	foreach ( $links as $link => $page ) {
		if ( $checked >= 80 || ! sc_qa_same_site( $link ) ) {
			continue;
		}
		$path = (string) wp_parse_url( $link, PHP_URL_PATH );
		if ( preg_match( '#/(wp-admin|wp-login\.php|wp-json|xmlrpc\.php|feed|comments/feed)(/|$)#', $path ) || false !== strpos( $link, 'add-to-cart' ) || false !== strpos( $link, 'action=logout' ) ) {
			continue;
		}
		if ( in_array( $link, $urls, true ) || in_array( rtrim( $link, '/' ) . '/', $urls, true ) ) {
			continue; // Ya se probó como página.
		}
		++$checked;
		$code = sc_qa_probe( $link, $key );
		if ( $code >= 400 || 0 === $code ) {
			$probs[] = array( 'url' => $page, 'tipo' => 'enlace_roto', 'detalle' => $link . ' -> HTTP ' . $code );
		}
	}

	// CSS generado de Elementor.
	if ( $elem ) {
		foreach ( $page_for as $url => $pid ) {
			if ( ! $pid || 'builder' !== get_post_meta( $pid, '_elementor_edit_mode', true ) ) {
				continue;
			}
			$file = $up['basedir'] . '/elementor/css/post-' . $pid . '.css';
			$cssu = $up['baseurl'] . '/elementor/css/post-' . $pid . '.css';
			if ( ! file_exists( $file ) ) {
				$probs[] = array( 'url' => $url, 'tipo' => 'css_elementor', 'detalle' => 'Falta el CSS generado de Elementor (post-' . $pid . '.css).' );
				continue;
			}
			$code = sc_qa_probe( $cssu, $key );
			if ( 200 !== $code ) {
				$probs[] = array( 'url' => $url, 'tipo' => 'css_elementor', 'detalle' => 'El CSS de Elementor responde HTTP ' . $code . ' (' . $cssu . ').' );
			}
		}
	}

	$probs = (array) apply_filters( 'sc_selfcheck_problems', $probs, $urls );
	return array( 'ok' => ! $probs, 'problemas' => array_values( $probs ), 'urls' => $urls );
}
