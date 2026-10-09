<?php
/**
 * Servicom Core - Pantalla INSTRUCCIONES (guía para el cliente).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Enlaces (funciones puras; sin salida)
 * ---------------------------------------------------------------------- */

function sc_elementor_active(): bool {
	return defined( 'ELEMENTOR_VERSION' ) || class_exists( '\Elementor\Plugin' );
}

function sc_page_label( string $key, int $id ): string {
	$known = array(
		'home'      => 'Inicio',
		'nosotros'  => 'Nosotros',
		'servicios' => 'Servicios',
		'galeria'   => 'Galería',
		'contacto'  => 'Contacto',
		'tienda'    => 'Tienda',
	);
	$title = (string) get_the_title( $id );
	if ( isset( $known[ $key ] ) ) {
		return $known[ $key ];
	}
	return '' !== $title ? $title : ucfirst( $key );
}

/** URL del editor de un post (Elementor si está activo). */
function sc_post_edit_url( int $id ): string {
	$action = sc_elementor_active() ? 'elementor' : 'edit';
	return admin_url( 'post.php?post=' . $id . '&action=' . $action );
}

/**
 * Todos los enlaces directos de la pantalla. Claves estables:
 * pages[key] => [id,label,url], services[idx] => [id,label,url], y claves planas.
 */
function sc_instruction_links(): array {
	$ret   = sc_instructions_url();
	$L     = array();
	$pages = array();
	$ids   = get_option( 'sc_page_ids', array() );
	foreach ( (array) $ids as $key => $id ) {
		$id = (int) $id;
		$p  = $id ? get_post( $id ) : null;
		if ( ! $p || 'trash' === $p->post_status ) {
			continue;
		}
		$pages[ sanitize_key( (string) $key ) ] = array(
			'id'    => $id,
			'label' => sc_page_label( (string) $key, $id ),
			'url'   => sc_post_edit_url( $id ),
		);
	}
	$services = array();
	foreach ( (array) get_option( 'sc_service_pages', array() ) as $idx => $id ) {
		$id = (int) $id;
		$p  = $id ? get_post( $id ) : null;
		if ( ! $p || 'trash' === $p->post_status ) {
			continue;
		}
		$services[ (string) $idx ] = array(
			'id'    => $id,
			'label' => (string) get_the_title( $id ),
			'url'   => sc_post_edit_url( $id ),
		);
	}
	$L['pages']    = $pages;
	$L['services'] = $services;

	$L['site']                = home_url( '/' );
	$L['instructions']        = sc_instructions_url();
	$L['customizer_business'] = sc_customizer_url( 'sc_business', $ret );
	$L['customizer_social']   = sc_customizer_url( 'sc_social', $ret );
	$L['customizer_float']    = sc_customizer_url( 'sc_float', $ret );
	$L['customizer_style']    = sc_customizer_url( 'sc_style_section', $ret );
	$L['customizer_identity'] = sc_customizer_url( 'title_tagline', $ret );
	$L['customizer_menus']    = sc_customizer_url( 'nav_menus', $ret );
	$L['menus']               = admin_url( 'nav-menus.php' );
	$L['media']               = admin_url( 'upload.php' );
	$L['media_new']           = admin_url( 'media-new.php' );
	$L['pages_list']          = admin_url( 'edit.php?post_type=page' );
	$L['profile']             = admin_url( 'profile.php' );
	$L['lost_password']       = wp_lostpassword_url();

	// Colores y tipografías: Kit global de Elementor; si no, estilo visual.
	$kit = (int) get_option( 'elementor_active_kit', 0 );
	if ( $kit && sc_elementor_active() && get_post( $kit ) ) {
		$L['kit'] = admin_url( 'post.php?post=' . $kit . '&action=elementor' );
	} else {
		$L['kit'] = $L['customizer_style'];
	}

	// Formulario de contacto.
	$contact = $pages['contacto']['url'] ?? $L['pages_list'];
	$L['contact_form'] = $contact;
	if ( defined( 'FLUENTFORM' ) || defined( 'FLUENTFORM_VERSION' ) ) {
		$L['contact_form_settings'] = admin_url( 'admin.php?page=fluent_forms' );
	} elseif ( defined( 'WPCF7_VERSION' ) ) {
		$L['contact_form_settings'] = admin_url( 'admin.php?page=wpcf7' );
	} else {
		$L['contact_form_settings'] = $contact;
	}

	// Tienda.
	$plan = (string) get_option( 'sc_plan', '' );
	$L['is_store']   = ( 'tienda' === $plan ) || ( '' === $plan && function_exists( 'sc_woo_active' ) && sc_woo_active() );
	$L['products']   = admin_url( 'edit.php?post_type=product' );
	$L['product_new'] = admin_url( 'post-new.php?post_type=product' );
	$L['categories'] = admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' );
	$L['orders']     = sc_wc_orders_url();
	$L['payments']   = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=bacs' );
	$L['payments_cod'] = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=cod' );
	$L['customers']  = $L['orders'];

	// Soporte por WhatsApp a Servicom.
	$wa = preg_replace( '/\D+/', '', (string) get_option( 'sc_wa_servicom', '' ) );
	$L['wa_servicom'] = '';
	if ( '' !== $wa ) {
		$msg = 'Hola Servicom, necesito ayuda con mi sitio ' . sc_biz_name() . ' (' . home_url( '/' ) . ')';
		$L['wa_servicom'] = 'https://wa.me/' . $wa . '?text=' . rawurlencode( $msg );
	}
	return (array) apply_filters( 'sc_instruction_links', $L );
}

/* -------------------------------------------------------------------------
 * Tarjetas
 * ---------------------------------------------------------------------- */

function sc_ins_page_buttons( array $L, string $prefix = 'Editar' ): array {
	$b = array();
	foreach ( $L['pages'] as $key => $p ) {
		if ( 'tienda' === $key && empty( $L['is_store'] ) ) {
			continue;
		}
		$b[] = array( 'label' => $prefix . ' ' . $p['label'], 'url' => $p['url'] );
	}
	return $b;
}

function sc_instruction_cards(): array {
	$L    = sc_instruction_links();
	$page = static function ( string $key ) use ( $L ) {
		return $L['pages'][ $key ]['url'] ?? $L['pages_list'];
	};
	$pb = static function ( string $key, string $label ) use ( $L ) {
		return isset( $L['pages'][ $key ] ) ? array( 'label' => $label, 'url' => $L['pages'][ $key ]['url'] ) : array( 'label' => 'Ver mis páginas', 'url' => $L['pages_list'] );
	};
	$editor = sc_elementor_active() ? 'Se abre el editor visual' : 'Se abre el editor';
	$cust   = static function ( string $url, string $label ) {
		return array( 'label' => $label, 'url' => $url );
	};

	$cards = array();

	// --- Contenido ---------------------------------------------------------
	$cards[] = array(
		'id' => 'textos', 'group' => 'contenido', 'icon' => 'text', 'title' => 'Cambiar textos',
		'keywords' => 'texto parrafo frase descripcion escribir contenido palabras',
		'steps' => array( 'Elige la página que quieres cambiar.', $editor . ': haz clic sobre el texto y escribe.', 'Pulsa el botón azul «Actualizar» para publicar.' ),
		'buttons' => sc_ins_page_buttons( $L ),
	);
	$cards[] = array(
		'id' => 'titulos', 'group' => 'contenido', 'icon' => 'text', 'title' => 'Cambiar títulos',
		'keywords' => 'titulo encabezado titular h1 nombre de seccion',
		'steps' => array( 'Abre la página donde está el título.', 'Haz clic en el título y escribe el nuevo.', 'Pulsa «Actualizar».' ),
		'buttons' => sc_ins_page_buttons( $L ),
	);
	$cards[] = array(
		'id' => 'imagenes', 'group' => 'contenido', 'icon' => 'image', 'title' => 'Cambiar imágenes y fotos',
		'keywords' => 'imagen foto fotografia picture cambiar imagen subir',
		'steps' => array( 'Abre la página donde está la foto.', 'Haz clic sobre la imagen y elige «Elegir imagen» o «Subir».', 'Selecciona una foto de tu computadora o de la biblioteca y pulsa «Actualizar».' ),
		'buttons' => array_merge( array( $cust( $L['media_new'], 'Subir fotos nuevas' ) ), sc_ins_page_buttons( $L ) ),
	);
	$cards[] = array(
		'id' => 'iconos', 'group' => 'contenido', 'icon' => 'star', 'title' => 'Cambiar iconos',
		'keywords' => 'icono simbolo dibujo servicios tarjeta',
		'steps' => array( 'Abre la página donde está el icono (por lo general Inicio).', 'Haz clic sobre el icono y elige «Icono» en el panel izquierdo.', 'Busca el que más te guste y pulsa «Actualizar».' ),
		'buttons' => array( $pb( 'home', 'Editar Inicio' ) ),
	);
	$cards[] = array(
		'id' => 'botones', 'group' => 'contenido', 'icon' => 'button', 'title' => 'Cambiar botones (texto y enlace)',
		'keywords' => 'boton enlace link llamado a la accion cta',
		'steps' => array( 'Abre la página y haz clic sobre el botón.', 'En el panel izquierdo cambia el «Texto» y el «Enlace».', 'Pulsa «Actualizar».' ),
		'buttons' => sc_ins_page_buttons( $L ),
	);
	$cards[] = array(
		'id' => 'banner', 'group' => 'contenido', 'icon' => 'banner', 'title' => 'Cambiar el banner de portada',
		'keywords' => 'banner portada hero slider imagen principal cabecera grande',
		'steps' => array( 'Abre la página de Inicio.', 'Haz clic en la parte superior (banner) y cambia la imagen de fondo, el título o el botón.', 'Pulsa «Actualizar».' ),
		'buttons' => array( $pb( 'home', 'Editar el banner' ) ),
	);
	$svc = array();
	foreach ( $L['services'] as $s ) {
		$svc[] = array( 'label' => 'Editar: ' . $s['label'], 'url' => $s['url'] );
	}
	$cards[] = array(
		'id' => 'servicios', 'group' => 'contenido', 'icon' => 'grid', 'title' => 'Servicios',
		'keywords' => 'servicio servicios oferta lista agregar quitar',
		'steps' => array( 'Para cambiar el resumen, abre la página Servicios o Inicio.', 'Para cambiar el detalle de un servicio, abre su página con los botones de esta tarjeta.', 'Pulsa «Actualizar» al terminar.' ),
		'buttons' => array_merge( array( $pb( 'servicios', 'Editar Servicios' ) ), array_slice( $svc, 0, 12 ) ),
	);
	$cards[] = array(
		'id' => 'galeria', 'group' => 'contenido', 'icon' => 'gallery', 'title' => 'Galería de fotos',
		'keywords' => 'galeria fotos album imagenes trabajos portafolio',
		'steps' => array( 'Abre la página Galería.', 'Haz clic en la galería y pulsa «Agregar imágenes» (o la X para quitar).', 'Pulsa «Actualizar».' ),
		'buttons' => array( $pb( 'galeria', 'Editar Galería' ), $cust( $L['media_new'], 'Subir fotos nuevas' ) ),
	);
	$cards[] = array(
		'id' => 'video', 'group' => 'contenido', 'icon' => 'video', 'title' => 'Video de YouTube',
		'keywords' => 'video youtube reproducir pelicula',
		'steps' => array( 'Abre la página donde está el video.', 'Haz clic sobre el video y pega el enlace de YouTube en «Enlace».', 'Pulsa «Actualizar».' ),
		'buttons' => array( $pb( 'home', 'Editar Inicio' ) ),
	);

	// --- Apariencia --------------------------------------------------------
	$cards[] = array(
		'id' => 'menu', 'group' => 'apariencia', 'icon' => 'menu', 'title' => 'Menú de navegación',
		'keywords' => 'menu navegacion enlaces superior paginas ordenar',
		'steps' => array( 'Entra a «Menús».', 'Arrastra las opciones para cambiar el orden o agrega páginas nuevas.', 'Pulsa «Guardar menú».' ),
		'buttons' => array( $cust( $L['menus'], 'Abrir Menús' ) ),
	);
	$cards[] = array(
		'id' => 'logo', 'group' => 'apariencia', 'icon' => 'logo', 'title' => 'Logo del sitio',
		'keywords' => 'logo marca logotipo icono del sitio favicon',
		'steps' => array( 'Entra a «Identidad del sitio».', 'Pulsa «Cambiar logotipo» y sube tu imagen.', 'Pulsa «Publicar».' ),
		'buttons' => array( $cust( $L['customizer_identity'], 'Cambiar el logo' ) ),
	);
	$cards[] = array(
		'id' => 'colores', 'group' => 'apariencia', 'icon' => 'palette', 'title' => 'Colores y tipografías',
		'keywords' => 'color colores tipografia letra fuente fuentes estilo paleta',
		'steps' => array( sc_elementor_active() ? 'Se abre el editor con los «Ajustes del sitio».' : 'Se abre el Personalizador en «Estilo visual».', sc_elementor_active() ? 'Elige «Colores globales» o «Tipografía global» y cambia lo que quieras.' : 'Elige el estilo que más te guste.', 'Pulsa «Actualizar» o «Publicar».' ),
		'buttons' => array( $cust( $L['kit'], 'Cambiar colores y letras' ), $cust( $L['customizer_style'], 'Cambiar el estilo del sitio' ) ),
	);
	$cards[] = array(
		'id' => 'estilo', 'group' => 'apariencia', 'icon' => 'palette', 'title' => 'Estilo visual del sitio',
		'keywords' => 'estilo diseno apariencia look tema plantilla',
		'steps' => array( 'Entra a «Estilo visual».', 'Elige una de las opciones; tu contenido no se pierde.', 'Pulsa «Publicar».' ),
		'buttons' => array( $cust( $L['customizer_style'], 'Elegir estilo' ) ),
	);
	$cards[] = array(
		'id' => 'encabezado', 'group' => 'apariencia', 'icon' => 'header', 'title' => 'Encabezado (parte de arriba)',
		'keywords' => 'encabezado cabecera header arriba logo menu telefono',
		'steps' => array( 'El logo se cambia en «Identidad del sitio».', 'El menú se cambia en «Menús».', 'El teléfono y WhatsApp se cambian en «Datos del negocio».' ),
		'buttons' => array( $cust( $L['customizer_identity'], 'Logo' ), $cust( $L['menus'], 'Menús' ), $cust( $L['customizer_business'], 'Datos del negocio' ) ),
	);
	$cards[] = array(
		'id' => 'pie', 'group' => 'apariencia', 'icon' => 'footer', 'title' => 'Pie de página (parte de abajo)',
		'keywords' => 'pie footer abajo creditos derechos copyright',
		'steps' => array( 'Los datos de contacto y redes se cambian en «Datos del negocio» y «Redes sociales».', 'El texto de créditos se cambia en «Botones y pie de página».', 'Pulsa «Publicar».' ),
		'buttons' => array( $cust( $L['customizer_float'], 'Créditos del pie' ), $cust( $L['customizer_business'], 'Datos del negocio' ) ),
	);

	// --- Datos del negocio -----------------------------------------------
	$cards[] = array(
		'id' => 'contacto', 'group' => 'datos', 'icon' => 'phone', 'title' => 'Teléfono, WhatsApp, correo, dirección y horario',
		'keywords' => 'telefono whatsapp celular correo email direccion horario atencion llamar contacto datos',
		'steps' => array( 'Entra a «Datos del negocio».', 'Cambia lo que necesites. Se actualiza en todo el sitio al mismo tiempo.', 'Pulsa «Publicar».' ),
		'buttons' => array( $cust( $L['customizer_business'], 'Cambiar datos del negocio' ) ),
	);
	$cards[] = array(
		'id' => 'redes', 'group' => 'datos', 'icon' => 'share', 'title' => 'Redes sociales',
		'keywords' => 'redes sociales facebook instagram tiktok youtube twitter linkedin',
		'steps' => array( 'Entra a «Redes sociales».', 'Pega el enlace de tu perfil o escribe tu @usuario. Deja vacías las que no uses.', 'Pulsa «Publicar».' ),
		'buttons' => array( $cust( $L['customizer_social'], 'Cambiar redes sociales' ) ),
	);
	$cards[] = array(
		'id' => 'flotante', 'group' => 'datos', 'icon' => 'chat', 'title' => 'Botón flotante de WhatsApp y barra del celular',
		'keywords' => 'boton flotante whatsapp barra inferior celular movil mensaje',
		'steps' => array( 'Entra a «Botones y pie de página».', 'Activa o desactiva el botón y la barra del celular.', 'El mensaje inicial se cambia en «Datos del negocio».' ),
		'buttons' => array( $cust( $L['customizer_float'], 'Botones flotantes' ), $cust( $L['customizer_business'], 'Mensaje de WhatsApp' ) ),
	);
	$cards[] = array(
		'id' => 'formulario', 'group' => 'datos', 'icon' => 'mail', 'title' => 'Formulario de contacto',
		'keywords' => 'formulario contacto mensajes correo recibir campos',
		'steps' => array( 'Los mensajes llegan al correo de contacto: cámbialo en «Datos del negocio».', 'Para cambiar los campos o textos del formulario, abre su pantalla.', 'Guarda los cambios.' ),
		'buttons' => array( $cust( $L['contact_form_settings'], 'Abrir el formulario' ), $cust( $L['customizer_business'], 'Cambiar correo de contacto' ) ),
	);
	$cards[] = array(
		'id' => 'mapa', 'group' => 'datos', 'icon' => 'pin', 'title' => 'Mapa y ubicación',
		'keywords' => 'mapa ubicacion google maps direccion como llegar',
		'steps' => array( 'En Google Maps busca tu negocio, pulsa «Compartir» y copia el enlace.', 'Entra a «Datos del negocio» y pégalo en «Enlace de Google Maps».', 'Pulsa «Publicar».' ),
		'buttons' => array( $cust( $L['customizer_business'], 'Cambiar el mapa' ) ),
	);

	// --- Tienda ----------------------------------------------------------
	if ( ! empty( $L['is_store'] ) ) {
		$cards[] = array(
			'id' => 'productos', 'group' => 'tienda', 'icon' => 'bag', 'title' => 'Productos (agregar, cambiar o quitar)',
			'keywords' => 'producto productos articulo agregar nuevo quitar foto descripcion',
			'steps' => array( 'Entra a «Productos».', 'Pulsa el nombre de un producto para editarlo, o «Añadir nuevo».', 'Pulsa «Actualizar».' ),
			'buttons' => array( $cust( $L['products'], 'Ver productos' ), $cust( $L['product_new'], 'Agregar un producto' ) ),
			'store' => true,
		);
		$cards[] = array(
			'id' => 'categorias', 'group' => 'tienda', 'icon' => 'folder', 'title' => 'Categorías de la tienda',
			'keywords' => 'categoria categorias grupos clasificar',
			'steps' => array( 'Entra a «Categorías».', 'Escribe el nombre y pulsa «Añadir».', 'Para asignarla, edita el producto y marca la categoría.' ),
			'buttons' => array( $cust( $L['categories'], 'Abrir categorías' ) ),
			'store' => true,
		);
		$cards[] = array(
			'id' => 'precios', 'group' => 'tienda', 'icon' => 'tag', 'title' => 'Precios y ofertas',
			'keywords' => 'precio precios oferta descuento costo rebaja',
			'steps' => array( 'Abre el producto.', 'Más abajo, en «Datos del producto», cambia el «Precio normal» (y el «Precio rebajado» si hay oferta).', 'Pulsa «Actualizar».' ),
			'buttons' => array( $cust( $L['products'], 'Ver productos' ) ),
			'store' => true,
		);
		$cards[] = array(
			'id' => 'stock', 'group' => 'tienda', 'icon' => 'boxes', 'title' => 'Existencias (stock)',
			'keywords' => 'stock existencia inventario cantidad agotado disponible',
			'steps' => array( 'Abre el producto y ve a «Inventario».', 'Cambia la «Cantidad en inventario».', 'Pulsa «Actualizar».' ),
			'buttons' => array( $cust( $L['products'], 'Ver productos' ) ),
			'store' => true,
		);
		$cards[] = array(
			'id' => 'pedidos', 'group' => 'tienda', 'icon' => 'receipt', 'title' => 'Pedidos',
			'keywords' => 'pedido pedidos ordenes compras ventas estado entregado',
			'steps' => array( 'Entra a «Pedidos».', 'Abre un pedido para ver los datos del cliente y lo que compró.', 'Cambia el estado (por ejemplo «Completado») y pulsa «Actualizar».' ),
			'buttons' => array( $cust( $L['orders'], 'Ver pedidos' ) ),
			'store' => true,
		);
		$cards[] = array(
			'id' => 'pagos', 'group' => 'tienda', 'icon' => 'card', 'title' => 'Métodos de pago',
			'keywords' => 'pago pagos metodo contra entrega transferencia cobrar',
			'steps' => array( 'Entra a «Métodos de pago».', 'Activa o desactiva «Transferencia bancaria» y «Pago contra entrega».', 'Pulsa «Guardar cambios».' ),
			'buttons' => array( $cust( $L['payments'], 'Transferencia bancaria' ), $cust( $L['payments_cod'], 'Pago contra entrega' ) ),
			'store' => true,
		);
		$cards[] = array(
			'id' => 'banco', 'group' => 'tienda', 'icon' => 'bank', 'title' => 'Datos bancarios',
			'keywords' => 'banco cuenta bancaria numero de cuenta titular deposito transferencia',
			'steps' => array( 'Entra a «Transferencia bancaria».', 'Cambia banco, número de cuenta y titular.', 'Pulsa «Guardar cambios».' ),
			'buttons' => array( $cust( $L['payments'], 'Cambiar datos bancarios' ) ),
			'store' => true,
		);
		$cards[] = array(
			'id' => 'cuentas', 'group' => 'tienda', 'icon' => 'user', 'title' => 'Cuentas y datos de clientes',
			'keywords' => 'cliente clientes cuenta usuario comprador registro',
			'steps' => array( 'Los clientes pueden comprar con su cuenta o sin ella.', 'Para ver los datos de quien compró, abre el pedido.', 'Ahí encuentras nombre, teléfono, correo y dirección de entrega.' ),
			'buttons' => array( $cust( $L['customers'], 'Ver pedidos' ) ),
			'store' => true,
		);
	}

	// --- Cuenta ------------------------------------------------------------
	$cards[] = array(
		'id' => 'password', 'group' => 'cuenta', 'icon' => 'lock', 'title' => 'Cambiar mi contraseña',
		'keywords' => 'contrasena clave password acceso seguridad cuenta perfil',
		'steps' => array( 'Entra a «Mi perfil».', 'Baja hasta «Gestión de la cuenta» y pulsa «Establecer nueva contraseña».', 'Pulsa «Actualizar perfil».' ),
		'buttons' => array( $cust( $L['profile'], 'Abrir mi perfil' ) ),
	);

	$cards = (array) apply_filters( 'sc_instruction_cards', $cards, $L );
	return array_values( array_filter( $cards, 'is_array' ) );
}

/* -------------------------------------------------------------------------
 * Menú y pantalla
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'sc_ins_menu', 1 );
function sc_ins_menu(): void {
	$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M10 1.500a6 6 0 0 0-3.500 10.900c.3.300.5.700.5 1.100v.500h6v-.500c0-.4.200-.8.500-1.100A6 6 0 0 0 10 1.500zM7.500 15.500h5v1a1 1 0 0 1-1 1h-3a1 1 0 0 1-1-1zM8 18h4v.800a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1z"/></svg>';
	$icon = 'data:image/svg+xml;base64,' . base64_encode( $svg );
	add_menu_page( 'Instrucciones', 'INSTRUCCIONES', 'edit_pages', 'sc-instrucciones', 'sc_ins_render', $icon, 1.5 );
}

add_action( 'admin_enqueue_scripts', 'sc_ins_assets' );
function sc_ins_assets( $hook ): void {
	if ( 'toplevel_page_sc-instrucciones' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'sc-instructions', SC_CORE_URL . '/assets/instructions.css', array(), SC_CORE_VERSION );
	wp_enqueue_script( 'sc-instructions', SC_CORE_URL . '/assets/instructions.js', array(), SC_CORE_VERSION, true );
}

// Icono del menú: estilo propio coherente con el resto de iconos de WP.
add_action( 'admin_head', 'sc_ins_menu_css' );
function sc_ins_menu_css(): void {
	echo '<style>#adminmenu .toplevel_page_sc-instrucciones{background:rgba(255,255,255,.04)}#adminmenu .toplevel_page_sc-instrucciones .wp-menu-name{font-weight:700;font-size:12.5px;letter-spacing:0;white-space:nowrap}#adminmenu .toplevel_page_sc-instrucciones .wp-menu-image img{padding:7px 0 0;opacity:1}</style>';
}

function sc_ins_palette(): array {
	$pal = array(
		1 => array( '#1e3a5f', '#e8eef6' ),
		2 => array( '#2563eb', '#e8f0fe' ),
		3 => array( '#be123c', '#fdecef' ),
		4 => array( '#b45309', '#fdf1e4' ),
		5 => array( '#111827', '#e9ebef' ),
	);
	$p   = $pal[ sc_biz( 'style' ) ] ?? $pal[1];
	$p   = (array) apply_filters( 'sc_admin_palette', array( 'primary' => $p[0], 'soft' => $p[1] ) );
	return array(
		'primary' => preg_match( '/^#[0-9a-f]{3,8}$/i', (string) $p['primary'] ) ? $p['primary'] : '#1e3a5f',
		'soft'    => preg_match( '/^#[0-9a-f]{3,8}$/i', (string) $p['soft'] ) ? $p['soft'] : '#e8eef6',
	);
}

function sc_ins_icon( string $name ): string {
	$p = array(
		'text'    => '<path d="M5 5h14M12 5v14M9 19h6"/>',
		'image'   => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.500"/><path d="M21 16l-5-5-8 8"/>',
		'button'  => '<rect x="3" y="8" width="18" height="8" rx="4"/><path d="M8 12h8"/>',
		'star'    => '<path d="M12 3l2.700 5.600 6.100.9-4.400 4.300 1 6.100L12 17l-5.400 2.900 1-6.100L3.200 9.500l6.100-.9z"/>',
		'banner'  => '<rect x="3" y="4" width="18" height="9" rx="1"/><path d="M3 17h10M3 20h6"/>',
		'grid'    => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
		'gallery' => '<rect x="3" y="6" width="14" height="14" rx="2"/><path d="M7 3h12a2 2 0 0 1 2 2v12"/>',
		'video'   => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9l5 3-5 3z"/>',
		'menu'    => '<path d="M3 6h18M3 12h18M3 18h18"/>',
		'logo'    => '<circle cx="12" cy="12" r="9"/><path d="M8 15l4-7 4 7M9.500 13h5"/>',
		'palette' => '<path d="M12 3a9 9 0 1 0 0 18c1.100 0 1.500-.8 1.200-1.600-.4-1 .2-2.400 1.600-2.400H17a4 4 0 0 0 4-4C21 7 17 3 12 3z"/><circle cx="7.500" cy="11" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="15" cy="7" r="1"/>',
		'header'  => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/>',
		'footer'  => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 15h18"/>',
		'phone'   => '<path d="M5 4h4l2 5-2.500 1.500a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
		'share'   => '<circle cx="6" cy="12" r="2.500"/><circle cx="18" cy="6" r="2.500"/><circle cx="18" cy="18" r="2.500"/><path d="M8.200 11l7.600-4M8.200 13l7.600 4"/>',
		'chat'    => '<path d="M4 20l1.500-4A8 8 0 1 1 8 18.500z"/>',
		'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'pin'     => '<path d="M12 21s7-6.200 7-11a7 7 0 0 0-14 0c0 4.800 7 11 7 11z"/><circle cx="12" cy="10" r="2.500"/>',
		'bag'     => '<path d="M5 8h14l-1 12H6z"/><path d="M9 8a3 3 0 0 1 6 0"/>',
		'folder'  => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
		'tag'     => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.500" cy="8.500" r="1.200"/>',
		'boxes'   => '<path d="M3 8l9-5 9 5v8l-9 5-9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
		'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
		'card'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
		'bank'    => '<path d="M3 10l9-6 9 6M5 10v8M9 10v8M15 10v8M19 10v8M3 20h18"/>',
		'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'lock'    => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
		'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
	);
	$d = $p[ $name ] ?? $p['star'];
	return '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
}

function sc_ins_groups(): array {
	return array(
		'contenido'  => 'Contenido',
		'apariencia' => 'Apariencia',
		'datos'      => 'Datos del negocio',
		'tienda'     => 'Tienda',
		'cuenta'     => 'Mi cuenta',
	);
}

function sc_ins_norm( string $s ): string {
	return strtolower( remove_accents( $s ) );
}

function sc_ins_render(): void {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html( 'No tienes permiso para ver esta pantalla.' ) );
	}
	$L      = sc_instruction_links();
	$cards  = sc_instruction_cards();
	$groups = sc_ins_groups();
	$pal    = sc_ins_palette();
	$name   = sc_biz_name();
	$used   = array();
	foreach ( $cards as $c ) {
		$used[ $c['group'] ?? '' ] = true;
	}
	$style = sprintf( '--sc-ins-primary:%s;--sc-ins-soft:%s', $pal['primary'], $pal['soft'] );
	echo '<div class="wrap sc-ins" style="' . esc_attr( $style ) . '">';
	echo '<h1 class="screen-reader-text">Instrucciones</h1>';

	if ( isset( $_GET['sc_aviso'] ) ) { // phpcs:ignore
		echo '<div class="sc-ins__notice" role="status">Esa sección no está disponible en tu cuenta. Aquí abajo tienes todo lo que sí puedes cambiar.</div>';
	}

	echo '<header class="sc-ins__hero">';
	echo '<p class="sc-ins__kicker">Guía de tu sitio web</p>';
	echo '<h2 class="sc-ins__title">Hola, ' . esc_html( $name ) . '</h2>';
	echo '<p class="sc-ins__lead">Aquí te explicamos, paso a paso, cómo cambiar cualquier cosa de tu sitio. Elige lo que quieres cambiar y pulsa el botón: te llevamos al lugar exacto.</p>';
	echo '<div class="sc-ins__quick">';
	echo '<a class="sc-ins__btn sc-ins__btn--light" href="' . esc_url( $L['site'] ) . '" target="_blank" rel="noopener">Ver mi sitio</a>';
	echo '<a class="sc-ins__btn sc-ins__btn--ghost" href="' . esc_url( $L['customizer_business'] ) . '">Cambiar datos del negocio</a>';
	echo '</div></header>';

	echo '<section class="sc-ins__find" aria-labelledby="sc-ins-h">';
	echo '<h2 id="sc-ins-h" class="sc-ins__h">¿Qué quieres cambiar?</h2>';
	echo '<div class="sc-ins__search"><label class="screen-reader-text" for="sc-ins-q">Buscar</label>';
	echo '<input id="sc-ins-q" type="search" placeholder="Ej.: logo, teléfono, precios…" autocomplete="off" enterkeyhint="search"></div>';
	echo '<div class="sc-ins__chips" role="group" aria-label="Filtrar por tema"><button type="button" class="sc-ins__chip is-active" data-group="" aria-pressed="true">Todo</button>';
	foreach ( $groups as $g => $label ) {
		if ( isset( $used[ $g ] ) ) {
			echo '<button type="button" class="sc-ins__chip" data-group="' . esc_attr( $g ) . '" aria-pressed="false">' . esc_html( $label ) . '</button>';
		}
	}
	echo '</div><p class="sc-ins__count" id="sc-ins-count" aria-live="polite"></p>';

	echo '<div class="sc-ins__grid" id="sc-ins-grid">';
	foreach ( $cards as $c ) {
		$hay = sc_ins_norm( ( $c['title'] ?? '' ) . ' ' . ( $c['keywords'] ?? '' ) );
		echo '<article class="sc-ins__card" data-group="' . esc_attr( $c['group'] ?? '' ) . '" data-k="' . esc_attr( $hay ) . '" id="sc-card-' . esc_attr( $c['id'] ?? '' ) . '">';
		echo '<div class="sc-ins__ico">' . sc_ins_icon( (string) ( $c['icon'] ?? 'star' ) ) . '</div>';
		echo '<h3 class="sc-ins__ct">' . esc_html( $c['title'] ?? '' ) . '</h3>';
		echo '<ol class="sc-ins__steps">';
		foreach ( (array) ( $c['steps'] ?? array() ) as $s ) {
			echo '<li>' . esc_html( $s ) . '</li>';
		}
		echo '</ol><div class="sc-ins__btns">';
		$first = true;
		foreach ( (array) ( $c['buttons'] ?? array() ) as $b ) {
			if ( empty( $b['url'] ) ) {
				continue;
			}
			echo '<a class="sc-ins__btn ' . ( $first ? 'sc-ins__btn--primary' : 'sc-ins__btn--soft' ) . '" href="' . esc_url( $b['url'] ) . '">' . esc_html( $b['label'] ) . '</a>';
			$first = false;
		}
		echo '</div></article>';
	}
	echo '</div>';
	echo '<p class="sc-ins__empty" id="sc-ins-empty" hidden>No encontramos eso. Prueba con otra palabra (por ejemplo: logo, foto, precio) o escríbenos por WhatsApp: con gusto te ayudamos.</p>';
	echo '</section>';

	echo '<section class="sc-ins__extra" aria-label="Más información">';
	echo '<div class="sc-ins__box"><h3>Correos con tu dominio</h3><p>Si contrataste correos corporativos (por ejemplo info@tudominio.com), estarán listos <strong>2 días hábiles después de publicar</strong> tu sitio. Te avisaremos cuando puedas usarlos.</p></div>';
	echo '<div class="sc-ins__box"><h3>Cambiar mi contraseña</h3><p>Entra a tu perfil, baja hasta «Gestión de la cuenta» y pulsa «Establecer nueva contraseña».</p><a class="sc-ins__btn sc-ins__btn--soft" href="' . esc_url( $L['profile'] ) . '">Abrir mi perfil</a></div>';
	if ( '' !== $L['wa_servicom'] ) {
		echo '<div class="sc-ins__box sc-ins__box--wa"><h3>¿Necesitas ayuda?</h3><p>Escríbenos por WhatsApp y te ayudamos con lo que necesites.</p><a class="sc-ins__btn sc-ins__btn--wa" href="' . esc_url( $L['wa_servicom'] ) . '" target="_blank" rel="noopener noreferrer">Escribir a Servicom por WhatsApp</a></div>';
	}
	echo '</section></div>';
}
