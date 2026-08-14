<?php
/**
 * Panel de administración CGM: menú, pestañas y render de campos.
 *
 * @package CGM_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CGM_Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Menú principal "CGM".
	 */
	public function menu() {
		add_menu_page(
			__( 'CGM', 'cgm' ),
			__( 'CGM', 'cgm' ),
			'manage_options',
			'cgm-settings',
			array( $this, 'render_page' ),
			'dashicons-buddicons-buddypress-logo',
			3
		);
		add_submenu_page( 'cgm-settings', __( 'Configuración', 'cgm' ), __( 'Configuración', 'cgm' ), 'manage_options', 'cgm-settings', array( $this, 'render_page' ) );
	}

	/**
	 * Encola scripts solo en las pantallas de CGM (settings + slides).
	 *
	 * @param string $hook Hook actual.
	 */
	public function assets( $hook ) {
		$screen     = get_current_screen();
		$is_slide   = $screen && CGM_Slides::CPT === $screen->post_type;
		$is_setting = false !== strpos( (string) $hook, 'cgm-settings' );
		if ( ! $is_slide && ! $is_setting ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'cgm-admin', CGM_CORE_URL . 'assets/css/admin.css', array(), CGM_CORE_VERSION );
		wp_enqueue_script( 'cgm-admin', CGM_CORE_URL . 'assets/js/admin.js', array( 'jquery' ), CGM_CORE_VERSION, true );
		wp_localize_script(
			'cgm-admin',
			'CGM_Admin',
			array(
				'chooseText' => __( 'Seleccionar imagen', 'cgm' ),
				'useText'    => __( 'Usar esta imagen', 'cgm' ),
			)
		);
	}

	/**
	 * Render de la página con pestañas.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$schema  = cgm_settings_schema();
		$tabs    = array();
		foreach ( $schema as $key => $tab ) {
			$tabs[ $key ] = $tab['label'];
		}
		$tabs['import'] = __( 'Importar contenido', 'cgm' );

		$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $active ] ) ) {
			$active = 'general';
		}
		?>
		<div class="wrap cgm-wrap">
			<h1><?php esc_html_e( 'CGM – Crea Grandes Momentos', 'cgm' ); ?></h1>
			<?php settings_errors( 'cgm_settings' ); ?>

			<h2 class="nav-tab-wrapper cgm-tabs">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cgm-settings&tab=' . $key ) ); ?>"
						class="nav-tab <?php echo $active === $key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<?php
			if ( 'import' === $active ) {
				$this->render_import_tab();
			} else {
				$this->render_settings_tab( $schema, $active );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render de una pestaña de ajustes.
	 */
	private function render_settings_tab( $schema, $active ) {
		?>
		<form method="post" action="options.php" class="cgm-form">
			<?php settings_fields( 'cgm_settings_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( CGM_CORE_OPTION ); ?>[__present]" value="<?php echo esc_attr( implode( ',', array_keys( $schema[ $active ]['fields'] ) ) ); ?>">
			<table class="form-table" role="presentation">
				<tbody>
				<?php foreach ( $schema[ $active ]['fields'] as $key => $field ) : ?>
					<?php $this->render_field( $key, $field ); ?>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Guardar cambios', 'cgm' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render de un campo individual.
	 */
	private function render_field( $key, $field ) {
		$type  = $field['type'] ?? 'text';
		$value = cgm_get_option( $key );
		$name  = CGM_CORE_OPTION . '[' . $key . ']';
		$id    = 'cgm_' . $key;
		$desc  = ! empty( $field['desc'] ) ? '<p class="description">' . esc_html( $field['desc'] ) . '</p>' : '';
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
			<td>
				<?php
				switch ( $type ) {
					case 'textarea':
						printf(
							'<textarea id="%1$s" name="%2$s" rows="%3$d" class="large-text">%4$s</textarea>',
							esc_attr( $id ),
							esc_attr( $name ),
							absint( $field['rows'] ?? 4 ),
							esc_textarea( $value )
						);
						break;

					case 'checkbox':
						printf(
							'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s> %4$s</label>',
							esc_attr( $id ),
							esc_attr( $name ),
							checked( (int) $value, 1, false ),
							esc_html__( 'Activar', 'cgm' )
						);
						break;

					case 'number':
						printf(
							'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" step="%6$s" class="small-text">',
							esc_attr( $id ),
							esc_attr( $name ),
							esc_attr( $value ),
							esc_attr( $field['min'] ?? '' ),
							esc_attr( $field['max'] ?? '' ),
							esc_attr( $field['step'] ?? '1' )
						);
						break;

					case 'media':
						$img_id  = absint( $value );
						$preview = $img_id ? wp_get_attachment_image( $img_id, 'medium' ) : '';
						?>
						<div class="cgm-media-field">
							<div class="cgm-media-preview"><?php echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							<input type="hidden" id="<?php echo esc_attr( $id ); ?>" class="cgm-media-id" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $img_id ); ?>">
							<button type="button" class="button cgm-media-upload"><?php esc_html_e( 'Seleccionar', 'cgm' ); ?></button>
							<button type="button" class="button cgm-media-remove"><?php esc_html_e( 'Quitar', 'cgm' ); ?></button>
						</div>
						<?php
						break;

					case 'select':
						echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
						foreach ( ( $field['choices'] ?? array() ) as $ck => $cl ) {
							printf( '<option value="%s" %s>%s</option>', esc_attr( $ck ), selected( $value, $ck, false ), esc_html( $cl ) );
						}
						echo '</select>';
						break;

					case 'url':
					case 'email':
					case 'tel':
					case 'text':
					default:
						$html_type = in_array( $type, array( 'url', 'email', 'tel' ), true ) ? $type : 'text';
						printf(
							'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text">',
							esc_attr( $html_type ),
							esc_attr( $id ),
							esc_attr( $name ),
							esc_attr( $value )
						);
						break;
				}
				echo $desc; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado arriba.
				?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Pestaña de importación de contenido.
	 */
	private function render_import_tab() {
		$done = isset( $_GET['cgm_import'] ) && 'done' === $_GET['cgm_import']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$log  = get_transient( 'cgm_import_log' );
		$imported_version = get_option( 'cgm_demo_imported' );
		?>
		<div class="cgm-card">
			<h2><?php esc_html_e( 'Importar contenido inicial', 'cgm' ); ?></h2>
			<p><?php esc_html_e( 'Crea las categorías, productos, páginas, slides del hero, menú principal e iconos de confianza con el contenido oficial de CGM. Es seguro ejecutarlo varias veces: no duplica ni borra contenido existente.', 'cgm' ); ?></p>
			<?php if ( $imported_version ) : ?>
				<p class="cgm-badge-ok"><?php echo esc_html( sprintf( __( 'Ya se ejecutó una importación (v%s). Puedes volver a ejecutarla para completar lo que falte.', 'cgm' ), $imported_version ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
				<p class="cgm-badge-warn"><?php esc_html_e( 'WooCommerce no está activo: se crearán páginas, slides y menú, pero no productos ni categorías. Actívalo y vuelve a ejecutar para cargar la tienda.', 'cgm' ); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cgm_run_import">
				<?php wp_nonce_field( 'cgm_run_import' ); ?>
				<?php submit_button( __( 'Ejecutar importación', 'cgm' ), 'primary', 'submit', false ); ?>
			</form>

			<?php if ( $done && is_array( $log ) ) : ?>
				<h3><?php esc_html_e( 'Resultado', 'cgm' ); ?></h3>
				<ul class="cgm-log">
					<?php
					delete_transient( 'cgm_import_log' );
					foreach ( $log as $line ) :
						$type = $line[0];
						$msg  = $line[1];
						$icon = 'ok' === $type ? '✅' : ( 'skip' === $type ? '⏭️' : ( 'warn' === $type ? '⚠️' : 'ℹ️' ) );
						?>
						<li><?php echo esc_html( $icon . ' ' . $msg ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="cgm-card">
			<h3><?php esc_html_e( 'Siguientes pasos recomendados', 'cgm' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Sube las fotografías reales de cada producto en Productos → Editar → Imagen del producto y Galería.', 'cgm' ); ?></li>
				<li><?php esc_html_e( 'Para colores: convierte el producto a "Variable", crea el atributo Color con sus términos y asigna una imagen a cada variación (la foto principal cambiará al elegir color).', 'cgm' ); ?></li>
				<li><?php esc_html_e( 'Configura los métodos de envío y de pago (Depósito bancario / pasarela) en WooCommerce → Ajustes.', 'cgm' ); ?></li>
				<li><?php esc_html_e( 'Ajusta el porcentaje del combo y las categorías elegibles en la pestaña "Productos y Combos".', 'cgm' ); ?></li>
			</ol>
		</div>
		<?php
	}
}
