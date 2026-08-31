<?php
/**
 * AGROINCO — caché de página simple para visitantes anónimos.
 * Guarda el HTML de respuestas GET 200 y lo sirve al instante en la siguiente visita.
 * Se omite: usuarios con sesión, POST, query strings, carrito/checkout/cuenta, wp-admin,
 * vistas previas y peticiones AJAX. TTL 6 horas. Escrito a medida: sin plugins.
 */
if ( defined( 'WP_CLI' ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) return;
if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) return;
if ( ! empty( $_SERVER['QUERY_STRING'] ) ) return;
$uri = $_SERVER['REQUEST_URI'] ?? '/';
if ( preg_match( '#^/(wp-admin|wp-login|wp-json|carrito|finalizar-compra|mi-cuenta|wp-cron)#', $uri ) ) return;
foreach ( array_keys( $_COOKIE ) as $c ) {
	if ( preg_match( '/^(wordpress_logged_in|wp-postpass|woocommerce_cart_hash|woocommerce_items_in_cart|comment_author)/', $c ) ) return;
}
$agro_cache_dir  = WP_CONTENT_DIR . '/cache/agro-pages';
$agro_cache_file = $agro_cache_dir . '/' . md5( ( $_SERVER['HTTP_HOST'] ?? '' ) . $uri ) . '.html';
if ( is_file( $agro_cache_file ) && ( time() - filemtime( $agro_cache_file ) ) < 21600 ) {
	header( 'X-Agro-Cache: HIT' );
	header( 'Content-Type: text/html; charset=UTF-8' );
	readfile( $agro_cache_file );
	exit;
}
if ( ! is_dir( $agro_cache_dir ) ) @mkdir( $agro_cache_dir, 0755, true );
ob_start( function ( $html ) use ( $agro_cache_file ) {
	if ( strlen( $html ) > 255 && http_response_code() === 200 && stripos( $html, '</html>' ) !== false ) {
		@file_put_contents( $agro_cache_file . '.tmp', $html, LOCK_EX );
		@rename( $agro_cache_file . '.tmp', $agro_cache_file );
	}
	return $html;
} );
