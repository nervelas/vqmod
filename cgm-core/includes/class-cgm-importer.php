<?php
/**
 * Importador de contenido inicial de CGM. Idempotente: no duplica ni borra.
 *
 * @package CGM_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CGM_Importer {

	private static $instance = null;
	private $log = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_cgm_run_import', array( $this, 'handle_import' ) );
	}

	/**
	 * Punto de entrada desde el botón del admin (con nonce + capacidad).
	 */
	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'cgm' ) );
		}
		check_admin_referer( 'cgm_run_import' );

		$this->run();

		$redirect = add_query_arg(
			array(
				'page'       => 'cgm-settings',
				'tab'        => 'import',
				'cgm_import' => 'done',
			),
			admin_url( 'admin.php' )
		);
		set_transient( 'cgm_import_log', $this->log, 120 );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Ejecuta la importación completa (idempotente).
	 */
	public function run() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$this->import_badges();
		$this->import_pages();
		$this->import_slides();
		$this->import_menu();

		if ( class_exists( 'WooCommerce' ) ) {
			$this->import_products();
		} else {
			$this->log[] = array( 'warn', __( 'WooCommerce no está activo: se omitieron categorías y productos.', 'cgm' ) );
		}

		update_option( 'cgm_demo_imported', CGM_CORE_VERSION );
		$this->log[] = array( 'ok', __( 'Importación finalizada.', 'cgm' ) );
	}

	/* ---------------- Media ---------------- */

	/**
	 * Sube (una sola vez) un archivo de /demo/media a la biblioteca.
	 *
	 * @param string $filename Nombre de archivo en demo/media.
	 * @param string $title    Título / alt.
	 * @return int Attachment ID o 0.
	 */
	private function sideload( $filename, $title ) {
		$source = CGM_CORE_DIR . 'demo/media/' . $filename;
		if ( ! file_exists( $source ) ) {
			return 0;
		}
		// Idempotencia: ¿ya existe un adjunto de este archivo fuente?
		$existing = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_key'    => '_cgm_source',
				'meta_value'  => $filename,
			)
		);
		if ( ! empty( $existing ) ) {
			return (int) $existing[0];
		}

		$tmp = wp_tempnam( $filename );
		if ( ! $tmp ) {
			return 0;
		}
		copy( $source, $tmp );

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp,
		);
		$id = media_handle_sideload( $file_array, 0, $title );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			return 0;
		}
		update_post_meta( $id, '_cgm_source', $filename );
		update_post_meta( $id, '_wp_attachment_image_alt', $title );
		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => $title,
			)
		);
		return (int) $id;
	}

	/* ---------------- Badges ---------------- */

	private function import_badges() {
		$badges = array(
			1 => array( 'badge-envios-domicilio.png', 'Envíos a domicilio' ),
			2 => array( 'badge-cambios-garantizados.png', 'Cambios garantizados' ),
			3 => array( 'badge-pago-seguro.png', 'Pago seguro' ),
			4 => array( 'badge-compra-responsable.png', 'Compra responsable' ),
			5 => array( 'badge-atencion-personalizada.png', 'Atención personalizada' ),
		);
		$opts = get_option( CGM_CORE_OPTION, array() );
		$opts = is_array( $opts ) ? $opts : array();
		foreach ( $badges as $i => $b ) {
			$key = "badge_{$i}_icon";
			if ( empty( $opts[ $key ] ) ) {
				$id = $this->sideload( $b[0], $b[1] );
				if ( $id ) {
					$opts[ $key ] = $id;
				}
			}
		}
		update_option( CGM_CORE_OPTION, $opts );
		$this->log[] = array( 'ok', __( 'Iconos de confianza (badges) cargados.', 'cgm' ) );
	}

	/* ---------------- Pages ---------------- */

	private function get_or_create_page( $slug, $title, $content = '', $template = '' ) {
		$existing = get_page_by_path( $slug );
		if ( $existing instanceof WP_Post ) {
			return $existing->ID;
		}
		$id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => $content,
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_cgm_demo', 1 );
			if ( $template ) {
				update_post_meta( $id, '_wp_page_template', $template );
			}
			return $id;
		}
		return 0;
	}

	private function import_pages() {
		// Página de inicio (usa plantilla del tema front-page.php automáticamente).
		$home = $this->get_or_create_page( 'inicio', __( 'Inicio', 'cgm' ), '' );

		$about   = $this->get_or_create_page( 'sobre-nosotros', __( 'Sobre Nosotros', 'cgm' ), '', 'page-templates/tpl-about.php' );
		$contact = $this->get_or_create_page( 'contacto', __( 'Contacto', 'cgm' ), '', 'page-templates/tpl-contact.php' );

		// Páginas de políticas (contenido desde opciones; contenido base como respaldo).
		$this->get_or_create_page( 'politicas-de-envio', __( 'Políticas de Envío', 'cgm' ), cgm_format_text( cgm_get_option( 'shipping_body' ) ) );
		$this->get_or_create_page( 'cambios-garantizados', __( 'Cambios Garantizados', 'cgm' ), cgm_format_text( cgm_get_option( 'returns_body' ) ) );
		$this->get_or_create_page( 'pago-seguro', __( 'Pago Seguro', 'cgm' ), cgm_format_text( cgm_get_option( 'payment_body' ) ) );
		$this->get_or_create_page( 'compra-responsable', __( 'Compra Responsable', 'cgm' ), cgm_format_text( cgm_get_option( 'responsible_body' ) ) );

		if ( $home ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home );
		}
		$this->log[] = array( 'ok', __( 'Páginas creadas / verificadas.', 'cgm' ) );
	}

	/* ---------------- Slides ---------------- */

	private function import_slides() {
		$existing = get_posts(
			array(
				'post_type'   => CGM_Slides::CPT,
				'numberposts' => 1,
				'fields'      => 'ids',
				'post_status' => 'any',
			)
		);
		if ( ! empty( $existing ) ) {
			$this->log[] = array( 'skip', __( 'Slides ya existentes: no se recrearon.', 'cgm' ) );
			return;
		}

		$shop_url = class_exists( 'WooCommerce' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' );

		$slides = array(
			array(
				'title'    => 'Colección CGM',
				'subtitle' => 'Para cada historia, el compañero perfecto',
				'heading'  => 'Crea Grandes Momentos',
				'desc'     => 'Termos, pachones y accesorios diseñados para acompañarte en cada aventura.',
				'cta'      => 'Comprar ahora',
				'file'     => 'hero-coleccion-crea-grandes-momentos.jpg',
			),
			array(
				'title'    => 'Tumbler para tu día',
				'subtitle' => 'Para tu día',
				'heading'  => 'Para cada gran momento',
				'desc'     => 'Diseñado para acompañarte siempre. Frío o calor, donde sea que vayas.',
				'cta'      => 'Ver productos',
				'file'     => 'hero-tumbler-para-cada-gran-momento.jpg',
			),
			array(
				'title'    => 'Línea Kids',
				'subtitle' => 'Para pequeños sueños',
				'heading'  => 'Para grandes momentos',
				'desc'     => 'Diseñados para acompañar sus aventuras, siempre listos para crear grandes momentos juntos.',
				'cta'      => 'Descubrir Kids',
				'file'     => 'hero-kids-para-grandes-momentos.jpg',
			),
		);

		$order = 0;
		foreach ( $slides as $s ) {
			$id = wp_insert_post(
				array(
					'post_type'   => CGM_Slides::CPT,
					'post_status' => 'publish',
					'post_title'  => $s['title'],
					'menu_order'  => $order++,
				)
			);
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			$img = $this->sideload( $s['file'], $s['title'] );
			update_post_meta( $id, '_cgm_subtitle', $s['subtitle'] );
			update_post_meta( $id, '_cgm_heading', $s['heading'] );
			update_post_meta( $id, '_cgm_description', $s['desc'] );
			update_post_meta( $id, '_cgm_cta_text', $s['cta'] );
			update_post_meta( $id, '_cgm_cta_url', $shop_url );
			update_post_meta( $id, '_cgm_image_id', $img );
			update_post_meta( $id, '_cgm_active', 1 );
			if ( $img ) {
				set_post_thumbnail( $id, $img );
			}
		}
		$this->log[] = array( 'ok', __( 'Slides del hero creados.', 'cgm' ) );
	}

	/* ---------------- Menu ---------------- */

	private function import_menu() {
		$menu_name = 'CGM Principal';
		$menu      = wp_get_nav_menu_object( $menu_name );
		if ( ! $menu ) {
			$menu_id = wp_create_nav_menu( $menu_name );
			if ( is_wp_error( $menu_id ) ) {
				return;
			}
		} else {
			$menu_id = $menu->term_id;
			// Ya existe: solo asegura la ubicación.
			$this->assign_menu_location( $menu_id );
			return;
		}

		$home    = get_page_by_path( 'inicio' );
		$about   = get_page_by_path( 'sobre-nosotros' );
		$contact = get_page_by_path( 'contacto' );
		$shop_id = class_exists( 'WooCommerce' ) ? wc_get_page_id( 'shop' ) : 0;

		if ( $home ) {
			wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => __( 'Inicio', 'cgm' ), 'menu-item-object' => 'page', 'menu-item-object-id' => $home->ID, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
		}
		if ( $about ) {
			wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => __( 'Sobre Nosotros', 'cgm' ), 'menu-item-object' => 'page', 'menu-item-object-id' => $about->ID, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
		}
		if ( $shop_id > 0 ) {
			wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => __( 'Nuestros Productos', 'cgm' ), 'menu-item-object' => 'page', 'menu-item-object-id' => $shop_id, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
		}
		if ( $contact ) {
			wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => __( 'Contacto', 'cgm' ), 'menu-item-object' => 'page', 'menu-item-object-id' => $contact->ID, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
		}

		$this->assign_menu_location( $menu_id );
		$this->log[] = array( 'ok', __( 'Menú principal creado y asignado.', 'cgm' ) );
	}

	private function assign_menu_location( $menu_id ) {
		$locations             = get_theme_mod( 'nav_menu_locations', array() );
		$locations['primary']  = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	/* ---------------- Products ---------------- */

	private function get_or_create_cat( $slug, $name ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $term ) {
			return (int) $term->term_id;
		}
		$res = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
		if ( is_wp_error( $res ) ) {
			return 0;
		}
		return (int) $res['term_id'];
	}

	private function build_description( $intro, $features ) {
		$html = '<p>' . esc_html( $intro ) . '</p>';
		if ( ! empty( $features ) ) {
			$html .= '<h3>' . esc_html__( 'Características', 'cgm' ) . '</h3><ul class="cgm-features">';
			foreach ( $features as $f ) {
				$html .= '<li>' . esc_html( $f ) . '</li>';
			}
			$html .= '</ul>';
		}
		return $html;
	}

	private function get_or_create_product( $sku, $args ) {
		$existing = wc_get_product_id_by_sku( $sku );
		if ( $existing ) {
			return $existing;
		}
		$product = new WC_Product_Simple();
		$product->set_name( $args['name'] );
		$product->set_sku( $sku );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_short_description( '<p>' . esc_html( $args['intro'] ) . '</p>' );
		$product->set_description( $this->build_description( $args['intro'], $args['features'] ) );
		if ( isset( $args['price'] ) && '' !== $args['price'] ) {
			$product->set_regular_price( (string) $args['price'] );
			$product->set_manage_stock( false );
			$product->set_stock_status( 'instock' );
		} else {
			// Sin precio: no comprable hasta que el admin lo defina.
			$product->set_stock_status( 'instock' );
		}
		if ( ! empty( $args['cat_ids'] ) ) {
			$product->set_category_ids( $args['cat_ids'] );
		}
		$id = $product->save();
		if ( $id ) {
			update_post_meta( $id, '_cgm_demo', 1 );
			if ( ! empty( $args['personalizable'] ) ) {
				update_post_meta( $id, '_cgm_personalizable', 'yes' );
			}
		}
		return $id;
	}

	private function import_products() {
		$cats = array(
			'pachones'      => $this->get_or_create_cat( 'pachones', 'Pachones' ),
			'termos'        => $this->get_or_create_cat( 'termos', 'Termos' ),
			'kids'          => $this->get_or_create_cat( 'kids', 'Kids' ),
			'combos'        => $this->get_or_create_cat( 'combos', 'Combos' ),
			'personalizados'=> $this->get_or_create_cat( 'personalizados', 'Personalizados' ),
			'accesorios'    => $this->get_or_create_cat( 'accesorios', 'Accesorios' ),
		);

		$products = array(
			// PACHONES
			array(
				'sku'   => 'CGM-KETTLE-850', 'name' => 'Kettle 850 ml', 'price' => '170.00', 'cat' => 'pachones',
				'intro' => 'El compañero ideal para entrenamientos, caminatas y aventuras al aire libre.',
				'features' => array( 'Acero inoxidable de doble pared', 'Conserva bebidas frías y calientes', 'Botón de apertura automática', 'Sistema antiderrames', 'Agarradera resistente', 'Base antideslizante', 'Capacidad: 850 ml' ),
			),
			array(
				'sku'   => 'CGM-KETTLE-SPORT-1000', 'name' => 'Kettle Sport 1000 ml', 'price' => '170.00', 'cat' => 'pachones',
				'intro' => 'Mayor capacidad para quienes necesitan hidratación durante todo el día.',
				'features' => array( 'Acero Premium 316', 'Doble pared térmica', 'Botón automático con seguro', 'Asa rígida', 'Sistema antiderrames', 'Capacidad: 1000 ml' ),
			),
			array(
				'sku'   => 'CGM-TUMBLER-40OZ', 'name' => 'Tumbler Ergonómico 40 oz', 'price' => '225.00', 'cat' => 'pachones',
				'intro' => 'Perfecto para oficina, automóvil y viajes.',
				'features' => array( 'Conserva frío hasta 24 horas', 'Pajilla reutilizable', 'Base para portavasos', 'Asa ergonómica', 'Tapa hermética', 'Acabado premium' ),
			),
			// TERMOS
			array(
				'sku'   => 'CGM-TERMO-SPORT-DIGITAL', 'name' => 'Termo Sport Digital', 'price' => '170.00', 'cat' => 'termos',
				'intro' => 'Tecnología inteligente para conocer la temperatura exacta de tu bebida.',
				'features' => array( 'Pantalla táctil LED', 'Temperatura en tiempo real', 'Acero inoxidable 316', 'Doble pared al vacío', 'Sistema antiderrames', 'Acabado mate premium' ),
			),
			// KIDS
			array(
				'sku'   => 'CGM-KIDS-OSITO-400', 'name' => 'Pachón Infantil Osito 400 ml', 'price' => '140.00', 'cat' => 'kids',
				'intro' => 'Diseñado especialmente para acompañar a los más pequeños.',
				'features' => array( 'Orejitas 3D', 'Acero inoxidable', 'Botón automático', 'Sistema antiderrames', 'Base antideslizante', 'Ideal para escuela' ),
			),
			array(
				'sku'   => 'CGM-KIDS-TERMO-24OZ', 'name' => 'Termo Kids 24 oz', 'price' => '180.00', 'cat' => 'kids',
				'intro' => 'Diseño práctico y resistente para seguir el ritmo escolar.',
				'features' => array( 'Acero inoxidable', 'Sistema antiderrames', 'Boquilla', 'Base de silicón', 'Ideal para escuela' ),
			),
			// ACCESORIOS (sin precio: el documento no lo especifica)
			array(
				'sku'   => 'CGM-ACC-PROTECTOR-SILICON', 'name' => 'Protector de Silicón', 'price' => '', 'cat' => 'accesorios',
				'intro' => 'Completa tu experiencia y protege tu termo o pachón.',
				'features' => array( 'Antideslizante', 'Protege contra golpes', 'Reduce el ruido al apoyar el termo', 'Fácil de instalar' ),
			),
			// PERSONALIZADOS (servicio; precio configurable por el admin)
			array(
				'sku'   => 'CGM-PERSONALIZACION', 'name' => 'Personalización con Vinil', 'price' => '', 'cat' => 'personalizados', 'personalizable' => true,
				'intro' => 'Hazlo único: personaliza tu termo o pachón con nombre, iniciales o frase corta en vinil de alta calidad. Ideal para regalos.',
				'features' => array( 'Nombre', 'Iniciales', 'Frase corta', 'Vinil resistente al agua', 'Ideal para regalos' ),
			),
		);

		$created = 0;
		foreach ( $products as $p ) {
			$cat_id = isset( $cats[ $p['cat'] ] ) ? array( $cats[ $p['cat'] ] ) : array();
			$id     = $this->get_or_create_product(
				$p['sku'],
				array(
					'name'           => $p['name'],
					'price'          => $p['price'],
					'intro'          => $p['intro'],
					'features'       => $p['features'],
					'cat_ids'        => $cat_id,
					'personalizable' => ! empty( $p['personalizable'] ),
				)
			);
			if ( $id ) {
				$created++;
			}
		}
		$this->log[] = array( 'ok', sprintf( __( 'Categorías y productos verificados (%d productos).', 'cgm' ), $created ) );
		$this->log[] = array( 'info', __( 'Las fotografías de producto y las variaciones de color se agregan luego desde WooCommerce (Productos → Editar).', 'cgm' ) );
	}
}
