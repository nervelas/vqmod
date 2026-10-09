<?php
/**
 * Servicom Core - Endurecimiento de seguridad.
 *
 * Todo funciona sin plugins y sin Elementor/WooCommerce.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Constantes
 * ---------------------------------------------------------------------- */

// Nadie edita archivos desde el panel.
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}
// Nadie instala/actualiza plugins o temas desde el panel. El admin técnico puede
// forzar con define( 'SC_ALLOW_FILE_MODS', true ) en wp-config.php.
if ( ! defined( 'DISALLOW_FILE_MODS' ) && ! ( defined( 'SC_ALLOW_FILE_MODS' ) && SC_ALLOW_FILE_MODS ) ) {
	define( 'DISALLOW_FILE_MODS', true );
}

/* -------------------------------------------------------------------------
 * XML-RPC, pingbacks, generator
 * ---------------------------------------------------------------------- */

add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', 'sc_hard_xmlrpc_methods' );
function sc_hard_xmlrpc_methods( $m ) {
	return array();
}

if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
	if ( ! headers_sent() ) {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
	}
	echo 'Acceso denegado.';
	exit;
}

add_filter( 'wp_headers', 'sc_hard_wp_headers' );
function sc_hard_wp_headers( $headers ) {
	unset( $headers['X-Pingback'] );
	foreach ( sc_security_headers() as $k => $v ) {
		$headers[ $k ] = $v;
	}
	return $headers;
}

add_filter( 'pings_open', '__return_false', 99 );
add_filter( 'pre_option_default_ping_status', 'sc_hard_closed' );
add_filter( 'pre_option_default_pingback_flag', '__return_zero' );
function sc_hard_closed() {
	return 'closed';
}

add_action( 'init', 'sc_hard_init', 1 );
function sc_hard_init(): void {
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'rest_output_link_wp_head' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'template_redirect', 'rest_output_link_header', 11 );
	remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
	add_filter( 'the_generator', '__return_empty_string' );
	sc_hard_disable_emojis();
}

add_filter( 'script_loader_src', 'sc_hard_strip_ver', 20 );
add_filter( 'style_loader_src', 'sc_hard_strip_ver', 20 );
function sc_hard_strip_ver( $src ) {
	global $wp_version;
	if ( is_string( $src ) && false !== strpos( $src, 'ver=' . $wp_version ) ) {
		$src = remove_query_arg( 'ver', $src );
	}
	return $src;
}

function sc_hard_disable_emojis(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_action( 'embed_head', 'print_emoji_detection_script' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
	add_filter( 'tiny_mce_plugins', 'sc_hard_tinymce_emoji' );
	add_filter( 'wp_resource_hints', 'sc_hard_resource_hints_emoji', 10, 2 );
}

function sc_hard_tinymce_emoji( $plugins ) {
	return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : array();
}

function sc_hard_resource_hints_emoji( $urls, $relation ) {
	if ( 'dns-prefetch' === $relation && is_array( $urls ) ) {
		$urls = array_filter(
			$urls,
			static function ( $u ) {
				return false === strpos( is_array( $u ) ? ( $u['href'] ?? '' ) : (string) $u, 's.w.org' );
			}
		);
	}
	return $urls;
}

// Contraseñas de aplicación: no se usan en estos sitios.
add_filter( 'wp_is_application_passwords_available', '__return_false' );

/* -------------------------------------------------------------------------
 * Enumeración de usuarios
 * ---------------------------------------------------------------------- */

add_action( 'parse_request', 'sc_hard_block_author_query', 1 );
function sc_hard_block_author_query( $wp ): void {
	if ( is_admin() || is_user_logged_in() ) {
		return;
	}
	$bad = false;
	if ( isset( $_GET['author'] ) || isset( $_GET['author_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$bad = true;
	}
	if ( ! empty( $wp->query_vars['author'] ) || ! empty( $wp->query_vars['author_name'] ) ) {
		$bad = true;
	}
	if ( $bad ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}

// Los archivos de autor no se usan en estos sitios.
add_action( 'template_redirect', 'sc_hard_no_author_archives', 1 );
function sc_hard_no_author_archives(): void {
	if ( is_author() && ! is_user_logged_in() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}

add_filter( 'rest_endpoints', 'sc_hard_rest_endpoints' );
function sc_hard_rest_endpoints( $endpoints ) {
	if ( is_user_logged_in() ) {
		return $endpoints;
	}
	foreach ( array_keys( (array) $endpoints ) as $route ) {
		if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
			unset( $endpoints[ $route ] );
		}
	}
	return $endpoints;
}

add_filter( 'oembed_response_data', 'sc_hard_oembed_author', 99 );
function sc_hard_oembed_author( $data ) {
	if ( is_array( $data ) ) {
		unset( $data['author_name'], $data['author_url'] );
	}
	return $data;
}

add_filter( 'wp_sitemaps_add_provider', 'sc_hard_sitemap_users', 10, 2 );
function sc_hard_sitemap_users( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}

add_filter( 'author_link', 'sc_hard_author_link', 99 );
function sc_hard_author_link( $link ) {
	return home_url( '/' );
}

/* -------------------------------------------------------------------------
 * Inicio de sesión: límite de intentos y mensajes genéricos
 * ---------------------------------------------------------------------- */

const SC_LOGIN_MAX    = 5;
const SC_LOGIN_WINDOW = 900; // 15 min.

function sc_hard_client_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0'; // phpcs:ignore
	return (string) apply_filters( 'sc_client_ip', $ip );
}

function sc_hard_login_keys( string $user ): array {
	return array(
		'sc_ll_ip_' . md5( sc_hard_client_ip() ),
		'sc_ll_u_' . md5( strtolower( trim( $user ) ) ),
	);
}

/** Segundos restantes de bloqueo (0 = libre). */
function sc_hard_login_locked( string $user ): int {
	$left = 0;
	foreach ( sc_hard_login_keys( $user ) as $k ) {
		$rec = get_transient( $k );
		if ( is_array( $rec ) && ( $rec['n'] ?? 0 ) >= SC_LOGIN_MAX ) {
			$left = max( $left, (int) $rec['t'] + SC_LOGIN_WINDOW - time() );
		}
	}
	return max( 0, $left );
}

add_filter( 'authenticate', 'sc_hard_authenticate', 99, 3 );
function sc_hard_authenticate( $user, $username = '', $password = '' ) {
	if ( '' === (string) $username ) {
		return $user;
	}
	$left = sc_hard_login_locked( (string) $username );
	if ( $left > 0 ) {
		$min = max( 1, (int) ceil( $left / 60 ) );
		return new WP_Error(
			'sc_lockout',
			sprintf( 'Demasiados intentos. Por seguridad, espera %d minuto(s) antes de intentarlo de nuevo.', $min )
		);
	}
	return $user;
}

add_action( 'wp_login_failed', 'sc_hard_login_failed', 10, 2 );
function sc_hard_login_failed( $username, $error = null ): void {
	$username = (string) $username;
	if ( '' === $username ) {
		return;
	}
	if ( $error instanceof WP_Error && $error->get_error_code() === 'sc_lockout' ) {
		return;
	}
	foreach ( sc_hard_login_keys( $username ) as $k ) {
		$rec = get_transient( $k );
		if ( ! is_array( $rec ) || time() - (int) $rec['t'] > SC_LOGIN_WINDOW ) {
			$rec = array( 'n' => 0, 't' => time() );
		}
		++$rec['n'];
		set_transient( $k, $rec, SC_LOGIN_WINDOW );
	}
}

add_action( 'wp_login', 'sc_hard_login_ok', 10, 1 );
function sc_hard_login_ok( $user_login ): void {
	delete_transient( 'sc_ll_u_' . md5( strtolower( trim( (string) $user_login ) ) ) );
	delete_transient( 'sc_ll_ip_' . md5( sc_hard_client_ip() ) );
}

// Mensajes genéricos: nunca revelan si el usuario existe.
add_filter( 'wp_login_errors', 'sc_hard_login_errors', 99 );
function sc_hard_login_errors( $errors ) {
	if ( ! ( $errors instanceof WP_Error ) ) {
		return $errors;
	}
	$codes = $errors->get_error_codes();
	if ( in_array( 'sc_lockout', $codes, true ) ) {
		$msg = $errors->get_error_message( 'sc_lockout' );
		return new WP_Error( 'sc_lockout', $msg );
	}
	$generic = array( 'incorrect_password', 'invalid_username', 'invalid_email', 'invalidcombo', 'authentication_failed', 'empty_username', 'empty_password', 'invalid_key', 'expired_key' );
	if ( array_intersect( $codes, $generic ) ) {
		$new = new WP_Error( 'sc_login_generic', 'Los datos de acceso no son correctos. Revisa tu usuario y contraseña e inténtalo de nuevo.' );
		foreach ( $codes as $c ) {
			if ( ! in_array( $c, $generic, true ) ) {
				foreach ( $errors->get_error_messages( $c ) as $m ) {
					$new->add( $c, $m );
				}
			}
		}
		return $new;
	}
	return $errors;
}

add_filter( 'login_headerurl', 'sc_hard_login_headerurl' );
function sc_hard_login_headerurl() {
	return home_url( '/' );
}
add_filter( 'login_headertext', 'sc_hard_login_headertext' );
function sc_hard_login_headertext() {
	return (string) get_bloginfo( 'name' );
}
add_filter( 'login_display_language_dropdown', '__return_false' );

/* -------------------------------------------------------------------------
 * Cabeceras de seguridad
 * ---------------------------------------------------------------------- */

function sc_security_headers(): array {
	$h = array(
		'X-Content-Type-Options' => 'nosniff',
		'X-Frame-Options'        => 'SAMEORIGIN',
		'Referrer-Policy'        => 'strict-origin-when-cross-origin',
		'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=(self), usb=(), interest-cohort=()',
	);
	if ( is_ssl() ) {
		$h['Strict-Transport-Security'] = 'max-age=31536000';
	}
	return $h;
}

function sc_hard_send_security_headers(): void {
	if ( headers_sent() ) {
		return;
	}
	foreach ( sc_security_headers() as $k => $v ) {
		header( $k . ': ' . $v );
	}
	header_remove( 'X-Powered-By' );
}
add_action( 'login_init', 'sc_hard_send_security_headers' );
add_action( 'admin_init', 'sc_hard_send_security_headers', 1 );

/* -------------------------------------------------------------------------
 * Actualizaciones: ocultas al cliente
 * ---------------------------------------------------------------------- */

add_action( 'admin_init', 'sc_hard_hide_updates', 1 );
function sc_hard_hide_updates(): void {
	if ( current_user_can( 'update_core' ) && ! ( function_exists( 'sc_is_client' ) && sc_is_client() ) ) {
		return;
	}
	remove_action( 'admin_notices', 'update_nag', 3 );
	remove_action( 'network_admin_notices', 'update_nag', 3 );
	remove_action( 'admin_notices', 'maintenance_nag', 10 );
}

add_action( 'admin_head', 'sc_hard_admin_css' );
function sc_hard_admin_css(): void {
	if ( ! function_exists( 'sc_is_client' ) || ! sc_is_client() ) {
		return;
	}
	echo '<style id="sc-hard-admin">.update-nag,.notice-info,.notice-warning,.e-notice,.elementor-message,#wp-admin-bar-updates,.wc-admin-notice,.woocommerce-message.is-dismissible,#contextual-help-link-wrap,#screen-meta-links .show-settings{display:none!important}</style>';
}

add_filter( 'admin_footer_text', 'sc_hard_footer_text' );
function sc_hard_footer_text( $t ) {
	return ( function_exists( 'sc_is_client' ) && sc_is_client() ) ? 'Sitio creado por Servicom' : $t;
}
add_filter( 'update_footer', 'sc_hard_update_footer', 99 );
function sc_hard_update_footer( $t ) {
	return ( function_exists( 'sc_is_client' ) && sc_is_client() ) ? '' : $t;
}

/* -------------------------------------------------------------------------
 * Archivos .htaccess que usa el portal al construir
 * ---------------------------------------------------------------------- */

/**
 * Reglas para el .htaccess de la raíz del sitio. Deben ir ANTES del bloque
 * "# BEGIN WordPress".
 */
function sc_hardening_htaccess_rules(): string {
	$deny      = "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n";
	$php_ext   = 'php[0-9]*|phtml|phar|pl|py|cgi|sh|shtml|asp|aspx|jsp';
	$rules     = "# BEGIN Servicom Hardening\n";
	$rules    .= "Options -Indexes\n\n";
	$rules    .= "<IfModule mod_rewrite.c>\nRewriteEngine On\n";
	$rules    .= "# Sin scripts en las subidas ni en los trabajos de construcción\n";
	$rules    .= "RewriteRule ^wp-content/uploads/.*\\.(?:" . $php_ext . ")$ - [F,L,NC]\n";
	$rules    .= "RewriteRule ^wp-content/sc-jobs(?:/|$) - [F,L]\n";
	$rules    .= "# Archivos y carpetas ocultos (excepto .well-known)\n";
	$rules    .= "RewriteRule (?:^|/)\\.(?!well-known(?:/|$)) - [F,L]\n";
	$rules    .= "</IfModule>\n\n";
	$rules    .= "# Archivos sensibles\n";
	$rules    .= "<FilesMatch \"^(?:wp-config\\.php|wp-config-sample\\.php|xmlrpc\\.php|readme\\.html|license\\.txt)$\">\n" . $deny . "</FilesMatch>\n";
	$rules    .= "<FilesMatch \"(?:\\.(?:log|sql|bak|old|orig|swp|sh|ini|dist)|~)$\">\n" . $deny . "</FilesMatch>\n\n";
	$rules    .= <<<'HT'
# Compresión
<IfModule mod_deflate.c>
	AddOutputFilterByType DEFLATE text/html text/plain text/css text/xml text/javascript application/javascript application/x-javascript application/json application/xml application/rss+xml image/svg+xml font/ttf font/otf application/vnd.ms-fontobject
</IfModule>

# Caché del navegador
<IfModule mod_expires.c>
	ExpiresActive On
	ExpiresByType image/jpeg "access plus 1 year"
	ExpiresByType image/png "access plus 1 year"
	ExpiresByType image/gif "access plus 1 year"
	ExpiresByType image/webp "access plus 1 year"
	ExpiresByType image/avif "access plus 1 year"
	ExpiresByType image/svg+xml "access plus 1 year"
	ExpiresByType image/x-icon "access plus 1 year"
	ExpiresByType image/vnd.microsoft.icon "access plus 1 year"
	ExpiresByType font/woff "access plus 1 year"
	ExpiresByType font/woff2 "access plus 1 year"
	ExpiresByType application/font-woff2 "access plus 1 year"
	ExpiresByType text/css "access plus 1 month"
	ExpiresByType text/javascript "access plus 1 month"
	ExpiresByType application/javascript "access plus 1 month"
	ExpiresByType video/mp4 "access plus 1 month"
</IfModule>

# Cabeceras de seguridad
<IfModule mod_headers.c>
	Header set X-Content-Type-Options "nosniff"
	Header set X-Frame-Options "SAMEORIGIN"
	Header set Referrer-Policy "strict-origin-when-cross-origin"
	Header set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(self), usb=(), interest-cohort=()"
	Header set Strict-Transport-Security "max-age=31536000" env=HTTPS
	Header unset X-Powered-By
</IfModule>
# END Servicom Hardening

HT;
	return $rules;
}

/** .htaccess para wp-content/uploads (sin ejecución de scripts). */
function sc_hardening_uploads_htaccess(): string {
	return <<<'HT'
# Servicom: en las subidas nunca se ejecutan scripts
Options -Indexes
<FilesMatch "\.(?:php[0-9]*|phtml|phar|pl|py|cgi|sh|shtml|asp|aspx|jsp)$">
	<IfModule mod_authz_core.c>
		Require all denied
	</IfModule>
	<IfModule !mod_authz_core.c>
		Order allow,deny
		Deny from all
	</IfModule>
</FilesMatch>

HT;
}

/** Garantiza uploads/.htaccess e index.php (barato; una vez por versión). */
add_action( 'admin_init', 'sc_hard_ensure_files' );
function sc_hard_ensure_files(): void {
	if ( get_option( 'sc_hardening_files' ) === SC_CORE_VERSION ) {
		return;
	}
	$u = wp_upload_dir( null, false );
	if ( empty( $u['basedir'] ) || ! is_dir( $u['basedir'] ) || ! is_writable( $u['basedir'] ) ) { // phpcs:ignore
		return;
	}
	$ht = $u['basedir'] . '/.htaccess';
	if ( ! file_exists( $ht ) ) {
		@file_put_contents( $ht, sc_hardening_uploads_htaccess() ); // phpcs:ignore
	}
	$ix = $u['basedir'] . '/index.php';
	if ( ! file_exists( $ix ) ) {
		@file_put_contents( $ix, "<?php\n// Silence is golden.\n" ); // phpcs:ignore
	}
	update_option( 'sc_hardening_files', SC_CORE_VERSION, false );
}
