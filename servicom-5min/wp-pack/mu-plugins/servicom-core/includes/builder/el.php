<?php
/**
 * Servicom builder: fábrica de elementos de Elementor (datos JSON).
 * Solo contenedores flexbox ("container") y widgets nativos gratuitos.
 * Formato validado contra la estructura pública de Elementor 3.2x
 * (ver README-FORMATO.md para lo que NO pudo probarse contra el real).
 */
if (!defined('ABSPATH')) {
	exit;
}

class SC_El
{
	const ALLOWED_WIDGETS = array(
		'heading', 'text-editor', 'image', 'icon', 'icon-box', 'image-box', 'button', 'image-gallery',
		'image-carousel', 'icon-list', 'social-icons', 'google_maps', 'video', 'shortcode', 'accordion',
		'divider', 'spacer',
	);

	private static $seed = '';
	private static $n = 0;
	private static $used = array();

	/** Empieza un documento: los ids salen deterministas de la semilla. */
	public static function begin($seed)
	{
		self::$seed = (string) $seed;
		self::$n = 0;
		self::$used = array();
	}

	/** Id de 7 hex, único dentro del documento y determinista. */
	public static function id()
	{
		do {
			$id = substr(md5(self::$seed . '|' . (self::$n++)), 0, 7);
		} while (isset(self::$used[$id]));
		self::$used[$id] = true;
		return $id;
	}

	/* ---------- valores de control ---------- */

	public static function dim($t, $r = null, $b = null, $l = null, $unit = 'px')
	{
		$r = $r ?? $t;
		$b = $b ?? $t;
		$l = $l ?? $r;
		$linked = ($t === $r && $r === $b && $b === $l);
		return array('unit' => $unit, 'top' => (string) $t, 'right' => (string) $r, 'bottom' => (string) $b, 'left' => (string) $l, 'isLinked' => $linked);
	}

	public static function size($v, $unit = 'px')
	{
		return array('unit' => $unit, 'size' => $v, 'sizes' => array());
	}

	public static function gap($px)
	{
		return array('column' => (string) $px, 'row' => (string) $px, 'isLinked' => true, 'unit' => 'px', 'size' => $px);
	}

	public static function link($url, $external = false)
	{
		return array('url' => (string) $url, 'is_external' => $external ? 'on' : '', 'nofollow' => '', 'custom_attributes' => '');
	}

	public static function icon($cls)
	{
		$cls = trim((string) $cls);
		if (!preg_match('/^(fa[srbl]?)\s+fa-[a-z0-9-]+$/', $cls)) {
			$cls = 'fas fa-check';
		}
		$lib = array('fas' => 'fa-solid', 'far' => 'fa-regular', 'fab' => 'fa-brands', 'fa' => 'fa-solid', 'fal' => 'fa-solid');
		$pre = strtok($cls, ' ');
		return array('value' => $cls, 'library' => $lib[$pre] ?? 'fa-solid');
	}

	/** Texto de usuario -> HTML seguro sin shortcodes accidentales. */
	public static function esc($s)
	{
		return str_replace(array('[', ']'), array('&#91;', '&#93;'), esc_html((string) $s));
	}

	/** Texto de usuario multilínea -> párrafos <p>. */
	public static function prose($s)
	{
		$s = str_replace("\r", '', trim((string) $s));
		if ($s === '') {
			return '';
		}
		$paras = preg_split('/\n{2,}/', $s);
		$out = '';
		foreach ($paras as $p) {
			$p = trim($p);
			if ($p === '') {
				continue;
			}
			$out .= '<p>' . str_replace("\n", '<br>', self::esc($p)) . '</p>';
		}
		return $out;
	}

	/** Recorta en límite de palabra. */
	public static function trim_words($s, $max)
	{
		$s = trim(preg_replace('/\s+/u', ' ', (string) $s));
		if (mb_strlen($s) <= $max) {
			return $s;
		}
		$cut = mb_substr($s, 0, $max);
		$sp = mb_strrpos($cut, ' ');
		if ($sp !== false && $sp > $max * 0.6) {
			$cut = mb_substr($cut, 0, $sp);
		}
		return rtrim($cut, " ,;:.-") . '…';
	}

	/* ---------- elementos ---------- */

	/**
	 * Contenedor flexbox.
	 * $o: dir(column|row), gap, boxed(bool), width(px boxed), align, justify, wrap, min_h(['vh'|'px',n]),
	 *     pad(array dim), bg(array settings extra), anim(bool), eid, tablet_dir, mobile_dir, extra(array)
	 */
	public static function container(array $children, $classes = '', array $o = array(), $inner = false)
	{
		$s = array();
		$s['content_width'] = !empty($o['boxed']) ? 'boxed' : 'full';
		if (!empty($o['boxed'])) {
			$s['boxed_width'] = self::size($o['width'] ?? 1200);
		}
		$s['flex_direction'] = $o['dir'] ?? 'column';
		if (isset($o['tablet_dir'])) {
			$s['flex_direction_tablet'] = $o['tablet_dir'];
		}
		if (isset($o['mobile_dir'])) {
			$s['flex_direction_mobile'] = $o['mobile_dir'];
		}
		if (isset($o['gap'])) {
			$s['flex_gap'] = self::gap($o['gap']);
		}
		if (isset($o['justify'])) {
			$s['flex_justify_content'] = $o['justify'];
		}
		if (isset($o['align'])) {
			$s['flex_align_items'] = $o['align'];
		}
		if (!empty($o['wrap'])) {
			$s['flex_wrap'] = 'wrap';
		}
		if (isset($o['min_h'])) {
			$s['min_height'] = self::size($o['min_h'][1], $o['min_h'][0]);
			$s['min_height_type'] = 'min-height';
		}
		if (isset($o['pad'])) {
			$s['padding'] = $o['pad'];
		}
		if (!empty($o['eid'])) {
			$s['_element_id'] = $o['eid'];
		}
		if (!empty($o['anim'])) {
			$s['animation'] = 'fadeInUp';
			$s['animation_duration'] = 'normal';
			$classes = trim($classes . ' sc-reveal');
		}
		if ($classes !== '') {
			$s['css_classes'] = $classes;
		}
		if (!empty($o['bg'])) {
			$s = array_merge($s, $o['bg']);
		}
		if (!empty($o['extra'])) {
			$s = array_merge($s, $o['extra']);
		}
		return array(
			'id' => self::id(),
			'elType' => 'container',
			'settings' => $s,
			'elements' => array_values(array_filter($children)),
			'isInner' => (bool) $inner,
		);
	}

	public static function widget($type, array $settings, $classes = '', $anim = false)
	{
		if ($classes !== '') {
			$settings['_css_classes'] = trim($classes . ($anim ? ' sc-reveal' : ''));
		} elseif ($anim) {
			$settings['_css_classes'] = 'sc-reveal';
		}
		if ($anim) {
			$settings['_animation'] = 'fadeInUp';
		}
		return array(
			'id' => self::id(),
			'elType' => 'widget',
			'settings' => $settings,
			'elements' => array(),
			'widgetType' => $type,
		);
	}

	public static function heading($text, $tag = 'h2', $classes = '', array $x = array())
	{
		$s = array('title' => self::esc($text), 'header_size' => $tag);
		if (!empty($x['url'])) {
			$s['link'] = self::link($x['url']);
		}
		if (!empty($x['align'])) {
			$s['align'] = $x['align'];
		}
		return self::widget('heading', $s, $classes, !empty($x['anim']));
	}

	/** $html ya es HTML seguro (usar self::prose o shortcodes). */
	public static function text($html, $classes = '', $anim = false)
	{
		return self::widget('text-editor', array('editor' => $html), $classes, $anim);
	}

	public static function image($attId, $url, $alt = '', $classes = '', $size = 'large', $anim = false)
	{
		return self::widget('image', array(
			'image' => array('url' => $url, 'id' => (int) $attId, 'alt' => $alt, 'source' => 'library'),
			'image_size' => $size,
			'caption_source' => 'none',
			'link_to' => 'none',
		), $classes, $anim);
	}

	public static function icon_widget($cls, $classes = '', $view = 'default', $size = 0)
	{
		$s = array('selected_icon' => self::icon($cls), 'view' => $view);
		if ($size) {
			$s['size'] = self::size($size);
		}
		return self::widget('icon', $s, $classes);
	}

	public static function button($text, $url, $classes = '', array $x = array())
	{
		$s = array(
			'text' => (string) $text,
			'link' => self::link($url, !empty($x['external'])),
			'align' => $x['align'] ?? 'left',
			'size' => 'md',
		);
		if (!empty($x['icon'])) {
			$s['selected_icon'] = self::icon($x['icon']);
			$s['icon_align'] = 'row';
		}
		return self::widget('button', $s, $classes, !empty($x['anim']));
	}

	public static function shortcode($sc, $classes = '', $anim = false)
	{
		return self::widget('shortcode', array('shortcode' => $sc), $classes, $anim);
	}

	public static function gallery(array $items, $cols = 3, $classes = '')
	{
		return self::widget('image-gallery', array(
			'wp_gallery' => $items,
			'thumbnail_size' => 'medium_large',
			'gallery_columns' => (string) $cols,
			'gallery_columns_tablet' => '2',
			'gallery_columns_mobile' => '2',
			'gallery_link' => 'file',
			'open_lightbox' => 'yes',
			'gallery_rand' => '',
		), $classes);
	}

	public static function maps($address, $height = 380, $classes = '')
	{
		return self::widget('google_maps', array(
			'address' => $address,
			'zoom' => self::size(15),
			'height' => self::size($height),
		), $classes);
	}

	public static function video($ytUrl, $classes = '')
	{
		return self::widget('video', array(
			'video_type' => 'youtube',
			'youtube_url' => $ytUrl,
			'yt_privacy' => 'yes',
			'lazy_load' => 'yes',
			'aspect_ratio' => '169',
			'show_image_overlay' => '',
		), $classes);
	}

	public static function social(array $nets, $classes = '')
	{
		$map = array(
			'facebook' => 'fab fa-facebook-f', 'instagram' => 'fab fa-instagram', 'tiktok' => 'fab fa-tiktok',
			'youtube' => 'fab fa-youtube', 'x' => 'fab fa-x-twitter', 'linkedin' => 'fab fa-linkedin-in',
		);
		$list = array();
		foreach ($nets as $k => $url) {
			if (!isset($map[$k]) || $url === '') {
				continue;
			}
			$list[] = array(
				'_id' => self::id(),
				'social_icon' => self::icon($map[$k]),
				'link' => self::link($url, true),
			);
		}
		if (!$list) {
			return null;
		}
		return self::widget('social-icons', array('social_icon_list' => $list, 'shape' => 'rounded'), $classes);
	}

	public static function icon_list(array $rows, $classes = '')
	{
		$list = array();
		foreach ($rows as $r) {
			$list[] = array('_id' => self::id(), 'text' => $r[1], 'selected_icon' => self::icon($r[0]));
		}
		return self::widget('icon-list', array('icon_list' => $list, 'view' => 'traditional'), $classes);
	}

	/* ---------- documento ---------- */

	/** Valida un árbol; devuelve lista de problemas (vacía = bien). */
	public static function validate(array $data)
	{
		$errs = array();
		$ids = array();
		$walk = function ($els, $depth) use (&$walk, &$errs, &$ids) {
			foreach ($els as $e) {
				$id = $e['id'] ?? '';
				if (!preg_match('/^[0-9a-f]{7}$/', (string) $id)) {
					$errs[] = 'id inválido: ' . $id;
				}
				if (isset($ids[$id])) {
					$errs[] = 'id duplicado: ' . $id;
				}
				$ids[$id] = 1;
				$t = $e['elType'] ?? '';
				if ($t === 'container') {
					$walk($e['elements'] ?? array(), $depth + 1);
				} elseif ($t === 'widget') {
					$w = $e['widgetType'] ?? '';
					if (!in_array($w, self::ALLOWED_WIDGETS, true)) {
						$errs[] = 'widget no permitido: ' . $w;
					}
				} else {
					$errs[] = 'elType no permitido: ' . $t;
				}
			}
		};
		$walk($data, 0);
		return $errs;
	}

	public static function encode(array $data)
	{
		return wp_json_encode($data);
	}
}
