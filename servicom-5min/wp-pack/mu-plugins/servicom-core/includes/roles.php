<?php
/**
 * Servicom Core - Rol "Cliente de Servicom" y panel simplificado.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SC_ROLE = 'sc_cliente';

/* -------------------------------------------------------------------------
 * Capacidades
 * ---------------------------------------------------------------------- */

function sc_woo_active(): bool {
	return class_exists( 'WooCommerce' ) || defined( 'WC_VERSION' );
}

/**
 * Capacidades del rol cliente. Con $woo=null se detecta WooCommerce.
 */
function sc_client_caps( ?bool $woo = null ): array {
	$woo  = ( null === $woo ) ? sc_woo_active() : $woo;
	$caps = array(
		'read',
		'upload_files',
		'edit_theme_options', // Personalizador y menús.
		'manage_categories',
		// Entradas.
		'edit_posts', 'edit_others_posts', 'edit_published_posts', 'edit_private_posts',
		'publish_posts', 'read_private_posts',
		'delete_posts', 'delete_others_posts', 'delete_published_posts', 'delete_private_posts',
		// Páginas.
		'edit_pages', 'edit_others_pages', 'edit_published_pages', 'edit_private_pages',
		'publish_pages', 'read_private_pages',
		'delete_pages', 'delete_others_pages', 'delete_published_pages', 'delete_private_pages',
	);
	if ( $woo ) {
		$caps = array_merge(
			$caps,
			array(
				'manage_woocommerce',
				// Productos.
				'edit_product', 'read_product', 'delete_product',
				'edit_products', 'edit_others_products', 'publish_products', 'read_private_products',
				'delete_products', 'delete_private_products', 'delete_published_products', 'delete_others_products',
				'edit_private_products', 'edit_published_products',
				'manage_product_terms', 'edit_product_terms', 'delete_product_terms', 'assign_product_terms',
				// Pedidos.
				'edit_shop_order', 'read_shop_order', 'delete_shop_order',
				'edit_shop_orders', 'edit_others_shop_orders', 'publish_shop_orders', 'read_private_shop_orders',
				'delete_shop_orders', 'delete_private_shop_orders', 'delete_published_shop_orders', 'delete_others_shop_orders',
				'edit_private_shop_orders', 'edit_published_shop_orders',
			)
		);
	}
	return (array) apply_filters( 'sc_client_caps', array_values( array_unique( $caps ) ), $woo );
}

/** Crea o sincroniza el rol (idempotente; solo escribe si cambió). */
function sc_roles_sync( bool $force = false ): void {
	if ( function_exists( 'wp_installing' ) && wp_installing() ) {
		return;
	}
	$caps = sc_client_caps();
	sort( $caps );
	$sig = md5( implode( ',', $caps ) . '|' . SC_CORE_VERSION );
	if ( ! $force && get_option( 'sc_roles_sig' ) === $sig && get_role( SC_ROLE ) ) {
		return;
	}
	$role = get_role( SC_ROLE );
	if ( ! $role ) {
		add_role( SC_ROLE, 'Cliente de Servicom', array() );
		$role = get_role( SC_ROLE );
	}
	if ( ! $role ) {
		return;
	}
	foreach ( array_keys( $role->capabilities ) as $c ) {
		if ( ! in_array( $c, $caps, true ) ) {
			$role->remove_cap( $c );
		}
	}
	foreach ( $caps as $c ) {
		if ( empty( $role->capabilities[ $c ] ) ) {
			$role->add_cap( $c, true );
		}
	}
	update_option( 'sc_roles_sig', $sig, false );
}
add_action( 'init', 'sc_roles_sync', 5 );

/** ¿Es un usuario "cliente" (limitado)? Los administradores nunca lo son. */
function sc_is_client( $user = null ): bool {
	if ( null === $user ) {
		$user = wp_get_current_user();
	} elseif ( is_numeric( $user ) ) {
		$user = get_userdata( (int) $user );
	}
	if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
		return false;
	}
	if ( user_can( $user, 'manage_options' ) ) {
		return false;
	}
	return in_array( SC_ROLE, (array) $user->roles, true );
}

// El cliente jamás edita CSS adicional ni HTML sin filtrar.
add_filter( 'map_meta_cap', 'sc_roles_map_meta_cap', 10, 4 );
function sc_roles_map_meta_cap( $caps, $cap, $user_id, $args ) {
	static $busy = false;
	if ( $busy || ! in_array( $cap, array( 'edit_css', 'unfiltered_html', 'unfiltered_upload' ), true ) ) {
		return $caps;
	}
	$busy = true;
	$is   = sc_is_client( (int) $user_id );
	$busy = false;
	return $is ? array( 'do_not_allow' ) : $caps;
}

/* -------------------------------------------------------------------------
 * Crear usuario cliente
 * ---------------------------------------------------------------------- */

add_filter( 'password_reset_expiration', 'sc_roles_reset_expiration' );
function sc_roles_reset_expiration( $s ) {
	return 7 * DAY_IN_SECONDS;
}

/**
 * Crea (o reutiliza) el usuario cliente. No se envía ningún correo.
 *
 * @return array{user_id:int,reset_url:string,error?:string}
 */
function sc_create_client_user( string $email, string $name ): array {
	$fail  = static function ( string $m ): array {
		return array( 'user_id' => 0, 'reset_url' => '', 'error' => $m );
	};
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) {
		return $fail( 'Correo no válido.' );
	}
	sc_roles_sync( true );
	$name = trim( sanitize_text_field( $name ) );
	if ( '' === $name ) {
		$name = (string) strstr( $email, '@', true );
	}

	$existing = get_user_by( 'email', $email );
	if ( $existing ) {
		if ( user_can( $existing, 'manage_options' ) ) {
			return $fail( 'Ese correo pertenece a un administrador.' );
		}
		$uid = (int) $existing->ID;
		$existing->set_role( SC_ROLE );
		wp_update_user( array( 'ID' => $uid, 'display_name' => $name, 'first_name' => $name ) );
	} else {
		$base  = sanitize_user( strtolower( $email ), true );
		$login = $base;
		for ( $i = 2; username_exists( $login ); $i++ ) {
			$login = $base . $i;
		}
		$uid = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'user_email'   => $email,
				'display_name' => $name,
				'nickname'     => $name,
				'first_name'   => $name,
				'role'         => SC_ROLE,
			)
		);
		if ( is_wp_error( $uid ) ) {
			return $fail( $uid->get_error_message() );
		}
		$uid = (int) $uid;
	}

	update_user_meta( $uid, 'sc_client', 1 );
	update_option( 'sc_client_user_id', $uid, false );

	$user = get_userdata( $uid );
	$key  = get_password_reset_key( $user );
	if ( is_wp_error( $key ) ) {
		return array( 'user_id' => $uid, 'reset_url' => '', 'error' => $key->get_error_message() );
	}
	$url = network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ), 'login' );
	return array( 'user_id' => $uid, 'reset_url' => $url );
}

/* -------------------------------------------------------------------------
 * Panel simplificado para el cliente
 * ---------------------------------------------------------------------- */

function sc_instructions_url( array $args = array() ): string {
	return add_query_arg( $args, admin_url( 'admin.php?page=sc-instrucciones' ) );
}

function sc_wc_orders_url(): string {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
		return admin_url( 'admin.php?page=wc-orders' );
	}
	return admin_url( 'edit.php?post_type=shop_order' );
}

/** Menús permitidos del cliente (primer nivel). */
function sc_client_menu_allowlist(): array {
	return (array) apply_filters(
		'sc_client_menu_allowlist',
		array( 'sc-instrucciones', 'edit.php?post_type=page', 'edit.php', 'upload.php', 'themes.php', 'edit.php?post_type=product', 'sc-pedidos', 'profile.php', 'fluent_forms', 'wpcf7' )
	);
}

add_action( 'admin_menu', 'sc_roles_trim_menu', 9999 );
function sc_roles_trim_menu(): void {
	if ( ! sc_is_client() ) {
		return;
	}
	global $menu, $submenu;
	$allow = sc_client_menu_allowlist();
	foreach ( (array) $menu as $pos => $item ) {
		$slug = (string) ( $item[2] ?? '' );
		if ( '' === ( $item[0] ?? '' ) && 0 === strpos( (string) ( $item[4] ?? '' ), 'wp-menu-separator' ) ) {
			continue; // Separadores.
		}
		if ( ! in_array( $slug, $allow, true ) ) {
			remove_menu_page( $slug );
		}
	}
	// Apariencia: solo Personalizar y Menús.
	if ( isset( $submenu['themes.php'] ) ) {
		foreach ( $submenu['themes.php'] as $k => $sub ) {
			$s = (string) $sub[2];
			if ( 'nav-menus.php' !== $s && 0 !== strpos( $s, 'customize.php' ) ) {
				unset( $submenu['themes.php'][ $k ] );
			}
		}
	}
	// Productos: sin atributos ni importadores.
	if ( isset( $submenu['edit.php?post_type=product'] ) ) {
		foreach ( $submenu['edit.php?post_type=product'] as $k => $sub ) {
			if ( preg_match( '/attributes|import|export|tag/i', (string) $sub[2] ) ) {
				unset( $submenu['edit.php?post_type=product'][ $k ] );
			}
		}
	}
	// Entradas: sin etiquetas.
	if ( isset( $submenu['edit.php'] ) ) {
		foreach ( $submenu['edit.php'] as $k => $sub ) {
			if ( false !== strpos( (string) $sub[2], 'post_tag' ) ) {
				unset( $submenu['edit.php'][ $k ] );
			}
		}
	}
	// Pedidos de WooCommerce como menú propio.
	if ( sc_woo_active() && current_user_can( 'edit_shop_orders' ) ) {
		add_menu_page( 'Pedidos', 'Pedidos', 'edit_shop_orders', 'sc-pedidos', '__return_null', 'dashicons-cart', 58 );
	}
}

// Pedidos: enlazamos directamente a la lista de WooCommerce.
add_action( 'admin_menu', 'sc_roles_orders_link', 10000 );
function sc_roles_orders_link(): void {
	global $menu;
	if ( ! sc_is_client() ) {
		return;
	}
	foreach ( (array) $menu as $k => $item ) {
		if ( 'sc-pedidos' === ( $item[2] ?? '' ) ) {
			$menu[ $k ][2] = ltrim( str_replace( admin_url(), '', sc_wc_orders_url() ), '/' );
		}
	}
}

/** Pantallas que el cliente nunca ve: lo manda a INSTRUCCIONES. */
add_action( 'admin_init', 'sc_roles_guard_screens', 2 );
add_action( 'admin_page_access_denied', 'sc_roles_guard_screens' );
function sc_roles_guard_screens(): void {
	if ( ! sc_is_client() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
		return;
	}
	global $pagenow;
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
	$pt   = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : ''; // phpcs:ignore
	$tax  = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : ''; // phpcs:ignore
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore

	$always_ok = array(
		'admin-ajax.php', 'admin-post.php', 'async-upload.php', 'upload.php', 'media.php', 'media-new.php',
		'post.php', 'nav-menus.php', 'customize.php', 'profile.php', 'load-scripts.php', 'load-styles.php',
		'admin-footer.php', 'link.php',
	);
	$blocked = false;
	if ( in_array( $pagenow, $always_ok, true ) ) {
		$blocked = false;
	} elseif ( 'post-new.php' === $pagenow || 'edit.php' === $pagenow ) {
		$blocked = ! in_array( $pt, array( '', 'post', 'page', 'product', 'shop_order' ), true );
	} elseif ( 'edit-tags.php' === $pagenow || 'term.php' === $pagenow ) {
		$blocked = ! in_array( $tax, array( 'category', 'product_cat' ), true );
	} elseif ( 'admin.php' === $pagenow ) {
		$ok_pages = (array) apply_filters( 'sc_client_allowed_pages', array( 'sc-instrucciones', 'wc-orders', 'fluent_forms', 'wpcf7', 'sc-pedidos' ) );
		$blocked  = true;
		if ( in_array( $page, $ok_pages, true ) ) {
			$blocked = false;
		} elseif ( 'wc-settings' === $page && 'checkout' === $tab ) {
			$blocked = false; // Métodos de pago y datos bancarios.
		}
	} else {
		$blocked = true; // index.php, plugins, tools, options, users, themes, etc.
	}

	// post.php: no permitir editar tipos internos de Elementor distintos del kit.
	if ( 'post.php' === $pagenow && ! $blocked ) {
		$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore
		if ( $id ) {
			$p = get_post( $id );
			if ( $p && 'elementor_library' === $p->post_type && (int) get_option( 'elementor_active_kit' ) !== $id ) {
				$blocked = true;
			}
		}
	}

	if ( $blocked ) {
		wp_safe_redirect( sc_instructions_url( array( 'sc_aviso' => 1 ) ) );
		exit;
	}
}

// Quitar la bandeja de Woo para otras tabs de ajustes.
add_action( 'admin_head', 'sc_roles_wc_settings_css' );
function sc_roles_wc_settings_css(): void {
	if ( sc_is_client() && isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] ) { // phpcs:ignore
		echo '<style>.woocommerce .nav-tab-wrapper,.woocommerce .subsubsub:not(.sc-keep){display:none!important}</style>';
	}
}

add_action( 'wp_dashboard_setup', 'sc_roles_clean_dashboard', 999 );
function sc_roles_clean_dashboard(): void {
	if ( ! sc_is_client() ) {
		return;
	}
	global $wp_meta_boxes;
	$wp_meta_boxes['dashboard'] = array();
	remove_action( 'welcome_panel', 'wp_welcome_panel' );
}

add_action( 'admin_bar_menu', 'sc_roles_admin_bar', 999 );
function sc_roles_admin_bar( $bar ): void {
	if ( function_exists( 'sc_instructions_url' ) && current_user_can( 'edit_pages' ) ) {
		$bar->add_node(
			array(
				'id'    => 'sc-instrucciones',
				'title' => 'Cómo editar mi web',
				'href'  => sc_instructions_url(),
			)
		);
	}
	if ( ! sc_is_client() ) {
		return;
	}
	$keep = array( 'menu-toggle', 'site-name', 'view-site', 'edit', 'customize', 'top-secondary', 'my-account', 'user-actions', 'user-info', 'edit-profile', 'logout', 'elementor_edit_page', 'sc-instrucciones', 'root-default' );
	foreach ( $bar->get_nodes() as $node ) {
		if ( ! in_array( $node->id, $keep, true ) ) {
			$bar->remove_node( $node->id );
		}
	}
}

// Inicio de sesión: directo a INSTRUCCIONES.
add_filter( 'login_redirect', 'sc_roles_login_redirect', 99, 3 );
function sc_roles_login_redirect( $redirect_to, $requested, $user ) {
	if ( ! ( $user instanceof WP_User ) || ! sc_is_client( $user ) ) {
		return $redirect_to;
	}
	$path = (string) wp_parse_url( (string) $requested, PHP_URL_PATH );
	$base = basename( rtrim( $path, '/' ) );
	if ( '' === (string) $requested || in_array( $base, array( 'wp-admin', 'index.php', 'wp-login.php', '' ), true ) ) {
		return sc_instructions_url();
	}
	return $redirect_to;
}
