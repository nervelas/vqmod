<?php
/**
 * Servicom Core - Modo del sitio: preview / demo / published (contrato §10).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SC_MODES = array( 'preview', 'demo', 'published' );

function sc_mode(): string {
	$m = (string) get_option( 'sc_mode', 'published' );
	return in_array( $m, SC_MODES, true ) ? $m : 'published';
}

/**
 * Cambia el modo del sitio y deja blog_public coherente.
 */
function sc_set_mode( string $mode ): bool {
	if ( ! in_array( $mode, SC_MODES, true ) ) {
		return false;
	}
	update_option( 'sc_mode', $mode );
	remove_filter( 'pre_option_blog_public', 'sc_pv_blog_public' );
	update_option( 'blog_public', 'published' === $mode ? '1' : '0' );
	add_filter( 'pre_option_blog_public', 'sc_pv_blog_public' );
	return true;
}

// Mientras no esté publicado, el sitio nunca se anuncia como indexable.
add_filter( 'pre_option_blog_public', 'sc_pv_blog_public' );
function sc_pv_blog_public( $v ) {
	return 'published' === sc_mode() ? $v : '0';
}

function sc_pv_has_access(): bool {
	if ( is_user_logged_in() ) {
		return true;
	}
	$key = (string) get_option( 'sc_preview_key', '' );
	$c   = isset( $_COOKIE['sc_pk'] ) ? (string) wp_unslash( $_COOKIE['sc_pk'] ) : ''; // phpcs:ignore
	return '' !== $key && '' !== $c && hash_equals( $key, $c );
}

/** Contextos donde nunca se pinta la barra (editores, iframes del Personalizador). */
function sc_pv_is_editor_context(): bool {
	if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
		return true;
	}
	foreach ( array( 'elementor-preview', 'preview', 'customize_changeset_uuid', 'customize_theme', 'fl_builder', 'sc_nobar' ) as $k ) {
		if ( isset( $_GET[ $k ] ) ) { // phpcs:ignore
			return true;
		}
	}
	if ( class_exists( '\Elementor\Plugin' ) ) {
		try {
			$p = \Elementor\Plugin::$instance;
			if ( isset( $p->preview ) && method_exists( $p->preview, 'is_preview_mode' ) && $p->preview->is_preview_mode() ) {
				return true;
			}
		} catch ( \Throwable $e ) {
			return false;
		}
	}
	return false;
}

function sc_pv_clean_url(): string {
	$origin = preg_replace( '#^(https?://[^/]+).*#i', '$1', home_url() );
	$uri    = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/'; // phpcs:ignore
	return $origin . remove_query_arg( 'scpk', $uri );
}

function sc_pv_set_cookie( string $key ): void {
	if ( headers_sent() ) {
		return;
	}
	setcookie(
		'sc_pk',
		$key,
		array(
			'expires'  => time() + 30 * DAY_IN_SECONDS,
			'path'     => '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
}

/* -------------------------------------------------------------------------
 * Puerta de acceso
 * ---------------------------------------------------------------------- */

add_action( 'template_redirect', 'sc_pv_gate', 0 );
function sc_pv_gate(): void {
	if ( 'preview' !== sc_mode() ) {
		return;
	}
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}
	$key = (string) get_option( 'sc_preview_key', '' );
	if ( isset( $_GET['scpk'] ) ) { // phpcs:ignore
		$given = (string) wp_unslash( $_GET['scpk'] ); // phpcs:ignore
		if ( '' !== $key && hash_equals( $key, $given ) ) {
			sc_pv_set_cookie( $key );
			nocache_headers();
			wp_safe_redirect( sc_pv_clean_url(), 302 );
			exit;
		}
	}
	if ( sc_pv_has_access() || is_robots() ) {
		return;
	}
	sc_pv_private_screen();
}

function sc_pv_private_screen(): void {
	status_header( 403 );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	$name  = esc_html( (string) get_bloginfo( 'name' ) );
	$login = esc_url( wp_login_url() );
	echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
	echo '<meta name="robots" content="noindex, nofollow"><title>Vista previa privada</title>';
	echo '<style>*{box-sizing:border-box}html,body{margin:0;min-height:100%}body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;background:#f4f6f9;color:#1b2430;font:16px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}.c{max-width:460px;width:100%;background:#fff;border-radius:18px;padding:36px 30px;text-align:center;box-shadow:0 12px 40px rgba(20,30,50,.1)}.i{width:56px;height:56px;border-radius:50%;background:#e8eef6;color:#1e3a5f;display:flex;align-items:center;justify-content:center;margin:0 auto 16px}h1{font-size:1.4rem;margin:0 0 8px;line-height:1.25}p{margin:0 0 10px;color:#4a5563}small{display:block;margin-top:18px;color:#6b7280}a{color:#1e3a5f}</style></head><body><main class="c">';
	echo '<div class="i" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg></div>';
	echo '<h1>Vista previa privada</h1>';
	if ( '' !== $name ) {
		echo '<p><strong>' . $name . '</strong> aún no está publicado.</p>'; // phpcs:ignore
	}
	echo '<p>Esta página solo se puede ver con el enlace de acceso que le compartió Servicom. Abra de nuevo ese enlace para continuar.</p>';
	echo '<small><a href="' . $login . '">Soy administrador del sitio</a></small>'; // phpcs:ignore
	echo '</main></body></html>';
	exit;
}

// REST: en vista previa privada, los visitantes sin acceso no leen el contenido.
add_filter( 'rest_authentication_errors', 'sc_pv_rest_gate', 20 );
function sc_pv_rest_gate( $result ) {
	if ( ! empty( $result ) || 'preview' !== sc_mode() || sc_pv_has_access() ) {
		return $result;
	}
	$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
	if ( '' === $route && isset( $_GET['rest_route'] ) ) { // phpcs:ignore
		$route = (string) wp_unslash( $_GET['rest_route'] ); // phpcs:ignore
	}
	$route = '/' . ltrim( $route, '/' );
	if ( 0 === strpos( $route, '/wp/v2' ) || 0 === strpos( $route, '/oembed' ) ) {
		return new WP_Error( 'sc_preview_private', 'Vista previa privada.', array( 'status' => 403 ) );
	}
	return $result;
}

/* -------------------------------------------------------------------------
 * noindex
 * ---------------------------------------------------------------------- */

add_filter( 'wp_robots', 'sc_pv_wp_robots', 99 );
function sc_pv_wp_robots( $robots ) {
	if ( 'published' !== sc_mode() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['max-image-preview'] );
	}
	return $robots;
}

add_filter( 'wp_headers', 'sc_pv_headers' );
function sc_pv_headers( $headers ) {
	if ( 'published' !== sc_mode() ) {
		$headers['X-Robots-Tag'] = 'noindex, nofollow';
	}
	return $headers;
}

add_filter( 'robots_txt', 'sc_pv_robots_txt', 99, 2 );
function sc_pv_robots_txt( $output, $public ) {
	return 'published' !== sc_mode() ? "User-agent: *\nDisallow: /\n" : $output;
}

/* -------------------------------------------------------------------------
 * Barra de vista previa
 * ---------------------------------------------------------------------- */

function sc_pv_show_bar(): bool {
	if ( 'preview' !== sc_mode() || is_admin() || is_feed() || is_embed() || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
		return false;
	}
	if ( sc_pv_is_editor_context() || ! sc_pv_has_access() ) {
		return false;
	}
	return true;
}

function sc_pv_bar_html(): string {
	$pay  = (string) get_option( 'sc_pay_url', '' );
	$edit = (string) get_option( 'sc_edit_url', '' );
	$h    = '<div id="sc-pv-bar" class="scpv" role="region" aria-label="Vista previa del sitio"><span class="scpv__t"><span class="scpv__dot" aria-hidden="true"></span>Vista previa</span><span class="scpv__a">';
	if ( '' !== $pay ) {
		$h .= '<a class="scpv__b scpv__b--pay" href="' . esc_url( $pay ) . '">Aprobar y pagar</a>';
	}
	if ( '' !== $edit ) {
		$h .= '<a class="scpv__b" href="' . esc_url( $edit ) . '">Editar datos</a>';
	}
	return $h . '</span></div>';
}

function sc_pv_bar_css(): string {
	return '<style id="sc-pv-css">.scpv{box-sizing:border-box;position:relative;z-index:20;width:100%;max-width:100%;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:6px 14px;padding:6px 12px;background:#1b2430;color:#fff;font:600 13px/1.3 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;text-align:center}.scpv *{box-sizing:border-box}.scpv__t{display:inline-flex;align-items:center;gap:7px;opacity:.9}.scpv__dot{width:8px;height:8px;border-radius:50%;background:#f5b942}.scpv__a{display:inline-flex;flex-wrap:wrap;justify-content:center;gap:6px}.scpv__b{display:inline-block;padding:5px 12px;border-radius:999px;border:1px solid rgba(255,255,255,.55);color:#fff;text-decoration:none;font-weight:600}.scpv__b:hover,.scpv__b:focus-visible{background:rgba(255,255,255,.16);color:#fff}.scpv__b--pay{background:#fff;color:#1b2430;border-color:#fff}.scpv__b--pay:hover,.scpv__b--pay:focus-visible{background:#e9eef5;color:#1b2430}.scpv a:focus-visible{outline:2px solid #f5b942;outline-offset:2px}@media print{.scpv{display:none}}</style>';
}

$GLOBALS['sc_pv_bar_printed'] = false;

add_action( 'wp_head', 'sc_pv_print_css', 99 );
function sc_pv_print_css(): void {
	if ( sc_pv_show_bar() ) {
		echo sc_pv_bar_css(); // phpcs:ignore
	}
}

add_action( 'wp_body_open', 'sc_pv_print_bar', 1 );
function sc_pv_print_bar(): void {
	if ( $GLOBALS['sc_pv_bar_printed'] || ! sc_pv_show_bar() ) {
		return;
	}
	$GLOBALS['sc_pv_bar_printed'] = true;
	echo sc_pv_bar_html(); // phpcs:ignore
}

// Si el tema no llama a wp_body_open, se imprime en el pie y se mueve arriba.
add_action( 'wp_footer', 'sc_pv_print_bar_fallback', 1 );
function sc_pv_print_bar_fallback(): void {
	if ( $GLOBALS['sc_pv_bar_printed'] || ! sc_pv_show_bar() ) {
		return;
	}
	$GLOBALS['sc_pv_bar_printed'] = true;
	echo sc_pv_bar_html(); // phpcs:ignore
	echo '<script>(function(){var b=document.getElementById("sc-pv-bar");if(b&&document.body){document.body.insertBefore(b,document.body.firstChild);}})();</script>';
}
