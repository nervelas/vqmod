<?php
/**
 * CPT "Slides" para el hero / carrusel principal. Administrable, reordenable.
 *
 * @package CGM_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CGM_Slides {

	private static $instance = null;
	const CPT = 'cgm_slide';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
		add_action( 'save_post_' . self::CPT, array( $this, 'save' ), 10, 2 );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
	}

	/**
	 * Registra el post type de slides.
	 */
	public static function register_cpt() {
		$labels = array(
			'name'               => __( 'Slides (Hero)', 'cgm' ),
			'singular_name'      => __( 'Slide', 'cgm' ),
			'add_new'            => __( 'Añadir slide', 'cgm' ),
			'add_new_item'       => __( 'Añadir nuevo slide', 'cgm' ),
			'edit_item'          => __( 'Editar slide', 'cgm' ),
			'new_item'           => __( 'Nuevo slide', 'cgm' ),
			'view_item'          => __( 'Ver slide', 'cgm' ),
			'search_items'       => __( 'Buscar slides', 'cgm' ),
			'not_found'          => __( 'No hay slides', 'cgm' ),
			'menu_name'          => __( 'Slides (Hero)', 'cgm' ),
		);

		register_post_type(
			self::CPT,
			array(
				'labels'          => $labels,
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'cgm-settings',
				'supports'        => array( 'title', 'thumbnail', 'page-attributes' ),
				'menu_icon'       => 'dashicons-images-alt2',
				'capability_type' => 'post',
				'hierarchical'    => false,
			)
		);
	}

	/**
	 * Meta box con los campos del slide.
	 */
	public function meta_box() {
		add_meta_box( 'cgm_slide_fields', __( 'Contenido del slide', 'cgm' ), array( $this, 'render' ), self::CPT, 'normal', 'high' );
	}

	/**
	 * Render del meta box.
	 *
	 * @param WP_Post $post Post actual.
	 */
	public function render( $post ) {
		wp_nonce_field( 'cgm_slide_save', 'cgm_slide_nonce' );
		$fields = self::get_meta( $post->ID );
		?>
		<style>
			.cgm-slide-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
			.cgm-slide-grid .full{grid-column:1/-1}
			.cgm-slide-grid label{font-weight:600;display:block;margin-bottom:4px}
			.cgm-slide-grid input[type=text],.cgm-slide-grid input[type=url],.cgm-slide-grid textarea{width:100%}
			.cgm-media-preview img{max-width:220px;height:auto;display:block;margin:6px 0;border:1px solid #ddd;border-radius:6px}
		</style>
		<div class="cgm-slide-grid">
			<div class="full">
				<label><?php esc_html_e( 'Subtítulo (texto pequeño superior)', 'cgm' ); ?></label>
				<input type="text" name="cgm_slide[subtitle]" value="<?php echo esc_attr( $fields['subtitle'] ); ?>">
			</div>
			<div class="full">
				<label><?php esc_html_e( 'Título principal', 'cgm' ); ?></label>
				<input type="text" name="cgm_slide[heading]" value="<?php echo esc_attr( $fields['heading'] ); ?>">
			</div>
			<div class="full">
				<label><?php esc_html_e( 'Descripción', 'cgm' ); ?></label>
				<textarea name="cgm_slide[description]" rows="2"><?php echo esc_textarea( $fields['description'] ); ?></textarea>
			</div>
			<div>
				<label><?php esc_html_e( 'Texto del botón (CTA)', 'cgm' ); ?></label>
				<input type="text" name="cgm_slide[cta_text]" value="<?php echo esc_attr( $fields['cta_text'] ); ?>">
			</div>
			<div>
				<label><?php esc_html_e( 'URL del botón', 'cgm' ); ?></label>
				<input type="url" name="cgm_slide[cta_url]" value="<?php echo esc_attr( $fields['cta_url'] ); ?>">
			</div>
			<div class="full cgm-media-field">
				<label><?php esc_html_e( 'Imagen (escritorio)', 'cgm' ); ?></label>
				<div class="cgm-media-preview">
					<?php if ( $fields['image_id'] ) { echo wp_get_attachment_image( $fields['image_id'], 'medium' ); } ?>
				</div>
				<input type="hidden" name="cgm_slide[image_id]" class="cgm-media-id" value="<?php echo esc_attr( $fields['image_id'] ); ?>">
				<button type="button" class="button cgm-media-upload"><?php esc_html_e( 'Seleccionar imagen', 'cgm' ); ?></button>
				<button type="button" class="button cgm-media-remove"><?php esc_html_e( 'Quitar', 'cgm' ); ?></button>
			</div>
			<div class="full cgm-media-field">
				<label><?php esc_html_e( 'Imagen (móvil, opcional)', 'cgm' ); ?></label>
				<div class="cgm-media-preview">
					<?php if ( $fields['image_mobile_id'] ) { echo wp_get_attachment_image( $fields['image_mobile_id'], 'medium' ); } ?>
				</div>
				<input type="hidden" name="cgm_slide[image_mobile_id]" class="cgm-media-id" value="<?php echo esc_attr( $fields['image_mobile_id'] ); ?>">
				<button type="button" class="button cgm-media-upload"><?php esc_html_e( 'Seleccionar imagen', 'cgm' ); ?></button>
				<button type="button" class="button cgm-media-remove"><?php esc_html_e( 'Quitar', 'cgm' ); ?></button>
			</div>
			<div class="full">
				<label><input type="checkbox" name="cgm_slide[active]" value="1" <?php checked( $fields['active'], 1 ); ?>> <?php esc_html_e( 'Slide activo', 'cgm' ); ?></label>
				<p class="description"><?php esc_html_e( 'El orden se controla con el campo "Orden" en Atributos de página (menor = primero).', 'cgm' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Guarda los campos del slide.
	 *
	 * @param int     $post_id ID.
	 * @param WP_Post $post    Post.
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['cgm_slide_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['cgm_slide_nonce'] ), 'cgm_slide_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in = isset( $_POST['cgm_slide'] ) && is_array( $_POST['cgm_slide'] ) ? wp_unslash( $_POST['cgm_slide'] ) : array();

		update_post_meta( $post_id, '_cgm_subtitle', sanitize_text_field( $in['subtitle'] ?? '' ) );
		update_post_meta( $post_id, '_cgm_heading', sanitize_text_field( $in['heading'] ?? '' ) );
		update_post_meta( $post_id, '_cgm_description', sanitize_textarea_field( $in['description'] ?? '' ) );
		update_post_meta( $post_id, '_cgm_cta_text', sanitize_text_field( $in['cta_text'] ?? '' ) );
		update_post_meta( $post_id, '_cgm_cta_url', esc_url_raw( $in['cta_url'] ?? '' ) );
		update_post_meta( $post_id, '_cgm_image_id', absint( $in['image_id'] ?? 0 ) );
		update_post_meta( $post_id, '_cgm_image_mobile_id', absint( $in['image_mobile_id'] ?? 0 ) );
		update_post_meta( $post_id, '_cgm_active', ! empty( $in['active'] ) ? 1 : 0 );
	}

	/**
	 * Devuelve los metadatos de un slide.
	 *
	 * @param int $post_id ID.
	 * @return array
	 */
	public static function get_meta( $post_id ) {
		return array(
			'subtitle'        => (string) get_post_meta( $post_id, '_cgm_subtitle', true ),
			'heading'         => (string) get_post_meta( $post_id, '_cgm_heading', true ),
			'description'     => (string) get_post_meta( $post_id, '_cgm_description', true ),
			'cta_text'        => (string) get_post_meta( $post_id, '_cgm_cta_text', true ),
			'cta_url'         => (string) get_post_meta( $post_id, '_cgm_cta_url', true ),
			'image_id'        => absint( get_post_meta( $post_id, '_cgm_image_id', true ) ),
			'image_mobile_id' => absint( get_post_meta( $post_id, '_cgm_image_mobile_id', true ) ),
			'active'          => (int) get_post_meta( $post_id, '_cgm_active', true ),
		);
	}

	/**
	 * Devuelve los slides activos ordenados.
	 *
	 * @return array Lista de arrays con id + meta.
	 */
	public static function get_active_slides() {
		$posts = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'numberposts'    => 20,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'   => '_cgm_active',
						'value' => 1,
					),
				),
			)
		);
		$out = array();
		foreach ( $posts as $p ) {
			$meta       = self::get_meta( $p->ID );
			$meta['id'] = $p->ID;
			$out[]      = $meta;
		}
		return $out;
	}

	public function columns( $columns ) {
		$new = array();
		foreach ( $columns as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['cgm_active'] = __( 'Activo', 'cgm' );
				$new['cgm_thumb']  = __( 'Imagen', 'cgm' );
			}
		}
		return $new;
	}

	public function column_content( $column, $post_id ) {
		if ( 'cgm_active' === $column ) {
			echo get_post_meta( $post_id, '_cgm_active', true ) ? '✅' : '—';
		}
		if ( 'cgm_thumb' === $column ) {
			$id = absint( get_post_meta( $post_id, '_cgm_image_id', true ) );
			if ( $id ) {
				echo wp_get_attachment_image( $id, array( 90, 50 ) );
			}
		}
	}
}
