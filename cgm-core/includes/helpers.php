<?php
/**
 * Helpers y esquema de configuración de CGM.
 *
 * @package CGM_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Esquema de configuración: pestañas -> campos.
 * Es la única fuente de verdad para: valores por defecto, render del admin,
 * saneamiento y fallback del frontend.
 *
 * Tipos soportados: text, textarea, url, email, tel, number, media, color,
 * checkbox, select, heading (solo título de sección).
 *
 * @return array
 */
function cgm_settings_schema() {
	$schema = array(

		'general' => array(
			'label'  => __( 'General', 'cgm' ),
			'fields' => array(
				'brand_name'   => array( 'type' => 'text', 'label' => __( 'Nombre de la empresa', 'cgm' ), 'default' => 'CGM' ),
				'brand_tagline'=> array( 'type' => 'text', 'label' => __( 'Concepto / Eslogan', 'cgm' ), 'default' => 'Crea Grandes Momentos' ),
				'logo_id'      => array( 'type' => 'media', 'label' => __( 'Logotipo', 'cgm' ), 'default' => 0, 'desc' => __( 'Si se deja vacío se usa el logotipo por defecto del tema.', 'cgm' ) ),
				'logo_light_id'=> array( 'type' => 'media', 'label' => __( 'Logotipo versión clara (para fondos oscuros / footer)', 'cgm' ), 'default' => 0 ),
				'favicon_id'   => array( 'type' => 'media', 'label' => __( 'Favicon', 'cgm' ), 'default' => 0, 'desc' => __( 'Imagen cuadrada (recomendado 512×512).', 'cgm' ) ),
			),
		),

		'contact' => array(
			'label'  => __( 'Contacto y Redes', 'cgm' ),
			'fields' => array(
				'whatsapp_link'  => array( 'type' => 'url', 'label' => __( 'Enlace de WhatsApp (botón asesor)', 'cgm' ), 'default' => 'https://wa.me/message/PDEHSEOR4N6TG1' ),
				'whatsapp_phone' => array( 'type' => 'text', 'label' => __( 'WhatsApp visible', 'cgm' ), 'default' => '5978-0608' ),
				'phone'          => array( 'type' => 'tel', 'label' => __( 'Teléfono', 'cgm' ), 'default' => '5978-0608' ),
				'email'          => array( 'type' => 'email', 'label' => __( 'Correo de Servicio al Cliente', 'cgm' ), 'default' => 'servicioalcliente@cgmlifestyle.com' ),
				'facebook'       => array( 'type' => 'url', 'label' => __( 'Facebook', 'cgm' ), 'default' => 'https://www.facebook.com/share/1ckUfLToTc/' ),
				'instagram'      => array( 'type' => 'url', 'label' => __( 'Instagram', 'cgm' ), 'default' => 'https://www.instagram.com/cgmom_ents?igsh=ZGtsMWZpY3d5bjdv' ),
				'tiktok'         => array( 'type' => 'url', 'label' => __( 'TikTok', 'cgm' ), 'default' => 'https://www.tiktok.com/@cgmoments?_r=1&_t=ZS-98YSPXPjdbj' ),
			),
		),

		'topbar' => array(
			'label'  => __( 'Barra superior', 'cgm' ),
			'fields' => array(
				'topbar_enabled' => array( 'type' => 'checkbox', 'label' => __( 'Mostrar barra superior', 'cgm' ), 'default' => 1 ),
				'topbar_1' => array( 'type' => 'text', 'label' => __( 'Beneficio 1', 'cgm' ), 'default' => 'Envíos a todo Guatemala' ),
				'topbar_2' => array( 'type' => 'text', 'label' => __( 'Beneficio 2', 'cgm' ), 'default' => 'Pago seguro' ),
				'topbar_3' => array( 'type' => 'text', 'label' => __( 'Beneficio 3', 'cgm' ), 'default' => 'Cambios garantizados' ),
				'topbar_4' => array( 'type' => 'text', 'label' => __( 'Beneficio 4', 'cgm' ), 'default' => 'Atención personalizada' ),
			),
		),

		'about' => array(
			'label'  => __( '¿Quiénes Somos?', 'cgm' ),
			'fields' => array(
				'about_title' => array( 'type' => 'text', 'label' => __( 'Título', 'cgm' ), 'default' => '¿Quiénes Somos?' ),
				'about_sub'   => array( 'type' => 'text', 'label' => __( 'Texto auxiliar', 'cgm' ), 'default' => 'Conoce más sobre nuestra marca, nuestra historia y lo que nos inspira' ),
				'about_heading' => array( 'type' => 'text', 'label' => __( 'Concepto destacado', 'cgm' ), 'default' => 'Crea Grandes Momentos' ),
				'about_body'  => array(
					'type' => 'textarea', 'label' => __( 'Descripción', 'cgm' ), 'rows' => 8,
					'default' => "En CGM creemos que los mejores recuerdos nacen de los momentos más sencillos: una pausa durante el trabajo, una caminata al aire libre, una clase, un entrenamiento o un viaje en familia.\n\nSomos una marca guatemalteca dedicada a ofrecer termos, pachones y accesorios que combinan diseño, calidad y funcionalidad para acompañarte en cada etapa de tu día.\n\nNo buscamos vender únicamente un producto. Queremos formar parte de las experiencias que hacen especial la vida de las personas.\n\nPorque cada sorbo de agua, cada taza de café y cada aventura pueden convertirse en un gran recuerdo.",
				),
				'mission_title' => array( 'type' => 'text', 'label' => __( 'Misión · Título', 'cgm' ), 'default' => 'Misión' ),
				'mission_sub'   => array( 'type' => 'text', 'label' => __( 'Misión · Texto auxiliar', 'cgm' ), 'default' => 'Descubre nuestro propósito y el compromiso que nos impulsa cada día' ),
				'mission_body'  => array( 'type' => 'textarea', 'label' => __( 'Misión · Descripción', 'cgm' ), 'rows' => 4, 'default' => 'Inspirar a las personas a crear grandes momentos ofreciendo productos de alta calidad que las acompañen en su vida diaria, promoviendo bienestar, confianza y experiencias memorables.' ),
				'vision_title' => array( 'type' => 'text', 'label' => __( 'Visión · Título', 'cgm' ), 'default' => 'Visión' ),
				'vision_sub'   => array( 'type' => 'text', 'label' => __( 'Visión · Texto auxiliar', 'cgm' ), 'default' => 'Conoce hacia dónde vamos y lo que queremos lograr en el futuro' ),
				'vision_body'  => array( 'type' => 'textarea', 'label' => __( 'Visión · Descripción', 'cgm' ), 'rows' => 4, 'default' => 'Ser una marca referente en Guatemala y Centroamérica por nuestra calidad, innovación y cercanía con nuestros clientes, transformando productos cotidianos en compañeros de vida que inspiren a crear grandes momentos.' ),
				'philosophy_title' => array( 'type' => 'text', 'label' => __( 'Filosofía · Título', 'cgm' ), 'default' => 'Filosofía' ),
				'philosophy_sub'   => array( 'type' => 'text', 'label' => __( 'Filosofía · Texto auxiliar', 'cgm' ), 'default' => 'Nuestros principios, creencias y la esencia que define a CGM' ),
				'philosophy_body'  => array(
					'type' => 'textarea', 'label' => __( 'Filosofía · Descripción', 'cgm' ), 'rows' => 8,
					'default' => "Vivimos en un mundo donde todo sucede muy rápido.\n\nLas obligaciones, el trabajo y la rutina hacen que muchas veces olvidemos disfrutar los pequeños instantes que realmente dan sentido a la vida.\n\nEn CGM creemos que un momento especial no depende del lugar ni de la ocasión. Puede comenzar con una conversación, un descanso, una clase, una caminata, un entrenamiento o simplemente con un café al iniciar el día.\n\nPor eso nuestra filosofía es sencilla:\n\nCrea Grandes Momentos.\n\nCada producto que diseñamos busca acompañarte en esos instantes, recordándote que las mejores historias se construyen día a día.",
				),
			),
		),

		'products' => array(
			'label'  => __( 'Productos y Combos', 'cgm' ),
			'fields' => array(
				'products_title' => array( 'type' => 'text', 'label' => __( 'Título sección productos', 'cgm' ), 'default' => 'NUESTROS PRODUCTOS' ),
				'products_sub'   => array( 'type' => 'text', 'label' => __( 'Texto auxiliar', 'cgm' ), 'default' => 'Descubre nuestra colección de pachones, termos y accesorios' ),
				'products_intro' => array(
					'type' => 'textarea', 'label' => __( 'Introducción', 'cgm' ), 'rows' => 4,
					'default' => "Crea grandes momentos con el compañero perfecto para cada ocasión.\n\nDescubre nuestra colección de pachones, termos y accesorios diseñados para acompañarte en el trabajo, la escuela, el deporte, los viajes y cada aventura. Fabricados con materiales de alta calidad, tecnología térmica y diseños modernos para adaptarse a tu estilo de vida.",
				),
				'combo_title' => array( 'type' => 'text', 'label' => __( 'Combos · Título', 'cgm' ), 'default' => 'Arma tu Combo Ideal' ),
				'combo_body'  => array( 'type' => 'textarea', 'label' => __( 'Combos · Texto', 'cgm' ), 'rows' => 3, 'default' => "Combina tus productos favoritos y obtén un descuento automático.\n\nElige cualquier termo, agrega cualquier pachón, combina colores y ahorra entre 5% y 10%. El descuento se aplica automáticamente." ),
				'combo_cta'   => array( 'type' => 'text', 'label' => __( 'Combos · Texto del botón', 'cgm' ), 'default' => 'Crear mi Combo' ),
				'combo_enabled'  => array( 'type' => 'checkbox', 'label' => __( 'Activar descuento automático de combos', 'cgm' ), 'default' => 1 ),
				'combo_discount' => array( 'type' => 'number', 'label' => __( 'Porcentaje de descuento del combo (%)', 'cgm' ), 'default' => 5, 'min' => 0, 'max' => 100, 'step' => '0.5', 'desc' => __( 'El cliente indica un ahorro de entre 5% y 10%. Define aquí el porcentaje a aplicar.', 'cgm' ) ),
				'combo_cats' => array( 'type' => 'text', 'label' => __( 'Categorías elegibles (slugs separados por coma)', 'cgm' ), 'default' => 'termos,pachones', 'desc' => __( 'El combo se activa cuando el carrito contiene al menos un producto de cada categoría indicada.', 'cgm' ) ),
			),
		),

		'custom' => array(
			'label'  => __( 'Personalizados', 'cgm' ),
			'fields' => array(
				'custom_title' => array( 'type' => 'text', 'label' => __( 'Título', 'cgm' ), 'default' => 'Hazlo único' ),
				'custom_body'  => array( 'type' => 'textarea', 'label' => __( 'Texto', 'cgm' ), 'rows' => 4, 'default' => "Personalizamos tu termo o pachón con el nombre, frase o diseño que prefieras mediante vinil de alta calidad.\n\nIncluye: nombre, iniciales, frase corta, vinil resistente al agua. Ideal para regalos." ),
				'custom_cta'   => array( 'type' => 'text', 'label' => __( 'Texto del botón', 'cgm' ), 'default' => 'Personalizar mi Producto' ),
				'custom_fee'   => array( 'type' => 'number', 'label' => __( 'Cargo adicional por personalización (Q)', 'cgm' ), 'default' => 0, 'min' => 0, 'step' => '0.01', 'desc' => __( 'Se agrega al precio cuando el cliente completa datos de personalización. Déjalo en 0 si no aplica.', 'cgm' ) ),
				'custom_maxlen'=> array( 'type' => 'number', 'label' => __( 'Longitud máxima de la frase', 'cgm' ), 'default' => 40, 'min' => 1, 'max' => 200 ),
			),
		),

		'accessories' => array(
			'label'  => __( 'Accesorios', 'cgm' ),
			'fields' => array(
				'acc_title' => array( 'type' => 'text', 'label' => __( 'Título', 'cgm' ), 'default' => 'Completa tu experiencia' ),
				'acc_body'  => array( 'type' => 'textarea', 'label' => __( 'Texto', 'cgm' ), 'rows' => 3, 'default' => 'Encuentra accesorios diseñados para proteger, personalizar y prolongar la vida útil de tu termo o pachón.' ),
			),
		),

		'policies' => array(
			'label'  => __( 'Políticas', 'cgm' ),
			'fields' => array(
				'shipping_title' => array( 'type' => 'text', 'label' => __( 'Envíos · Título', 'cgm' ), 'default' => 'Políticas de Envío' ),
				'shipping_body'  => array(
					'type' => 'textarea', 'label' => __( 'Envíos · Contenido', 'cgm' ), 'rows' => 8,
					'default' => "En CGM trabajamos para que recibas tu pedido de forma rápida y segura.\n\nCobertura: Realizamos envíos a toda Guatemala.\n\nTiempo de entrega:\n• Ciudad de Guatemala: 1 a 2 días hábiles.\n• Cabeceras departamentales: 2 a 4 días hábiles.\n• Áreas rurales: 3 a 5 días hábiles.\n\nCostos de envío: El costo se calculará automáticamente antes de finalizar la compra, según el destino y el peso del pedido.\n\nConfirmación: Una vez confirmado el pago, iniciaremos el proceso de preparación y despacho de tu pedido.",
				),
				'returns_title' => array( 'type' => 'text', 'label' => __( 'Cambios · Título', 'cgm' ), 'default' => 'Cambios Garantizados' ),
				'returns_body'  => array(
					'type' => 'textarea', 'label' => __( 'Cambios · Contenido', 'cgm' ), 'rows' => 8,
					'default' => "Tu satisfacción es importante para nosotros.\n\nAceptamos cambios cuando: tu producto presenta un inconveniente de fabricación o hubo un error en tu pedido. Escríbenos, revisaremos tu caso y, si corresponde, te ayudaremos a gestionar el cambio de manera ágil.\n\nNo aplican cambios cuando:\n• El producto presenta señales de uso.\n• El daño fue ocasionado por un uso inadecuado.\n• Han transcurrido más de 7 días calendario desde la entrega.\n\nPara solicitar un cambio, comunícate con nuestro equipo de atención al cliente.",
				),
				'payment_title' => array( 'type' => 'text', 'label' => __( 'Pago Seguro · Título', 'cgm' ), 'default' => 'Pago Seguro' ),
				'payment_sub'   => array( 'type' => 'text', 'label' => __( 'Pago Seguro · Subtítulo', 'cgm' ), 'default' => 'Compra con confianza' ),
				'payment_body'  => array(
					'type' => 'textarea', 'label' => __( 'Pago Seguro · Contenido', 'cgm' ), 'rows' => 6,
					'default' => "En CGM protegemos cada compra para brindarte una experiencia segura, rápida y confiable. Utilizamos métodos de pago seguros para que realices tu pedido con total tranquilidad.\n\nMétodos de pago: Depósito bancario y VisaLink.\n\nTu compra incluye: confirmación de tu pedido, atención personalizada, protección de tus datos y seguimiento de tu compra.\n\nCompra con la tranquilidad de estar en buenas manos. CGM – Crea Grandes Momentos.",
				),
				'pay_visa'       => array( 'type' => 'checkbox', 'label' => __( 'Mostrar logo Visa', 'cgm' ), 'default' => 1 ),
				'pay_mastercard' => array( 'type' => 'checkbox', 'label' => __( 'Mostrar logo Mastercard', 'cgm' ), 'default' => 1 ),
				'pay_visalink'   => array( 'type' => 'checkbox', 'label' => __( 'Mostrar logo VisaLink', 'cgm' ), 'default' => 1 ),
				'pay_deposit'    => array( 'type' => 'checkbox', 'label' => __( 'Mostrar “Depósito bancario”', 'cgm' ), 'default' => 1 ),
				'responsible_title' => array( 'type' => 'text', 'label' => __( 'Compra Responsable · Título', 'cgm' ), 'default' => 'Compra Responsable' ),
				'responsible_body'  => array(
					'type' => 'textarea', 'label' => __( 'Compra Responsable · Contenido', 'cgm' ), 'rows' => 6,
					'default' => "En CGM creemos que una buena compra comienza con una buena elección.\n\nAntes de confirmar tu pedido, verifica cuidadosamente el modelo, color, capacidad y características del producto.\n\nComprar de forma responsable ayuda a reducir devoluciones, optimiza los procesos de entrega y contribuye al cuidado del medio ambiente.\n\nNuestro objetivo es ofrecer productos duraderos que te acompañen durante mucho tiempo. CGM – Crea Grandes Momentos.",
				),
			),
		),

		'support' => array(
			'label'  => __( 'Atención Personalizada', 'cgm' ),
			'fields' => array(
				'support_title' => array( 'type' => 'text', 'label' => __( 'Título', 'cgm' ), 'default' => 'Atención Personalizada' ),
				'support_sub'   => array( 'type' => 'text', 'label' => __( 'Subtítulo', 'cgm' ), 'default' => 'Estamos aquí para ayudarte.' ),
				'support_body'  => array(
					'type' => 'textarea', 'label' => __( 'Texto', 'cgm' ), 'rows' => 6,
					'default' => "En CGM creemos que una gran experiencia comienza con una conversación. Si tienes dudas sobre nuestros productos, colores, capacidades, personalizaciones o el estado de tu pedido, uno de nuestros asesores estará listo para ayudarte de forma rápida, cercana y personalizada.\n\nPodemos ayudarte con: información sobre nuestros productos, disponibilidad de colores y modelos, personalización de termos y pachones, cotizaciones, estado de pedidos, cambios y garantías, y recomendaciones según tu estilo de vida.\n\nEn CGM no solo vendemos productos; te acompañamos para que encuentres el compañero perfecto para crear grandes momentos.",
				),
				'support_cta' => array( 'type' => 'text', 'label' => __( 'Texto del botón', 'cgm' ), 'default' => 'Contactar a un Asesor' ),
			),
		),

		'footer' => array(
			'label'  => __( 'Footer', 'cgm' ),
			'fields' => array(
				'footer_about' => array( 'type' => 'textarea', 'label' => __( 'Texto del footer', 'cgm' ), 'rows' => 3, 'default' => 'CGM – Crea Grandes Momentos. Termos, pachones y accesorios que combinan diseño, calidad y funcionalidad para acompañarte en cada etapa de tu día.' ),
				'footer_copyright' => array( 'type' => 'text', 'label' => __( 'Copyright', 'cgm' ), 'default' => '© %year% CGM – Crea Grandes Momentos. Todos los derechos reservados.', 'desc' => __( 'Usa %year% para insertar el año actual automáticamente.', 'cgm' ) ),
			),
		),

		'seo' => array(
			'label'  => __( 'SEO', 'cgm' ),
			'fields' => array(
				'seo_description' => array( 'type' => 'textarea', 'label' => __( 'Descripción por defecto del sitio (meta description)', 'cgm' ), 'rows' => 3, 'default' => 'CGM – Crea Grandes Momentos. Termos, pachones y accesorios de alta calidad en Guatemala. Envíos a todo el país, pago seguro y atención personalizada.' ),
				'seo_og_id'       => array( 'type' => 'media', 'label' => __( 'Imagen para compartir (Open Graph)', 'cgm' ), 'default' => 0 ),
				'seo_output'      => array( 'type' => 'checkbox', 'label' => __( 'Emitir metadatos SEO y Schema (desactívalo si usas Yoast/Rank Math)', 'cgm' ), 'default' => 1, 'desc' => __( 'Se desactiva automáticamente cuando detecta Yoast o Rank Math.', 'cgm' ) ),
			),
		),
	);

	// Trust badges (5 tarjetas de confianza, totalmente editables).
	$badges_defaults = array(
		1 => array( 'Envíos a domicilio', 'A toda Guatemala' ),
		2 => array( 'Cambios garantizados', 'Política de cambios fácil y segura' ),
		3 => array( 'Pago seguro', 'Compra con confianza' ),
		4 => array( 'Compra responsable', 'Productos reutilizables' ),
		5 => array( 'Atención personalizada', 'Te ayudamos a elegir' ),
	);
	$badge_fields = array();
	foreach ( $badges_defaults as $i => $vals ) {
		$badge_fields[ "badge_{$i}_icon" ]  = array( 'type' => 'media', 'label' => sprintf( __( 'Badge %d · Icono', 'cgm' ), $i ), 'default' => 0 );
		$badge_fields[ "badge_{$i}_title" ] = array( 'type' => 'text', 'label' => sprintf( __( 'Badge %d · Título', 'cgm' ), $i ), 'default' => $vals[0] );
		$badge_fields[ "badge_{$i}_sub" ]   = array( 'type' => 'text', 'label' => sprintf( __( 'Badge %d · Subtítulo', 'cgm' ), $i ), 'default' => $vals[1] );
	}
	$schema['badges'] = array(
		'label'  => __( 'Confianza (badges)', 'cgm' ),
		'fields' => $badge_fields,
	);

	return apply_filters( 'cgm_settings_schema', $schema );
}

/**
 * Devuelve un mapa plano clave => valor por defecto.
 *
 * @return array
 */
function cgm_settings_defaults() {
	$defaults = array();
	foreach ( cgm_settings_schema() as $tab ) {
		foreach ( $tab['fields'] as $key => $field ) {
			if ( 'heading' === ( $field['type'] ?? '' ) ) {
				continue;
			}
			$defaults[ $key ] = $field['default'] ?? '';
		}
	}
	return $defaults;
}

/**
 * Obtiene una opción de CGM con fallback a los valores por defecto.
 *
 * @param string $key     Clave.
 * @param mixed  $default Valor si no existe (opcional).
 * @return mixed
 */
function cgm_get_option( $key, $default = null ) {
	static $cache = null;
	if ( null === $cache ) {
		$stored = get_option( CGM_CORE_OPTION, array() );
		$cache  = wp_parse_args( is_array( $stored ) ? $stored : array(), cgm_settings_defaults() );
	}
	if ( isset( $cache[ $key ] ) && '' !== $cache[ $key ] ) {
		return $cache[ $key ];
	}
	if ( null !== $default ) {
		return $default;
	}
	return $cache[ $key ] ?? '';
}

/**
 * Devuelve la URL de una imagen guardada por ID de adjunto, con fallback.
 *
 * @param string $key      Clave de opción que guarda un attachment ID.
 * @param string $size     Tamaño de imagen.
 * @param string $fallback URL de reserva.
 * @return string
 */
function cgm_get_image_url( $key, $size = 'full', $fallback = '' ) {
	$id = absint( cgm_get_option( $key, 0 ) );
	if ( $id ) {
		$url = wp_get_attachment_image_url( $id, $size );
		if ( $url ) {
			return $url;
		}
	}
	return $fallback;
}

/**
 * Convierte texto plano (con saltos de línea y viñetas •) en HTML seguro.
 *
 * @param string $text Texto.
 * @return string
 */
function cgm_format_text( $text ) {
	$text   = wp_strip_all_tags( (string) $text );
	$blocks = preg_split( "/\n\s*\n/", trim( $text ) );
	$out    = '';
	$is_bullet = function ( $l ) {
		return (bool) preg_match( '/^\s*[•\-\*✔]/u', $l );
	};

	foreach ( $blocks as $block ) {
		$lines = preg_split( "/\n/", trim( $block ) );
		$buf   = array(); // Líneas de texto acumuladas (no viñetas).
		$list  = array(); // Viñetas acumuladas.

		$flush_text = function () use ( &$buf, &$out ) {
			if ( $buf ) {
				$out .= '<p>' . nl2br( esc_html( implode( "\n", $buf ) ) ) . '</p>';
				$buf  = array();
			}
		};
		$flush_list = function () use ( &$list, &$out ) {
			if ( $list ) {
				$out .= '<ul class="cgm-list">';
				foreach ( $list as $li ) {
					$out .= '<li>' . esc_html( $li ) . '</li>';
				}
				$out .= '</ul>';
				$list = array();
			}
		};

		foreach ( $lines as $line ) {
			if ( $is_bullet( $line ) ) {
				$flush_text();
				$list[] = trim( preg_replace( '/^\s*[•\-\*✔]\s*/u', '', $line ) );
			} else {
				$flush_list();
				$buf[] = trim( $line );
			}
		}
		$flush_text();
		$flush_list();
	}
	return $out;
}
