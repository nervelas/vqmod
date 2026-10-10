<?php
/**
 * Servicom LUXE: paso "pages" sin Elementor. Construye el modelo de contenido `sc_site`
 * (diseño desde el logo, secciones, servicios) y las páginas de WordPress que lo muestran.
 * Idempotente: se puede ejecutar de nuevo (regenera el modelo desde el manifiesto).
 */
if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/forms.php';
require_once dirname(__DIR__) . '/design.php';

class SC_Build_Luxe
{
	private $m;
	private $lang;
	private $assets = array();
	private $ids = array();
	private $svcs = array();
	private $seed = 1;
	private $tienda = false;
	private $wa = '';
	private $tel = '';

	public static function run(array $args)
	{
		$o = new self();
		return $o->go($args);
	}

	/* ------------------------------------------------------------------ utilidades */

	private function L($es, $en)
	{
		return $this->lang === 'en' ? $en : $es;
	}

	private function tx($k, $def = '')
	{
		$v = $this->m['texts'][$k] ?? '';
		$v = is_string($v) ? trim($v) : '';
		return $v !== '' ? $v : $def;
	}

	private function nombre()
	{
		$n = SC_Builder::biz('nombre', '');
		return $n !== '' ? $n : (string) ($this->m['site']['title'] ?? '');
	}

	private function slug($key)
	{
		$es = array('home' => 'inicio', 'nosotros' => 'nosotros', 'servicios' => 'servicios', 'galeria' => 'galeria', 'contacto' => 'contacto', 'tienda' => 'tienda');
		$en = array('home' => 'home', 'nosotros' => 'about-us', 'servicios' => 'services', 'galeria' => 'gallery', 'contacto' => 'contact', 'tienda' => 'shop');
		$t = $this->lang === 'en' ? $en : $es;
		return $t[$key] ?? $key;
	}

	/** Slot de imagen desde un id de asset del manifiesto. */
	private function slot($assetId, $seedOff = 0)
	{
		$id = 0;
		if ($assetId && isset($this->assets[(string) $assetId])) {
			$id = (int) $this->assets[(string) $assetId]['id'];
		}
		return array('id' => $id, 'seed' => $this->seed + $seedOff * 7 + 3);
	}

	/** Primer asset utilizable de una lista de ids. */
	private function pick(array $list, $i = 0)
	{
		$list = array_values(array_filter($list, function ($x) {
			return $x && isset($this->assets[(string) $x]);
		}));
		return $list[$i] ?? null;
	}

	private function stock($k)
	{
		return (array) ($this->m['content']['stock'][$k] ?? array());
	}

	private function rubroLabel()
	{
		$r = (string) ($this->m['rubro'] ?? 'otro');
		$es = array('abogado' => 'Despacho jurídico', 'clinica' => 'Clínica y salud', 'taller' => 'Taller automotriz', 'ropa' => 'Moda y boutique', 'restaurante' => 'Restaurante', 'transporte' => 'Transporte y logística', 'contabilidad' => 'Contabilidad y finanzas', 'importaciones' => 'Importaciones', 'otro' => 'Servicios profesionales');
		$en = array('abogado' => 'Law firm', 'clinica' => 'Clinic & health', 'taller' => 'Auto workshop', 'ropa' => 'Fashion & boutique', 'restaurante' => 'Restaurant', 'transporte' => 'Transport & logistics', 'contabilidad' => 'Accounting & finance', 'importaciones' => 'Imports', 'otro' => 'Professional services');
		$t = $this->lang === 'en' ? $en : $es;
		return $t[$r] ?? $t['otro'];
	}

	private function btnWa($text)
	{
		return array('text' => $text, 'url' => $this->wa !== '' ? 'wa' : 'page:contacto');
	}

	/* ------------------------------------------------------------------ principal */

	private function go(array $args)
	{
		$this->m = SC_Builder::manifest();
		$this->lang = SC_Builder::lang();
		$this->assets = SC_Builder::assets_map();
		$this->wa = SC_Builder::digits(SC_Builder::biz('whatsapp', ''));
		$this->tel = trim((string) SC_Builder::biz('telefono', ''));
		$this->seed = (int) (crc32((string) ($this->m['site']['slug'] ?? $this->nombre())) % 9000) + 11;
		$this->tienda = ($this->m['plan'] === 'tienda') && class_exists('WooCommerce');

		$st = SC_Builder::state();
		if (empty($st['steps']['media']['done'])) {
			SC_Builder::note('pages se ejecutó sin media completo');
		}
		SC_Builder::force_urls();
		SC_Builder::apply_business();
		SC_Builder::apply_logo();
		// Los elementos flotantes propios (llamar / WhatsApp / correo) sustituyen a los del tema
		$logoId = (int) get_theme_mod('custom_logo', 0);
		if ($logoId > 0) {
			update_option('site_icon', $logoId);
		}
		set_theme_mod('sc_float_wa', '0');
		set_theme_mod('sc_mobile_bar', '0');
		$form = SC_Build_Forms::ensure($this->m, $this->lang);

		$this->prepare_services();
		$site = $this->build_site();
		update_option('sc_site', $site, false);
		if (function_exists('sc_site_reload')) {
			sc_site_reload();
		}
		update_option('blogdescription', mb_substr((string) $site['seo']['description'], 0, 150));

		// Páginas
		$plan = $this->page_plan();
		$known = SC_Builder::keymap('page:');
		foreach ($plan as $key => $def) {
			$parent = (!empty($def['parent']) && isset($this->ids[$def['parent']])) ? $this->ids[$def['parent']] : 0;
			$this->ids[$key] = SC_Builder::ensure_post('page:' . $key, array(
				'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $def['title'],
				'post_name' => $def['slug'], 'post_parent' => $parent, 'menu_order' => $def['order'],
				'post_content' => '<!-- Servicom: esta página se edita con «Editar mi web» -->', 'comment_status' => 'closed', 'ping_status' => 'closed',
			), array('_sc_luxe' => $key), $known);
		}
		foreach ($known as $k => $id) {
			$kk = substr($k, 5);
			if (!isset($plan[$kk])) {
				wp_delete_post($id, true);
			}
		}
		$this->build_menus($plan);
		update_option('show_on_front', 'page');
		update_option('page_on_front', $this->ids['home']);
		update_option('page_for_posts', 0);
		$pageIds = array();
		$svcMap = array();
		foreach ($this->ids as $k => $id) {
			if (strpos($k, 'svc:') === 0) {
				$svcMap[(int) substr($k, 4)] = $id;
			} else {
				$pageIds[$k] = $id;
			}
		}
		update_option('sc_page_ids', $pageIds);
		update_option('sc_service_pages', $svcMap);
		// Elementor ya no participa del render
		update_option('sc_engine', 'luxe');

		$s = SC_Builder::state();
		unset($s['pages']);
		$s['steps']['pages'] = array('done' => true, 'at' => time());
		SC_Builder::save_state($s);

		$urls = array();
		foreach ($this->ids as $k => $id) {
			$urls[$k] = get_permalink($id);
		}
		return array('data' => array(
			'page_ids' => $pageIds, 'service_pages' => $svcMap, 'urls' => $urls, 'form' => $form, 'engine' => 'luxe',
			'palette_source' => $site['design']['source'] ?? '', 'elementor' => 'no se usa',
		), 'more' => false);
	}

	/* ------------------------------------------------------------------ servicios */

	private function prepare_services()
	{
		$this->svcs = array();
		$list = $this->m['content']['servicios'] ?? array();
		$tx = $this->m['texts']['servicios'] ?? array();
		$stockSv = $this->stock('servicios');
		if (!is_array($list)) {
			return;
		}
		$rubro = (string) ($this->m['rubro'] ?? 'otro');
		foreach (array_values($list) as $i => $s) {
			$nombre = trim((string) ($s['nombre'] ?? ''));
			if ($nombre === '') {
				continue;
			}
			$t = is_array($tx[$i] ?? null) ? $tx[$i] : array();
			$desc = trim((string) ($t['descripcion'] ?? ''));
			if ($desc === '') {
				$desc = trim((string) ($s['descripcion'] ?? ''));
			}
			$res = trim((string) ($t['resumen'] ?? ''));
			if ($res === '') {
				$res = mb_strlen($desc) > 140 ? rtrim(mb_substr($desc, 0, 137), " ,;.") . '…' : $desc;
			}
			$icon = (string) ($t['icono'] ?? $s['icono'] ?? '');
			if (!function_exists('sc_icon_keys') || !in_array($icon, sc_icon_keys(), true)) {
				$icon = function_exists('sc_icon_for') ? sc_icon_for($nombre . ' ' . $desc, $rubro) : 'star';
			}
			$photo = $s['foto'] ?? null;
			if (!$photo || !isset($this->assets[(string) $photo])) {
				$photo = $stockSv[$i] ?? null;
			}
			$this->svcs[] = array(
				'id' => 's' . ($i + 1), 'nombre' => $nombre, 'resumen' => $res, 'descripcion' => $desc, 'icono' => $icon,
				'img' => $this->slot($photo, $i + 1), 'post' => 0, 'origen' => (string) ($s['origen'] ?? 'form'), 'on' => true,
				'_i' => $i,
			);
		}
	}

	/* ------------------------------------------------------------------ modelo del sitio */

	private function build_site()
	{
		$m = $this->m;
		$logoId = $m['business']['logo'] ?? null;
		$logoPath = '';
		if ($logoId && isset($this->assets[(string) $logoId])) {
			$f = get_attached_file((int) $this->assets[(string) $logoId]['id']);
			if ($f && is_file($f)) {
				$logoPath = $f;
			}
		}
		$design = sc_design_default((string) ($m['rubro'] ?? 'otro'), (int) $m['style'], $this->seed, $logoPath, (array) ($m['design']['colores'] ?? array()));
		$nombre = $this->nombre();

		$banner = (array) ($m['content']['banner'] ?? array());
		$stockHero = $this->stock('hero');
		$gal = (array) ($m['content']['galeria'] ?? array());
		$stockGal = $this->stock('galeria');
		$stockAbout = $this->stock('about');

		$heroMain = $this->pick($banner, 0) ?: $this->pick($stockHero, 0);
		$heroSide = $this->pick($banner, 1) ?: $this->pick($gal, 0) ?: $this->pick($stockHero, 1) ?: $this->pick($stockAbout, 0);
		$aboutImg = $this->pick($stockAbout, 0) ?: $this->pick($gal, 0) ?: $this->pick($banner, 1) ?: $this->pick($stockHero, 1);
		$quoteImg = $this->pick($banner, 2) ?: $this->pick($stockHero, 1) ?: $this->pick($stockGal, 0);
		$ctaImg = $this->pick($banner, 0) ?: $this->pick($stockHero, 0) ?: $this->pick($stockGal, 1);
		$headImg = $this->pick($stockHero, 1) ?: $this->pick($banner, 0) ?: $this->pick($stockGal, 2);

		// Galería: fotos del cliente primero y luego stock
		$galIds = array();
		foreach (array_merge($gal, $stockGal) as $id) {
			if ($id && isset($this->assets[(string) $id]) && !in_array($id, $galIds, true)) {
				$galIds[] = $id;
			}
		}
		$galIds = array_slice($galIds, 0, 12);

		$slides = array();
		$bn = array_values(array_filter($banner, function ($x) {
			return $x && isset($this->assets[(string) $x]);
		}));
		if (count($bn) > 1) {
			foreach (array_slice($bn, 0, 4) as $n => $id) {
				$slides[] = $this->slot($id, $n);
			}
		}

		$values = array();
		foreach ((array) ($m['texts']['valores'] ?? array()) as $v) {
			if (is_array($v) && trim((string) ($v['titulo'] ?? '')) !== '') {
				$ic = (string) ($v['icono'] ?? '');
				if (!function_exists('sc_icon_keys') || !in_array($ic, sc_icon_keys(), true)) {
					$ic = function_exists('sc_icon_for') ? sc_icon_for((string) $v['titulo'], (string) ($m['rubro'] ?? '')) : 'gem';
				}
				$values[] = array('icon' => $ic, 'title' => trim((string) $v['titulo']), 'text' => trim((string) ($v['texto'] ?? '')));
			}
		}
		if (!$values) {
			$values = $this->default_values();
		}
		$process = array();
		foreach ((array) ($m['texts']['proceso'] ?? array()) as $p) {
			if (is_array($p) && trim((string) ($p['titulo'] ?? '')) !== '') {
				$process[] = array('title' => trim((string) $p['titulo']), 'text' => trim((string) ($p['texto'] ?? '')));
			}
		}
		if (!$process) {
			$process = $this->default_process();
		}
		$faq = array();
		foreach ((array) ($m['texts']['faq'] ?? array()) as $q) {
			if (is_array($q) && trim((string) ($q['p'] ?? '')) !== '' && trim((string) ($q['r'] ?? '')) !== '') {
				$faq[] = array('q' => trim((string) $q['p']), 'a' => trim((string) $q['r']));
			}
		}
		if (!$faq) {
			$faq = $this->default_faq();
		}

		$about = $this->tx('nosotros_texto', trim((string) ($m['content']['quienes'] ?? '')));
		$points = array();
		foreach (array_slice($values, 0, 3) as $v) {
			$points[] = array('icon' => 'check', 'text' => $v['title']);
		}
		$strip = array();
		foreach (array_slice($this->svcs, 0, 8) as $s) {
			$strip[] = $s['nombre'];
		}
		if (count($strip) < 3) {
			$strip = array_map(function ($v) {
				return $v['title'];
			}, array_slice($values, 0, 6));
		}
		$cita = $this->tx('cita', trim((string) ($m['content']['frase'] ?? '')));

		$heroTitle = $this->tx('hero_titulo', sprintf($this->L('Bienvenidos a %s', 'Welcome to %s'), $nombre));
		$ctaTitle = $this->tx('cta_titulo', $this->L('Hablemos de su proyecto', "Let's talk about your project"));

		$home = array();
		$home[] = array('id' => 'inicio-hero', 'type' => 'hero', 'on' => true, 'data' => array(
			'eyebrow' => $this->tx('hero_eyebrow', $this->rubroLabel()),
			'title' => $heroTitle, 'sub' => $this->tx('hero_subtitulo'),
			'btn1' => $this->btnWa($this->tx('hero_boton', $this->L('Escríbanos', 'Contact us'))),
			'btn2' => array('text' => $this->svcs ? $this->L('Conozca nuestros servicios', 'Discover our services') : $this->L('Conózcanos', 'About us'), 'url' => $this->svcs ? '#servicios' : 'page:nosotros'),
			'img' => $this->slot($heroMain, 0), 'slides' => $slides, 'side' => $this->slot($heroSide, 5), 'badge_icon' => ($design['motif'] === 'abstract' ? 'crown' : (function_exists('sc_icon_for') ? sc_icon_for('', (string) ($m['rubro'] ?? '')) : 'crown')),
		));
		if (count($strip) >= 3) {
			$home[] = array('id' => 'franja', 'type' => 'strip', 'on' => true, 'data' => array('items' => $strip));
		}
		if ($about !== '') {
			$home[] = array('id' => 'nosotros', 'type' => 'about', 'on' => true, 'data' => array(
				'side' => 'left', 'eyebrow' => $this->L('Nosotros', 'About us'), 'title' => $this->tx('nosotros_titulo', $this->L('Quiénes somos', 'Who we are')),
				'lead' => $this->tx('nosotros_lead'), 'text' => $about, 'points' => $points, 'img' => $this->slot($aboutImg, 2),
				'seal_icon' => 'award', 'btn' => array('text' => $this->L('Conózcanos', 'More about us'), 'url' => 'page:nosotros'),
			));
		}
		if ($this->svcs) {
			$home[] = array('id' => 'servicios', 'type' => 'services', 'on' => true, 'data' => array(
				'eyebrow' => $this->L('Lo que hacemos', 'What we do'), 'title' => $this->tx('servicios_titulo', $this->L('Nuestros servicios', 'Our services')),
				'lead' => $this->tx('servicios_intro'), 'limit' => 6, 'more' => $this->L('Ver más', 'Learn more'),
				'btn' => array('text' => $this->L('Ver todos los servicios', 'View all services'), 'url' => 'page:servicios'),
			));
		}
		if ($this->tienda) {
			$home[] = array('id' => 'productos', 'type' => 'products', 'on' => true, 'data' => array(
				'eyebrow' => $this->L('Tienda', 'Store'), 'title' => $this->tx('tienda_titulo', $this->L('Productos destacados', 'Featured products')), 'lead' => $this->tx('tienda_intro'),
				'limit' => 8, 'btn' => array('text' => $this->L('Ver toda la tienda', 'Visit the store'), 'url' => 'page:tienda'),
			));
		}
		$home[] = array('id' => 'valores', 'type' => 'values', 'on' => true, 'data' => array(
			'eyebrow' => $this->L('Nuestro compromiso', 'Our promise'), 'title' => $this->tx('valores_titulo', $this->L('Por qué elegirnos', 'Why choose us')), 'lead' => '', 'items' => $values,
		));
		if ($cita !== '') {
			$home[] = array('id' => 'cita', 'type' => 'quote', 'on' => true, 'data' => array('text' => $cita, 'by' => $nombre, 'img' => $this->slot($quoteImg, 3)));
		}
		$home[] = array('id' => 'proceso', 'type' => 'process', 'on' => true, 'data' => array(
			'eyebrow' => $this->L('Así trabajamos', 'How we work'), 'title' => $this->tx('proceso_titulo', $this->L('Un proceso simple y claro', 'A simple, clear process')), 'lead' => '', 'items' => $process,
		));
		if (count($galIds) >= 3) {
			$items = array();
			foreach (array_slice($galIds, 0, 6) as $n => $id) {
				$s = $this->slot($id, $n);
				$items[] = $s;
			}
			$home[] = array('id' => 'galeria', 'type' => 'gallery', 'on' => true, 'data' => array(
				'eyebrow' => $this->L('Galería', 'Gallery'), 'title' => $this->tx('galeria_titulo', $this->L('Galería', 'Gallery')), 'lead' => '', 'items' => $items,
			));
		}
		$yt = trim((string) ($m['business']['youtube'] ?? ''));
		if ($yt !== '') {
			$home[] = array('id' => 'video', 'type' => 'video', 'on' => true, 'data' => array('eyebrow' => '', 'title' => $this->L('Conózcanos en video', 'Meet us on video'), 'lead' => '', 'url' => $yt));
		}
		$home[] = array('id' => 'preguntas', 'type' => 'faq', 'on' => true, 'data' => array(
			'eyebrow' => $this->L('Resolvemos sus dudas', 'Your questions'), 'title' => $this->tx('faq_titulo', $this->L('Preguntas frecuentes', 'Frequently asked questions')), 'lead' => '', 'items' => $faq,
		));
		$cta = array('id' => 'cta', 'type' => 'cta', 'on' => true, 'data' => array(
			'eyebrow' => $this->L('¿Listo para empezar?', 'Ready to start?'), 'title' => $ctaTitle, 'text' => $this->tx('cta_texto'),
			'btn1' => $this->btnWa($this->tx('cta_boton', $this->L('Contáctenos', 'Contact us'))),
			'btn2' => $this->tel !== '' ? array('text' => $this->L('Llámenos', 'Call us'), 'url' => 'tel') : array('text' => $this->L('Escriba aquí', 'Write here'), 'url' => 'page:contacto'),
			'img' => $this->slot($ctaImg, 4),
		));
		$home[] = $cta;
		$contact = array('id' => 'contacto', 'type' => 'contact', 'on' => true, 'data' => array(
			'eyebrow' => $this->L('Contacto', 'Contact'), 'title' => $this->tx('contacto_titulo', $this->L('Hablemos', "Let's talk")), 'lead' => $this->tx('contacto_intro'),
			'form_title' => $this->L('Envíenos un mensaje', 'Send us a message'),
		));
		$home[] = $contact;

		$pages = array('home' => array('sections' => $home));
		$ph = function ($title, $lead, $n) use ($headImg) {
			return array('id' => 'cabecera', 'type' => 'pagehero', 'on' => true, 'data' => array('eyebrow' => $this->nombre(), 'title' => $title, 'lead' => $lead, 'img' => $this->slot($headImg, $n)));
		};
		if ($about !== '') {
			$pages['nosotros'] = array('sections' => array(
				$ph($this->tx('nosotros_titulo', $this->L('Quiénes somos', 'Who we are')), $this->tx('nosotros_lead'), 11),
				array('id' => 'nosotros', 'type' => 'about', 'on' => true, 'data' => array(
					'side' => 'right', 'eyebrow' => $this->L('Nuestra historia', 'Our story'), 'title' => $this->tx('nosotros_titulo', $this->L('Quiénes somos', 'Who we are')),
					'lead' => $this->tx('nosotros_lead'), 'text' => $about, 'points' => $points, 'img' => $this->slot($aboutImg, 6), 'seal_icon' => 'award',
					'btn' => array('text' => $this->L('Contáctenos', 'Contact us'), 'url' => 'page:contacto'),
				)),
				array('id' => 'valores', 'type' => 'values', 'on' => true, 'data' => array('eyebrow' => $this->L('Nuestro compromiso', 'Our promise'), 'title' => $this->tx('valores_titulo', $this->L('Por qué elegirnos', 'Why choose us')), 'lead' => '', 'items' => $values)),
				array('id' => 'proceso', 'type' => 'process', 'on' => true, 'data' => array('eyebrow' => $this->L('Así trabajamos', 'How we work'), 'title' => $this->tx('proceso_titulo', $this->L('Un proceso simple y claro', 'A simple, clear process')), 'lead' => '', 'items' => $process)),
				$cta,
			));
		}
		if ($this->svcs) {
			$pages['servicios'] = array('sections' => array(
				$ph($this->tx('servicios_titulo', $this->L('Nuestros servicios', 'Our services')), $this->tx('servicios_intro'), 12),
				array('id' => 'servicios', 'type' => 'services', 'on' => true, 'data' => array('eyebrow' => '', 'title' => '', 'lead' => '', 'limit' => 0, 'more' => $this->L('Ver más', 'Learn more'))),
				array('id' => 'proceso', 'type' => 'process', 'on' => true, 'data' => array('eyebrow' => $this->L('Así trabajamos', 'How we work'), 'title' => $this->tx('proceso_titulo', $this->L('Un proceso simple y claro', 'A simple, clear process')), 'lead' => '', 'items' => $process)),
				$cta,
			));
		}
		if (count($galIds) >= 1) {
			$items = array();
			foreach ($galIds as $n => $id) {
				$items[] = $this->slot($id, $n);
			}
			$pages['galeria'] = array('sections' => array(
				$ph($this->tx('galeria_titulo', $this->L('Galería', 'Gallery')), '', 13),
				array('id' => 'galeria', 'type' => 'gallery', 'on' => true, 'data' => array('eyebrow' => '', 'title' => '', 'lead' => '', 'items' => $items)),
				$cta,
			));
		}
		if ($this->tienda) {
			$pages['tienda'] = array('sections' => array(
				$ph($this->tx('tienda_titulo', $this->L('Nuestra tienda', 'Our store')), $this->tx('tienda_intro'), 14),
				array('id' => 'productos', 'type' => 'products', 'on' => true, 'data' => array('eyebrow' => '', 'title' => '', 'lead' => '', 'limit' => 24, 'btn' => array('text' => '', 'url' => ''))),
			));
		}
		$pages['contacto'] = array('sections' => array(
			$ph($this->tx('contacto_titulo', $this->L('Contáctenos', 'Contact us')), $this->tx('contacto_intro'), 15),
			array('id' => 'contacto', 'type' => 'contact', 'on' => true, 'data' => array('eyebrow' => '', 'title' => '', 'lead' => '', 'form_title' => $this->L('Envíenos un mensaje', 'Send us a message'))),
		));

		$seo = $this->tx('seo_descripcion', mb_substr($this->tx('hero_subtitulo', $nombre), 0, 158));
		$services = array();
		foreach ($this->svcs as $s) {
			unset($s['_i']);
			$services[] = $s;
		}
		return array(
			'v' => 1, 'design' => $design, 'brand' => array('nombre' => $nombre, 'logo' => (int) get_theme_mod('custom_logo', 0), 'servicios_label' => $this->L('Servicios', 'Services')),
			'pages' => $pages, 'services' => $services, 'seo' => array('description' => $seo),
			'footer' => array('texto' => '', 'credit' => (string) ($m['business']['footer_credit'] ?? '')),
		);
	}

	private function default_values()
	{
		return array(
			array('icon' => 'handshake', 'title' => $this->L('Trato personalizado', 'Personal attention'), 'text' => $this->L('Escuchamos primero, para ofrecerle una solución a la medida de lo que usted necesita.', 'We listen first, to offer a solution tailored to what you need.')),
			array('icon' => 'shield', 'title' => $this->L('Confianza y seriedad', 'Trust and integrity'), 'text' => $this->L('Trabajamos con transparencia y responsabilidad en cada detalle.', 'We work with transparency and responsibility in every detail.')),
			array('icon' => 'gem', 'title' => $this->L('Calidad cuidada', 'Careful quality'), 'text' => $this->L('Cuidamos cada etapa para entregar resultados de los que pueda sentirse orgulloso.', 'We care for every stage to deliver results you can be proud of.')),
			array('icon' => 'clock', 'title' => $this->L('Respuesta ágil', 'Quick response'), 'text' => $this->L('Valoramos su tiempo y le respondemos con rapidez y claridad.', 'We value your time and respond promptly and clearly.')),
		);
	}

	private function default_process()
	{
		return array(
			array('title' => $this->L('Conversamos', 'We talk'), 'text' => $this->L('Nos cuenta lo que necesita y resolvemos sus dudas.', 'You tell us what you need and we answer your questions.')),
			array('title' => $this->L('Proponemos', 'We propose'), 'text' => $this->L('Le presentamos una propuesta clara, pensada para usted.', 'We present a clear proposal designed for you.')),
			array('title' => $this->L('Trabajamos', 'We work'), 'text' => $this->L('Nos encargamos con dedicación, manteniéndolo informado.', 'We take care of it with dedication, keeping you informed.')),
			array('title' => $this->L('Acompañamos', 'We support'), 'text' => $this->L('Seguimos a su lado después de entregar el resultado.', 'We stay by your side after delivering the result.')),
		);
	}

	private function default_faq()
	{
		return array(
			array('q' => $this->L('¿Cómo puedo contactarlos?', 'How can I contact you?'), 'a' => $this->L('Puede escribirnos por WhatsApp, llamarnos o enviarnos un mensaje desde el formulario de esta página. Le responderemos lo antes posible.', 'You can message us on WhatsApp, call us or use the form on this page. We will reply as soon as possible.')),
			array('q' => $this->L('¿Cuál es su horario de atención?', 'What are your opening hours?'), 'a' => $this->L('Encontrará nuestro horario en la sección de contacto. Fuera de horario, déjenos un mensaje y le responderemos al siguiente día hábil.', 'You will find our hours in the contact section. Outside hours, leave a message and we will reply on the next business day.')),
			array('q' => $this->L('¿Puedo pedir más información antes de decidir?', 'Can I ask for more information before deciding?'), 'a' => $this->L('Por supuesto. Escríbanos y con gusto resolvemos todas sus preguntas, sin compromiso.', 'Of course. Write to us and we will gladly answer all your questions, with no obligation.')),
		);
	}

	/* ------------------------------------------------------------------ páginas y menús */

	private function page_plan()
	{
		$p = array();
		$p['home'] = array('title' => SC_Builder::t('inicio'), 'slug' => $this->slug('home'), 'order' => 0);
		$site = get_option('sc_site', array());
		$pages = $site['pages'] ?? array();
		if (isset($pages['nosotros'])) {
			$p['nosotros'] = array('title' => $this->tx('nosotros_titulo', SC_Builder::t('nosotros')), 'slug' => $this->slug('nosotros'), 'order' => 2);
		}
		if (isset($pages['servicios'])) {
			$p['servicios'] = array('title' => $this->tx('servicios_titulo', SC_Builder::t('servicios')), 'slug' => $this->slug('servicios'), 'order' => 1);
			foreach ($this->svcs as $n => $s) {
				$slug = trim(preg_replace('/%[0-9a-f]{2}/i', '', sanitize_title(remove_accents(mb_substr($s['nombre'], 0, 70)))), '-');
				if ($slug === '') {
					$slug = 'servicio-' . ($s['_i'] + 1);
				}
				$p['svc:' . $n] = array('title' => $s['nombre'], 'slug' => $slug, 'order' => $n, 'parent' => 'servicios');
			}
		}
		if (isset($pages['galeria'])) {
			$p['galeria'] = array('title' => $this->tx('galeria_titulo', SC_Builder::t('galeria')), 'slug' => $this->slug('galeria'), 'order' => 3);
		}
		if (isset($pages['tienda'])) {
			$p['tienda'] = array('title' => SC_Builder::t('tienda'), 'slug' => $this->slug('tienda'), 'order' => 4);
		}
		$p['contacto'] = array('title' => $this->tx('contacto_titulo', SC_Builder::t('contacto')), 'slug' => $this->slug('contacto'), 'order' => 5);
		return $p;
	}

	private function ensure_menu($key, $name)
	{
		$map = SC_Builder::termkeymap($key, 'nav_menu');
		if (isset($map[$key]) && wp_get_nav_menu_object($map[$key])) {
			$id = $map[$key];
			wp_update_nav_menu_object($id, array('menu-name' => $name));
			return $id;
		}
		$obj = wp_get_nav_menu_object($name);
		if ($obj) {
			$id = (int) $obj->term_id;
		} else {
			$id = wp_create_nav_menu($name);
			if (is_wp_error($id)) {
				throw new SC_Builder_Fatal('menú: ' . $id->get_error_message(), true);
			}
		}
		update_term_meta($id, '_sc_key', $key);
		return (int) $id;
	}

	private function add_item($menu, $key, $title, $objId, $parent = 0)
	{
		$item = wp_update_nav_menu_item($menu, 0, array(
			'menu-item-title' => mb_substr($title, 0, 60), 'menu-item-object' => 'page', 'menu-item-object-id' => $objId,
			'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-parent-id' => $parent,
		));
		if (is_wp_error($item)) {
			throw new SC_Builder_Fatal('ítem de menú: ' . $item->get_error_message(), true);
		}
		update_post_meta($item, '_sc_key', $key);
		return (int) $item;
	}

	private function clear_menu($menu)
	{
		foreach ((array) wp_get_nav_menu_items($menu, array('post_status' => 'any')) as $it) {
			wp_delete_post($it->ID, true);
		}
	}

	private function build_menus(array $plan)
	{
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
		$main = $this->ensure_menu('menu:primary', SC_Builder::t('menu_principal'));
		$this->clear_menu($main);
		foreach (array('home', 'servicios', 'nosotros', 'galeria', 'tienda', 'contacto') as $k) {
			if (!isset($this->ids[$k])) {
				continue;
			}
			$label = ($k === 'home') ? SC_Builder::t('inicio') : (($k === 'servicios') ? SC_Builder::t('servicios') : $plan[$k]['title']);
			$parent = $this->add_item($main, 'item:primary:' . $k, $label, $this->ids[$k]);
			if ($k === 'servicios') {
				foreach ($this->svcs as $n => $s) {
					if (isset($this->ids['svc:' . $n])) {
						$this->add_item($main, 'item:primary:svc:' . $n, $s['nombre'], $this->ids['svc:' . $n], $parent);
					}
				}
			}
		}
		$foot = $this->ensure_menu('menu:footer', SC_Builder::t('menu_pie'));
		$this->clear_menu($foot);
		foreach (array('home', 'servicios', 'nosotros', 'galeria', 'tienda', 'contacto') as $k) {
			if (isset($this->ids[$k])) {
				$label = ($k === 'home') ? SC_Builder::t('inicio') : (($k === 'servicios') ? SC_Builder::t('servicios') : $plan[$k]['title']);
				$this->add_item($foot, 'item:footer:' . $k, $label, $this->ids[$k]);
			}
		}
		$locs = get_theme_mod('nav_menu_locations', array());
		if (!is_array($locs)) {
			$locs = array();
		}
		$locs['primary'] = $main;
		$locs['footer'] = $foot;
		set_theme_mod('nav_menu_locations', $locs);
	}
}
