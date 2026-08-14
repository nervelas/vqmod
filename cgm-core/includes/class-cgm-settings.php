<?php
/**
 * Registro y saneamiento de las opciones de CGM.
 *
 * @package CGM_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CGM_Settings {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Registra la opción única y su callback de saneamiento.
	 */
	public function register() {
		register_setting(
			'cgm_settings_group',
			CGM_CORE_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => cgm_settings_defaults(),
			)
		);
	}

	/**
	 * Sanea todos los campos según su tipo definido en el esquema.
	 *
	 * @param array $input Valores enviados.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$schema  = cgm_settings_schema();
		$current = get_option( CGM_CORE_OPTION, array() );
		$current = is_array( $current ) ? $current : array();
		$clean   = $current; // Parte de lo guardado para no perder pestañas no enviadas.

		// Solo procesamos los campos que pertenecen a la pestaña enviada,
		// así los checkboxes de otras pestañas no se reinician a 0.
		$present = array();
		if ( isset( $input['__present'] ) ) {
			$present = array_filter( array_map( 'sanitize_key', explode( ',', (string) $input['__present'] ) ) );
		}
		$has_scope = ! empty( $present );

		foreach ( $schema as $tab ) {
			foreach ( $tab['fields'] as $key => $field ) {
				$type = $field['type'] ?? 'text';

				if ( 'heading' === $type ) {
					continue;
				}

				// Si conocemos el alcance de la pestaña, ignora campos ajenos.
				if ( $has_scope && ! in_array( $key, $present, true ) ) {
					continue;
				}

				if ( 'checkbox' === $type ) {
					$clean[ $key ] = ( ! empty( $input[ $key ] ) ) ? 1 : 0;
					continue;
				}

				if ( ! array_key_exists( $key, $input ) ) {
					continue; // No enviado en esta pestaña; conserva valor previo.
				}

				$raw = $input[ $key ];

				switch ( $type ) {
					case 'textarea':
						$clean[ $key ] = sanitize_textarea_field( $raw );
						break;
					case 'url':
						$clean[ $key ] = esc_url_raw( trim( $raw ) );
						break;
					case 'email':
						$clean[ $key ] = sanitize_email( $raw );
						break;
					case 'number':
						$clean[ $key ] = is_numeric( $raw ) ? $raw + 0 : 0;
						break;
					case 'media':
						$clean[ $key ] = absint( $raw );
						break;
					case 'color':
						$clean[ $key ] = sanitize_hex_color( $raw );
						break;
					case 'select':
						$choices       = array_keys( $field['choices'] ?? array() );
						$clean[ $key ] = in_array( $raw, $choices, true ) ? $raw : ( $field['default'] ?? '' );
						break;
					case 'tel':
					case 'text':
					default:
						$clean[ $key ] = sanitize_text_field( $raw );
						break;
				}
			}
		}

		add_settings_error( 'cgm_settings', 'cgm_saved', __( 'Configuración de CGM guardada correctamente.', 'cgm' ), 'updated' );

		return $clean;
	}
}
