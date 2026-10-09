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

/** Tarjetas de la tienda (WooCommerce): comunes al sitio clásico y al motor Luxe. */
function sc_ins_store_cards( array $L ): array {
	$cust = static function ( string $url, string $label ) {
		return array( 'label' => $label, 'url' => $url );
	};
	$cards = array();
	$cards[] = array(
		'id' => 'productos', 'group' => 'tienda', 'icon' => 'bag', 'title' => 'Productos (agregar, cambiar o quitar)',
		'keywords' => 'producto productos articulo agregar nuevo quitar foto descripcion',
		'steps' => array( 'Entre a «Productos».', 'Pulse el nombre de un producto para editarlo, o «Añadir nuevo».', 'Pulse «Actualizar».' ),
		'buttons' => array( $cust( $L['products'], 'Ver productos' ), $cust( $L['product_new'], 'Agregar un producto' ) ),
		'store' => true,
	);
	$cards[] = array(
		'id' => 'categorias', 'group' => 'tienda', 'icon' => 'folder', 'title' => 'Categorías de la tienda',
		'keywords' => 'categoria categorias grupos clasificar',
		'steps' => array( 'Entre a «Categorías».', 'Escriba el nombre y pulse «Añadir».', 'Para asignarla, edite el producto y marque la categoría.' ),
		'buttons' => array( $cust( $L['categories'], 'Abrir categorías' ) ),
		'store' => true,
	);
	$cards[] = array(
		'id' => 'precios', 'group' => 'tienda', 'icon' => 'tag', 'title' => 'Precios y ofertas',
		'keywords' => 'precio precios oferta descuento costo rebaja',
		'steps' => array( 'Abra el producto.', 'Más abajo, en «Datos del producto», cambia el «Precio normal» (y el «Precio rebajado» si hay oferta).', 'Pulse «Actualizar».' ),
		'buttons' => array( $cust( $L['products'], 'Ver productos' ) ),
		'store' => true,
	);
	$cards[] = array(
		'id' => 'stock', 'group' => 'tienda', 'icon' => 'boxes', 'title' => 'Existencias (stock)',
		'keywords' => 'stock existencia inventario cantidad agotado disponible',
		'steps' => array( 'Abra el producto y ve a «Inventario».', 'Cambie la «Cantidad en inventario».', 'Pulse «Actualizar».' ),
		'buttons' => array( $cust( $L['products'], 'Ver productos' ) ),
		'store' => true,
	);
	$cards[] = array(
		'id' => 'pedidos', 'group' => 'tienda', 'icon' => 'receipt', 'title' => 'Pedidos',
		'keywords' => 'pedido pedidos ordenes compras ventas estado entregado',
		'steps' => array( 'Entre a «Pedidos».', 'Abra un pedido para ver los datos del cliente y lo que compró.', 'Cambie el estado (por ejemplo «Completado») y pulse «Actualizar».' ),
		'buttons' => array( $cust( $L['orders'], 'Ver pedidos' ) ),
		'store' => true,
	);
	$cards[] = array(
		'id' => 'pagos', 'group' => 'tienda', 'icon' => 'card', 'title' => 'Métodos de pago',
		'keywords' => 'pago pagos metodo contra entrega transferencia cobrar',
		'steps' => array( 'Entre a «Métodos de pago».', 'Active o desactiva «Transferencia bancaria» y «Pago contra entrega».', 'Pulse «Guardar cambios».' ),
		'buttons' => array( $cust( $L['payments'], 'Transferencia bancaria' ), $cust( $L['payments_cod'], 'Pago contra entrega' ) ),
		'store' => true,
	);
	$cards[] = array(
		'id' => 'banco', 'group' => 'tienda', 'icon' => 'bank', 'title' => 'Datos bancarios',
		'keywords' => 'banco cuenta bancaria numero de cuenta titular deposito transferencia',
		'steps' => array( 'Entre a «Transferencia bancaria».', 'Cambie banco, número de cuenta y titular.', 'Pulse «Guardar cambios».' ),
		'buttons' => array( $cust( $L['payments'], 'Cambier datos bancarios' ) ),
		'store' => true,
	);
	$cards[] = array(
		'id' => 'cuentas', 'group' => 'tienda', 'icon' => 'user', 'title' => 'Cuentas y datos de clientes',
		'keywords' => 'cliente clientes cuenta usuario comprador registro',
		'steps' => array( 'Los clientes pueden comprar con su cuenta o sin ella.', 'Para ver los datos de quien compró, abre el pedido.', 'Ahí encuentra nombre, teléfono, correo y dirección de entrega.' ),
		'buttons' => array( $cust( $L['customers'], 'Ver pedidos' ) ),
		'store' => true,
	);

	return $cards;
}

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

function sc_instruction_cards_legacy( array $L ): array {
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
		'id' => 'textos', 'group' => 'contenido', 'icon' => 'text', 'title' => 'Cambier textos',
		'keywords' => 'texto parrafo frase descripcion escribir contenido palabras',
		'steps' => array( 'Elija la página que desea cambiar.', $editor . ': haga clic sobre el texto y escribe.', 'Pulse el botón azul «Actualizar» para publicar.' ),
		'buttons' => sc_ins_page_buttons( $L ),
	);
	$cards[] = array(
		'id' => 'titulos', 'group' => 'contenido', 'icon' => 'text', 'title' => 'Cambier títulos',
		'keywords' => 'titulo encabezado titular h1 nombre de seccion',
		'steps' => array( 'Abra la página donde está el título.', 'Haga clic en el título y escriba el nuevo.', 'Pulse «Actualizar».' ),
		'buttons' => sc_ins_page_buttons( $L ),
	);
	$cards[] = array(
		'id' => 'imagenes', 'group' => 'contenido', 'icon' => 'image', 'title' => 'Cambier imágenes y fotos',
		'keywords' => 'imagen foto fotografia picture cambiar imagen subir',
		'steps' => array( 'Abra la página donde está la foto.', 'Haga clic sobre la imagen y elige «Elegir imagen» o «Subir».', 'Seleccione una foto de su computadora o de la biblioteca y pulse «Actualizar».' ),
		'buttons' => array_merge( array( $cust( $L['media_new'], 'Subir fotos nuevas' ) ), sc_ins_page_buttons( $L ) ),
	);
	$cards[] = array(
		'id' => 'iconos', 'group' => 'contenido', 'icon' => 'star', 'title' => 'Cambier iconos',
		'keywords' => 'icono simbolo dibujo servicios tarjeta',
		'steps' => array( 'Abra la página donde está el icono (por lo general Inicio).', 'Haga clic sobre el icono y elige «Icono» en el panel izquierdo.', 'Busque el que más te guste y pulse «Actualizar».' ),
		'buttons' => array( $pb( 'home', 'Editar Inicio' ) ),
	);
	$cards[] = array(
		'id' => 'botones', 'group' => 'contenido', 'icon' => 'button', 'title' => 'Cambier botones (texto y enlace)',
		'keywords' => 'boton enlace link llamado a la accion cta',
		'steps' => array( 'Abra la página y haga clic sobre el botón.', 'En el panel izquierdo cambia el «Texto» y el «Enlace».', 'Pulse «Actualizar».' ),
		'buttons' => sc_ins_page_buttons( $L ),
	);
	$cards[] = array(
		'id' => 'banner', 'group' => 'contenido', 'icon' => 'banner', 'title' => 'Cambier el banner de portada',
		'keywords' => 'banner portada hero slider imagen principal cabecera grande',
		'steps' => array( 'Abra la página de Inicio.', 'Haga clic en la parte superior (banner) y cambia la imagen de fondo, el título o el botón.', 'Pulse «Actualizar».' ),
		'buttons' => array( $pb( 'home', 'Editar el banner' ) ),
	);
	$svc = array();
	foreach ( $L['services'] as $s ) {
		$svc[] = array( 'label' => 'Editar: ' . $s['label'], 'url' => $s['url'] );
	}
	$cards[] = array(
		'id' => 'servicios', 'group' => 'contenido', 'icon' => 'grid', 'title' => 'Servicios',
		'keywords' => 'servicio servicios oferta lista agregar quitar',
		'steps' => array( 'Para cambiar el resumen, abre la página Servicios o Inicio.', 'Para cambiar el detalle de un servicio, abre su página con los botones de esta tarjeta.', 'Pulse «Actualizar» al terminar.' ),
		'buttons' => array_merge( array( $pb( 'servicios', 'Editar Servicios' ) ), array_slice( $svc, 0, 12 ) ),
	);
	$cards[] = array(
		'id' => 'galeria', 'group' => 'contenido', 'icon' => 'gallery', 'title' => 'Galería de fotos',
		'keywords' => 'galeria fotos album imagenes trabajos portafolio',
		'steps' => array( 'Abra la página Galería.', 'Haga clic en la galería y pulse «Agregar imágenes» (o la X para quitar).', 'Pulse «Actualizar».' ),
		'buttons' => array( $pb( 'galeria', 'Editar Galería' ), $cust( $L['media_new'], 'Subir fotos nuevas' ) ),
	);
	$cards[] = array(
		'id' => 'video', 'group' => 'contenido', 'icon' => 'video', 'title' => 'Video de YouTube',
		'keywords' => 'video youtube reproducir pelicula',
		'steps' => array( 'Abra la página donde está el video.', 'Haga clic sobre el video y pega el enlace de YouTube en «Enlace».', 'Pulse «Actualizar».' ),
		'buttons' => array( $pb( 'home', 'Editar Inicio' ) ),
	);

	// --- Apariencia --------------------------------------------------------
	$cards[] = array(
		'id' => 'menu', 'group' => 'apariencia', 'icon' => 'menu', 'title' => 'Menú de navegación',
		'keywords' => 'menu navegacion enlaces superior paginas ordenar',
		'steps' => array( 'Entre a «Menús».', 'Arrastre las opciones para cambiar el orden o agrega páginas nuevas.', 'Pulse «Guardar menú».' ),
		'buttons' => array( $cust( $L['menus'], 'Abrir Menús' ) ),
	);
	$cards[] = array(
		'id' => 'logo', 'group' => 'apariencia', 'icon' => 'logo', 'title' => 'Logo del sitio',
		'keywords' => 'logo marca logotipo icono del sitio favicon',
		'steps' => array( 'Entre a «Identidad del sitio».', 'Pulse «Cambier logotipo» y sube su imagen.', 'Pulse «Publicar».' ),
		'buttons' => array( $cust( $L['customizer_identity'], 'Cambier el logo' ) ),
	);
	$cards[] = array(
		'id' => 'colores', 'group' => 'apariencia', 'icon' => 'palette', 'title' => 'Colores y tipografías',
		'keywords' => 'color colores tipografia letra fuente fuentes estilo paleta',
		'steps' => array( sc_elementor_active() ? 'Se abre el editor con los «Ajustes del sitio».' : 'Se abre el Personalizador en «Estilo visual».', sc_elementor_active() ? 'Elija «Colores globales» o «Tipografía global» y cambia lo que quieras.' : 'Elija el estilo que más te guste.', 'Pulse «Actualizar» o «Publicar».' ),
		'buttons' => array( $cust( $L['kit'], 'Cambier colores y letras' ), $cust( $L['customizer_style'], 'Cambier el estilo del sitio' ) ),
	);
	$cards[] = array(
		'id' => 'estilo', 'group' => 'apariencia', 'icon' => 'palette', 'title' => 'Estilo visual del sitio',
		'keywords' => 'estilo diseno apariencia look tema plantilla',
		'steps' => array( 'Entre a «Estilo visual».', 'Elija una de las opciones; su contenido no se pierde.', 'Pulse «Publicar».' ),
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
		'steps' => array( 'Los datos de contacto y redes se cambian en «Datos del negocio» y «Redes sociales».', 'El texto de créditos se cambia en «Botones y pie de página».', 'Pulse «Publicar».' ),
		'buttons' => array( $cust( $L['customizer_float'], 'Créditos del pie' ), $cust( $L['customizer_business'], 'Datos del negocio' ) ),
	);

	// --- Datos del negocio -----------------------------------------------
	$cards[] = array(
		'id' => 'contacto', 'group' => 'datos', 'icon' => 'phone', 'title' => 'Teléfono, WhatsApp, correo, dirección y horario',
		'keywords' => 'telefono whatsapp celular correo email direccion horario atencion llamar contacto datos',
		'steps' => array( 'Entre a «Datos del negocio».', 'Cambie lo que necesites. Se actualiza en todo el sitio al mismo tiempo.', 'Pulse «Publicar».' ),
		'buttons' => array( $cust( $L['customizer_business'], 'Cambier datos del negocio' ) ),
	);
	$cards[] = array(
		'id' => 'redes', 'group' => 'datos', 'icon' => 'share', 'title' => 'Redes sociales',
		'keywords' => 'redes sociales facebook instagram tiktok youtube twitter linkedin',
		'steps' => array( 'Entre a «Redes sociales».', 'Pegue el enlace de su perfil o escriba su @usuario. Deje vacías las que no uses.', 'Pulse «Publicar».' ),
		'buttons' => array( $cust( $L['customizer_social'], 'Cambier redes sociales' ) ),
	);
	$cards[] = array(
		'id' => 'flotante', 'group' => 'datos', 'icon' => 'chat', 'title' => 'Botón flotante de WhatsApp y barra del celular',
		'keywords' => 'boton flotante whatsapp barra inferior celular movil mensaje',
		'steps' => array( 'Entre a «Botones y pie de página».', 'Active o desactiva el botón y la barra del celular.', 'El mensaje inicial se cambia en «Datos del negocio».' ),
		'buttons' => array( $cust( $L['customizer_float'], 'Botones flotantes' ), $cust( $L['customizer_business'], 'Mensaje de WhatsApp' ) ),
	);
	$cards[] = array(
		'id' => 'formulario', 'group' => 'datos', 'icon' => 'mail', 'title' => 'Formulario de contacto',
		'keywords' => 'formulario contacto mensajes correo recibir campos',
		'steps' => array( 'Los mensajes llegan al correo de contacto: cámbialo en «Datos del negocio».', 'Para cambiar los campos o textos del formulario, abre su pantalla.', 'Guarda los cambios.' ),
		'buttons' => array( $cust( $L['contact_form_settings'], 'Abrir el formulario' ), $cust( $L['customizer_business'], 'Cambier correo de contacto' ) ),
	);
	$cards[] = array(
		'id' => 'mapa', 'group' => 'datos', 'icon' => 'pin', 'title' => 'Mapa y ubicación',
		'keywords' => 'mapa ubicacion google maps direccion como llegar',
		'steps' => array( 'En Google Maps busca su negocio, pulse «Compartir» y copia el enlace.', 'Entre a «Datos del negocio» y pégalo en «Enlace de Google Maps».', 'Pulse «Publicar».' ),
		'buttons' => array( $cust( $L['customizer_business'], 'Cambier el mapa' ) ),
	);

	// --- Tienda ----------------------------------------------------------
	if ( ! empty( $L['is_store'] ) ) {
		$cards = array_merge( $cards, sc_ins_store_cards( $L ) );
	}

	// --- Cuenta ------------------------------------------------------------
	$cards[] = array(
		'id' => 'password', 'group' => 'cuenta', 'icon' => 'lock', 'title' => 'Cambier mi contraseña',
		'keywords' => 'contrasena clave password acceso seguridad cuenta perfil',
		'steps' => array( 'Entre a «Mi perfil».', 'Baje hasta «Gestión de la cuenta» y pulse «Establecer nueva contraseña».', 'Pulse «Actualizar perfil».' ),
		'buttons' => array( $cust( $L['profile'], 'Abrir mi perfil' ) ),
	);

	return $cards;
}

/* -------------------------------------------------------------------------
 * Motor Luxe: rutas del editor en vivo calculadas desde `sc_site`
 * ---------------------------------------------------------------------- */

/** Contenido del sitio (option `sc_site`). */
function sc_ins_site(): array {
	$s = function_exists( 'sc_site' ) ? sc_site() : get_option( 'sc_site', array() );
	return is_array( $s ) ? $s : array();
}

function sc_ins_is_luxe( array $site ): bool {
	return ! empty( $site['v'] ) && ! empty( $site['pages'] ) && is_array( $site['pages'] );
}

/** Valor en una ruta con puntos (misma lógica que `sc_lx_get`); null si no existe. */
function sc_ins_get( array $site, string $path ) {
	$cur = $site;
	foreach ( explode( '.', $path ) as $k ) {
		if ( is_array( $cur ) && array_key_exists( $k, $cur ) ) {
			$cur = $cur[ $k ];
		} else {
			return null;
		}
	}
	return $cur;
}

function sc_ins_has( array $site, string $path ): bool {
	return null !== sc_ins_get( $site, $path );
}

/** Secciones de un tipo: [ ['page'=>, 'idx'=>, 'base'=>'pages.home.sections.2', 'id'=>, 'on'=>], ... ] (inicio primero). */
function sc_ins_sections( array $site, string $type = '' ): array {
	$out   = array();
	$pages = (array) ( $site['pages'] ?? array() );
	$keys  = array_merge( array( 'home' ), array_diff( array_keys( $pages ), array( 'home' ) ) );
	foreach ( $keys as $pk ) {
		foreach ( (array) ( $pages[ $pk ]['sections'] ?? array() ) as $i => $sec ) {
			if ( ! is_array( $sec ) || ( '' !== $type && ( $sec['type'] ?? '' ) !== $type ) ) {
				continue;
			}
			$out[] = array(
				'page' => (string) $pk,
				'idx'  => (int) $i,
				'type' => (string) ( $sec['type'] ?? '' ),
				'id'   => (string) ( $sec['id'] ?? '' ),
				'on'   => ! empty( $sec['on'] ),
				'base' => 'pages.' . $pk . '.sections.' . $i,
			);
		}
	}
	return $out;
}

/** Primera sección de un tipo (o null). */
function sc_ins_first( array $site, string $type ): ?array {
	$all = sc_ins_sections( $site, $type );
	return $all ? $all[0] : null;
}

/** Página del sitio (clave de `sc_page_ids`) donde se encuentra una ruta. */
function sc_ins_page_for( array $site, string $focus ): string {
	if ( preg_match( '/^pages\.([A-Za-z0-9_-]+)(\.|$)/', $focus, $m ) ) {
		return $m[1];
	}
	if ( 0 === strpos( $focus, 'services.' ) ) {
		return isset( $site['pages']['servicios'] ) ? 'servicios' : 'home';
	}
	return 'home';
}

function sc_ins_page_url( string $key ): string {
	if ( 'home' !== $key ) {
		$ids = (array) get_option( 'sc_page_ids', array() );
		$id  = (int) ( $ids[ $key ] ?? 0 );
		if ( $id && get_post( $id ) ) {
			$l = get_permalink( $id );
			if ( $l ) {
				return (string) $l;
			}
		}
	}
	return home_url( '/' );
}

/**
 * URL del editor en vivo, opcionalmente enfocado en un elemento (ruta `data-sc`).
 * Usa `sc_editor_url()` del editor si existe; si no, la construye directamente.
 */
function sc_ins_editor_url( string $focus = '', ?array $site = null ): string {
	if ( function_exists( 'sc_editor_url' ) ) {
		$u = sc_editor_url( $focus );
		if ( is_string( $u ) && false !== strpos( $u, 'sc_edit=1' ) && ( '' === $focus || false !== strpos( $u, 'sc_focus=' ) ) ) {
			return $u;
		}
	}
	$site = null === $site ? sc_ins_site() : $site;
	$base = '' === $focus ? home_url( '/' ) : sc_ins_page_url( sc_ins_page_for( $site, $focus ) );
	$args = array( 'sc_edit' => 1 );
	if ( '' !== $focus ) {
		$args['sc_focus'] = $focus;
	}
	return add_query_arg( $args, $base );
}

function sc_ins_section_label( string $type ): string {
	$l = array(
		'hero' => 'Portada', 'strip' => 'Franja de servicios', 'about' => 'Nosotros', 'services' => 'Servicios', 'values' => 'Por qué elegirnos',
		'process' => 'Cómo trabajamos', 'gallery' => 'Galería', 'quote' => 'Frase destacada', 'faq' => 'Preguntas frecuentes',
		'cta' => 'Invitación a contactar', 'contact' => 'Contacto', 'video' => 'Video', 'products' => 'Productos', 'text' => 'Texto',
	);
	return $l[ $type ] ?? ucfirst( $type );
}

/**
 * Tarjetas «Cómo…» para el motor Luxe. Los botones sólo existen cuando la ruta
 * existe en `sc_site`; las tarjetas que dependen de una sección ausente se omiten.
 */
function sc_instruction_cards_luxe( array $L, array $site ): array {
	$ed  = static function ( string $focus = '' ) use ( $site ) {
		return sc_ins_editor_url( $focus, $site );
	};
	// Botón a una ruta; null si la ruta no existe en el sitio.
	$to  = static function ( string $label, string $path ) use ( $site, $ed ) {
		return sc_ins_has( $site, $path ) ? array( 'label' => $label, 'url' => $ed( $path ), 'focus' => $path ) : null;
	};
	$sec = static function ( string $label, ?array $s ) use ( $ed ) {
		return $s ? array( 'label' => $label, 'url' => $ed( $s['base'] ), 'focus' => $s['base'] ) : null;
	};
	$all = static function ( array $b ) {
		return array_values( array_filter( $b ) );
	};
	$open = array( 'label' => 'Abrir mi web en modo edición', 'url' => $ed(), 'focus' => '' );

	$hero    = sc_ins_first( $site, 'hero' );
	$about   = sc_ins_first( $site, 'about' );
	$svcsec  = sc_ins_first( $site, 'services' );
	$values  = sc_ins_first( $site, 'values' );
	$faq     = sc_ins_first( $site, 'faq' );
	$video   = sc_ins_first( $site, 'video' );
	$cta     = sc_ins_first( $site, 'cta' );
	$contact = sc_ins_first( $site, 'contact' );
	$gals    = sc_ins_sections( $site, 'gallery' );
	$svcs    = array();
	foreach ( (array) ( $site['services'] ?? array() ) as $n => $s ) {
		if ( is_array( $s ) && '' !== trim( (string) ( $s['nombre'] ?? '' ) ) ) {
			$svcs[ (int) $n ] = trim( (string) $s['nombre'] );
		}
	}
	$hb = $hero ? $hero['base'] . '.data.' : '';

	$cards = array();

	// --- Empezar -----------------------------------------------------------
	$cards[] = array(
		'id' => 'primer-paso', 'group' => 'empezar', 'icon' => 'pen', 'wide' => true, 'open' => true, 'visual' => 'toolbar',
		'title' => 'Primer paso: pulse «Editar mi web»',
		'lead' => 'Su web se edite directamente sobre la página, sin formularios ni menús complicados.',
		'keywords' => 'empezar comenzar primer paso editar mi web barra superior modo edicion como se usa herramientas deshacer panel secciones diseno',
		'steps' => array(
			'Entre a su sitio con su usuario y contraseña: arriba verá una barra oscura con las herramientas.',
			'Pulse «Editar mi web». Los textos, fotos, íconos y botones que puede cambiar se resaltan al pasar el cursor.',
			'Pulse sobre lo que desea cambiar, escriba o elija el nuevo contenido, y listo: se guarda solo y verá el aviso «Guardado».',
			'Si algo no le gusta, pulse «Deshacer» en la barra.',
		),
		'buttons' => array( $open, array( 'label' => 'Ver mi web', 'url' => $L['site'], 'focus' => '' ) ),
	);
	$cards[] = array(
		'id' => 'guia-visual', 'group' => 'empezar', 'icon' => 'cursor', 'wide' => true, 'visual' => 'page',
		'title' => 'Qué se puede cambiar en cada página',
		'lead' => 'Reconozca de un vistazo lo que se puede tocar cuando está en modo edición.',
		'keywords' => 'que puedo cambiar texto foto icono boton elementos resaltados guia visual esquema',
		'steps' => array(
			'Textos: pulse y escriba directamente.',
			'Fotos: pulse la foto y elija una de su biblioteca o suba una nueva.',
			'Íconos y botones: pulse para elegir otro ícono, o cambiar el texto y el enlace del botón.',
		),
		'buttons' => array( $open ),
	);
	$cards[] = array(
		'id' => 'deshacer', 'group' => 'empezar', 'icon' => 'undo',
		'title' => 'Cómo deshacer un cambio',
		'lead' => 'Se equivocó o se arrepintió: no pasa nada.',
		'keywords' => 'deshacer revertir error equivoque arrepenti volver atras restaurar anterior cambio',
		'steps' => array(
			'Abra su web en modo edición (botón «Editar mi web»).',
			'En la barra superior pulse «Deshacer»: vuelve al estado anterior. Puede pulsarlo varias veces.',
			'Si ya salió del editor, entre de nuevo y corrija lo que necesite: no se pierde nada más.',
		),
		'buttons' => array( $open ),
	);

	// --- Contenido ----------------------------------------------------------
	if ( $hero ) {
		$cards[] = array(
			'id' => 'portada-titulo', 'group' => 'contenido', 'icon' => 'text',
			'title' => 'Cambier el título de la portada',
			'lead' => 'La frase grande que ven primero sus visitantes.',
			'keywords' => 'titulo portada principal hero inicio encabezado frase grande subtitulo eslogan sobretitulo',
			'steps' => array( 'Pulse el primer botón: se abre su portada con el título ya seleccionado.', 'Escriba el nuevo título (también puede cambiar el subtítulo con los otros botones).', 'Cuando termine no necesita guardar: aparece «Guardado».' ),
			'buttons' => $all( array( $to( 'Cambier el título', $hb . 'title' ), $to( 'Cambier el subtítulo', $hb . 'sub' ), $to( 'Cambier el sobretítulo', $hb . 'eyebrow' ) ) ),
		);
		$cards[] = array(
			'id' => 'portada-imagen', 'group' => 'contenido', 'icon' => 'image',
			'title' => 'Cambier la imagen principal',
			'lead' => 'La foto de fondo de la portada.',
			'keywords' => 'imagen principal portada foto fondo banner hero cabecera grande cambiar subir',
			'steps' => array( 'Pulse el botón: la portada se abre con la imagen seleccionada.', 'Elija una foto de su biblioteca o suba una nueva desde su computadora.', 'Confirme y verá «Guardado». Use fotos horizontales y bien iluminadas.' ),
			'buttons' => $all( array( $to( 'Cambier la imagen principal', $hb . 'img' ), $to( 'Cambier la foto lateral', $hb . 'side' ), array( 'label' => 'Subir fotos nuevas', 'url' => $L['media_new'], 'focus' => '' ) ) ),
		);
	}
	$tx = $all( array(
		$about ? $to( 'Texto de «Nosotros»', $about['base'] . '.data.text' ) : null,
		$about ? $to( 'Título de «Nosotros»', $about['base'] . '.data.title' ) : null,
		$svcsec ? $to( 'Título de «Servicios»', $svcsec['base'] . '.data.title' ) : null,
		$cta ? $to( 'Texto de la invitación final', $cta['base'] . '.data.text' ) : null,
		$contact ? $to( 'Texto de «Contacto»', $contact['base'] . '.data.lead' ) : null,
	) );
	$cards[] = array(
		'id' => 'textos', 'group' => 'contenido', 'icon' => 'text',
		'title' => 'Cambier cualquier texto',
		'lead' => 'Títulos, párrafos, frases: todo se escriba directamente sobre la página.',
		'keywords' => 'texto parrafo frase descripcion escribir contenido palabras titulo nosotros quienes somos',
		'steps' => array( 'Pulse uno de los botones para ir directo a ese texto, o abra su web en modo edición y pulse cualquier texto.', 'Escriba o corrija; los textos largos aceptan negrita y enlaces.', 'Se guarda solo («Guardado»).' ),
		'buttons' => $tx ? $tx : array( $open ),
	);
	if ( $svcs ) {
		$ico = array();
		$k   = 0;
		foreach ( $svcs as $n => $nombre ) {
			if ( $k++ >= 6 ) {
				break;
			}
			$ico[] = $to( 'Ícono de «' . $nombre . '»', 'services.' . $n . '.icono' );
		}
		if ( $values ) {
			$ico[] = $to( 'Íconos de «' . sc_ins_section_label( 'values' ) . '»', $values['base'] . '.data.items.0.icon' );
		}
		$cards[] = array(
			'id' => 'icono-servicio', 'group' => 'contenido', 'icon' => 'star',
			'title' => 'Cambier un ícono de un servicio',
			'lead' => 'Cada servicio tiene su ícono; hay más de cien para elegir.',
			'keywords' => 'icono simbolo dibujo servicio tarjeta cambiar valores',
			'steps' => array( 'Pulse el botón del servicio: se abre su web con el ícono seleccionado.', 'Pulse el ícono y elija otro del selector (puede buscarlo por nombre).', 'Verá «Guardado».' ),
			'buttons' => $all( $ico ),
		);

		$edit = array();
		$k    = 0;
		foreach ( $svcs as $n => $nombre ) {
			if ( $k++ >= 8 ) {
				break;
			}
			$edit[] = $to( $nombre, 'services.' . $n . '.nombre' );
		}
		$cards[] = array(
			'id' => 'servicio-editar', 'group' => 'contenido', 'icon' => 'grid',
			'title' => 'Editar un servicio',
			'lead' => 'Nombre, resumen, descripción, ícono y foto de cada servicio.',
			'keywords' => 'servicio servicios editar cambiar nombre resumen descripcion foto precio oferta',
			'steps' => array( 'Pulse el botón con el nombre del servicio que desea cambiar.', 'Edite el nombre, el resumen, el ícono o la foto directamente sobre la tarjeta.', 'Para ver y editar la descripción completa, abra el servicio desde «Ver más».' ),
			'buttons' => $all( $edit ),
		);
		$svb = $all( array( $sec( 'Ir a la sección de servicios', $svcsec ) ) );
		if ( ! $svb ) {
			$svb = array( $open );
		}
		$cards[] = array(
			'id' => 'servicio-agregar', 'group' => 'contenido', 'icon' => 'plus',
			'title' => 'Agregar un servicio nuevo',
			'lead' => 'Sume un servicio y aparecerá en su página, su menú y su propia página de detalle.',
			'keywords' => 'agregar nuevo servicio anadir crear sumar duplicar',
			'steps' => array( 'Pulse el botón para ir a la sección de servicios en modo edición.', 'Use el control de agregar que aparece en la sección (o duplique un servicio parecido para ahorrar tiempo).', 'Escriba el nombre y el resumen, y elija un ícono y una foto.' ),
			'buttons' => $svb,
		);
		$del = array();
		$k   = 0;
		foreach ( $svcs as $n => $nombre ) {
			if ( $k++ >= 6 ) {
				break;
			}
			$del[] = $to( 'Quitar: ' . $nombre, 'services.' . $n . '.nombre' );
		}
		$cards[] = array(
			'id' => 'servicio-eliminar', 'group' => 'contenido', 'icon' => 'trash',
			'title' => 'Eliminar un servicio',
			'lead' => 'Quite lo que ya no ofrece.',
			'keywords' => 'eliminar quitar borrar servicio esconder ocultar',
			'steps' => array( 'Pulse el botón del servicio que desea quitar.', 'Sobre su tarjeta use el control de eliminar (o duplicar, si quiere conservar una copia).', 'Confirme. Si se equivoca, pulse «Deshacer» en la barra.' ),
			'buttons' => $all( $del ),
		);
	}
	if ( $gals ) {
		$g = array();
		foreach ( $gals as $s ) {
			$g[] = array( 'label' => ( 'home' === $s['page'] ? 'Ir a la galería' : 'Galería (página completa)' ), 'url' => $ed( $s['base'] ), 'focus' => $s['base'] );
			if ( sc_ins_has( $site, $s['base'] . '.data.items.0' ) ) {
				$g[] = array( 'label' => 'Cambier la primera foto' . ( 'home' === $s['page'] ? '' : ' (página completa)' ), 'url' => $ed( $s['base'] . '.data.items.0' ), 'focus' => $s['base'] . '.data.items.0' );
			}
		}
		$g[] = array( 'label' => 'Subir fotos nuevas', 'url' => $L['media_new'], 'focus' => '' );
		$cards[] = array(
			'id' => 'galeria', 'group' => 'contenido', 'icon' => 'gallery',
			'title' => 'Subir o cambiar una foto de la galería',
			'lead' => 'Muestre sus trabajos con fotos propias.',
			'keywords' => 'galeria fotos album imagenes trabajos portafolio subir cambiar agregar quitar',
			'steps' => array( 'Pulse «Ir a la galería»: se abre en modo edición.', 'Pulse una foto para reemplazarla por otra de su biblioteca o por una nueva que suba.', 'Para agregar o quitar fotos, use los controles que aparecen sobre la galería.' ),
			'buttons' => array_slice( $g, 0, 5 ),
		);
	}
	if ( $faq ) {
		$cards[] = array(
			'id' => 'faq', 'group' => 'contenido', 'icon' => 'help',
			'title' => 'Agregar o quitar una pregunta frecuente',
			'lead' => 'Responda de antemano lo que sus clientes siempre preguntan.',
			'keywords' => 'preguntas frecuentes faq pregunta respuesta dudas agregar quitar eliminar',
			'steps' => array( 'Pulse el botón para ir a las preguntas frecuentes.', 'Pulse una pregunta o su respuesta y escriba. Para sumar otra o quitar una, use los controles de agregar y eliminar de la sección.', 'Verá «Guardado».' ),
			'buttons' => $all( array( $sec( 'Ir a las preguntas frecuentes', $faq ), $to( 'Editar la primera pregunta', $faq['base'] . '.data.items.0.q' ) ) ),
		);
	}
	if ( $video ) {
		$cards[] = array(
			'id' => 'video', 'group' => 'contenido', 'icon' => 'video',
			'title' => 'Agregar o cambiar un video de YouTube',
			'lead' => 'Muestre su negocio en movimiento.',
			'keywords' => 'video youtube reproducir pelicula enlace',
			'steps' => array( 'Suba su video a YouTube y copie su enlace (botón «Compartir»).', 'Pulse el botón para ir a la sección de video.', 'Pegue el enlace en el campo de dirección y verá «Guardado».' ),
			'buttons' => $all( array( $to( 'Cambier el enlace del video', $video['base'] . '.data.url' ), $sec( 'Ir a la sección de video', $video ) ) ),
		);
	}
	$bt = $all( array(
		$hero ? $to( 'Botón principal de la portada', $hb . 'btn1' ) : null,
		$hero ? $to( 'Segundo botón de la portada', $hb . 'btn2' ) : null,
		$about ? $to( 'Botón de «Nosotros»', $about['base'] . '.data.btn' ) : null,
		$cta ? $to( 'Botón de la invitación final', $cta['base'] . '.data.btn1' ) : null,
	) );
	$cards[] = array(
		'id' => 'botones', 'group' => 'contenido', 'icon' => 'button',
		'title' => 'Cambier un botón y a dónde lleva',
		'lead' => 'Cambie lo que dice el botón y la página, el WhatsApp o el enlace al que dirige.',
		'keywords' => 'boton enlace link llamado a la accion cta texto del boton a donde lleva url whatsapp',
		'steps' => array( 'Pulse el botón que desea editar para ir directo a él.', 'En la ventana que se abre escriba el texto del botón y elija a dónde lleva (WhatsApp, llamada, correo, una página o un enlace).', 'Confirme y verá «Guardado».' ),
		'buttons' => $bt ? $bt : array( $open ),
	);
	$secs = array();
	foreach ( sc_ins_sections( $site ) as $s ) {
		if ( 'home' === $s['page'] && ! in_array( $s['type'], array( 'pagehero', 'products' ), true ) && count( $secs ) < 10 ) {
			$secs[] = $sec( sc_ins_section_label( $s['type'] ), $s );
		}
	}
	$cards[] = array(
		'id' => 'secciones', 'group' => 'contenido', 'icon' => 'layers',
		'title' => 'Ocultar o mostrar una sección y cambiar su orden',
		'lead' => 'Decida qué se ve en su página de inicio y en qué orden.',
		'keywords' => 'seccion secciones ocultar mostrar esconder orden ordenar mover subir bajar flechas',
		'steps' => array( 'Pulse «Secciones» en la barra del editor, o el botón de la sección que desea mover.', 'Use el ojo para ocultarla o mostrarla, y las flechas para subirla o bajarla.', 'Una sección oculta no se borra: puede mostrarla cuando quiera.' ),
		'buttons' => array_merge( array( $open ), $all( $secs ) ),
	);

	// --- Apariencia ---------------------------------------------------------
	$cards[] = array(
		'id' => 'menu', 'group' => 'apariencia', 'icon' => 'menu',
		'title' => 'Editar el menú',
		'lead' => 'Los enlaces de la parte de arriba de su web.',
		'keywords' => 'menu navegacion enlaces superior paginas ordenar',
		'steps' => array( 'Entre a «Menús».', 'Arrastre las opciones para cambiar el orden, o agregue páginas nuevas.', 'Pulse «Guardar menú».' ),
		'buttons' => array( array( 'label' => 'Abrir Menús', 'url' => $L['menus'], 'focus' => '' ) ),
	);
	$cards[] = array(
		'id' => 'logo', 'group' => 'apariencia', 'icon' => 'logo',
		'title' => 'Cambier el logo',
		'lead' => 'Use una imagen con fondo transparente (PNG) para que se vea mejor.',
		'keywords' => 'logo marca logotipo imagen del sitio favicon cambiar',
		'steps' => array( 'En el editor, pulse «Datos del negocio» en la barra superior.', 'Busque «Logo» y elija o suba su imagen.', 'Verá «Guardado». También puede hacerlo desde el Personalizador.' ),
		'buttons' => array( array( 'label' => 'Abrir el editor', 'url' => $ed(), 'focus' => '' ), array( 'label' => 'Cambier desde el Personalizador', 'url' => $L['customizer_identity'], 'focus' => '' ) ),
	);
	$cards[] = array(
		'id' => 'colores', 'group' => 'apariencia', 'icon' => 'palette',
		'title' => 'Cambier colores y tipografías',
		'lead' => 'Los colores salen de su logo; aquí puede ajustarlos y cambiar las letras.',
		'keywords' => 'color colores tipografia letra fuente fuentes estilo paleta diseno tema oscuro claro',
		'steps' => array( 'Abra su web en modo edición y pulse «Diseño» en la barra superior.', 'Elija los colores y las tipografías que prefiera; verá el cambio al instante.', 'Todo se guarda solo.' ),
		'buttons' => array( $open ),
	);

	// --- Datos del negocio -------------------------------------------------
	$cards[] = array(
		'id' => 'contacto', 'group' => 'datos', 'icon' => 'phone',
		'title' => 'Cambier mi teléfono, WhatsApp, correo, dirección y horario',
		'lead' => 'Se cambian una sola vez y se actualizan en todo el sitio.',
		'keywords' => 'telefono whatsapp celular correo email direccion horario atencion llamar contacto datos',
		'steps' => array( 'En el editor, pulse «Datos del negocio» en la barra superior.', 'Cambie lo que necesite: teléfono, WhatsApp, correo, dirección y horario.', 'Se guarda solo. También puede hacerlo desde el Personalizador.' ),
		'buttons' => array( array( 'label' => 'Abrir el editor', 'url' => $ed(), 'focus' => '' ), array( 'label' => 'Cambier desde el Personalizador', 'url' => $L['customizer_business'], 'focus' => '' ) ),
	);
	$cards[] = array(
		'id' => 'redes', 'group' => 'datos', 'icon' => 'share',
		'title' => 'Cambier mis redes sociales',
		'lead' => 'Facebook, Instagram, TikTok, YouTube y más.',
		'keywords' => 'redes sociales facebook instagram tiktok youtube twitter linkedin',
		'steps' => array( 'En el editor, pulse «Datos del negocio».', 'Pegue el enlace de su perfil en cada red. Deje vacías las que no use.', 'Se guarda solo. También puede hacerlo desde el Personalizador.' ),
		'buttons' => array( array( 'label' => 'Abrir el editor', 'url' => $ed(), 'focus' => '' ), array( 'label' => 'Cambier desde el Personalizador', 'url' => $L['customizer_social'], 'focus' => '' ) ),
	);
	$fb = array();
	if ( $contact ) {
		$fb[] = $sec( 'Ver el formulario de contacto', $contact );
	}
	$fb[] = array( 'label' => 'Cambier el correo de recepción', 'url' => $L['customizer_business'], 'focus' => '' );
	if ( ! empty( $L['contact_form_settings'] ) && $L['contact_form_settings'] !== ( $L['pages']['contacto']['url'] ?? '' ) && $L['contact_form_settings'] !== $L['pages_list'] ) {
		$fb[] = array( 'label' => 'Ajustes del formulario', 'url' => $L['contact_form_settings'], 'focus' => '' );
	}
	$cards[] = array(
		'id' => 'formulario', 'group' => 'datos', 'icon' => 'mail',
		'title' => 'Cómo recibo los mensajes del formulario de contacto',
		'lead' => 'Cada mensaje llega a su correo; puede cambiar la dirección que lo recibe.',
		'keywords' => 'formulario contacto mensajes correo recibir campos bandeja llegan',
		'steps' => array( 'Cuando alguien llena el formulario de su web, el mensaje llega al correo de contacto de «Datos del negocio».', 'Para cambiar el correo que los recibe, use el botón de abajo.', 'Revise también la carpeta de correo no deseado la primera vez.' ),
		'buttons' => $all( $fb ),
	);
	$cards[] = array(
		'id' => 'flotante', 'group' => 'datos', 'icon' => 'chat',
		'title' => 'Botón flotante de WhatsApp y barra del celular',
		'lead' => 'Los botones que acompañan a sus visitantes mientras recorren la web.',
		'keywords' => 'boton flotante whatsapp barra inferior celular movil mensaje llamar',
		'steps' => array( 'Entre a «Botones y pie de página».', 'Active o desactive el botón y la barra del celular.', 'Pulse «Publicar».' ),
		'buttons' => array( array( 'label' => 'Botones flotantes', 'url' => $L['customizer_float'], 'focus' => '' ) ),
	);

	// --- Tienda -------------------------------------------------------------
	if ( ! empty( $L['is_store'] ) ) {
		$cards = array_merge( $cards, sc_ins_store_cards( $L ) );
	}

	// --- Cuenta -------------------------------------------------------------
	$cards[] = array(
		'id' => 'password', 'group' => 'cuenta', 'icon' => 'lock',
		'title' => 'Cambier mi contraseña',
		'lead' => 'Cámbiela cada cierto tiempo y no la comparta.',
		'keywords' => 'contrasena clave password acceso seguridad cuenta perfil',
		'steps' => array( 'Entre a «Mi perfil».', 'Baje hasta «Gestión de la cuenta» y pulse «Establecer nueva contraseña».', 'Pulse «Actualizar perfil».' ),
		'buttons' => array( array( 'label' => 'Abrir mi perfil', 'url' => $L['profile'], 'focus' => '' ) ),
	);
	$cards[] = array(
		'id' => 'renovacion', 'group' => 'cuenta', 'icon' => 'clock',
		'title' => 'Cómo se renueva mi web',
		'lead' => 'El dominio y el alojamiento se renuevan cada año.',
		'keywords' => 'renovar renovacion vencimiento dominio hosting pago anual vence',
		'steps' => array( 'Unos días antes del vencimiento le avisaremos por correo o WhatsApp.', 'Usted confirma la renovación con nosotros; no tiene que hacer ningún trámite técnico.', 'Mientras tanto, su web y sus correos siguen funcionando con normalidad.' ),
		'buttons' => $L['wa_servicom'] ? array( array( 'label' => 'Consultar mi renovación por WhatsApp', 'url' => $L['wa_servicom'], 'focus' => '', 'external' => true ) ) : array(),
	);
	$cards[] = array(
		'id' => 'ayuda', 'group' => 'cuenta', 'icon' => 'support',
		'title' => 'Cómo pedir ayuda',
		'lead' => 'Si algo no sale como espera, escríbanos.',
		'keywords' => 'ayuda soporte contacto servicom problema error no puedo whatsapp asistencia',
		'steps' => array( 'Pulse el botón de WhatsApp y cuéntenos qué quiere lograr.', 'Si puede, envíe una captura de pantalla de lo que ve.', 'Le responderemos lo antes posible.' ),
		'buttons' => $L['wa_servicom'] ? array( array( 'label' => 'Escribir a Servicom por WhatsApp', 'url' => $L['wa_servicom'], 'focus' => '', 'external' => true ) ) : array(),
	);
	return $cards;
}

function sc_instruction_cards(): array {
	$L    = sc_instruction_links();
	$site = sc_ins_site();
	$cards = sc_ins_is_luxe( $site ) ? sc_instruction_cards_luxe( $L, $site ) : sc_instruction_cards_legacy( $L );
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
		'pen'     => '<path d="M4 20l1-4L16 5l3 3L8 19z"/><path d="M14 7l3 3"/>',
		'cursor'  => '<path d="M5 3l14 7-6 2-2 6z"/>',
		'undo'    => '<path d="M9 14L4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/>',
		'plus'    => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
		'trash'   => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
		'help'    => '<circle cx="12" cy="12" r="9"/><path d="M9.500 9.500a2.500 2.500 0 1 1 3.500 2.300c-.7.400-1 .9-1 1.700M12 17v.1"/>',
		'layers'  => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
		'support' => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="14" width="4" height="6" rx="1.500"/><rect x="17" y="14" width="4" height="6" rx="1.500"/>',
		'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
	);
	$d = $p[ $name ] ?? $p['star'];
	return '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
}

function sc_ins_groups(): array {
	return array(
		'empezar'    => 'Primeros pasos',
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

/** Herramientas de la barra del editor, en el orden en que aparecen. */
function sc_ins_toolbar_tools(): array {
	return array(
		array( 'Editar mi web', 'Activa el modo edición: pulse un texto, foto, ícono o botón y cámbielo.' ),
		array( 'Deshacer', 'Revierte el último cambio que hizo.' ),
		array( 'Datos del negocio', 'Teléfono, WhatsApp, correo, dirección, horario, redes y logo.' ),
		array( 'Diseño', 'Colores y tipografías de todo el sitio.' ),
		array( 'Secciones', 'Mostrar u ocultar secciones y cambiar su orden.' ),
		array( 'Panel', 'Vuelve a este panel de WordPress (instrucciones, medios, pedidos).' ),
	);
}

/** Esquema SVG de la barra del editor (ancho: una fila; angosto: dos filas). Decorativo: el texto va al lado. */
function sc_ins_toolbar_svg( bool $narrow = false ): string {
	$tools = sc_ins_toolbar_tools();
	$w     = $narrow ? 360 : 760;
	$h     = $narrow ? 214 : 150;
	$svg   = '<svg class="sc-ins__svg sc-ins__svg--' . ( $narrow ? 'narrow' : 'wide' ) . '" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="Esquema de la barra superior del editor con seis herramientas numeradas" xmlns="http://www.w3.org/2000/svg" font-family="system-ui,-apple-system,Segoe UI,Roboto,sans-serif">';
	$svg  .= '<rect x="1" y="1" width="' . ( $w - 2 ) . '" height="' . ( $h - 2 ) . '" rx="14" fill="#faf7f0" stroke="#e7dfcf"/>';
	$barH  = $narrow ? 116 : 62;
	$svg  .= '<rect x="14" y="14" width="' . ( $w - 28 ) . '" height="' . $barH . '" rx="12" fill="#1b1a17"/>';
	$cols  = $narrow ? 3 : 6;
	$cw    = ( $w - 28 - 16 - ( $cols - 1 ) * 8 ) / $cols;
	foreach ( $tools as $i => $t ) {
		$r  = (int) floor( $i / $cols );
		$c  = $i % $cols;
		$x  = 22 + $c * ( $cw + 8 );
		$y  = 22 + $r * 54;
		$on = 0 === $i;
		$ph = 46;
		$svg .= '<rect x="' . round( $x, 1 ) . '" y="' . $y . '" width="' . round( $cw, 1 ) . '" height="' . $ph . '" rx="10" fill="' . ( $on ? '#c9a45c' : '#2b2924' ) . '"/>';
		$fs   = $narrow ? 11.5 : 13;
		$svg .= '<text x="' . round( $x + $cw / 2, 1 ) . '" y="' . ( $y + 28 ) . '" text-anchor="middle" font-size="' . $fs . '" font-weight="700" fill="' . ( $on ? '#1b1a17' : '#f3ead7' ) . '">' . esc_html( $t[0] ) . '</text>';
		$mx   = round( $x + $cw / 2, 1 );
		$svg .= '<line x1="' . $mx . '" y1="' . ( $y + $ph ) . '" x2="' . $mx . '" y2="' . ( $narrow ? $y + $ph + 6 : $barH + 24 ) . '" stroke="#c9a45c" stroke-width="1.5" stroke-dasharray="3 3"/>';
		if ( ! $narrow ) {
			$svg .= '<circle cx="' . $mx . '" cy="' . ( $barH + 44 ) . '" r="15" fill="#c9a45c"/><text x="' . $mx . '" y="' . ( $barH + 50 ) . '" text-anchor="middle" font-size="16" font-weight="800" fill="#1b1a17">' . ( $i + 1 ) . '</text>';
		}
	}
	if ( $narrow ) {
		// En dos filas los números van sobre cada botón.
		$svg .= '<g>';
		foreach ( $tools as $i => $t ) {
			$r  = (int) floor( $i / $cols );
			$c  = $i % $cols;
			$mx = round( 22 + $c * ( $cw + 8 ) + $cw / 2, 1 );
			$svg .= '<circle cx="' . $mx . '" cy="' . ( 22 + $r * 54 ) . '" r="10" fill="#c9a45c" stroke="#1b1a17" stroke-width="2"/><text x="' . $mx . '" y="' . ( 22 + $r * 54 + 4 ) . '" text-anchor="middle" font-size="12" font-weight="800" fill="#1b1a17">' . ( $i + 1 ) . '</text>';
		}
		$svg .= '</g>';
		$svg .= '<rect x="14" y="146" width="' . ( $w - 28 ) . '" height="54" rx="10" fill="#fff" stroke="#e7dfcf"/><rect x="28" y="160" width="120" height="8" rx="4" fill="#d9cfb9"/><rect x="28" y="176" width="200" height="8" rx="4" fill="#ebe4d3"/>';
	} else {
		$svg .= '<rect x="14" y="' . ( $barH + 70 ) . '" width="' . ( $w - 28 ) . '" height="10" rx="5" fill="#ebe4d3"/>';
	}
	return $svg . '</svg>';
}

/** Esquema SVG de una página con los elementos que se pueden tocar (decorativo). */
function sc_ins_page_svg(): string {
	$g = '#c9a45c';
	$s = '<svg class="sc-ins__svg sc-ins__svg--page" viewBox="0 0 640 300" role="img" aria-label="Esquema de una página: texto, foto, ícono y botón resaltados como elementos editables" xmlns="http://www.w3.org/2000/svg" font-family="system-ui,-apple-system,Segoe UI,Roboto,sans-serif">';
	$s .= '<rect x="1" y="1" width="638" height="298" rx="14" fill="#faf7f0" stroke="#e7dfcf"/>';
	$s .= '<rect x="14" y="14" width="612" height="272" rx="10" fill="#1b1a17"/>';
	$s .= '<rect x="30" y="26" width="64" height="10" rx="5" fill="#6b6451"/><rect x="520" y="26" width="90" height="10" rx="5" fill="#6b6451"/>';
	// foto
	$s .= '<rect x="372" y="58" width="236" height="170" rx="10" fill="#34312a"/><path d="M372 208l60-54 46 38 40-30 90 56v10H372z" fill="#4a463b"/><circle cx="560" cy="92" r="14" fill="#6b6451"/>';
	$s .= '<rect x="372" y="58" width="236" height="170" rx="10" fill="none" stroke="' . $g . '" stroke-width="2.5" stroke-dasharray="6 5"/>';
	$s .= '<g><rect x="380" y="66" width="52" height="22" rx="11" fill="' . $g . '"/><text x="406" y="81" text-anchor="middle" font-size="12.5" font-weight="800" fill="#1b1a17">Foto</text></g>';
	// título
	$s .= '<rect x="30" y="72" width="270" height="18" rx="9" fill="#f3ead7"/><rect x="30" y="98" width="200" height="18" rx="9" fill="#f3ead7"/>';
	$s .= '<rect x="24" y="64" width="284" height="60" rx="8" fill="none" stroke="' . $g . '" stroke-width="2.5" stroke-dasharray="6 5"/>';
	$s .= '<g><rect x="32" y="40" width="58" height="22" rx="11" fill="' . $g . '"/><text x="61" y="55" text-anchor="middle" font-size="12.5" font-weight="800" fill="#1b1a17">Texto</text></g>';
	// parrafo
	$s .= '<rect x="30" y="138" width="260" height="8" rx="4" fill="#8a8470"/><rect x="30" y="154" width="230" height="8" rx="4" fill="#8a8470"/>';
	// boton
	$s .= '<rect x="30" y="188" width="132" height="38" rx="19" fill="' . $g . '"/><rect x="48" y="203" width="96" height="8" rx="4" fill="#1b1a17" opacity=".55"/>';
	$s .= '<rect x="24" y="182" width="144" height="50" rx="25" fill="none" stroke="#fff" stroke-width="2" stroke-dasharray="6 5"/>';
	$s .= '<g><rect x="176" y="194" width="62" height="22" rx="11" fill="#fff"/><text x="207" y="209" text-anchor="middle" font-size="12.5" font-weight="800" fill="#1b1a17">Botón</text></g>';
	// icono
	$s .= '<circle cx="290" cy="244" r="22" fill="#2b2924"/><path d="M290 232l3.500 7.100 7.800 1.100-5.650 5.500 1.300 7.800-6.950-3.650-7 3.650 1.350-7.800-5.650-5.500 7.800-1.100z" fill="' . $g . '"/>';
	$s .= '<circle cx="290" cy="244" r="28" fill="none" stroke="' . $g . '" stroke-width="2.5" stroke-dasharray="6 5"/>';
	$s .= '<g><rect x="326" y="233" width="56" height="22" rx="11" fill="' . $g . '"/><text x="354" y="248" text-anchor="middle" font-size="12.5" font-weight="800" fill="#1b1a17">Ícono</text></g>';
	return $s . '</svg>';
}

function sc_ins_render_card( array $c ): void {
	$hay  = sc_ins_norm( ( $c['title'] ?? '' ) . ' ' . ( $c['keywords'] ?? '' ) . ' ' . ( $c['lead'] ?? '' ) );
	$mods = ( ! empty( $c['wide'] ) ? ' sc-ins__card--wide' : '' );
	echo '<article class="sc-ins__card' . esc_attr( $mods ) . '" data-group="' . esc_attr( $c['group'] ?? '' ) . '" data-k="' . esc_attr( $hay ) . '" id="sc-card-' . esc_attr( $c['id'] ?? '' ) . '">';
	echo '<header class="sc-ins__chead"><div class="sc-ins__ico">' . sc_ins_icon( (string) ( $c['icon'] ?? 'star' ) ) . '</div><div class="sc-ins__ctx">';
	echo '<h3 class="sc-ins__ct">' . esc_html( $c['title'] ?? '' ) . '</h3>';
	if ( ! empty( $c['lead'] ) ) {
		echo '<p class="sc-ins__cl">' . esc_html( $c['lead'] ) . '</p>';
	}
	echo '</div></header>';
	$visual = (string) ( $c['visual'] ?? '' );
	if ( 'toolbar' === $visual ) {
		echo '<figure class="sc-ins__fig">' . sc_ins_toolbar_svg( false ) . sc_ins_toolbar_svg( true ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<ol class="sc-ins__legend">';
		foreach ( sc_ins_toolbar_tools() as $t ) {
			echo '<li><b>' . esc_html( $t[0] ) . '</b><span>' . esc_html( $t[1] ) . '</span></li>';
		}
		echo '</ol></figure>';
	} elseif ( 'page' === $visual ) {
		echo '<figure class="sc-ins__fig">' . sc_ins_page_svg() . '</figure>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	$steps = (array) ( $c['steps'] ?? array() );
	echo '<details class="sc-ins__acc"' . ( ! empty( $c['open'] ) ? ' open' : '' ) . '><summary><span>Paso a paso</span><em>' . count( $steps ) . ' pasos</em></summary>';
	echo '<ol class="sc-ins__steps">';
	foreach ( $steps as $s ) {
		echo '<li>' . esc_html( $s ) . '</li>';
	}
	echo '</ol></details>';
	$btns = array_filter( (array) ( $c['buttons'] ?? array() ), static function ( $b ) {
		return ! empty( $b['url'] );
	} );
	if ( $btns ) {
		echo '<div class="sc-ins__btns">';
		$first = true;
		foreach ( $btns as $b ) {
			$ext = ! empty( $b['external'] );
			echo '<a class="sc-ins__btn ' . ( $first ? 'sc-ins__btn--primary' : 'sc-ins__btn--soft' ) . '" href="' . esc_url( $b['url'] ) . '"' . ( $ext ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html( $b['label'] ) . '</a>';
			$first = false;
		}
		echo '</div>';
	}
	echo '</article>';
}

function sc_ins_render(): void {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html( 'No tiene permiso para ver esta pantalla.' ) );
	}
	$L      = sc_instruction_links();
	$cards  = sc_instruction_cards();
	$groups = sc_ins_groups();
	$pal    = sc_ins_palette();
	$name   = sc_biz_name();
	$luxe   = sc_ins_is_luxe( sc_ins_site() );
	$used   = array();
	foreach ( $cards as $c ) {
		$used[ $c['group'] ?? '' ] = true;
	}
	$style = sprintf( '--sc-ins-primary:%s;--sc-ins-soft:%s', $pal['primary'], $pal['soft'] );
	echo '<div class="wrap sc-ins" style="' . esc_attr( $style ) . '">';
	echo '<h1 class="screen-reader-text">Instrucciones</h1>';

	if ( isset( $_GET['sc_aviso'] ) ) { // phpcs:ignore
		echo '<div class="sc-ins__notice" role="status">Esa sección no está disponible en su cuenta. Aquí abajo tiene todo lo que sí puede cambiar.</div>';
	}

	echo '<header class="sc-ins__hero">';
	echo '<p class="sc-ins__kicker">Guía de su sitio web</p>';
	echo '<h2 class="sc-ins__title">Hola, ' . esc_html( $name ) . '</h2>';
	echo '<p class="sc-ins__lead">Aquí le explicamos, paso a paso, cómo cambiar cualquier cosa de su sitio. Elija lo que desea cambiar y pulse el botón: lo llevamos al lugar exacto.</p>';
	echo '<div class="sc-ins__quick">';
	if ( $luxe ) {
		echo '<a class="sc-ins__btn sc-ins__btn--gold" href="' . esc_url( sc_ins_editor_url( '' ) ) . '">Editar mi web</a>';
		echo '<a class="sc-ins__btn sc-ins__btn--ghost" href="' . esc_url( $L['site'] ) . '" target="_blank" rel="noopener">Ver mi sitio</a>';
	} else {
		echo '<a class="sc-ins__btn sc-ins__btn--light" href="' . esc_url( $L['site'] ) . '" target="_blank" rel="noopener">Ver mi sitio</a>';
		echo '<a class="sc-ins__btn sc-ins__btn--ghost" href="' . esc_url( $L['customizer_business'] ) . '">Cambiar datos del negocio</a>';
	}
	echo '</div></header>';

	echo '<section class="sc-ins__find" aria-labelledby="sc-ins-h">';
	echo '<h2 id="sc-ins-h" class="sc-ins__h">¿Qué desea cambiar?</h2>';
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
		sc_ins_render_card( $c );
	}
	echo '</div>';
	echo '<p class="sc-ins__empty" id="sc-ins-empty" hidden>No encontramos eso. Pruebe con otra palabra (por ejemplo: logo, foto, precio) o escríbanos por WhatsApp: con gusto le ayudamos.</p>';
	echo '</section>';

	echo '<section class="sc-ins__extra" aria-label="Más información">';
	echo '<div class="sc-ins__box"><h3>Correos con su dominio</h3><p>Si contrató correos corporativos (por ejemplo info@sudominio.com), estarán listos <strong>2 días hábiles después de publicar</strong> su sitio. Le avisaremos cuando pueda usarlos.</p></div>';
	echo '<div class="sc-ins__box"><h3>Cambiar mi contraseña</h3><p>Entre a su perfil, baje hasta «Gestión de la cuenta» y pulse «Establecer nueva contraseña».</p><a class="sc-ins__btn sc-ins__btn--soft" href="' . esc_url( $L['profile'] ) . '">Abrir mi perfil</a></div>';
	if ( '' !== $L['wa_servicom'] ) {
		echo '<div class="sc-ins__box sc-ins__box--wa"><h3>¿Necesita ayuda?</h3><p>Escríbanos por WhatsApp y le ayudamos con lo que necesite.</p><a class="sc-ins__btn sc-ins__btn--wa" href="' . esc_url( $L['wa_servicom'] ) . '" target="_blank" rel="noopener noreferrer">Escribir a Servicom por WhatsApp</a></div>';
	}
	echo '</section></div>';
}
