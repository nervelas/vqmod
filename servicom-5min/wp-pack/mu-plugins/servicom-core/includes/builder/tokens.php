<?php
/**
 * Servicom builder: tokens de estilo y diccionario de etiquetas fijas.
 * PHP 8.0 compatible.
 */
if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('sc_style_tokens')) {
	/**
	 * Paleta y fuentes de los 5 estilos (acordado con el tema: las familias
	 * son las que el tema autoaloja en assets/fonts). Si el tema publica sus
	 * propios tokens en assets/css/style-N.css, intentamos coincidir leyendo
	 * las variables --sc-primary, --sc-accent, etc. (ver sc_style_tokens_from_theme).
	 *
	 * @param int $n 1..5
	 * @return array
	 */
	function sc_style_tokens($n = 1)
	{
		$n = max(1, min(5, (int) $n));
		$all = array(
			1 => array(
				'name' => 'Corporativo',
				'primary' => '#1f3a5f', 'primary_ink' => '#ffffff', 'secondary' => '#2c4a7a', 'accent' => '#b8893b',
				'text' => '#1f2933', 'muted' => '#5b6770', 'bg' => '#ffffff', 'bg_alt' => '#f3f5f8', 'border' => '#dde2e8',
				'font_head' => 'Playfair Display', 'font_body' => 'Lato',
			),
			2 => array(
				'name' => 'Moderno',
				'primary' => '#2563eb', 'primary_ink' => '#ffffff', 'secondary' => '#1e40af', 'accent' => '#f59e0b',
				'text' => '#0f172a', 'muted' => '#64748b', 'bg' => '#ffffff', 'bg_alt' => '#f1f5f9', 'border' => '#e2e8f0',
				'font_head' => 'Sora', 'font_body' => 'Inter',
			),
			3 => array(
				'name' => 'Natural',
				'primary' => '#2f7d5b', 'primary_ink' => '#ffffff', 'secondary' => '#1f5a41', 'accent' => '#d98e3f',
				'text' => '#26322c', 'muted' => '#667267', 'bg' => '#fbfaf6', 'bg_alt' => '#f0f0e6', 'border' => '#e1e1d3',
				'font_head' => 'Fraunces', 'font_body' => 'Nunito Sans',
			),
			4 => array(
				'name' => 'Elegante',
				'primary' => '#14161c', 'primary_ink' => '#ffffff', 'secondary' => '#2b2f3a', 'accent' => '#c9a24a',
				'text' => '#1b1d22', 'muted' => '#6a6e78', 'bg' => '#ffffff', 'bg_alt' => '#f6f4ef', 'border' => '#e6e2d8',
				'font_head' => 'Cormorant Garamond', 'font_body' => 'Manrope',
			),
			5 => array(
				'name' => 'Vibrante',
				'primary' => '#e11d48', 'primary_ink' => '#ffffff', 'secondary' => '#7c3aed', 'accent' => '#f59e0b',
				'text' => '#1a1523', 'muted' => '#6b6577', 'bg' => '#ffffff', 'bg_alt' => '#fdf2f5', 'border' => '#f0dbe2',
				'font_head' => 'DM Serif Display', 'font_body' => 'DM Sans',
			),
		);
		$t = $all[$n];
		return apply_filters('sc_style_tokens', sc_style_tokens_from_theme($n, $t), $n);
	}

	/** Lee --sc-* del style-N.css del tema, si existe, para coincidir con su paleta. */
	function sc_style_tokens_from_theme($n, array $t)
	{
		if (!function_exists('get_theme_root')) {
			return $t;
		}
		$dirs = array();
		if (defined('SC_THEME_DIR')) {
			$dirs[] = SC_THEME_DIR;
		}
		$dirs[] = get_theme_root() . '/servicom';
		foreach ($dirs as $d) {
			$f = $d . '/assets/css/style-' . (int) $n . '.css';
			if (!is_readable($f)) {
				continue;
			}
			$css = (string) @file_get_contents($f, false, null, 0, 20000);
			$map = array(
				'primary' => 'primary', 'primary_ink' => 'primary-ink', 'accent' => 'accent', 'text' => 'text',
				'muted' => 'muted', 'bg' => 'bg', 'bg_alt' => 'bg-alt', 'border' => 'border',
			);
			foreach ($map as $k => $v) {
				if (preg_match('/--sc-' . preg_quote($v, '/') . '\s*:\s*(#[0-9a-fA-F]{3,8})\s*;/', $css, $m)) {
					$t[$k] = strtolower($m[1]);
				}
			}
			foreach (array('head' => 'font-head', 'body' => 'font-body') as $k => $v) {
				if (preg_match('/--sc-' . $v . '\s*:\s*[\'"]?([A-Za-z][A-Za-z0-9 ]+)[\'"]?\s*[,;]/', $css, $m)) {
					$t['font_' . $k] = trim($m[1]);
				}
			}
			break;
		}
		return $t;
	}
}

if (!function_exists('sc_dict')) {
	/** Diccionario de etiquetas fijas es/en. */
	function sc_dict($key, $lang = 'es', ...$args)
	{
		static $d = null;
		if ($d === null) {
			$d = array(
				'es' => array(
					'inicio' => 'Inicio', 'servicios' => 'Servicios', 'nosotros' => 'Nosotros', 'galeria' => 'Galería',
					'contacto' => 'Contacto', 'tienda' => 'Tienda', 'menu_principal' => 'Menú principal',
					'ver_servicios' => 'Ver servicios', 'ver_mas' => 'Ver más', 'conocer_mas' => 'Conocer más',
					'ver_galeria' => 'Ver galería', 'ver_tienda' => 'Ver tienda', 'contactenos' => 'Contáctenos',
					'whatsapp' => 'WhatsApp', 'escribanos' => 'Escríbanos por WhatsApp',
					'todos_servicios' => 'Ver todos los servicios', 'ver_mapa' => 'Ver en el mapa',
					'telefono' => 'Teléfono', 'correo' => 'Correo', 'direccion' => 'Dirección', 'horario' => 'Horario',
					'siguenos' => 'Síganos', 'datos_contacto' => 'Datos de contacto', 'enviar_mensaje' => 'Envíenos un mensaje',
					'wa_servicio' => 'Hola, me interesa el servicio: %s', 'wa_consulta' => 'Hola, quisiera más información.',
					'hero_titulo' => 'Bienvenidos a %s', 'cta_titulo' => 'Escríbanos hoy', 'cta_boton' => 'Contáctenos',
					't_servicios' => 'Nuestros servicios', 't_nosotros' => 'Quiénes somos', 't_contacto' => 'Contáctenos',
					't_galeria' => 'Galería', 't_tienda' => 'Nuestra tienda', 't_destacados' => 'Productos destacados',
					'otros_servicios' => 'Otros servicios', 'volver' => 'Volver a servicios', 'video' => 'Video',
					'menu_pie' => 'Menú del pie',
				),
				'en' => array(
					'inicio' => 'Home', 'servicios' => 'Services', 'nosotros' => 'About us', 'galeria' => 'Gallery',
					'contacto' => 'Contact', 'tienda' => 'Store', 'menu_principal' => 'Main menu',
					'ver_servicios' => 'View services', 'ver_mas' => 'Learn more', 'conocer_mas' => 'About us',
					'ver_galeria' => 'View gallery', 'ver_tienda' => 'Visit the store', 'contactenos' => 'Contact us',
					'whatsapp' => 'WhatsApp', 'escribanos' => 'Message us on WhatsApp',
					'todos_servicios' => 'View all services', 'ver_mapa' => 'View on map',
					'telefono' => 'Phone', 'correo' => 'Email', 'direccion' => 'Address', 'horario' => 'Hours',
					'siguenos' => 'Follow us', 'datos_contacto' => 'Contact details', 'enviar_mensaje' => 'Send us a message',
					'wa_servicio' => 'Hello, I am interested in the service: %s', 'wa_consulta' => 'Hello, I would like more information.',
					'hero_titulo' => 'Welcome to %s', 'cta_titulo' => 'Get in touch today', 'cta_boton' => 'Contact us',
					't_servicios' => 'Our services', 't_nosotros' => 'About us', 't_contacto' => 'Contact us',
					't_galeria' => 'Gallery', 't_tienda' => 'Our store', 't_destacados' => 'Featured products',
					'otros_servicios' => 'Other services', 'volver' => 'Back to services', 'video' => 'Video',
					'menu_pie' => 'Footer menu',
				),
			);
		}
		$l = ($lang === 'en') ? 'en' : 'es';
		$s = $d[$l][$key] ?? ($d['es'][$key] ?? $key);
		if ($args) {
			$s = vsprintf($s, $args);
		}
		return $s;
	}
}
