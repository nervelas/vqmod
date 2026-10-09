<?php
/**
 * ############################################################################
 *  SOLO PARA PRUEBAS - NO EMPAQUETAR - NO ES ELEMENTOR
 * ----------------------------------------------------------------------------
 *  Simulador mínimo de Elementor para el sandbox (sin acceso a wordpress.org).
 *  JAMÁS debe copiarse al ZIP final ni a un sitio real: vive en wp-pack/tests-only.
 *
 *  Qué hace (mu-plugin de pruebas):
 *   - Define ELEMENTOR_SIM y la clase \Elementor\Plugin mínima (files_manager->clear_cache()).
 *   - Registra el CPT elementor_library.
 *   - Renderiza _elementor_data a HTML con la MISMA estructura/clases que usa Elementor
 *     real (.e-con.e-flex, .e-con-inner, .elementor-widget-heading .elementor-heading-title,
 *     .elementor-button, .elementor-icon-box-wrapper, .elementor-background-slideshow, ...).
 *   - Genera /uploads/elementor/css/post-N.css (reglas con variables --flex-direction, --gap,
 *     etc., como el real) y el CSS del kit global.
 *   - Aproxima el CSS base del frontend de Elementor (tomado de memoria; NO es idéntico).
 *   - Iconos Font Awesome: no hay FA en el sandbox; se pinta un marcador genérico.
 *  Lo que NO simula: editor, JS real de Elementor (se sustituye por un mini-script
 *  para animaciones y slideshow), tipografías globales completas, responsive exacto.
 * ############################################################################
 */
namespace Elementor\Core\Files\CSS {
	class Post
	{
		private $id;
		public static function create($id)
		{
			$o = new self();
			$o->id = (int) $id;
			return $o;
		}
		public function update()
		{
			\SC_Elementor_Sim::write_css($this->id);
		}
	}
}

namespace Elementor {
	class FilesManager
	{
		public function clear_cache()
		{
			\SC_Elementor_Sim::clear_css();
			\SC_Elementor_Sim::regenerate_all();
		}
	}
	class Plugin
	{
		public static $instance;
		public $files_manager;
		public function __construct()
		{
			$this->files_manager = new FilesManager();
		}
	}
	Plugin::$instance = new Plugin();
}

namespace {

if (!defined('ABSPATH')) {
	exit;
}
if (!defined('ELEMENTOR_SIM')) {
	define('ELEMENTOR_SIM', true);
}
if (!defined('ELEMENTOR_VERSION')) {
	define('ELEMENTOR_VERSION', '3.24.0-sim');
}

class SC_Elementor_Sim
{
	public static $warn = array();

	public static function init()
	{
		add_action('init', array(__CLASS__, 'cpt'));
		add_filter('the_content', array(__CLASS__, 'content'), 9);
		add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'), 20);
		add_action('wp_head', array(__CLASS__, 'head_css'), 1);
		add_action('wp_footer', array(__CLASS__, 'footer_js'), 99);
		add_filter('body_class', array(__CLASS__, 'body_class'));
		add_action('save_post', array(__CLASS__, 'on_save'), 20, 2);
	}

	public static function cpt()
	{
		register_post_type('elementor_library', array('label' => 'Elementor library (sim)', 'public' => false, 'show_ui' => false, 'supports' => array('title')));
	}

	/* ---------------- ayudas ---------------- */

	public static function built($id)
	{
		return $id && get_post_meta($id, '_elementor_edit_mode', true) === 'builder';
	}

	public static function data($id)
	{
		$raw = get_post_meta($id, '_elementor_data', true);
		$d = is_string($raw) ? json_decode($raw, true) : $raw;
		return is_array($d) ? $d : array();
	}

	public static function kit_id()
	{
		return (int) get_option('elementor_active_kit', 0);
	}

	private static function css_dir()
	{
		$u = wp_upload_dir();
		return array($u['basedir'] . '/elementor/css', $u['baseurl'] . '/elementor/css');
	}

	/* ---------------- render ---------------- */

	public static function content($content)
	{
		if (is_admin()) {
			return $content;
		}
		$id = get_the_ID();
		if (!self::built($id)) {
			return $content;
		}
		$data = self::data($id);
		self::ensure_css($id);
		$html = '<div data-elementor-type="wp-page" data-elementor-id="' . (int) $id . '" class="elementor elementor-' . (int) $id . '">';
		foreach ($data as $el) {
			$html .= self::el($el, true, $id);
		}
		$html .= '</div>';
		return $html;
	}

	private static function esc($s)
	{
		return esc_attr((string) $s);
	}

	private static function size_class($v)
	{
		return $v;
	}

	private static function el(array $el, $parent, $postId)
	{
		$t = $el['elType'] ?? '';
		$s = is_array($el['settings'] ?? null) ? $el['settings'] : array();
		$id = $el['id'] ?? '';
		$custom = trim((string) ($s['css_classes'] ?? $s['_css_classes'] ?? ''));
		$eid = trim((string) ($s['_element_id'] ?? ''));
		$inv = (!empty($s['animation']) || !empty($s['_animation'])) ? ' elementor-invisible' : '';
		$dset = array();
		if (!empty($s['animation'])) {
			$dset['animation'] = $s['animation'];
		}
		if (!empty($s['_animation'])) {
			$dset['_animation'] = $s['_animation'];
		}
		if ($t === 'container') {
			$boxed = (($s['content_width'] ?? 'boxed') === 'boxed');
			$cls = 'elementor-element elementor-element-' . $id . ' e-flex ' . ($boxed ? 'e-con-boxed' : 'e-con-full') . ' e-con ' . ($parent ? 'e-parent' : 'e-child');
			if ($custom !== '') {
				$cls .= ' ' . $custom;
			}
			$cls .= $inv;
			$bg = '';
			if (($s['background_background'] ?? '') === 'slideshow' && !empty($s['background_slideshow_gallery'])) {
				$dset['background_background'] = 'slideshow';
				$dset['background_slideshow_gallery'] = $s['background_slideshow_gallery'];
				$bg .= '<div class="elementor-background-slideshow swiper-container swiper" data-sim-slides="' . count($s['background_slideshow_gallery']) . '"><div class="swiper-wrapper">';
				foreach ($s['background_slideshow_gallery'] as $n => $g) {
					$bg .= '<div class="elementor-background-slideshow__slide swiper-slide' . ($n === 0 ? ' is-active' : '') . '"><div class="elementor-background-slideshow__slide__image" style="background-image:url(' . esc_url($g['url'] ?? '') . ')"></div></div>';
				}
				$bg .= '</div></div>';
			}
			if (!empty($s['background_overlay_background'])) {
				$bg .= '<div class="elementor-background-overlay"></div>';
			}
			$inner = '';
			foreach ($el['elements'] ?? array() as $c) {
				$inner .= self::el($c, false, $postId);
			}
			$o = '<div class="' . esc_attr($cls) . '"' . ($eid !== '' ? ' id="' . esc_attr($eid) . '"' : '') . ' data-id="' . esc_attr($id) . '" data-element_type="container"'
				. ($dset ? " data-settings='" . esc_attr(wp_json_encode($dset)) . "'" : '') . '>';
			$o .= $bg;
			$o .= $boxed ? '<div class="e-con-inner">' . $inner . '</div>' : $inner;
			return $o . '</div>';
		}
		if ($t === 'widget') {
			$w = $el['widgetType'] ?? '';
			$cls = 'elementor-element elementor-element-' . $id . ($custom !== '' ? ' ' . $custom : '') . $inv . ' elementor-widget elementor-widget-' . $w;
			$body = self::widget($w, $s, $id);
			return '<div class="' . esc_attr($cls) . '" data-id="' . esc_attr($id) . '" data-element_type="widget"' . ($dset ? " data-settings='" . esc_attr(wp_json_encode($dset)) . "'" : '')
				. ' data-widget_type="' . esc_attr($w) . '.default"><div class="elementor-widget-container">' . $body . '</div></div>';
		}
		self::$warn[] = 'elType desconocido: ' . $t;
		return '';
	}

	private static function icon_html($ic, $extra = '')
	{
		$v = is_array($ic) ? ($ic['value'] ?? '') : '';
		if (is_array($v)) {
			return '';
		}
		return '<i aria-hidden="true" class="' . esc_attr($v) . '"' . $extra . '></i>';
	}

	private static function link_attrs($l)
	{
		if (!is_array($l) || empty($l['url'])) {
			return '';
		}
		$a = ' href="' . esc_url($l['url']) . '"';
		if (!empty($l['is_external'])) {
			$a .= ' target="_blank" rel="noopener noreferrer"';
		}
		return $a;
	}

	private static function widget($w, array $s, $id)
	{
		switch ($w) {
			case 'heading':
				$tag = in_array($s['header_size'] ?? 'h2', array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p'), true) ? $s['header_size'] : 'h2';
				$title = wp_kses_post((string) ($s['title'] ?? ''));
				if (!empty($s['link']['url'])) {
					$title = '<a' . self::link_attrs($s['link']) . '>' . $title . '</a>';
				}
				return '<' . $tag . ' class="elementor-heading-title elementor-size-default">' . $title . '</' . $tag . '>';
			case 'text-editor':
				return '<div class="elementor-text-editor elementor-clearfix">' . wpautop(do_shortcode(wp_kses_post((string) ($s['editor'] ?? '')))) . '</div>';
			case 'image':
				$im = $s['image'] ?? array();
				$sz = $s['image_size'] ?? 'large';
				$img = '';
				if (!empty($im['id'])) {
					$img = wp_get_attachment_image((int) $im['id'], $sz, false, array('class' => 'attachment-' . $sz . ' size-' . $sz . ' wp-image-' . (int) $im['id']));
				}
				if ($img === '' && !empty($im['url'])) {
					$img = '<img src="' . esc_url($im['url']) . '" alt="' . esc_attr($im['alt'] ?? '') . '" loading="lazy">';
				}
				if (($s['link_to'] ?? 'none') === 'custom' && !empty($s['link']['url'])) {
					$img = '<a' . self::link_attrs($s['link']) . '>' . $img . '</a>';
				}
				return $img;
			case 'icon':
				return '<div class="elementor-icon-wrapper"><div class="elementor-icon">' . self::icon_html($s['selected_icon'] ?? null) . '</div></div>';
			case 'icon-box':
				return '<div class="elementor-icon-box-wrapper"><div class="elementor-icon-box-icon"><span class="elementor-icon">' . self::icon_html($s['selected_icon'] ?? null) . '</span></div>'
					. '<div class="elementor-icon-box-content"><h3 class="elementor-icon-box-title"><span>' . wp_kses_post($s['title_text'] ?? '') . '</span></h3>'
					. '<p class="elementor-icon-box-description">' . wp_kses_post($s['description_text'] ?? '') . '</p></div></div>';
			case 'image-box':
				$im = $s['image'] ?? array();
				$img = !empty($im['id']) ? wp_get_attachment_image((int) $im['id'], 'medium_large') : '';
				return '<div class="elementor-image-box-wrapper"><figure class="elementor-image-box-img">' . $img . '</figure><div class="elementor-image-box-content">'
					. '<h3 class="elementor-image-box-title">' . wp_kses_post($s['title_text'] ?? '') . '</h3><p class="elementor-image-box-description">' . wp_kses_post($s['description_text'] ?? '') . '</p></div></div>';
			case 'button':
				$al = $s['align'] ?? 'left';
				return '<div class="elementor-button-wrapper elementor-align-' . esc_attr($al) . '"><a class="elementor-button elementor-button-link elementor-size-' . esc_attr($s['size'] ?? 'md') . '"' . self::link_attrs($s['link'] ?? array()) . '>'
					. '<span class="elementor-button-content-wrapper"><span class="elementor-button-text">' . esc_html($s['text'] ?? '') . '</span></span></a></div>';
			case 'image-gallery':
				$o = '<div class="elementor-image-gallery"><div id="gallery-' . esc_attr($id) . '" class="gallery gallery-columns-' . (int) ($s['gallery_columns'] ?? 4) . ' gallery-size-' . esc_attr($s['thumbnail_size'] ?? 'thumbnail') . '">';
				foreach ($s['wp_gallery'] ?? array() as $g) {
					$full = wp_get_attachment_url((int) $g['id']);
					$o .= '<figure class="gallery-item"><div class="gallery-icon landscape"><a href="' . esc_url($full) . '" data-elementor-open-lightbox="yes">'
						. wp_get_attachment_image((int) $g['id'], $s['thumbnail_size'] ?? 'medium_large') . '</a></div></figure>';
				}
				return $o . '</div></div>';
			case 'image-carousel':
				$o = '<div class="elementor-image-carousel-wrapper swiper"><div class="elementor-image-carousel swiper-wrapper">';
				foreach ($s['carousel'] ?? array() as $g) {
					$o .= '<div class="swiper-slide"><figure class="swiper-slide-inner">' . wp_get_attachment_image((int) $g['id'], 'large', false, array('class' => 'swiper-slide-image')) . '</figure></div>';
				}
				return $o . '</div></div>';
			case 'icon-list':
				$o = '<ul class="elementor-icon-list-items">';
				foreach ($s['icon_list'] ?? array() as $it) {
					$o .= '<li class="elementor-icon-list-item"><span class="elementor-icon-list-icon">' . self::icon_html($it['selected_icon'] ?? null) . '</span><span class="elementor-icon-list-text">' . esc_html($it['text'] ?? '') . '</span></li>';
				}
				return $o . '</ul>';
			case 'social-icons':
				$o = '<div class="elementor-social-icons-wrapper elementor-grid">';
				foreach ($s['social_icon_list'] ?? array() as $it) {
					$v = $it['social_icon']['value'] ?? '';
					$name = str_replace(array('fab fa-', 'fas fa-'), '', $v);
					$o .= '<span class="elementor-grid-item"><a class="elementor-icon elementor-social-icon elementor-social-icon-' . esc_attr($name) . ' elementor-repeater-item-' . esc_attr($it['_id'] ?? '') . '"' . self::link_attrs($it['link'] ?? array()) . '><span class="elementor-screen-only">' . esc_html($name) . '</span>' . self::icon_html($it['social_icon'] ?? null) . '</a></span>';
				}
				return $o . '</div>';
			case 'google_maps':
				$addr = (string) ($s['address'] ?? '');
				$z = (int) ($s['zoom']['size'] ?? 10);
				return '<div class="elementor-custom-embed"><iframe loading="lazy" src="https://maps.google.com/maps?q=' . rawurlencode($addr) . '&amp;t=m&amp;z=' . $z . '&amp;output=embed&amp;iwloc=near" title="' . esc_attr($addr) . '" aria-label="' . esc_attr($addr) . '"></iframe><span class="sim-map-label">[SIM] ' . esc_html($addr) . '</span></div>';
			case 'video':
				return '<div class="elementor-wrapper elementor-open-inline"><div class="elementor-video sim-video" data-sim-src="' . esc_url($s['youtube_url'] ?? '') . '"><span class="sim-play">&#9654;</span></div></div>';
			case 'shortcode':
				return '<div class="elementor-shortcode">' . do_shortcode(shortcode_unautop((string) ($s['shortcode'] ?? ''))) . '</div>';
			case 'accordion':
				$o = '<div class="elementor-accordion">';
				foreach ($s['tabs'] ?? array() as $tab) {
					$o .= '<div class="elementor-accordion-item"><div class="elementor-tab-title"><a class="elementor-accordion-title">' . esc_html($tab['tab_title'] ?? '') . '</a></div><div class="elementor-tab-content elementor-clearfix">' . wp_kses_post($tab['tab_content'] ?? '') . '</div></div>';
				}
				return $o . '</div>';
			case 'divider':
				return '<div class="elementor-divider"><span class="elementor-divider-separator"></span></div>';
			case 'spacer':
				return '<div class="elementor-spacer"><div class="elementor-spacer-inner"></div></div>';
		}
		self::$warn[] = 'widget no soportado: ' . $w;
		return '<div class="sim-unsupported" style="color:#b00;border:1px dashed #b00;padding:6px">[SIM] widget no soportado: ' . esc_html($w) . '</div>';
	}

	/* ---------------- CSS ---------------- */

	private static function dim($v)
	{
		if (!is_array($v) || !isset($v['top'])) {
			return null;
		}
		$u = $v['unit'] ?? 'px';
		return (float) $v['top'] . $u . ' ' . (float) $v['right'] . $u . ' ' . (float) $v['bottom'] . $u . ' ' . (float) $v['left'] . $u;
	}

	private static function sz($v)
	{
		if (!is_array($v) || !isset($v['size']) || $v['size'] === '') {
			return null;
		}
		return $v['size'] . ($v['unit'] ?? 'px');
	}

	private static function vars_for(array $s, $dev)
	{
		$sfx = $dev === 'tablet' ? '_tablet' : ($dev === 'mobile' ? '_mobile' : '');
		$v = array();
		if (!empty($s['flex_direction' . $sfx])) {
			$v[] = '--flex-direction:' . $s['flex_direction' . $sfx];
		}
		if ($dev === '') {
			if (!empty($s['flex_wrap'])) {
				$v[] = '--flex-wrap:' . $s['flex_wrap'];
			}
			if (!empty($s['flex_justify_content'])) {
				$v[] = '--justify-content:' . $s['flex_justify_content'];
			}
			if (!empty($s['flex_align_items'])) {
				$v[] = '--align-items:' . $s['flex_align_items'];
			}
			if (!empty($s['flex_gap']) && is_array($s['flex_gap'])) {
				$g = $s['flex_gap'];
				$u = $g['unit'] ?? 'px';
				$r = ($g['row'] ?? 0) . $u;
				$c = ($g['column'] ?? 0) . $u;
				$v[] = '--gap:' . $r . ' ' . $c . ';--row-gap:' . $r . ';--column-gap:' . $c;
			}
			if (($s['content_width'] ?? '') === 'boxed' && ($bw = self::sz($s['boxed_width'] ?? null))) {
				$v[] = '--content-width:min(100%,' . $bw . ')';
			}
			if (($mh = self::sz($s['min_height'] ?? null))) {
				$v[] = '--min-height:' . $mh;
			}
			if (($p = self::dim($s['padding'] ?? null)) !== null) {
				$pp = explode(' ', $p);
				$v[] = '--padding-top:' . $pp[0] . ';--padding-right:' . $pp[1] . ';--padding-bottom:' . $pp[2] . ';--padding-left:' . $pp[3];
			}
		}
		return $v;
	}

	public static function build_css($id)
	{
		$data = self::data($id);
		$css = '';
		$walk = function ($els) use (&$walk, &$css, $id) {
			foreach ($els as $el) {
				$sel = '.elementor-' . $id . ' .elementor-element.elementor-element-' . $el['id'];
				$s = is_array($el['settings'] ?? null) ? $el['settings'] : array();
				if (($el['elType'] ?? '') === 'container') {
					$v = self::vars_for($s, '');
					$bgc = '';
					if (($s['background_background'] ?? '') === 'gradient') {
						$ang = (int) ($s['background_gradient_angle']['size'] ?? 180);
						$bgc .= 'background-image:linear-gradient(' . $ang . 'deg,' . ($s['background_color'] ?? '#000') . ' ' . ($s['background_color_stop']['size'] ?? 0) . '%,' . ($s['background_color_b'] ?? '#fff') . ' ' . ($s['background_color_b_stop']['size'] ?? 100) . '%);';
					} elseif (($s['background_background'] ?? '') === 'classic' && !empty($s['background_color'])) {
						$bgc .= 'background-color:' . $s['background_color'] . ';';
					}
					if ($v || $bgc) {
						$css .= $sel . '{' . implode(';', $v) . ($v ? ';' : '') . $bgc . '}';
					}
					if (!empty($s['background_overlay_background'])) {
						$op = $s['background_overlay_opacity']['size'] ?? 0.5;
						$css .= $sel . '>.elementor-background-overlay{background-color:' . ($s['background_overlay_color'] ?? '#000') . ';opacity:' . $op . '}';
					}
					if (($s['background_background'] ?? '') === 'slideshow') {
						$dur = max(1, (int) ($s['background_slideshow_slide_duration'] ?? 5000)) / 1000;
						$css .= $sel . ' .elementor-background-slideshow{--sim-dur:' . $dur . 's}';
					}
					foreach (array('tablet' => 1024, 'mobile' => 767) as $dev => $max) {
						$vv = self::vars_for($s, $dev);
						if ($vv) {
							$css .= '@media(max-width:' . $max . 'px){' . $sel . '{' . implode(';', $vv) . '}}';
						}
					}
					$walk($el['elements'] ?? array());
				} elseif (($el['elType'] ?? '') === 'widget') {
					if (!empty($s['align']) && in_array($el['widgetType'], array('heading', 'text-editor', 'button', 'image'), true)) {
						$css .= $sel . '{text-align:' . $s['align'] . '}';
					}
					if (($el['widgetType'] ?? '') === 'icon' && ($isz = self::sz($s['size'] ?? null))) {
						$css .= $sel . ' .elementor-icon{font-size:' . $isz . '}';
					}
					if (($el['widgetType'] ?? '') === 'google_maps' && ($h = self::sz($s['height'] ?? null))) {
						$css .= $sel . ' iframe,' . $sel . ' .elementor-custom-embed{height:' . $h . '}';
					}
				}
			}
		};
		$walk($data);
		return '/* [SIM] CSS de Elementor simulado para el post ' . $id . ' */' . "\n" . $css;
	}

	public static function kit_css()
	{
		$kid = self::kit_id();
		if (!$kid) {
			return '';
		}
		$s = get_post_meta($kid, '_elementor_page_settings', true);
		if (!is_array($s)) {
			return '';
		}
		$css = '.elementor-kit-' . $kid . '{';
		foreach (array('system_colors', 'custom_colors') as $g) {
			foreach ($s[$g] ?? array() as $c) {
				$css .= '--e-global-color-' . $c['_id'] . ':' . $c['color'] . ';';
			}
		}
		foreach ($s['system_typography'] ?? array() as $t) {
			$css .= '--e-global-typography-' . $t['_id'] . '-font-family:"' . ($t['typography_font_family'] ?? '') . '";';
			$css .= '--e-global-typography-' . $t['_id'] . '-font-weight:' . ($t['typography_font_weight'] ?? 400) . ';';
		}
		$cw = self::sz($s['container_width'] ?? null);
		if ($cw) {
			$css .= '--container-max-width:' . $cw . ';';
		}
		$css .= '--kit-widget-spacing:' . (self::sz(array('size' => $s['space_between_widgets']['size'] ?? 0, 'unit' => 'px')) ?: '0px') . ';';
		if (($p = self::dim($s['container_padding'] ?? null)) !== null) {
			$pp = explode(' ', $p);
			$css .= '--container-default-padding-block-start:' . $pp[0] . ';--container-default-padding-inline-end:' . $pp[1] . ';--container-default-padding-block-end:' . $pp[2] . ';--container-default-padding-inline-start:' . $pp[3] . ';';
		}
		$css .= '}';
		if (!empty($s['body_color'])) {
			$css .= 'body.elementor-kit-' . $kid . '{color:' . $s['body_color'] . ';font-family:"' . ($s['body_typography_font_family'] ?? 'inherit') . '",sans-serif}';
		}
		foreach (array('h1', 'h2', 'h3', 'h4', 'h5', 'h6') as $h) {
			if (!empty($s[$h . '_typography_font_family'])) {
				$css .= '.elementor-kit-' . $kid . ' ' . $h . '{font-family:"' . $s[$h . '_typography_font_family'] . '",serif;color:' . ($s[$h . '_color'] ?? 'inherit') . '}';
			}
		}
		return '/* [SIM] kit */' . "\n" . $css;
	}

	public static function write_css($id)
	{
		list($dir) = self::css_dir();
		if (!wp_mkdir_p($dir)) {
			return false;
		}
		$kid = self::kit_id();
		$css = ((int) $id === $kid) ? self::kit_css() : self::build_css($id);
		return file_put_contents($dir . '/post-' . (int) $id . '.css', $css) !== false;
	}

	public static function ensure_css($id)
	{
		list($dir) = self::css_dir();
		$f = $dir . '/post-' . (int) $id . '.css';
		$mt = get_post_modified_time('U', true, $id);
		if (!file_exists($f) || ($mt && filemtime($f) < $mt - 1)) {
			self::write_css($id);
		}
		$kid = self::kit_id();
		if ($kid && !file_exists($dir . '/post-' . $kid . '.css')) {
			self::write_css($kid);
		}
	}

	public static function clear_css()
	{
		list($dir) = self::css_dir();
		foreach ((array) glob($dir . '/post-*.css') as $f) {
			@unlink($f);
		}
	}

	public static function regenerate_all()
	{
		global $wpdb;
		$ids = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_elementor_edit_mode' AND meta_value='builder'");
		foreach ((array) $ids as $id) {
			self::write_css((int) $id);
		}
	}

	public static function on_save($id, $post)
	{
		if (wp_is_post_revision($id) || !self::built($id)) {
			return;
		}
		// el CSS se regenerará al renderizar (filemtime < post_modified)
	}

	public static function enqueue()
	{
		$id = is_singular() ? get_the_ID() : 0;
		if (!$id || !self::built($id)) {
			return;
		}
		self::ensure_css($id);
		list($dir, $url) = self::css_dir();
		$kid = self::kit_id();
		if ($kid) {
			wp_enqueue_style('elementor-post-' . $kid, $url . '/post-' . $kid . '.css', array(), filemtime($dir . '/post-' . $kid . '.css'));
		}
		wp_enqueue_style('elementor-post-' . $id, $url . '/post-' . $id . '.css', array(), @filemtime($dir . '/post-' . $id . '.css'));
	}

	public static function body_class($c)
	{
		$c[] = 'elementor-default';
		if ($kid = self::kit_id()) {
			$c[] = 'elementor-kit-' . $kid;
		}
		if (is_singular() && self::built(get_the_ID())) {
			$c[] = 'elementor-page';
			$c[] = 'elementor-page-' . get_the_ID();
		}
		return $c;
	}

	public static function head_css()
	{
		if (!is_singular() || !self::built(get_the_ID())) {
			return;
		}
		echo '<style id="elementor-sim-frontend">' . self::base_css() . '</style>' . "\n";
	}

	/** Aproximación (de memoria) del CSS base de frontend/container de Elementor. */
	public static function base_css()
	{
		return <<<'CSS'
.elementor-element{--flex-direction:initial;--flex-wrap:initial;--justify-content:initial;--align-items:initial;--align-content:initial;--gap:initial;--flex-basis:initial;--flex-grow:initial;--flex-shrink:initial;--order:initial;--align-self:initial;flex-basis:var(--flex-basis);flex-grow:var(--flex-grow);flex-shrink:var(--flex-shrink);order:var(--order);align-self:var(--align-self)}
.elementor-invisible{visibility:hidden}
.e-con{--border-radius:0;--padding-top:var(--container-default-padding-block-start,10px);--padding-right:var(--container-default-padding-inline-end,10px);--padding-bottom:var(--container-default-padding-block-end,10px);--padding-left:var(--container-default-padding-inline-start,10px);--content-width:min(100%,var(--container-max-width,1140px));--width:100%;--min-height:initial;--height:auto;position:relative;width:var(--width);min-width:0;min-height:var(--min-height);height:var(--height);box-sizing:border-box;margin-block:0;padding:var(--padding-top) var(--padding-right) var(--padding-bottom) var(--padding-left)}
.e-con.e-flex{--display:flex;--flex-direction:column;--flex-basis:auto;--flex-grow:0;--flex-shrink:1;flex:var(--flex-grow) var(--flex-shrink) var(--flex-basis);display:flex}
.e-con-full.e-flex,.e-con.e-flex>.e-con-inner{flex-direction:var(--flex-direction)}
.e-con.e-flex>.e-con-inner{display:flex}
.e-con-full,.e-con>.e-con-inner{text-align:var(--text-align);padding:0}
.e-con-full{flex-wrap:var(--flex-wrap);justify-content:var(--justify-content);align-items:var(--align-items);align-content:var(--align-content);gap:var(--gap)}
.e-con.e-con-boxed{flex-direction:column;align-items:center}
.e-con>.e-con-inner{width:100%;max-width:var(--content-width);margin:0 auto;height:100%;gap:var(--gap);flex-wrap:var(--flex-wrap);justify-content:var(--justify-content);align-items:var(--align-items);align-content:var(--align-content);flex-basis:auto;flex-grow:1;flex-shrink:1;align-self:auto}
.e-con>.elementor-widget{max-width:100%}
.e-con .elementor-widget:not(:last-child){margin-block-end:var(--kit-widget-spacing,20px)}
.elementor-widget-heading .elementor-heading-title{margin:0;padding:0;line-height:1.2}
.elementor-widget-container{width:100%}
.elementor-button{display:inline-block;line-height:1;background-color:#69727d;font-size:15px;padding:12px 24px;border-radius:3px;color:#fff;fill:#fff;text-align:center;text-decoration:none}
.elementor-button-wrapper{display:block}
.elementor-align-center{text-align:center}.elementor-align-right{text-align:right}.elementor-align-left{text-align:left}
.elementor-icon{display:inline-block;line-height:1;text-align:center;font-size:50px;color:#69727d}
.elementor-icon i{width:1em;height:1em;position:relative;display:block}
i[class*="fa-"]{display:inline-block;width:1em;height:1em;background:currentColor;-webkit-mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M12 2l3 7 7 .6-5.3 4.7 1.7 7.2L12 17.8 5.6 21.5l1.7-7.2L2 9.6 9 9z'/%3E%3C/svg%3E") center/contain no-repeat;mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M12 2l3 7 7 .6-5.3 4.7 1.7 7.2L12 17.8 5.6 21.5l1.7-7.2L2 9.6 9 9z'/%3E%3C/svg%3E") center/contain no-repeat}
.elementor-image-box-wrapper,.elementor-icon-box-wrapper{text-align:center}
.elementor-widget-image img{display:block;max-width:100%;height:auto;vertical-align:middle}
.elementor-widget-image-gallery .gallery{display:grid;grid-template-columns:repeat(var(--g-cols,3),1fr);gap:10px}
.gallery-columns-2{--g-cols:2}.gallery-columns-3{--g-cols:3}.gallery-columns-4{--g-cols:4}.gallery-columns-5{--g-cols:5}
.gallery-item{margin:0}.gallery-item img{width:100%;height:auto;display:block}
.elementor-custom-embed{position:relative;height:300px;width:100%;background:#e6e6e6}
.elementor-custom-embed iframe{width:100%;height:100%;border:0;display:block}
.sim-map-label{position:absolute;left:8px;bottom:8px;font:11px monospace;background:#fff8;padding:2px 6px}
.elementor-video{position:relative;width:100%;aspect-ratio:16/9;background:#111;display:flex;align-items:center;justify-content:center}
.sim-play{color:#fff;font-size:42px}
.elementor-icon-list-items{list-style:none;margin:0;padding:0}
.elementor-social-icons-wrapper{display:flex;gap:8px}
.elementor-social-icon{background:#69727d;color:#fff;border-radius:50%;width:2em;height:2em;display:inline-flex;align-items:center;justify-content:center;font-size:20px}
.elementor-screen-only{position:absolute;left:-10000px}
.elementor-background-slideshow{position:absolute;inset:0;z-index:0;overflow:hidden;pointer-events:none}
.elementor-background-slideshow .swiper-wrapper{position:absolute;inset:0}
.elementor-background-slideshow__slide{position:absolute;inset:0;opacity:0;transition:opacity .8s}
.elementor-background-slideshow__slide.is-active{opacity:1}
.elementor-background-slideshow__slide__image{position:absolute;inset:0;background-size:cover;background-position:center}
.elementor-background-overlay{position:absolute;inset:0;z-index:0;pointer-events:none}
.e-con>.e-con-inner,.e-con-full>.elementor-element{position:relative;z-index:1}
.animated{animation-duration:1.25s;animation-fill-mode:both}
@keyframes fadeInUp{from{opacity:0;transform:translate3d(0,40px,0)}to{opacity:1;transform:none}}
.fadeInUp{animation-name:fadeInUp}
CSS;
	}

	public static function footer_js()
	{
		if (!is_singular() || !self::built(get_the_ID())) {
			return;
		}
		echo <<<'JS'
<script id="elementor-sim-js">(function(){
var els=[].slice.call(document.querySelectorAll('.elementor-invisible'));
function show(e){var s={};try{s=JSON.parse(e.getAttribute('data-settings')||'{}')}catch(x){}var a=s.animation||s._animation||'fadeInUp';e.classList.remove('elementor-invisible');e.classList.add('animated',a)}
if(!('IntersectionObserver' in window)){els.forEach(show)}else{var io=new IntersectionObserver(function(en){en.forEach(function(x){if(x.isIntersecting){show(x.target);io.unobserve(x.target)}})},{rootMargin:'0px 0px -5% 0px'});els.forEach(function(e){io.observe(e)})}
[].forEach.call(document.querySelectorAll('.elementor-background-slideshow'),function(s){var sl=s.querySelectorAll('.elementor-background-slideshow__slide');if(sl.length<2)return;var i=0;setInterval(function(){sl[i].classList.remove('is-active');i=(i+1)%sl.length;sl[i].classList.add('is-active')},5000)});
})();</script>
JS;
	}
}

SC_Elementor_Sim::init();

}
