<?php
/**
 * Servicom LUXE — renderizador de páginas y secciones a partir de la opción `sc_site`.
 * Ver docs/CONTRATO-LUXE.md. Todo valor editable lleva data-sc="<ruta>" (solo para quien puede editar).
 */
if (!defined('ABSPATH')) {
	exit;
}

/* ------------------------------------------------------------------ datos */

function sc_site()
{
	static $s = null;
	if ($s === null || !empty($GLOBALS['sc_site_reload'])) {
		$o = get_option('sc_site', array());
		$s = is_array($o) ? $o : array();
		$GLOBALS['sc_site_reload'] = false;
	}
	return $s;
}

function sc_site_reload()
{
	$GLOBALS['sc_site_reload'] = true;
}

function sc_lx_active()
{
	$s = sc_site();
	return !empty($s['v']) && !empty($s['pages']);
}

function sc_lx_get($arr, $path, $def = '')
{
	$cur = $arr;
	foreach (explode('.', (string) $path) as $k) {
		if (is_array($cur) && array_key_exists($k, $cur)) {
			$cur = $cur[$k];
		} else {
			return $def;
		}
	}
	return $cur;
}

function sc_lx_can_edit()
{
	static $c = null;
	if ($c === null) {
		$c = is_user_logged_in() && (current_user_can('sc_edit_site') || current_user_can('manage_options'));
	}
	return $c;
}

function sc_lx_editing()
{
	return sc_lx_can_edit() && !empty($_GET['sc_edit']); // phpcs:ignore WordPress.Security.NonceVerification
}

/** Atributos del editor (vacío para visitantes). */
function sc_ed($path, $type = 'text')
{
	if (!sc_lx_can_edit()) {
		return '';
	}
	$multi = ($type === 'text' && preg_match('/\.(text|descripcion|a|sub|lead|resumen|intro)$/', $path)) ? ' data-sc-m="1"' : '';
	return ' data-sc="' . esc_attr($path) . '" data-sc-t="' . esc_attr($type) . '"' . $multi;
}

/** Elemento de una lista editable (permite agregar/quitar/mover). */
function sc_ed_li($list, $idx)
{
	if (!sc_lx_can_edit()) {
		return '';
	}
	return ' data-sc-li="' . esc_attr($list) . '" data-sc-i="' . (int) $idx . '"';
}

function sc_lx_t($path, $def = '')
{
	$v = sc_lx_get(sc_site(), $path, $def);
	return is_scalar($v) ? trim((string) $v) : (string) $def;
}

/** Texto escapado con saltos de línea. */
function sc_lx_h($s)
{
	return nl2br(esc_html((string) $s), false);
}

/** Elemento de texto editable. */
function sc_lx_el($tag, $class, $path, $def = '', $type = 'text')
{
	$v = sc_lx_t($path, $def);
	if ($v === '' && !sc_lx_editing()) {
		return '';
	}
	return '<' . $tag . ($class !== '' ? ' class="' . esc_attr($class) . '"' : '') . sc_ed($path, $type) . '>' . sc_lx_h($v) . '</' . $tag . '>';
}

function sc_lx_palette()
{
	$s = sc_site();
	$p = $s['design']['palette'] ?? array();
	return is_array($p) ? $p : array();
}

function sc_lx_motif()
{
	$s = sc_site();
	return (string) ($s['design']['motif'] ?? 'abstract');
}

/** URL con tokens: wa, tel, mail, page:<clave>, #ancla, rutas y URLs completas. */
function sc_lx_url($u)
{
	$u = trim((string) $u);
	if ($u === '') {
		return '#';
	}
	if ($u === 'wa') {
		return servicom_wa_url() ?: '#';
	}
	if ($u === 'tel') {
		return servicom_tel_href(servicom_biz('telefono')) ?: '#';
	}
	if ($u === 'mail') {
		$m = servicom_biz('correo');
		return $m !== '' ? 'mailto:' . $m : '#';
	}
	if (strpos($u, 'page:') === 0) {
		$ids = get_option('sc_page_ids', array());
		$k = substr($u, 5);
		if (is_array($ids) && !empty($ids[$k])) {
			$l = get_permalink((int) $ids[$k]);
			if ($l) {
				return $l;
			}
		}
		return home_url('/');
	}
	if ($u[0] === '#') {
		return $u;
	}
	if (strpos($u, 'svc:') === 0) {
		$m = get_option('sc_service_pages', array());
		$i = (int) substr($u, 4);
		if (is_array($m) && !empty($m[$i])) {
			$l = get_permalink((int) $m[$i]);
			if ($l) {
				return $l;
			}
		}
		return home_url('/');
	}
	if ($u[0] === '/') {
		return home_url($u);
	}
	return esc_url_raw($u) ?: '#';
}

/** Botón / enlace editable. $path apunta a {text,url}. */
function sc_lx_btn($path, $kind = 'primary', $icon = '', $extra = '')
{
	$b = sc_lx_get(sc_site(), $path, array());
	$text = is_array($b) ? trim((string) ($b['text'] ?? '')) : '';
	$url = is_array($b) ? (string) ($b['url'] ?? '') : '';
	if ($text === '') {
		return '';
	}
	$href = sc_lx_url($url);
	$ext = (strpos($href, 'http') === 0 && strpos($href, home_url()) !== 0);
	$ic = $icon !== '' && function_exists('sc_icon') ? sc_icon($icon, 'lx-btn__ic') : '';
	return '<a class="lx-btn lx-btn--' . esc_attr($kind) . ($extra !== '' ? ' ' . esc_attr($extra) : '') . '" href="' . esc_url($href) . '"' . ($ext ? ' target="_blank" rel="noopener noreferrer"' : '') . sc_ed($path, 'link') . '>'
		. $ic . '<span>' . esc_html($text) . '</span></a>';
}

/** Icono editable dentro de un contenedor. */
function sc_lx_icon($path, $class = 'lx-ico', $def = 'star')
{
	$k = sc_lx_t($path, $def);
	$svg = function_exists('sc_icon') ? sc_icon($k !== '' ? $k : $def) : '';
	return '<span class="' . esc_attr($class) . '"' . sc_ed($path, 'icon') . '>' . $svg . '</span>';
}

/** Imagen de un slot {id,seed} o arte generado. */
function sc_lx_media($path, $variant = 'card', $alt = '', $class = '', $size = 'large')
{
	$slot = sc_lx_get(sc_site(), $path, array());
	$id = is_array($slot) ? (int) ($slot['id'] ?? 0) : 0;
	$seed = is_array($slot) ? (int) ($slot['seed'] ?? 1) : 1;
	$inner = '';
	$has = false;
	if ($id > 0 && wp_attachment_is_image($id)) {
		$inner = wp_get_attachment_image($id, $size, false, array('class' => 'lx-img', 'alt' => $alt, 'loading' => 'lazy', 'decoding' => 'async'));
		$has = $inner !== '';
	}
	if ($inner === '') {
		if (function_exists('sc_art')) {
			$inner = sc_art($seed ?: 1, sc_lx_motif(), sc_lx_palette(), $variant);
		} else {
			$inner = '<div class="lx-art-fallback" aria-hidden="true"></div>';
		}
	}
	return '<div class="lx-media lx-media--' . esc_attr($variant) . ($has ? ' has-img' : ' is-art') . ($class !== '' ? ' ' . esc_attr($class) : '') . '"' . sc_ed($path, 'img') . '>' . $inner . '</div>';
}

function sc_lx_has_photo($path)
{
	$slot = sc_lx_get(sc_site(), $path, array());
	$id = is_array($slot) ? (int) ($slot['id'] ?? 0) : 0;
	return $id > 0 && wp_attachment_is_image($id);
}

/* ------------------------------------------------------------- orquestación */

function sc_render_page($key)
{
	$s = sc_site();
	if (strpos($key, 'svc:') === 0) {
		sc_lx_service_page((int) substr($key, 4));
		return;
	}
	$page = $s['pages'][$key] ?? null;
	if (!is_array($page) || empty($page['sections'])) {
		return;
	}
	$mood = (string) ($s['design']['mood'] ?? 'dark');
	echo '<main id="content" class="lx lx-' . esc_attr($mood) . ' lx-page lx-page--' . esc_attr($key) . '">' . "\n";
	foreach ($page['sections'] as $i => $sec) {
		if (!is_array($sec)) {
			continue;
		}
		$on = !empty($sec['on']);
		if (!$on && !sc_lx_editing()) {
			continue;
		}
		echo sc_lx_section($sec, 'pages.' . $key . '.sections.' . $i, $key, $on); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo "</main>\n";
}

function sc_lx_section(array $sec, $base, $pageKey, $on = true)
{
	$type = (string) ($sec['type'] ?? '');
	$fn = 'sc_sec_' . preg_replace('/[^a-z_]/', '', $type);
	if (!function_exists($fn)) {
		return '';
	}
	$id = preg_replace('/[^a-z0-9_-]/i', '', (string) ($sec['id'] ?? $type));
	$d = $base . '.data';
	ob_start();
	$fn($sec['data'] ?? array(), $d, $pageKey);
	$inner = ob_get_clean();
	if (trim($inner) === '') {
		return '';
	}
	$ed = sc_lx_can_edit() ? ' data-sc-sec="' . esc_attr($base) . '" data-sc-sec-label="' . esc_attr(sc_lx_section_label($type)) . '"' : '';
	return '<section id="' . esc_attr($id) . '" class="lx-sec lx-sec--' . esc_attr($type) . ($on ? '' : ' lx-off') . '"' . $ed . '>' . $inner . '</section>' . "\n";
}

function sc_lx_section_label($type)
{
	$l = array(
		'hero' => 'Portada', 'strip' => 'Franja de palabras', 'about' => 'Quiénes somos', 'services' => 'Servicios', 'values' => 'Por qué elegirnos',
		'process' => 'Cómo trabajamos', 'gallery' => 'Galería', 'quote' => 'Frase destacada', 'faq' => 'Preguntas frecuentes', 'cta' => 'Llamado a la acción',
		'contact' => 'Contacto', 'video' => 'Video', 'products' => 'Productos', 'text' => 'Texto', 'pagehero' => 'Encabezado de página',
	);
	return $l[$type] ?? $type;
}

/* ---------------------------------------------------------------- secciones */

function sc_lx_head($d, $eyebrow = 'eyebrow', $title = 'title', $lead = 'lead', $align = '')
{
	if (!sc_lx_editing() && sc_lx_t($d . '.' . $eyebrow) === '' && sc_lx_t($d . '.' . $title) === '' && sc_lx_t($d . '.' . $lead) === '') {
		return '';
	}
	$o = '<header class="lx-head' . ($align !== '' ? ' lx-head--' . $align : '') . ' lx-rv">';
	$o .= sc_lx_el('p', 'lx-eyebrow', $d . '.' . $eyebrow);
	$o .= sc_lx_el('h2', 'lx-title', $d . '.' . $title);
	$o .= sc_lx_el('p', 'lx-lead', $d . '.' . $lead);
	return $o . '</header>';
}

function sc_sec_hero($data, $d, $page)
{
	$variant = (string) (sc_site()['design']['hero'] ?? 'center');
	$variant = in_array($variant, array('center', 'split', 'splitr', 'left', 'full'), true) ? $variant : 'center';
	$hasPhoto = sc_lx_has_photo($d . '.img');
	$slides = array();
	foreach ((array) ($data['slides'] ?? array()) as $n => $sl) {
		if (sc_lx_has_photo($d . '.slides.' . $n)) {
			$slides[] = $n;
		}
	}
	echo '<div class="lx-hero lx-hero--' . esc_attr($variant) . ($hasPhoto || $slides ? ' has-photo' : '') . '">';
	echo '<div class="lx-hero__bg" data-lx-parallax>';
	if ($slides) {
		echo '<div class="lx-slides">';
		foreach ($slides as $k => $n) {
			echo '<div class="lx-slide' . ($k === 0 ? ' is-on' : '') . '">' . sc_lx_media($d . '.slides.' . $n, 'cover', '', 'lx-slide__m', 'full') . '</div>'; // phpcs:ignore
		}
		echo '</div>';
	} else {
		echo sc_lx_media($d . '.img', 'cover', '', '', 'full'); // phpcs:ignore
	}
	echo '</div><div class="lx-hero__shade"></div><div class="lx-hero__grain"></div>';
	echo '<div class="lx-wrap lx-hero__in"><div class="lx-hero__copy">';
	echo sc_lx_el('p', 'lx-eyebrow lx-eyebrow--hero lx-rv', $d . '.eyebrow'); // phpcs:ignore
	echo sc_lx_el('h1', 'lx-hero__title lx-rv', $d . '.title'); // phpcs:ignore
	echo sc_lx_el('p', 'lx-hero__sub lx-rv', $d . '.sub'); // phpcs:ignore
	echo '<div class="lx-hero__cta lx-rv">' . sc_lx_btn($d . '.btn1', 'primary', 'sparkles') . sc_lx_btn($d . '.btn2', 'ghost') . '</div>'; // phpcs:ignore
	echo '</div>';
	if ($variant === 'split' || $variant === 'splitr') {
		echo '<div class="lx-hero__side lx-rv">' . sc_lx_media($d . '.side', 'portrait', '', 'lx-hero__frame', 'large') . '<span class="lx-hero__badge">' . sc_lx_icon($d . '.badge_icon', 'lx-ico lx-ico--lg', 'crown') . '</span></div>'; // phpcs:ignore
	}
	echo '</div>';
	echo '<a class="lx-scroll" href="#siguiente" aria-label="Bajar"><span></span></a>';
	echo '</div><span id="siguiente"></span>';
}

function sc_sec_strip($data, $d, $page)
{
	$items = array_values(array_filter((array) ($data['items'] ?? array()), function ($x) {
		return is_string($x) && trim($x) !== '';
	}));
	if (!$items) {
		return;
	}
	$row = '';
	foreach ($items as $n => $it) {
		$row .= '<span class="lx-strip__i"' . sc_ed($d . '.items.' . $n, 'text') . sc_ed_li($d . '.items', $n) . '>' . esc_html($it) . '</span><span class="lx-strip__d" aria-hidden="true">' . (function_exists('sc_icon') ? sc_icon('diamond') : '✦') . '</span>';
	}
	echo '<div class="lx-strip" aria-hidden="false"><div class="lx-strip__t"><div class="lx-strip__g">' . $row . '</div><div class="lx-strip__g" aria-hidden="true">' . $row . '</div></div></div>'; // phpcs:ignore
}

function sc_sec_about($data, $d, $page)
{
	$side = (string) ($data['side'] ?? 'left');
	echo '<div class="lx-wrap lx-about lx-about--' . esc_attr($side) . '">';
	echo '<div class="lx-about__media lx-rv">' . sc_lx_media($d . '.img', 'portrait', '', 'lx-frame', 'large'); // phpcs:ignore
	echo '<span class="lx-about__seal">' . sc_lx_icon($d . '.seal_icon', 'lx-ico lx-ico--lg', 'award') . '</span></div>';
	echo '<div class="lx-about__body lx-rv">';
	echo sc_lx_el('p', 'lx-eyebrow', $d . '.eyebrow'); // phpcs:ignore
	echo sc_lx_el('h2', 'lx-title', $d . '.title'); // phpcs:ignore
	echo sc_lx_el('p', 'lx-about__lead', $d . '.lead'); // phpcs:ignore
	echo sc_lx_el('div', 'lx-about__text', $d . '.text'); // phpcs:ignore
	$pts = (array) ($data['points'] ?? array());
	if ($pts) {
		echo '<ul class="lx-points">';
		foreach ($pts as $n => $p) {
			echo '<li class="lx-point"' . sc_ed_li($d . '.points', $n) . '>' . sc_lx_icon($d . '.points.' . $n . '.icon', 'lx-ico', 'check') . '<span' . sc_ed($d . '.points.' . $n . '.text', 'text') . '>' . esc_html((string) ($p['text'] ?? '')) . '</span></li>'; // phpcs:ignore
		}
		echo '</ul>';
	}
	echo sc_lx_btn($d . '.btn', 'ghost'); // phpcs:ignore
	echo '</div></div>';
}

function sc_sec_services($data, $d, $page)
{
	$svcs = (array) (sc_site()['services'] ?? array());
	$limit = (int) ($data['limit'] ?? 0);
	$cards = '';
	$count = 0;
	$anyPhoto = false;
	foreach ($svcs as $n => $s) {
		if (is_array($s) && !empty($s['on']) && sc_lx_has_photo('services.' . $n . '.img')) {
			$anyPhoto = true;
			break;
		}
	}
	foreach ($svcs as $n => $s) {
		if (!is_array($s) || empty($s['on']) && !sc_lx_editing()) {
			continue;
		}
		if ($limit && $count >= $limit) {
			break;
		}
		$count++;
		$b = 'services.' . $n;
		$has = $anyPhoto || sc_lx_has_photo($b . '.img');
		$url = sc_lx_url('svc:' . $n);
		$cards .= '<article class="lx-card lx-rv" data-d="' . (int) ($count % 3) . '"' . (sc_lx_can_edit() ? ' data-sc-item="' . esc_attr($b) . '"' : '') . '>';
		if ($has) {
			$cards .= '<a class="lx-card__media" href="' . esc_url($url) . '" tabindex="-1" aria-hidden="true">' . sc_lx_media($b . '.img', 'card', '', '', 'large') . '</a>';
		}
		$cards .= '<div class="lx-card__body">' . sc_lx_icon($b . '.icono', 'lx-ico lx-ico--ring', 'star');
		$cards .= '<h3 class="lx-card__title"><a href="' . esc_url($url) . '"' . sc_ed($b . '.nombre', 'text') . '>' . esc_html((string) ($s['nombre'] ?? '')) . '</a></h3>';
		$cards .= '<p class="lx-card__text"' . sc_ed($b . '.resumen', 'text') . '>' . esc_html((string) ($s['resumen'] ?? '')) . '</p>';
		$cards .= '<a class="lx-more" href="' . esc_url($url) . '"><span>' . esc_html(sc_lx_t($d . '.more', 'Ver más')) . '</span>' . (function_exists('servicom_icon') ? servicom_icon('arrow', 18) : '→') . '</a>';
		$cards .= '</div></article>';
	}
	if ($cards === '') {
		return;
	}
	echo '<div class="lx-wrap">' . sc_lx_head($d, 'eyebrow', 'title', 'lead', 'center'); // phpcs:ignore
	echo '<div class="lx-grid lx-grid--cards">' . $cards . '</div>'; // phpcs:ignore
	if ($limit && count($svcs) > $limit) {
		echo '<p class="lx-center lx-rv">' . sc_lx_btn($d . '.btn', 'ghost') . '</p>'; // phpcs:ignore
	}
	echo '</div>';
}

function sc_sec_values($data, $d, $page)
{
	$items = (array) ($data['items'] ?? array());
	if (!$items) {
		return;
	}
	echo '<div class="lx-wrap">' . sc_lx_head($d, 'eyebrow', 'title', 'lead', 'center') . '<div class="lx-grid lx-grid--values is-' . count($items) . '">'; // phpcs:ignore
	foreach ($items as $n => $it) {
		$b = $d . '.items.' . $n;
		echo '<div class="lx-value lx-rv" data-d="' . (int) ($n % 3) . '"' . sc_ed_li($d . '.items', $n) . '>';
		echo '<span class="lx-value__n" aria-hidden="true">' . str_pad((string) ($n + 1), 2, '0', STR_PAD_LEFT) . '</span>';
		echo sc_lx_icon($b . '.icon', 'lx-ico lx-ico--ring', 'gem'); // phpcs:ignore
		echo '<h3 class="lx-value__t"' . sc_ed($b . '.title', 'text') . '>' . esc_html((string) ($it['title'] ?? '')) . '</h3>';
		echo '<p class="lx-value__x"' . sc_ed($b . '.text', 'text') . '>' . esc_html((string) ($it['text'] ?? '')) . '</p></div>';
	}
	echo '</div></div>';
}

function sc_sec_process($data, $d, $page)
{
	$items = (array) ($data['items'] ?? array());
	if (!$items) {
		return;
	}
	echo '<div class="lx-wrap">' . sc_lx_head($d, 'eyebrow', 'title', 'lead', 'center') . '<ol class="lx-steps">'; // phpcs:ignore
	foreach ($items as $n => $it) {
		$b = $d . '.items.' . $n;
		echo '<li class="lx-step lx-rv" data-d="' . (int) ($n % 4) . '"' . sc_ed_li($d . '.items', $n) . '><span class="lx-step__n">' . ($n + 1) . '</span>';
		echo '<h3 class="lx-step__t"' . sc_ed($b . '.title', 'text') . '>' . esc_html((string) ($it['title'] ?? '')) . '</h3>';
		echo '<p class="lx-step__x"' . sc_ed($b . '.text', 'text') . '>' . esc_html((string) ($it['text'] ?? '')) . '</p></li>';
	}
	echo '</ol></div>';
}

function sc_sec_gallery($data, $d, $page)
{
	$items = (array) ($data['items'] ?? array());
	$tiles = '';
	foreach ($items as $n => $it) {
		if (!sc_lx_has_photo($d . '.items.' . $n)) {
			continue;
		}
		$id = (int) ($it['id'] ?? 0);
		$full = wp_get_attachment_image_url($id, 'full');
		$tiles .= '<a class="lx-tile lx-rv lx-tile--' . (($n % 5 === 0) ? 'w' : (($n % 7 === 3) ? 't' : 'n')) . '" href="' . esc_url((string) $full) . '" data-lx-lightbox' . sc_ed_li($d . '.items', $n) . '>' . sc_lx_media($d . '.items.' . $n, 'card', '', '', 'large') . '<span class="lx-tile__zoom">' . (function_exists('servicom_icon') ? servicom_icon('search', 20) : '+') . '</span></a>'; // phpcs:ignore
	}
	if ($tiles === '') {
		return;
	}
	echo '<div class="lx-wrap">' . sc_lx_head($d, 'eyebrow', 'title', 'lead', 'center') . '<div class="lx-gallery">' . $tiles . '</div></div>'; // phpcs:ignore
}

function sc_sec_quote($data, $d, $page)
{
	$q = sc_lx_t($d . '.text');
	if ($q === '') {
		return;
	}
	echo '<div class="lx-quote"><div class="lx-quote__bg">' . sc_lx_media($d . '.img', 'wide', '', '', 'full') . '</div><div class="lx-wrap lx-quote__in lx-rv">'; // phpcs:ignore
	echo '<span class="lx-quote__mark" aria-hidden="true">“</span>';
	echo '<blockquote class="lx-quote__t"' . sc_ed($d . '.text', 'text') . '>' . esc_html($q) . '</blockquote>';
	echo sc_lx_el('p', 'lx-quote__by', $d . '.by'); // phpcs:ignore
	echo '</div></div>';
}

function sc_sec_faq($data, $d, $page)
{
	$items = (array) ($data['items'] ?? array());
	if (!$items) {
		return;
	}
	echo '<div class="lx-wrap lx-wrap--narrow">' . sc_lx_head($d, 'eyebrow', 'title', 'lead', 'center') . '<div class="lx-faq">'; // phpcs:ignore
	foreach ($items as $n => $it) {
		$b = $d . '.items.' . $n;
		echo '<details class="lx-qa lx-rv"' . ($n === 0 ? ' open' : '') . sc_ed_li($d . '.items', $n) . '><summary><span' . sc_ed($b . '.q', 'text') . '>' . esc_html((string) ($it['q'] ?? '')) . '</span><i aria-hidden="true">' . (function_exists('servicom_icon') ? servicom_icon('chevron', 18) : '+') . '</i></summary>';
		echo '<div class="lx-qa__a"><p' . sc_ed($b . '.a', 'text') . '>' . esc_html((string) ($it['a'] ?? '')) . '</p></div></details>';
	}
	echo '</div></div>';
}

function sc_sec_cta($data, $d, $page)
{
	echo '<div class="lx-cta"><div class="lx-cta__bg">' . sc_lx_media($d . '.img', 'wide', '', '', 'full') . '</div><div class="lx-cta__shade"></div>'; // phpcs:ignore
	echo '<div class="lx-wrap lx-cta__in lx-rv">';
	echo sc_lx_el('p', 'lx-eyebrow lx-eyebrow--hero', $d . '.eyebrow'); // phpcs:ignore
	echo sc_lx_el('h2', 'lx-cta__t', $d . '.title'); // phpcs:ignore
	echo sc_lx_el('p', 'lx-cta__x', $d . '.text'); // phpcs:ignore
	echo '<div class="lx-cta__b">' . sc_lx_btn($d . '.btn1', 'primary', 'whatsapp') . sc_lx_btn($d . '.btn2', 'ghost', 'phone') . '</div>'; // phpcs:ignore
	echo '</div></div>';
}

function sc_lx_contact_rows()
{
	$tel = servicom_biz('telefono');
	$wa = servicom_biz('whatsapp');
	$mail = servicom_biz('correo');
	$addr = servicom_biz('direccion');
	$hours = servicom_biz('horario');
	$rows = array();
	if ($tel !== '') {
		$rows[] = array('phone', 'Teléfono', $tel, servicom_tel_href($tel));
	}
	if ($wa !== '') {
		$rows[] = array('whatsapp', 'WhatsApp', '+' . servicom_digits($wa), servicom_wa_url());
	}
	if ($mail !== '' && is_email($mail)) {
		$rows[] = array('mail', 'Correo', $mail, 'mailto:' . $mail);
	}
	if ($addr !== '') {
		$rows[] = array('pin', 'Dirección', $addr, servicom_map_url());
	}
	if ($hours !== '') {
		$rows[] = array('clock', 'Horario', $hours, '');
	}
	return $rows;
}

function sc_sec_contact($data, $d, $page)
{
	echo '<div class="lx-wrap">' . sc_lx_head($d, 'eyebrow', 'title', 'lead', 'center') . '<div class="lx-contact">'; // phpcs:ignore
	echo '<div class="lx-contact__info lx-rv">';
	foreach (sc_lx_contact_rows() as $r) {
		$val = servicom_multiline($r[2]);
		echo '<div class="lx-crow"><span class="lx-ico lx-ico--ring">' . sc_icon($r[0]) . '</span><div><b>' . esc_html($r[1]) . '</b>'; // phpcs:ignore
		echo $r[3] !== '' ? '<a href="' . esc_url($r[3]) . '"' . (strpos($r[3], 'http') === 0 ? ' target="_blank" rel="noopener"' : '') . '>' . $val . '</a>' : '<span>' . $val . '</span>'; // phpcs:ignore
		echo '</div></div>';
	}
	$soc = servicom_socials_html('lx-social');
	if ($soc !== '') {
		echo '<div class="lx-crow lx-crow--soc"><div><b>Síganos</b>' . $soc . '</div></div>'; // phpcs:ignore
	}
	$map = servicom_map_url();
	if ($map) {
		echo '<a class="lx-btn lx-btn--ghost lx-btn--block" href="' . esc_url($map) . '" target="_blank" rel="noopener">' . sc_icon('route', 'lx-btn__ic') . '<span>Cómo llegar</span></a>'; // phpcs:ignore
	}
	echo '</div><div class="lx-contact__form lx-rv" id="contacto-form"><div class="lx-formcard">';
	echo sc_lx_el('h3', 'lx-formcard__t', $d . '.form_title'); // phpcs:ignore
	echo do_shortcode('[sc_contact_form]'); // phpcs:ignore
	echo '</div></div></div></div>';
}

function sc_sec_video($data, $d, $page)
{
	$u = sc_lx_t($d . '.url');
	if ($u === '' || !preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})~', $u, $m)) {
		return;
	}
	echo '<div class="lx-wrap lx-wrap--narrow">' . sc_lx_head($d, 'eyebrow', 'title', 'lead', 'center'); // phpcs:ignore
	echo '<div class="lx-video lx-rv" data-lx-yt="' . esc_attr($m[1]) . '"><img src="https://i.ytimg.com/vi/' . esc_attr($m[1]) . '/hqdefault.jpg" alt="" loading="lazy"><button type="button" aria-label="Reproducir video">' . sc_icon('play') . '</button></div></div>'; // phpcs:ignore
}

function sc_sec_products($data, $d, $page)
{
	if (!class_exists('WooCommerce')) {
		return;
	}
	$n = max(1, min(24, (int) ($data['limit'] ?? 8)));
	echo '<div class="lx-wrap">' . sc_lx_head($d, 'eyebrow', 'title', 'lead', 'center') . '<div class="lx-products lx-rv">' . do_shortcode('[products limit="' . $n . '" columns="4" orderby="date"]') . '</div>'; // phpcs:ignore
	echo '<p class="lx-center">' . sc_lx_btn($d . '.btn', 'ghost') . '</p></div>'; // phpcs:ignore
}

function sc_sec_text($data, $d, $page)
{
	echo '<div class="lx-wrap lx-wrap--narrow lx-prose lx-rv">' . sc_lx_el('h2', 'lx-title', $d . '.title') . sc_lx_el('div', 'lx-prose__b', $d . '.text') . '</div>'; // phpcs:ignore
}

function sc_sec_pagehero($data, $d, $page)
{
	echo '<div class="lx-pagehero"><div class="lx-pagehero__bg">' . sc_lx_media($d . '.img', 'wide', '', '', 'full') . '</div><div class="lx-hero__shade"></div><div class="lx-hero__grain"></div>'; // phpcs:ignore
	echo '<div class="lx-wrap lx-pagehero__in">';
	echo sc_lx_el('p', 'lx-eyebrow lx-eyebrow--hero lx-rv', $d . '.eyebrow'); // phpcs:ignore
	echo sc_lx_el('h1', 'lx-pagehero__t lx-rv', $d . '.title'); // phpcs:ignore
	echo sc_lx_el('p', 'lx-pagehero__x lx-rv', $d . '.lead'); // phpcs:ignore
	echo '</div></div>';
}

/** Página de detalle de un servicio. */
function sc_lx_service_page($idx)
{
	$s = sc_site();
	$sv = $s['services'][$idx] ?? null;
	if (!is_array($sv)) {
		return;
	}
	$mood = (string) ($s['design']['mood'] ?? 'dark');
	$b = 'services.' . $idx;
	echo '<main id="content" class="lx lx-' . esc_attr($mood) . ' lx-page lx-page--service">';
	echo '<section class="lx-sec lx-sec--pagehero"><div class="lx-pagehero"><div class="lx-pagehero__bg">' . sc_lx_media($b . '.img', 'wide', '', '', 'full') . '</div><div class="lx-hero__shade"></div><div class="lx-hero__grain"></div>'; // phpcs:ignore
	echo '<div class="lx-wrap lx-pagehero__in"><p class="lx-eyebrow lx-eyebrow--hero">' . esc_html(sc_lx_t('brand.servicios_label', 'Servicios')) . '</p>';
	echo '<h1 class="lx-pagehero__t"' . sc_ed($b . '.nombre', 'text') . '>' . esc_html((string) ($sv['nombre'] ?? '')) . '</h1>';
	echo '<p class="lx-pagehero__x"' . sc_ed($b . '.resumen', 'text') . '>' . esc_html((string) ($sv['resumen'] ?? '')) . '</p></div></div></section>';
	echo '<section class="lx-sec lx-sec--service"><div class="lx-wrap lx-svc"><div class="lx-svc__media lx-rv">' . sc_lx_media($b . '.img', 'portrait', '', 'lx-frame', 'large') . '</div>'; // phpcs:ignore
	echo '<div class="lx-svc__body lx-rv">' . sc_lx_icon($b . '.icono', 'lx-ico lx-ico--ring lx-ico--lg', 'star'); // phpcs:ignore
	echo '<div class="lx-svc__text"' . sc_ed($b . '.descripcion', 'text') . '>' . sc_lx_h((string) ($sv['descripcion'] ?? '')) . '</div>'; // phpcs:ignore
	echo '<div class="lx-svc__cta">' . sc_lx_btn('pages.home.sections.0.data.btn1', 'primary', 'whatsapp') . '<a class="lx-btn lx-btn--ghost" href="' . esc_url(sc_lx_url('page:contacto')) . '"><span>Contacto</span></a></div>'; // phpcs:ignore
	echo '</div></div></section>';
	// Otros servicios
	$others = '';
	$c = 0;
	foreach ((array) ($s['services'] ?? array()) as $n => $o) {
		if ($n === $idx || !is_array($o) || empty($o['on'])) {
			continue;
		}
		if ($c++ >= 3) {
			break;
		}
		$ob = 'services.' . $n;
		$others .= '<article class="lx-card lx-rv"><div class="lx-card__body">' . sc_lx_icon($ob . '.icono', 'lx-ico lx-ico--ring', 'star') . '<h3 class="lx-card__title"><a href="' . esc_url(sc_lx_url('svc:' . $n)) . '">' . esc_html((string) ($o['nombre'] ?? '')) . '</a></h3><p class="lx-card__text">' . esc_html((string) ($o['resumen'] ?? '')) . '</p></div></article>';
	}
	if ($others !== '') {
		echo '<section class="lx-sec lx-sec--services lx-sec--alt"><div class="lx-wrap"><header class="lx-head lx-head--center"><h2 class="lx-title">Otros servicios</h2></header><div class="lx-grid lx-grid--cards">' . $others . '</div></div></section>'; // phpcs:ignore
	}
	$cta = $s['pages']['home']['sections'] ?? array();
	foreach ($cta as $i => $sec) {
		if (($sec['type'] ?? '') === 'cta') {
			echo sc_lx_section($sec, 'pages.home.sections.' . $i, 'home', true); // phpcs:ignore
			break;
		}
	}
	echo '</main>';
}

/* -------------------------------------------------- botones flotantes y pie */

add_action('wp_footer', 'sc_lx_floats', 5);
function sc_lx_floats()
{
	if (!sc_lx_active()) {
		return;
	}
	$wa = servicom_wa_url();
	$tel = servicom_tel_href(servicom_biz('telefono'));
	$mail = servicom_biz('correo');
	$btns = '';
	if ($mail !== '' && is_email($mail)) {
		$btns .= '<a class="lx-fab lx-fab--mail" href="mailto:' . esc_attr($mail) . '" aria-label="Escribir un correo"><span class="lx-fab__tip">Correo</span>' . sc_icon('mail') . '</a>';
	}
	if ($tel) {
		$btns .= '<a class="lx-fab lx-fab--tel" href="' . esc_attr($tel) . '" aria-label="Llamar"><span class="lx-fab__tip">Llamar</span>' . sc_icon('phone') . '</a>';
	}
	if ($wa) {
		$btns .= '<a class="lx-fab lx-fab--wa" href="' . esc_url($wa) . '" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp"><span class="lx-fab__tip">WhatsApp</span>' . sc_icon('whatsapp') . '</a>';
	}
	if ($btns !== '') {
		echo '<div class="lx-fabs" role="complementary" aria-label="Contacto rápido">' . $btns . '</div>'; // phpcs:ignore
	}
	echo '<button type="button" class="lx-top" aria-label="Volver arriba" hidden>' . servicom_icon('chevron', 20) . '</button>'; // phpcs:ignore
	echo '<div class="lx-lightbox" hidden><button type="button" class="lx-lightbox__x" aria-label="Cerrar">' . servicom_icon('close', 22) . '</button></div>'; // phpcs:ignore
}


/* ---------------------------------------------------------- menú propio e interruptor claro/oscuro */

/** ¿La web ofrece los dos modos (claro y oscuro)? Requiere los colores base de la marca. */
function sc_lx_has_alt()
{
	$b = sc_site()['design']['base']['primary'] ?? '';
	return is_string($b) && preg_match('/^#[0-9a-fA-F]{6}$/', $b) && !sc_lx_editing();
}

add_filter('language_attributes', function ($out) {
	if (sc_lx_active()) {
		$out .= ' data-lx-theme="' . esc_attr((string) (sc_site()['design']['mood'] ?? 'light')) . '"';
	}
	return $out;
});

// Recuerda la elección del visitante antes de pintar (sin parpadeo)
add_action('wp_head', function () {
	if (sc_lx_active() && sc_lx_has_alt()) {
		echo "<script>(function(){try{var t=localStorage.getItem('sc_lx_theme');if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-lx-theme',t);}}catch(e){}})();</script>\n";
	}
}, 1);

/** Botón sol/luna del encabezado. */
function sc_lx_theme_toggle()
{
	if (!sc_lx_active() || !sc_lx_has_alt()) {
		return '';
	}
	$sun = '<svg class="lx-tt__sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';
	$moon = '<svg class="lx-tt__moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.8 6.8 0 0 0 9.8 9.8z"/></svg>';
	return '<button type="button" class="lx-tt" aria-label="Cambiar entre modo claro y modo oscuro" title="Modo claro / oscuro">' . $sun . $moon . '</button>';
}

/** Menú principal armado directamente desde el contenido de la web (no depende de los menús de WordPress). */
function sc_lx_menu_html()
{
	if (!sc_lx_active()) {
		return '';
	}
	$site = sc_site();
	$ids = (array) get_option('sc_page_ids', array());
	$pages = (array) ($site['pages'] ?? array());
	$cur = (int) get_queried_object_id();
	$items = array();
	$items[] = array('Inicio', home_url('/'), is_front_page(), array());
	$order = array('servicios' => 'Servicios', 'nosotros' => 'Quiénes somos', 'galeria' => 'Galería', 'tienda' => 'Tienda', 'contacto' => 'Contacto');
	foreach ($order as $k => $fallback) {
		if (!isset($pages[$k])) {
			continue;
		}
		$pid = (int) ($ids[$k] ?? 0);
		$url = $pid ? (string) get_permalink($pid) : '';
		if ($url === '' || get_post_status($pid) !== 'publish') {
			continue;
		}
		$label = (string) get_the_title($pid);
		$label = $label !== '' ? $label : $fallback;
		$kids = array();
		if ($k === 'servicios') {
			foreach ((array) ($site['services'] ?? array()) as $sv) {
				$sp = (int) ($sv['post'] ?? 0);
				if (!$sp || empty($sv['on']) || get_post_status($sp) !== 'publish') {
					continue;
				}
				$kids[] = array((string) ($sv['nombre'] ?? ''), (string) get_permalink($sp), $cur === $sp);
			}
		}
		$items[] = array($label, $url, $cur === $pid, $kids);
	}
	if (count($items) < 2) {
		return '';
	}
	$chev = function_exists('servicom_icon') ? servicom_icon('chevron', 16) : '';
	$out = '<ul id="menu-principal-lx" class="sc-menu">';
	foreach ($items as $n => $it) {
		list($label, $url, $is, $kids) = $it;
		$cls = 'menu-item menu-item-' . ($n + 1) . ($is ? ' current-menu-item' : '') . ($kids ? ' menu-item-has-children' : '');
		$out .= '<li class="' . esc_attr($cls) . '"><a href="' . esc_url($url) . '"' . ($is ? ' aria-current="page"' : '') . '>' . esc_html($label) . '</a>';
		if ($kids) {
			$out .= '<button type="button" class="sc-sub-toggle" aria-expanded="false" aria-label="' . esc_attr(sprintf('Mostrar submenú de %s', $label)) . '">' . $chev . '</button><ul class="sub-menu">';
			foreach ($kids as $kd) {
				$out .= '<li class="menu-item' . ($kd[2] ? ' current-menu-item' : '') . '"><a href="' . esc_url($kd[1]) . '">' . esc_html($kd[0]) . '</a></li>';
			}
			$out .= '</ul>';
		}
		$out .= '</li>';
	}
	return $out . '</ul>';
}


/** Menú plano del pie de página (sin submenús), armado igual que el principal. */
function sc_lx_footer_menu_html()
{
	$h = sc_lx_menu_html();
	if ($h === '') {
		return '';
	}
	$h = preg_replace('#<button type="button" class="sc-sub-toggle".*?</button>#s', '', $h);
	$h = preg_replace('#<ul class="sub-menu">.*?</ul>#s', '', $h);
	return str_replace('<ul id="menu-principal-lx" class="sc-menu">', '<ul class="sc-footer__menu">', (string) $h);
}

/* ---------------------------------------------------------- estilos y datos */

add_filter('body_class', function ($c) {
	if (sc_lx_active()) {
		$c[] = 'sc-luxe';
		$c[] = 'lx-body-' . (string) (sc_site()['design']['mood'] ?? 'light');
		if (function_exists('sc_design_dna_classes')) {
			foreach (sc_design_dna_classes(sc_site()['design']['dna'] ?? array()) as $dc) {
				$c[] = $dc;
			}
		}
		if (sc_lx_editing()) {
			$c[] = 'sc-editing';
		}
	}
	return $c;
});

add_action('wp_head', 'sc_lx_head_css', 99);
function sc_lx_head_css()
{
	if (!sc_lx_active() || !function_exists('sc_design_css')) {
		return;
	}
	$s = sc_site();
	echo '<style id="sc-luxe-vars">' . sc_design_css($s['design']) . '</style>' . "\n"; // phpcs:ignore
	$desc = sc_lx_t('seo.description');
	if ($desc !== '') {
		echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
	}
	$p = $s['design']['palette']['bg'] ?? '';
	if ($p) {
		echo '<meta name="theme-color" content="' . esc_attr($p) . '">' . "\n";
	}
	if (get_option('sc_mode', 'published') === 'published') {
		$name = servicom_name();
		$img = '';
		$hero = sc_lx_get($s, 'pages.home.sections.0.data.img.id', 0);
		if ((int) $hero > 0) {
			$img = (string) wp_get_attachment_image_url((int) $hero, 'large');
		}
		if ($img === '' && get_theme_mod('custom_logo')) {
			$img = (string) wp_get_attachment_image_url((int) get_theme_mod('custom_logo'), 'full');
		}
		echo '<meta property="og:type" content="website"><meta property="og:site_name" content="' . esc_attr($name) . '"><meta property="og:title" content="' . esc_attr(wp_get_document_title()) . '">';
		if ($desc !== '') {
			echo '<meta property="og:description" content="' . esc_attr($desc) . '">';
		}
		if ($img !== '') {
			echo '<meta property="og:image" content="' . esc_url($img) . '">';
		}
		echo "\n";
		$ld = array('@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $name, 'url' => home_url('/'));
		if ($desc !== '') {
			$ld['description'] = $desc;
		}
		if ($img !== '') {
			$ld['image'] = $img;
		}
		if (servicom_biz('telefono') !== '') {
			$ld['telephone'] = servicom_biz('telefono');
		}
		if (servicom_biz('correo') !== '') {
			$ld['email'] = servicom_biz('correo');
		}
		if (servicom_biz('direccion') !== '') {
			$ld['address'] = array('@type' => 'PostalAddress', 'streetAddress' => servicom_biz('direccion'));
		}
		if (servicom_biz('horario') !== '') {
			$ld['openingHours'] = servicom_biz('horario');
		}
		$same = array();
		foreach (array('facebook', 'instagram', 'tiktok', 'youtube', 'x', 'linkedin') as $k) {
			$u = servicom_biz($k);
			if ($u !== '' && preg_match('~^https?://~i', $u)) {
				$same[] = $u;
			}
		}
		if ($same) {
			$ld['sameAs'] = $same;
		}
		echo '<script type="application/ld+json">' . wp_json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n";
	}
}

add_action('wp_enqueue_scripts', function () {
	if (!sc_lx_active()) {
		return;
	}
	$u = SERVICOM_URI . '/assets/';
	$d = SERVICOM_DIR . '/assets/';
	$ver = function ($rel) use ($d) {
		$t = @filemtime($d . $rel);
		return SERVICOM_VER . ($t ? '.' . $t : '');
	};
	wp_enqueue_style('servicom-luxe', $u . 'css/luxe.css', array('servicom-style'), $ver('css/luxe.css'));
	wp_enqueue_script('servicom-luxe', $u . 'js/luxe.js', array(), $ver('js/luxe.js'), array('in_footer' => true, 'strategy' => 'defer'));
}, 10000);

/** Páginas luxe: el tema delega el render. */
function sc_lx_page_key($post_id = 0)
{
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if (!$post_id || !sc_lx_active()) {
		return '';
	}
	return (string) get_post_meta($post_id, '_sc_luxe', true);
}


/** Productos sin foto: arte generado en lugar de un recuadro vacío. */
add_filter('woocommerce_placeholder_img', function ($html, $size = '', $dim = array()) {
	if (!sc_lx_active() || !function_exists('sc_art')) {
		return $html;
	}
	static $n = 0;
	$n++;
	return '<span class="lx-media lx-media--card is-art lx-ph">' . sc_art(900 + $n * 13, sc_lx_motif(), sc_lx_palette(), 'square') . '</span>';
}, 10, 3);
