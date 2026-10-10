<?php
/**
 * Plugin Name: Servicom Core
 * Description: Infraestructura de Servicom para sitios "Tu web en 5 minutos": datos del negocio, seguridad, rol de cliente, instrucciones, vista previa privada, cambio de dominio y autochequeo.
 * Version: 1.0.0
 * Author: Servicom
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'SC_CORE_VERSION' ) ) {
	return;
}

define( 'SC_CORE_VERSION', '1.0.0' );
define( 'SC_CORE_DIR', __DIR__ . '/servicom-core' );
define( 'SC_CORE_URL', function_exists( 'plugins_url' ) ? untrailingslashit( plugins_url( 'servicom-core', __FILE__ ) ) : '' );

/**
 * Carga un archivo sin que un error en él tumbe todo el sitio.
 */
function sc_core_require( string $file ): void {
	if ( ! is_file( $file ) ) {
		return;
	}
	try {
		require_once $file;
	} catch ( \Throwable $e ) {
		error_log( 'Servicom Core: no se pudo cargar ' . basename( $file ) . ': ' . $e->getMessage() );
	}
}

foreach ( array( 'hardening', 'design', 'roles', 'editor', 'business', 'instructions', 'pending', 'preview', 'domain', 'qa-support' ) as $sc_inc ) {
	sc_core_require( SC_CORE_DIR . '/includes/' . $sc_inc . '.php' );
}
unset( $sc_inc );

$sc_builder_files = glob( SC_CORE_DIR . '/includes/builder/*.php' );
if ( is_array( $sc_builder_files ) ) {
	sort( $sc_builder_files, SORT_STRING );
	foreach ( $sc_builder_files as $sc_bf ) {
		sc_core_require( $sc_bf );
	}
}
unset( $sc_builder_files, $sc_bf );
