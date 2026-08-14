<?php
/**
 * Integración con WooCommerce: descuento automático de combos y
 * personalización de productos (datos guardados en carrito, checkout y pedido).
 *
 * @package CGM_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CGM_Woo {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// --- Combos: descuento automático ---
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_combo_discount' ), 20 );

		// --- Personalización: campo en datos del producto ---
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'product_option_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_option' ) );

		// --- Personalización: front del producto ---
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_personalization_fields' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_personalization' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_personalization_to_cart' ), 10, 3 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_personalization_fee' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_personalization_in_cart' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_personalization_to_order' ), 10, 4 );
	}

	/* ============================================================
	 * COMBOS
	 * ============================================================ */

	/**
	 * Aplica el descuento de combo si el carrito cumple las condiciones.
	 *
	 * @param WC_Cart $cart Carrito.
	 */
	public function apply_combo_discount( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! cgm_get_option( 'combo_enabled' ) ) {
			return;
		}
		$percent = (float) cgm_get_option( 'combo_discount', 0 );
		if ( $percent <= 0 ) {
			return;
		}
		$cats = array_filter( array_map( 'trim', explode( ',', (string) cgm_get_option( 'combo_cats' ) ) ) );
		if ( count( $cats ) < 2 ) {
			return;
		}

		// Verifica que exista al menos un producto de cada categoría requerida.
		$found            = array_fill_keys( $cats, false );
		$eligible_subtotal = 0.0;
		foreach ( $cart->get_cart() as $item ) {
			$pid           = $item['product_id'];
			$product_cats  = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'slugs' ) );
			$is_eligible   = false;
			foreach ( $cats as $slug ) {
				if ( in_array( $slug, $product_cats, true ) ) {
					$found[ $slug ] = true;
					$is_eligible    = true;
				}
			}
			if ( $is_eligible ) {
				$eligible_subtotal += (float) $item['line_subtotal'];
			}
		}

		if ( in_array( false, $found, true ) || $eligible_subtotal <= 0 ) {
			return; // Falta al menos una categoría.
		}

		$discount = round( $eligible_subtotal * ( $percent / 100 ), 2 );
		if ( $discount > 0 ) {
			$label = sprintf( __( 'Descuento Combo (%s%%)', 'cgm' ), wc_trim_zeros( (string) $percent ) );
			$cart->add_fee( $label, -$discount, false );
		}
	}

	/* ============================================================
	 * PERSONALIZACIÓN
	 * ============================================================ */

	/**
	 * ¿El producto acepta personalización?
	 *
	 * @param int $product_id ID.
	 * @return bool
	 */
	public static function is_personalizable( $product_id ) {
		if ( 'yes' === get_post_meta( $product_id, '_cgm_personalizable', true ) ) {
			return true;
		}
		// Auto: productos de la categoría "personalizados".
		$cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'slugs' ) );
		return in_array( 'personalizados', (array) $cats, true );
	}

	/**
	 * Casilla en Datos del producto → General.
	 */
	public function product_option_field() {
		woocommerce_wp_checkbox(
			array(
				'id'          => '_cgm_personalizable',
				'label'       => __( 'Permitir personalización CGM', 'cgm' ),
				'description' => __( 'Muestra campos de nombre, iniciales y frase antes de agregar al carrito.', 'cgm' ),
			)
		);
	}

	/**
	 * Guarda la casilla.
	 *
	 * @param int $post_id ID.
	 */
	public function save_product_option( $post_id ) {
		$val = isset( $_POST['_cgm_personalizable'] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifica el nonce del producto.
		update_post_meta( $post_id, '_cgm_personalizable', $val );
	}

	/**
	 * Campos de personalización en la ficha del producto.
	 */
	public function render_personalization_fields() {
		global $product;
		if ( ! $product || ! self::is_personalizable( $product->get_id() ) ) {
			return;
		}
		$maxlen = absint( cgm_get_option( 'custom_maxlen', 40 ) );
		$fee    = (float) cgm_get_option( 'custom_fee', 0 );
		wp_nonce_field( 'cgm_personalize', 'cgm_personalize_nonce' );
		echo '<div class="cgm-personalize" aria-label="' . esc_attr__( 'Personalización', 'cgm' ) . '">';
		echo '<h3 class="cgm-personalize__title">' . esc_html( cgm_get_option( 'custom_title', 'Hazlo único' ) ) . '</h3>';
		if ( $fee > 0 ) {
			echo '<p class="cgm-personalize__fee">' . esc_html( sprintf( __( 'Cargo adicional por personalización: %s', 'cgm' ), wp_strip_all_tags( wc_price( $fee ) ) ) ) . '</p>';
		}
		?>
		<p class="cgm-field">
			<label for="cgm_p_name"><?php esc_html_e( 'Nombre', 'cgm' ); ?></label>
			<input type="text" id="cgm_p_name" name="cgm_p_name" maxlength="<?php echo esc_attr( $maxlen ); ?>">
		</p>
		<p class="cgm-field">
			<label for="cgm_p_initials"><?php esc_html_e( 'Iniciales', 'cgm' ); ?></label>
			<input type="text" id="cgm_p_initials" name="cgm_p_initials" maxlength="6">
		</p>
		<p class="cgm-field">
			<label for="cgm_p_phrase"><?php echo esc_html( sprintf( __( 'Frase corta (máx. %d caracteres)', 'cgm' ), $maxlen ) ); ?></label>
			<input type="text" id="cgm_p_phrase" name="cgm_p_phrase" maxlength="<?php echo esc_attr( $maxlen ); ?>">
		</p>
		<p class="cgm-field">
			<label for="cgm_p_notes"><?php esc_html_e( 'Observaciones', 'cgm' ); ?></label>
			<textarea id="cgm_p_notes" name="cgm_p_notes" rows="2" maxlength="300"></textarea>
		</p>
		<?php
		echo '</div>';
	}

	/**
	 * Sanea y recoge la personalización desde $_POST.
	 *
	 * @return array
	 */
	private function collect_personalization() {
		if ( ! isset( $_POST['cgm_personalize_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['cgm_personalize_nonce'] ), 'cgm_personalize' ) ) {
			return array();
		}
		$maxlen = absint( cgm_get_option( 'custom_maxlen', 40 ) );
		$data   = array();
		$map    = array(
			'name'     => 'cgm_p_name',
			'initials' => 'cgm_p_initials',
			'phrase'   => 'cgm_p_phrase',
			'notes'    => 'cgm_p_notes',
		);
		foreach ( $map as $key => $field ) {
			if ( ! empty( $_POST[ $field ] ) ) {
				$val = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
				if ( 'phrase' === $key || 'name' === $key ) {
					$val = mb_substr( $val, 0, $maxlen );
				}
				if ( 'initials' === $key ) {
					$val = mb_substr( $val, 0, 6 );
				}
				if ( 'notes' === $key ) {
					$val = mb_substr( $val, 0, 300 );
				}
				if ( '' !== $val ) {
					$data[ $key ] = $val;
				}
			}
		}
		return $data;
	}

	/**
	 * Validación al agregar al carrito (por si en el futuro se hace obligatorio).
	 */
	public function validate_personalization( $passed, $product_id, $quantity ) {
		return $passed; // La personalización es opcional; no bloquea la compra.
	}

	/**
	 * Adjunta la personalización al item del carrito.
	 */
	public function add_personalization_to_cart( $cart_item_data, $product_id, $variation_id ) {
		if ( ! self::is_personalizable( $product_id ) ) {
			return $cart_item_data;
		}
		$data = $this->collect_personalization();
		if ( ! empty( $data ) ) {
			$cart_item_data['cgm_personalization'] = $data;
			// Hace único el item para que no se agrupe con otro sin personalización.
			$cart_item_data['cgm_unique'] = md5( wp_json_encode( $data ) . microtime() );
		}
		return $cart_item_data;
	}

	/**
	 * Aplica el cargo adicional de personalización al precio del item.
	 *
	 * @param WC_Cart $cart Carrito.
	 */
	public function apply_personalization_fee( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		$fee = (float) cgm_get_option( 'custom_fee', 0 );
		if ( $fee <= 0 ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item['cgm_personalization'] ) || ! isset( $item['data'] ) || ! is_object( $item['data'] ) ) {
				continue;
			}
			// Precio base leído de nuevo desde el producto para NO acumular el cargo
			// si el hook se ejecuta varias veces en la misma petición.
			$ref     = ! empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'];
			$product = wc_get_product( $ref );
			if ( ! $product ) {
				continue;
			}
			$base = (float) $product->get_price( 'edit' );
			$item['data']->set_price( $base + $fee );
		}
	}

	/**
	 * Muestra la personalización en carrito y checkout.
	 */
	public function display_personalization_in_cart( $item_data, $cart_item ) {
		if ( empty( $cart_item['cgm_personalization'] ) ) {
			return $item_data;
		}
		foreach ( $this->labels() as $key => $label ) {
			if ( ! empty( $cart_item['cgm_personalization'][ $key ] ) ) {
				$item_data[] = array(
					'key'   => $label,
					'value' => wc_clean( $cart_item['cgm_personalization'][ $key ] ),
				);
			}
		}
		return $item_data;
	}

	/**
	 * Persiste la personalización en la línea del pedido (visible para el admin).
	 */
	public function save_personalization_to_order( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['cgm_personalization'] ) ) {
			return;
		}
		foreach ( $this->labels() as $key => $label ) {
			if ( ! empty( $values['cgm_personalization'][ $key ] ) ) {
				$item->add_meta_data( $label, $values['cgm_personalization'][ $key ], true );
			}
		}
	}

	/**
	 * Etiquetas legibles de los campos de personalización.
	 *
	 * @return array
	 */
	private function labels() {
		return array(
			'name'     => __( 'Nombre', 'cgm' ),
			'initials' => __( 'Iniciales', 'cgm' ),
			'phrase'   => __( 'Frase', 'cgm' ),
			'notes'    => __( 'Observaciones', 'cgm' ),
		);
	}
}
