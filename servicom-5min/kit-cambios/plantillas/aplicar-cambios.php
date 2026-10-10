<?php
/**
 * Cambios Servicom — DESCRIBIR AQUÍ QUÉ CAMBIA
 * ID: AAAAMMDD_HHMM (cámbielo en el nombre del archivo y en la línea $id de abajo)
 *
 * Se ejecuta UNA sola vez, la primera vez que alguien abre la web después de subir este archivo, y se borra sola.
 * Aplica los cambios de contenido con el MISMO motor del editor de la web (mismas validaciones, mismo «Deshacer»).
 * Si algo falla no rompe la web: guarda el motivo en la opción «sc_cambio_<ID>_error» y avisa en el panel.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

add_action( 'init', function () {
	$id = 'sc_cambio_AAAAMMDD_HHMM';   // ← ID único de este cambio (el mismo del nombre del archivo)
	if ( get_option( $id ) ) {            // ya aplicado antes
		@unlink( __FILE__ );
		return;
	}
	if ( ! function_exists( 'sc_ed_rest_edit' ) ) {   // el editor todavía no cargó
		return;
	}

	/* ======== CAMBIOS: lotes de operaciones del editor (máx. 40 por lote) ======== */
	$lotes = array(
		// array(
		//   array( 'op' => 'text', 'path' => 'pages.home.sections.0.data.title', 'value' => 'Nuevo título' ),
		//   array( 'op' => 'biz',  'values' => array( 'telefono' => '2222-3333', 'whatsapp' => '50255551234' ) ),
		// ),
	);
	/* ============================================================================= */

	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	if ( ! $admins ) {
		update_option( $id . '_error', 'No hay un usuario administrador para aplicar los cambios.', false );
		return;
	}
	wp_set_current_user( (int) $admins[0] );

	$hecho = (int) get_option( $id . '_lote', 0 );
	for ( $i = $hecho; $i < count( $lotes ); $i++ ) {
		$req = new WP_REST_Request( 'POST', '/sc/v1/edit' );
		$req->set_header( 'content-type', 'application/json' );
		$req->set_body( wp_json_encode( array( 'ops' => $lotes[ $i ] ) ) );
		$res  = rest_do_request( $req );
		$data = $res->get_data();
		if ( $res->get_status() >= 400 || empty( $data['ok'] ) ) {
			update_option( $id . '_error', 'Lote ' . ( $i + 1 ) . ': ' . ( is_array( $data ) && ! empty( $data['msg'] ) ? $data['msg'] : 'error ' . $res->get_status() ), false );
			add_action( 'admin_notices', function () use ( $id ) {
				echo '<div class="notice notice-error"><p>No se pudieron aplicar los cambios (' . esc_html( (string) get_option( $id . '_error' ) ) . '). Avise a Servicom.</p></div>';
			} );
			return;                        // no se borra: se puede corregir y reintentar
		}
		update_option( $id . '_lote', $i + 1, false );
	}

	/* ======== Otras acciones propias de este cambio (opcional; usar solo funciones de WordPress) ======== */
	// set_theme_mod( 'nombre', 'valor' );
	/* ===================================================================================================== */

	delete_option( $id . '_error' );
	update_option( $id, time(), false );
	@unlink( __FILE__ );
}, 30 );
