<?php
/**
 * Servicom builder: paso "pages". Genera páginas con datos de Elementor
 * (_elementor_data), kit global, menús, página de inicio y formulario.
 * Idempotente y reanudable (progreso en sc_build_state.pages).
 */
if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/forms.php';

class SC_Build_Pages
{
	private $m;
	private $lang;
	private $tok;
	private $assets = array();
	private $ids = array();      // clave de página => post_id
	private $svcs = array();     // servicios normalizados
	private $wa = '';
	private $tienda = false;
	private $form_sc = '[sc_contact_form]';
	private $alt = false;

	public static function run(array $args)
	{
		$o = new self();
		return $o->go($args);
	}

	/* ------------------------------------------------------------------ */

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

	private function asset($id)
	{
		if (!$id) {
			return null;
		}
		return $this->assets[(string) $id] ?? null;
	}

	private function url($key)
	{
		return isset($this->ids[$key]) ? get_permalink($this->ids[$key]) : '';
	}

	private function woo_present()
	{
		return defined('WC_SIM') || class_exists('WooCommerce', false) || defined('WC_VERSION');
	}

	private function btn($text, $url, $kind = 'primary', $anim = false)
	{
		return SC_El::button($text, $url, 'sc-btn sc-btn--' . $kind, array('anim' => $anim));
	}

	private function attr($s)
	{
		return str_replace(array('"', '[', ']', "\n", "\r"), array("'", '(', ')', ' ', ' '), (string) $s);
	}

	/** Botón de WhatsApp (shortcode propio que lee el número del Personalizador). */
	private function wa_btn($label, $msg = '', $kind = 'primary')
	{
		if ($this->wa === '') {
			return null;
		}
		$sc = '[sc_wa_btn texto="' . $this->attr($label) . '"' . ($msg !== '' ? ' mensaje="' . $this->attr(SC_El::trim_words($msg, 300)) . '"' : '') . ']';
		return SC_El::shortcode($sc, 'sc-btn sc-btn--' . $kind . ' sc-btn--wa');
	}

	private function sec(array $children, $mods = '', array $o = array())
	{
		$cls = trim('sc-sec ' . $mods);
		return SC_El::container($children, $cls, array_merge(array('boxed' => true, 'dir' => 'column', 'gap' => 32), $o));
	}

	private function alt_mod()
	{
		$this->alt = !$this->alt;
		return $this->alt ? 'sc-sec--alt' : '';
	}

	private function head($title, $lead = '', $tag = 'h2')
	{
		$ch = array(SC_El::heading($title, $tag, 'sc-title'));
		if ($lead !== '') {
			$ch[] = SC_El::text(SC_El::prose($lead), 'sc-lead');
		}
		return SC_El::container($ch, 'sc-head', array('dir' => 'column', 'gap' => 12, 'anim' => true));
	}

	private function page_hero($title, $lead = '')
	{
		$ch = array(SC_El::heading($title, 'h1', 'sc-title sc-page-hero__title'));
		if ($lead !== '') {
			$ch[] = SC_El::text(SC_El::prose($lead), 'sc-lead sc-page-hero__lead');
		}
		return SC_El::container($ch, 'sc-page-hero', array('boxed' => true, 'dir' => 'column', 'gap' => 14));
	}

	/* ------------------------------------------------------------------ */

	private function go(array $args)
	{
		$this->m = SC_Builder::manifest();
		$this->lang = SC_Builder::lang();
		$this->tok = sc_style_tokens($this->m['style']);
		$this->assets = SC_Builder::assets_map();
		$this->wa = SC_Builder::digits(SC_Builder::biz('whatsapp', ''));
		$this->tienda = ($this->m['plan'] === 'tienda') && $this->woo_present();
		$this->prepare_services();

		$st = SC_Builder::state();
		if (empty($st['steps']['media']['done'])) {
			SC_Builder::note('pages se ejecutó sin media completo');
		}

		SC_Builder::force_urls();
		$this->setup_elementor();
		$kit = $this->ensure_kit();
		SC_Builder::apply_business();
		SC_Builder::apply_logo();
		$this->form_sc = SC_Build_Forms::ensure($this->m, $this->lang);

		// Fase A: carcasas de todas las páginas (ids estables para enlaces)
		$plan = $this->page_plan();
		$known = SC_Builder::keymap('page:');
		foreach ($plan as $key => $def) {
			$parent = 0;
			if (!empty($def['parent']) && isset($this->ids[$def['parent']])) {
				$parent = $this->ids[$def['parent']];
			}
			$this->ids[$key] = SC_Builder::ensure_post('page:' . $key, array(
				'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $def['title'],
				'post_name' => $def['slug'], 'post_parent' => $parent, 'menu_order' => $def['order'],
				'post_content' => '', 'comment_status' => 'closed', 'ping_status' => 'closed',
			), array(), $known);
		}
		// Páginas sobrantes de una ejecución previa con otro manifiesto: se retiran
		foreach ($known as $k => $id) {
			$kk = substr($k, 5);
			if (!isset($plan[$kk])) {
				wp_delete_post($id, true);
			}
		}

		// Fase B: contenido, con progreso
		$done = (array) ($st['pages']['done'] ?? array());
		$more = false;
		foreach ($plan as $key => $def) {
			if (in_array($key, $done, true)) {
				continue;
			}
			if (SC_Builder::out_of_time(3)) {
				$more = true;
				break;
			}
			$this->write_page($key, $def);
			$done[] = $key;
			SC_Builder::set_state('pages.done', $done);
		}
		if ($more) {
			return array('data' => array('written' => count($done), 'total' => count($plan)), 'more' => true);
		}

		// Fase C: menús, portada, opciones
		$this->build_menus($plan);
		update_option('show_on_front', 'page');
		update_option('page_on_front', $this->ids['home']);
		update_option('page_for_posts', 0);
		$pageIds = array();
		$svcMap = array();
		foreach ($this->ids as $k => $id) {
			if (str_starts_with($k, 'svc:')) {
				$svcMap[(int) substr($k, 4)] = $id;
			} else {
				$pageIds[$k] = $id;
			}
		}
		update_option('sc_page_ids', $pageIds);
		update_option('sc_service_pages', $svcMap);

		$s = SC_Builder::state();
		unset($s['pages']);
		$s['steps']['pages'] = array('done' => true, 'at' => time());
		$s['kit'] = $kit;
		SC_Builder::save_state($s);

		$urls = array();
		foreach ($this->ids as $k => $id) {
			$urls[$k] = get_permalink($id);
		}
		return array('data' => array(
			'page_ids' => $pageIds, 'service_pages' => $svcMap, 'kit' => $kit, 'urls' => $urls,
			'form' => $this->form_sc, 'elementor' => SC_Builder::elementor_info(),
		), 'more' => false);
	}

	/* ------------------------------------------------------------------ */

	private function prepare_services()
	{
		$this->svcs = array();
		$list = $this->m['content']['servicios'] ?? array();
		$tx = $this->m['texts']['servicios'] ?? array();
		if (!is_array($list)) {
			return;
		}
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
				$res = SC_El::trim_words($desc, 140);
			}
			$this->svcs[] = array(
				'i' => $i, 'nombre' => $nombre, 'desc' => $desc, 'resumen' => $res,
				'foto' => $this->asset($s['foto'] ?? null), 'icono' => (string) ($s['icono'] ?? ''),
			);
		}
	}

	private function gallery_assets()
	{
		$out = array();
		foreach ((array) ($this->m['content']['galeria'] ?? array()) as $id) {
			if ($a = $this->asset($id)) {
				$out[] = array('id' => $a['id'], 'url' => $a['url']);
			}
		}
		return $out;
	}

	private function banner_assets()
	{
		$out = array();
		foreach (array_slice((array) ($this->m['content']['banner'] ?? array()), 0, 3) as $id) {
			if ($a = $this->asset($id)) {
				$out[] = array('id' => $a['id'], 'url' => $a['url']);
			}
		}
		return $out;
	}

	private function about_text()
	{
		$t = $this->tx('nosotros_texto');
		if ($t === '') {
			$t = trim((string) ($this->m['content']['quienes'] ?? ''));
		}
		return $t;
	}

	private function about_photo()
	{
		$g = (array) ($this->m['content']['galeria'] ?? array());
		foreach ($g as $id) {
			if ($a = $this->asset($id)) {
				return $a;
			}
		}
		$b = (array) ($this->m['content']['banner'] ?? array());
		if (count($b) > 1 && ($a = $this->asset($b[1]))) {
			return $a;
		}
		return null;
	}

	private function page_plan()
	{
		$p = array();
		$p['home'] = array('title' => SC_Builder::t('inicio'), 'slug' => $this->slug('home'), 'order' => 0);
		if ($this->about_text() !== '') {
			$p['nosotros'] = array('title' => $this->tx('nosotros_titulo', SC_Builder::t('nosotros')), 'slug' => $this->slug('nosotros'), 'order' => 2);
		}
		if ($this->svcs) {
			$p['servicios'] = array('title' => $this->tx('servicios_titulo', SC_Builder::t('servicios')), 'slug' => $this->slug('servicios'), 'order' => 1);
			foreach ($this->svcs as $n => $s) {
				$slug = trim(preg_replace('/%[0-9a-f]{2}/i', '', sanitize_title(remove_accents(mb_substr($s['nombre'], 0, 70)))), '-');
				if ($slug === '') {
					$slug = 'servicio-' . ($s['i'] + 1);
				}
				$p['svc:' . $s['i']] = array('title' => $s['nombre'], 'slug' => $slug, 'order' => $n, 'parent' => 'servicios');
			}
		}
		if ($this->gallery_assets()) {
			$p['galeria'] = array('title' => $this->tx('galeria_titulo', SC_Builder::t('galeria')), 'slug' => $this->slug('galeria'), 'order' => 3);
		}
		if ($this->tienda) {
			$p['tienda'] = array('title' => SC_Builder::t('tienda'), 'slug' => $this->slug('tienda'), 'order' => 4);
		}
		$p['contacto'] = array('title' => $this->tx('contacto_titulo', SC_Builder::t('contacto')), 'slug' => $this->slug('contacto'), 'order' => 5);
		return $p;
	}

	/* ------------------------------------------------------------------ */

	private function write_page($key, array $def)
	{
		$id = $this->ids[$key];
		SC_El::begin('page:' . $key . ':' . $this->m['style']);
		$this->alt = false;
		if ($key === 'tienda') {
			$content = '';
			$intro = $this->tx('tienda_intro');
			if ($intro !== '') {
				$content .= '<p class="sc-lead">' . SC_El::esc($intro) . '</p>' . "\n";
			}
			$content .= '[sc_product_search]';
			wp_update_post(wp_slash(array('ID' => $id, 'post_content' => $content)));
			delete_post_meta($id, '_elementor_data');
			delete_post_meta($id, '_elementor_edit_mode');
			return;
		}
		if ($key === 'home') {
			$els = $this->build_home();
		} elseif ($key === 'nosotros') {
			$els = $this->build_nosotros();
		} elseif ($key === 'servicios') {
			$els = $this->build_servicios();
		} elseif (str_starts_with($key, 'svc:')) {
			$els = $this->build_service((int) substr($key, 4));
		} elseif ($key === 'galeria') {
			$els = $this->build_galeria();
		} else {
			$els = $this->build_contacto();
		}
		$els = array_values(array_filter($els));
		$errs = SC_El::validate($els);
		if ($errs) {
			throw new SC_Builder_Fatal('datos de Elementor inválidos en ' . $key . ': ' . $errs[0]);
		}
		update_post_meta($id, '_elementor_data', wp_slash(SC_El::encode($els)));
		update_post_meta($id, '_elementor_edit_mode', 'builder');
		update_post_meta($id, '_elementor_template_type', 'wp-page');
		update_post_meta($id, '_elementor_version', SC_Builder::ELEMENTOR_VER);
		update_post_meta($id, '_elementor_page_settings', array('hide_title' => 'yes'));
		update_post_meta($id, '_wp_page_template', 'default');
		update_post_meta($id, '_sc_hide_title', '1');
	}

	/* ------------------------------------------------------------------ *
	 * Piezas reutilizables
	 * ------------------------------------------------------------------ */

	private function card(array $s)
	{
		$url = $s['url'] ?? '';
		$ch = array();
		$cls = 'sc-card';
		if ($s['foto']) {
			$im = SC_El::image($s['foto']['id'], $s['foto']['url'], $s['foto']['alt'] ?: $s['nombre'], 'sc-card__media', 'medium_large');
			if ($url !== '') {
				$im['settings']['link_to'] = 'custom';
				$im['settings']['link'] = SC_El::link($url);
			}
			$ch[] = $im;
		} else {
			$cls .= ' sc-card--icon';
			$ch[] = SC_El::container(array(SC_El::icon_widget($s['icono'] ?: 'fas fa-check', 'sc-card__icon')), 'sc-card__media', array('dir' => 'column', 'align' => 'center', 'justify' => 'center'));
		}
		$ch[] = SC_El::heading($s['nombre'], 'h3', 'sc-card__title', $url !== '' ? array('url' => $url) : array());
		if (($s['resumen'] ?? '') !== '') {
			$ch[] = SC_El::text(SC_El::prose($s['resumen']), 'sc-card__text');
		}
		if ($url !== '') {
			$ch[] = $this->btn(SC_Builder::t('ver_mas'), $url, 'ghost');
		}
		return SC_El::container($ch, $cls, array('dir' => 'column', 'gap' => 12, 'anim' => true));
	}

	private function services_grid($limit = 0)
	{
		$cards = array();
		foreach ($this->svcs as $n => $s) {
			if ($limit && $n >= $limit) {
				break;
			}
			$s['url'] = $this->url('svc:' . $s['i']);
			$cards[] = $this->card($s);
		}
		return SC_El::container($cards, 'sc-grid', array('dir' => 'row', 'wrap' => true, 'gap' => 24));
	}

	private function cta_section()
	{
		$ch = array(
			SC_El::heading($this->tx('cta_titulo', SC_Builder::t('cta_titulo')), 'h2', 'sc-title'),
		);
		$txt = $this->tx('cta_texto');
		if ($txt !== '') {
			$ch[] = SC_El::text(SC_El::prose($txt), 'sc-lead');
		}
		$label = $this->tx('cta_boton', $this->wa !== '' ? SC_Builder::t('escribanos') : SC_Builder::t('cta_boton'));
		$b = $this->wa_btn($label, SC_Builder::t('wa_consulta'));
		if (!$b) {
			$b = $this->btn($label, $this->url('contacto'), 'primary');
		}
		$ch[] = $b;
		return SC_El::container($ch, 'sc-sec sc-sec--dark sc-cta', array('boxed' => true, 'dir' => 'row', 'gap' => 16, 'wrap' => true, 'justify' => 'center', 'align' => 'center', 'anim' => true));
	}

	/** Filas de contacto [icono, etiqueta, html con shortcodes] solo con datos presentes. */
	private function contact_rows()
	{
		$b = $this->m['business'];
		$rows = array();
		if (trim((string) ($b['telefono'] ?? '')) !== '') {
			$rows[] = array('fas fa-phone', SC_Builder::t('telefono'), '[sc_telefono_link]');
		}
		if ($this->wa !== '') {
			$rows[] = array('fab fa-whatsapp', 'WhatsApp', '[sc_wa_btn tipo="enlace"]');
		}
		if (trim((string) ($b['correo_contacto'] ?? '')) !== '') {
			$rows[] = array('fas fa-envelope', SC_Builder::t('correo'), '[sc_correo]');
		}
		if (trim((string) ($b['direccion'] ?? '')) !== '') {
			$rows[] = array('fas fa-location-dot', SC_Builder::t('direccion'), '[sc_direccion]');
		}
		if (trim((string) ($b['horario'] ?? '')) !== '') {
			$rows[] = array('fas fa-clock', SC_Builder::t('horario'), '[sc_horario]');
		}
		return $rows;
	}

	private function contact_row_el(array $r)
	{
		return SC_El::container(array(
			SC_El::icon_widget($r[0], 'sc-contact__icon', 'default', 20),
			SC_El::text('<strong>' . esc_html($r[1]) . '</strong><br>' . $r[2], 'sc-contact__text'),
		), 'sc-contact__row', array('dir' => 'row', 'gap' => 14, 'align' => 'center'));
	}

	private function has_social()
	{
		foreach ((array) ($this->m['business']['redes'] ?? array()) as $v) {
			if (trim((string) $v) !== '') {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------------ *
	 * Páginas
	 * ------------------------------------------------------------------ */

	private function build_home()
	{
		$els = array();
		$els[] = $this->hero();

		if ($this->svcs) {
			$more = count($this->svcs) > 6 ? array($this->btn(SC_Builder::t('todos_servicios'), $this->url('servicios'), 'ghost')) : array();
			$ch = array(
				$this->head($this->tx('servicios_titulo', SC_Builder::t('t_servicios')), $this->tx('servicios_intro')),
				$this->services_grid(6),
			);
			if ($more) {
				$ch[] = SC_El::container($more, 'sc-more', array('dir' => 'row', 'justify' => 'center'));
			}
			$els[] = $this->sec($ch, $this->alt_mod());
		}

		if ($this->tienda) {
			$ch = array(
				$this->head($this->tx('tienda_titulo', SC_Builder::t('t_destacados')), $this->tx('tienda_intro')),
				SC_El::shortcode('[products limit="8" columns="4" orderby="id" order="ASC"]', 'sc-products'),
				SC_El::container(array($this->btn(SC_Builder::t('ver_tienda'), $this->url('tienda'), 'primary')), 'sc-more', array('dir' => 'row', 'justify' => 'center')),
			);
			$els[] = $this->sec($ch, $this->alt_mod());
		}

		$about = $this->about_text();
		if ($about !== '') {
			$body = array(
				SC_El::heading($this->tx('nosotros_titulo', SC_Builder::t('t_nosotros')), 'h2', 'sc-title'),
				SC_El::text(SC_El::prose(SC_El::trim_words($about, 560)), 'sc-prose'),
			);
			if (isset($this->ids['nosotros'])) {
				$body[] = $this->btn(SC_Builder::t('conocer_mas'), $this->url('nosotros'), 'ghost');
			}
			$row = array();
			$ph = $this->about_photo();
			if ($ph) {
				$row[] = SC_El::image($ph['id'], $ph['url'], $ph['alt'], 'sc-about__media', 'large', true);
			}
			$row[] = SC_El::container($body, 'sc-about__body', array('dir' => 'column', 'gap' => 16, 'anim' => true));
			$els[] = $this->sec(array(SC_El::container($row, 'sc-about' . ($ph ? '' : ' sc-about--solo'), array('dir' => 'row', 'gap' => 40, 'align' => 'center', 'tablet_dir' => 'column', 'mobile_dir' => 'column'))), $this->alt_mod());
		}

		$gal = $this->gallery_assets();
		if ($gal) {
			$ch = array(
				$this->head($this->tx('galeria_titulo', SC_Builder::t('t_galeria'))),
				SC_El::container(array(SC_El::gallery(array_slice($gal, 0, 6), 3)), 'sc-gallery', array('dir' => 'column')),
			);
			if (isset($this->ids['galeria']) && count($gal) > 6) {
				$ch[] = SC_El::container(array($this->btn(SC_Builder::t('ver_galeria'), $this->url('galeria'), 'ghost')), 'sc-more', array('dir' => 'row', 'justify' => 'center'));
			}
			$els[] = $this->sec($ch, $this->alt_mod());
		}

		$yt = trim((string) ($this->m['business']['youtube'] ?? ''));
		if ($yt !== '' && preg_match('~^https?://(www\.|m\.)?(youtube\.com|youtu\.be|youtube-nocookie\.com)/~i', $yt)) {
			$els[] = $this->sec(array(SC_El::container(array(SC_El::video($yt)), 'sc-video', array('dir' => 'column', 'anim' => true))), $this->alt_mod());
		}

		$els[] = $this->cta_section();

		$rows = $this->contact_rows();
		if ($rows) {
			$items = array();
			foreach ($rows as $r) {
				$items[] = $this->contact_row_el($r);
			}
			$els[] = $this->sec(array(SC_El::container($items, 'sc-contact__info', array('dir' => 'row', 'wrap' => true, 'gap' => 28, 'justify' => 'space-between'))), 'sc-contact-strip ' . $this->alt_mod());
		}
		return $els;
	}

	private function hero()
	{
		$banner = $this->banner_assets();
		$cls = 'sc-hero';
		$o = array('dir' => 'column', 'justify' => 'center', 'align' => 'center', 'min_h' => array('vh', 78));
		if ($banner) {
			$o['bg'] = array(
				'background_background' => 'slideshow',
				'background_slideshow_gallery' => $banner,
				'background_slideshow_loop' => count($banner) > 1 ? 'yes' : '',
				'background_slideshow_slide_duration' => 5000,
				'background_slideshow_slide_transition' => 'fade',
				'background_slideshow_transition' => 'fade',
				'background_slideshow_transition_duration' => 800,
				'background_slideshow_ken_burns' => 'yes',
				'background_slideshow_background_size' => 'cover',
				'background_overlay_background' => 'classic',
				'background_overlay_color' => '#000000',
				'background_overlay_opacity' => array('unit' => 'px', 'size' => 0.55, 'sizes' => array()),
			);
		} else {
			$cls .= ' sc-hero--plain';
			$o['bg'] = array(
				'background_background' => 'gradient',
				'background_color' => $this->tok['primary'],
				'background_color_stop' => array('unit' => '%', 'size' => 0, 'sizes' => array()),
				'background_color_b' => $this->tok['secondary'],
				'background_color_b_stop' => array('unit' => '%', 'size' => 100, 'sizes' => array()),
				'background_gradient_type' => 'linear',
				'background_gradient_angle' => array('unit' => 'deg', 'size' => 135, 'sizes' => array()),
			);
		}
		$inner = array(SC_El::heading($this->tx('hero_titulo', SC_Builder::t('hero_titulo', $this->nombre())), 'h1', 'sc-hero__title'));
		$sub = $this->tx('hero_subtitulo');
		if ($sub !== '') {
			$inner[] = SC_El::text(SC_El::prose($sub), 'sc-hero__sub');
		}
		$acts = array();
		if ($this->wa !== '') {
			$acts[] = $this->wa_btn($this->tx('hero_boton', SC_Builder::t('whatsapp')), SC_Builder::t('wa_consulta'), 'primary');
		}
		if ($this->svcs) {
			$acts[] = $this->btn(SC_Builder::t('ver_servicios'), $this->url('servicios'), $this->wa !== '' ? 'ghost' : 'primary');
		} elseif ($this->tienda) {
			$acts[] = $this->btn(SC_Builder::t('ver_tienda'), $this->url('tienda'), $this->wa !== '' ? 'ghost' : 'primary');
		} elseif ($this->wa === '') {
			$acts[] = $this->btn(SC_Builder::t('contactenos'), $this->url('contacto'), 'primary');
		}
		$inner[] = SC_El::container($acts, 'sc-hero__actions', array('dir' => 'row', 'gap' => 14, 'wrap' => true));
		return SC_El::container(array(
			SC_El::container($inner, 'sc-hero__inner', array('boxed' => true, 'dir' => 'column', 'gap' => 20, 'anim' => true), true),
		), $cls, $o);
	}

	private function build_nosotros()
	{
		$els = array($this->page_hero($this->tx('nosotros_titulo', SC_Builder::t('nosotros'))));
		$row = array();
		$ph = $this->about_photo();
		if ($ph) {
			$row[] = SC_El::image($ph['id'], $ph['url'], $ph['alt'], 'sc-about__media', 'large', true);
		}
		$row[] = SC_El::container(array(SC_El::text(SC_El::prose($this->about_text()), 'sc-prose')), 'sc-about__body', array('dir' => 'column', 'gap' => 16, 'anim' => true));
		$els[] = $this->sec(array(SC_El::container($row, 'sc-about' . ($ph ? '' : ' sc-about--solo'), array('dir' => 'row', 'gap' => 40, 'align' => 'flex-start', 'tablet_dir' => 'column', 'mobile_dir' => 'column'))));
		$els[] = $this->cta_section();
		return $els;
	}

	private function build_servicios()
	{
		$els = array($this->page_hero($this->tx('servicios_titulo', SC_Builder::t('servicios')), $this->tx('servicios_intro')));
		$els[] = $this->sec(array($this->services_grid(0)));
		$els[] = $this->cta_section();
		return $els;
	}

	private function build_service($idx)
	{
		$s = null;
		foreach ($this->svcs as $x) {
			if ($x['i'] === $idx) {
				$s = $x;
			}
		}
		if (!$s) {
			return array();
		}
		$els = array($this->page_hero($s['nombre']));
		if ($s['foto']) {
			$media = SC_El::image($s['foto']['id'], $s['foto']['url'], $s['foto']['alt'] ?: $s['nombre'], 'sc-service__media', 'large', true);
		} else {
			$media = SC_El::container(array(SC_El::icon_widget($s['icono'] ?: 'fas fa-check', 'sc-service__icon')), 'sc-service__media sc-card--icon', array('dir' => 'column', 'align' => 'center', 'justify' => 'center', 'anim' => true));
		}
		$body = array();
		if ($s['desc'] !== '') {
			$body[] = SC_El::text(SC_El::prose($s['desc']), 'sc-prose');
		} elseif ($s['resumen'] !== '') {
			$body[] = SC_El::text(SC_El::prose($s['resumen']), 'sc-prose');
		}
		$acts = array();
		if ($b = $this->wa_btn(SC_Builder::t('escribanos'), SC_Builder::t('wa_servicio', $s['nombre']), 'primary')) {
			$acts[] = $b;
		} else {
			$acts[] = $this->btn(SC_Builder::t('contactenos'), $this->url('contacto'), 'primary');
		}
		$acts[] = $this->btn(SC_Builder::t('volver'), $this->url('servicios'), 'ghost');
		$body[] = SC_El::container($acts, 'sc-service__actions', array('dir' => 'row', 'gap' => 14, 'wrap' => true));
		$els[] = $this->sec(array(SC_El::container(array(
			$media,
			SC_El::container($body, 'sc-service__body', array('dir' => 'column', 'gap' => 20, 'anim' => true)),
		), 'sc-service', array('dir' => 'row', 'gap' => 40, 'align' => 'flex-start', 'tablet_dir' => 'column', 'mobile_dir' => 'column'))));
		return $els;
	}

	private function build_galeria()
	{
		$els = array($this->page_hero($this->tx('galeria_titulo', SC_Builder::t('galeria'))));
		$els[] = $this->sec(array(SC_El::container(array(SC_El::gallery($this->gallery_assets(), 3)), 'sc-gallery', array('dir' => 'column'))));
		return $els;
	}

	private function build_contacto()
	{
		$els = array($this->page_hero($this->tx('contacto_titulo', SC_Builder::t('contacto')), $this->tx('contacto_intro')));
		$info = array();
		$rows = $this->contact_rows();
		if ($rows || $this->has_social()) {
			$info[] = SC_El::heading(SC_Builder::t('datos_contacto'), 'h2', 'sc-title sc-contact__title');
			foreach ($rows as $r) {
				$info[] = $this->contact_row_el($r);
			}
			if ($this->has_social()) {
				$info[] = SC_El::text('<strong>' . esc_html(SC_Builder::t('siguenos')) . '</strong><br>[sc_redes]', 'sc-contact__social');
			}
		}
		$form = array(
			SC_El::heading(SC_Builder::t('enviar_mensaje'), 'h2', 'sc-title sc-contact__title'),
			SC_El::shortcode($this->form_sc, 'sc-form'),
		);
		$cols = array();
		if ($info) {
			$cols[] = SC_El::container($info, 'sc-contact__info', array('dir' => 'column', 'gap' => 18, 'anim' => true));
		}
		$cols[] = SC_El::container($form, 'sc-contact__form', array('dir' => 'column', 'gap' => 18, 'anim' => true));
		$els[] = SC_El::container($cols, 'sc-sec sc-contact', array('boxed' => true, 'dir' => 'row', 'gap' => 40, 'align' => 'flex-start', 'tablet_dir' => 'column', 'mobile_dir' => 'column'));

		$b = $this->m['business'];
		$addr = trim((string) ($b['direccion'] ?? ''));
		$maplink = trim((string) ($b['mapa_url'] ?? ''));
		if ($addr !== '') {
			$mapch = array(SC_El::maps($addr, 380));
			if ($maplink !== '') {
				$mapch[] = SC_El::container(array(SC_El::shortcode('[sc_mapa_link texto="' . $this->attr(SC_Builder::t('ver_mapa')) . '"]', 'sc-btn sc-btn--ghost')), 'sc-more', array('dir' => 'row', 'justify' => 'center'));
			}
			$els[] = $this->sec(array(SC_El::container($mapch, 'sc-map', array('dir' => 'column', 'gap' => 16))), 'sc-sec--map');
		} elseif ($maplink !== '') {
			$els[] = $this->sec(array(SC_El::container(array(SC_El::shortcode('[sc_mapa_link texto="' . $this->attr(SC_Builder::t('ver_mapa')) . '"]', 'sc-btn sc-btn--primary')), 'sc-map', array('dir' => 'row', 'justify' => 'center'))), 'sc-sec--map');
		}
		return $els;
	}

	/* ------------------------------------------------------------------ *
	 * Menús
	 * ------------------------------------------------------------------ */

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
		$title = SC_El::trim_words($title, 60);
		$item = wp_update_nav_menu_item($menu, 0, array(
			'menu-item-title' => $title,
			'menu-item-object' => 'page',
			'menu-item-object-id' => $objId,
			'menu-item-type' => 'post_type',
			'menu-item-status' => 'publish',
			'menu-item-parent-id' => $parent,
		));
		if (is_wp_error($item)) {
			throw new SC_Builder_Fatal('ítem de menú: ' . $item->get_error_message(), true);
		}
		update_post_meta($item, '_sc_key', $key);
		return (int) $item;
	}

	private function clear_menu($menu)
	{
		$items = wp_get_nav_menu_items($menu, array('post_status' => 'any'));
		foreach ((array) $items as $it) {
			wp_delete_post($it->ID, true);
		}
	}

	private function build_menus(array $plan)
	{
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
		$main = $this->ensure_menu('menu:primary', SC_Builder::t('menu_principal'));
		$this->clear_menu($main);
		$order = array('home', 'servicios', 'nosotros', 'galeria', 'tienda', 'contacto');
		foreach ($order as $k) {
			if (!isset($this->ids[$k])) {
				continue;
			}
			$label = ($k === 'home') ? SC_Builder::t('inicio') : $plan[$k]['title'];
			$label = ($k === 'servicios') ? SC_Builder::t('servicios') : $label;
			$parent = $this->add_item($main, 'item:primary:' . $k, $label, $this->ids[$k]);
			if ($k === 'servicios') {
				foreach ($this->svcs as $s) {
					$kk = 'svc:' . $s['i'];
					if (isset($this->ids[$kk])) {
						$this->add_item($main, 'item:primary:' . $kk, $s['nombre'], $this->ids[$kk], $parent);
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

	/* ------------------------------------------------------------------ *
	 * Elementor: opciones y kit global
	 * ------------------------------------------------------------------ */

	private function setup_elementor()
	{
		$o = array(
			'elementor_disable_color_schemes' => 'yes',
			'elementor_disable_typography_schemes' => 'yes',
			'elementor_google_font' => '0',            // sin Google Fonts por CDN: las fuentes las sirve el tema
			'elementor_load_fa4_shim' => '',
			'elementor_allow_tracking' => 'no',
			'elementor_tracker_notice' => '1',
			'elementor_default_generic_fonts' => 'Sans-serif',
			'elementor_global_image_lightbox' => 'yes',
			'elementor_experiment-container' => 'active',
			'elementor_unfiltered_files_upload' => '0',
		);
		foreach ($o as $k => $v) {
			update_option($k, $v);
		}
		update_option('elementor_cpt_support', array('page'));
	}

	private function ensure_kit()
	{
		$t = $this->tok;
		$h = $t['font_head'];
		$b = $t['font_body'];
		$cid = function ($name) {
			return substr(md5('sc-kit-' . $name), 0, 7);
		};
		$typo = function ($id, $title, $fam, $w, $size = null) {
			$r = array('_id' => $id, 'title' => $title, 'typography_typography' => 'custom', 'typography_font_family' => $fam, 'typography_font_weight' => (string) $w);
			if ($size) {
				$r['typography_font_size'] = array('unit' => 'px', 'size' => $size, 'sizes' => array());
			}
			return $r;
		};
		$settings = array(
			'system_colors' => array(
				array('_id' => 'primary', 'title' => 'Primary', 'color' => $t['primary']),
				array('_id' => 'secondary', 'title' => 'Secondary', 'color' => $t['secondary']),
				array('_id' => 'text', 'title' => 'Text', 'color' => $t['text']),
				array('_id' => 'accent', 'title' => 'Accent', 'color' => $t['accent']),
			),
			'custom_colors' => array(
				array('_id' => $cid('bg'), 'title' => 'Fondo', 'color' => $t['bg']),
				array('_id' => $cid('bg_alt'), 'title' => 'Fondo alterno', 'color' => $t['bg_alt']),
				array('_id' => $cid('muted'), 'title' => 'Texto suave', 'color' => $t['muted']),
				array('_id' => $cid('border'), 'title' => 'Borde', 'color' => $t['border']),
				array('_id' => $cid('ink'), 'title' => 'Texto sobre primario', 'color' => $t['primary_ink']),
			),
			'system_typography' => array(
				$typo('primary', 'Primary', $h, 700),
				$typo('secondary', 'Secondary', $h, 500),
				$typo('text', 'Text', $b, 400),
				$typo('accent', 'Accent', $b, 700),
			),
			'custom_typography' => array(),
			'default_generic_fonts' => 'Sans-serif',
			'container_width' => array('unit' => 'px', 'size' => 1200, 'sizes' => array()),
			'space_between_widgets' => array('column' => '0', 'row' => '0', 'isLinked' => true, 'unit' => 'px', 'size' => 0),
			'container_padding' => SC_El::dim('0'),
			'page_title_selector' => 'h1.entry-title',
			'body_color' => $t['text'],
			'body_typography_typography' => 'custom',
			'body_typography_font_family' => $b,
			'body_typography_font_weight' => '400',
			'link_normal_color' => $t['primary'],
			'link_hover_color' => $t['secondary'],
			'button_text_color' => $t['primary_ink'],
			'button_background_color' => $t['primary'],
			'button_hover_background_color' => $t['secondary'],
			'button_typography_typography' => 'custom',
			'button_typography_font_family' => $b,
			'button_typography_font_weight' => '700',
		);
		foreach (array('h1', 'h2', 'h3', 'h4', 'h5', 'h6') as $hn) {
			$settings[$hn . '_color'] = $t['text'];
			$settings[$hn . '_typography_typography'] = 'custom';
			$settings[$hn . '_typography_font_family'] = $h;
			$settings[$hn . '_typography_font_weight'] = '700';
		}
		$known = SC_Builder::keymap('kit:');
		$id = SC_Builder::ensure_post('kit:main', array(
			'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Servicom Kit', 'post_name' => 'servicom-kit',
			'post_content' => '',
		), array(
			'_elementor_edit_mode' => 'builder',
			'_elementor_template_type' => 'kit',
			'_elementor_version' => SC_Builder::ELEMENTOR_VER,
			'_wp_page_template' => 'default',
			'_elementor_page_settings' => $settings,
			'_elementor_data' => '[]',
		), $known);
		update_option('elementor_active_kit', $id);
		return $id;
	}
}
