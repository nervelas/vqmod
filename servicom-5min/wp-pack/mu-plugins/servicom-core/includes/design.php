<?php
/**
 * Servicom LUXE — motor de diseño: paleta desde el logo, contraste AA garantizado,
 * variables CSS (--lx-*) y puente a las variables del tema (--sc-*).
 * Funciona sin cargar WordPress (solo GD para leer imágenes).
 */
if (!defined('ABSPATH') && PHP_SAPI !== 'cli') {
	exit;
}

/* ----------------------------------------------------------------------------
 * Color: utilidades
 * ------------------------------------------------------------------------- */

function sc_hex2rgb($hex)
{
	$hex = ltrim((string) $hex, '#');
	if (strlen($hex) === 3) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
		return array(0, 0, 0);
	}
	return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

function sc_rgb2hex($r, $g, $b)
{
	$c = function ($v) {
		return str_pad(dechex(max(0, min(255, (int) round($v)))), 2, '0', STR_PAD_LEFT);
	};
	return '#' . $c($r) . $c($g) . $c($b);
}

/** @return array{0:float,1:float,2:float} h 0-360, s 0-1, l 0-1 */
function sc_rgb2hsl($r, $g, $b)
{
	$r /= 255;
	$g /= 255;
	$b /= 255;
	$max = max($r, $g, $b);
	$min = min($r, $g, $b);
	$l = ($max + $min) / 2;
	$d = $max - $min;
	if ($d < 1e-9) {
		return array(0.0, 0.0, $l);
	}
	$s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
	if ($max === $r) {
		$h = fmod((($g - $b) / $d), 6);
	} elseif ($max === $g) {
		$h = (($b - $r) / $d) + 2;
	} else {
		$h = (($r - $g) / $d) + 4;
	}
	$h *= 60;
	if ($h < 0) {
		$h += 360;
	}
	return array($h, $s, $l);
}

function sc_hsl2hex($h, $s, $l)
{
	$h = fmod(fmod($h, 360) + 360, 360);
	$s = max(0, min(1, $s));
	$l = max(0, min(1, $l));
	$c = (1 - abs(2 * $l - 1)) * $s;
	$x = $c * (1 - abs(fmod($h / 60, 2) - 1));
	$m = $l - $c / 2;
	if ($h < 60) {
		list($r, $g, $b) = array($c, $x, 0);
	} elseif ($h < 120) {
		list($r, $g, $b) = array($x, $c, 0);
	} elseif ($h < 180) {
		list($r, $g, $b) = array(0, $c, $x);
	} elseif ($h < 240) {
		list($r, $g, $b) = array(0, $x, $c);
	} elseif ($h < 300) {
		list($r, $g, $b) = array($x, 0, $c);
	} else {
		list($r, $g, $b) = array($c, 0, $x);
	}
	return sc_rgb2hex(($r + $m) * 255, ($g + $m) * 255, ($b + $m) * 255);
}

function sc_hex2hsl($hex)
{
	list($r, $g, $b) = sc_hex2rgb($hex);
	return sc_rgb2hsl($r, $g, $b);
}

function sc_lum($hex)
{
	list($r, $g, $b) = sc_hex2rgb($hex);
	$f = function ($v) {
		$v /= 255;
		return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
	};
	return 0.2126 * $f($r) + 0.7152 * $f($g) + 0.0722 * $f($b);
}

function sc_contrast($a, $b)
{
	$la = sc_lum($a);
	$lb = sc_lum($b);
	if ($la < $lb) {
		list($la, $lb) = array($lb, $la);
	}
	return ($la + 0.05) / ($lb + 0.05);
}

/** Mueve la luminosidad de $hex hasta lograr $min de contraste contra $bg. */
function sc_ensure_contrast($hex, $bg, $min = 4.5)
{
	if (sc_contrast($hex, $bg) >= $min) {
		return $hex;
	}
	list($h, $s, $l) = sc_hex2hsl($hex);
	$bgDark = sc_lum($bg) < 0.4;
	for ($i = 0; $i < 60; $i++) {
		$l += $bgDark ? 0.012 : -0.012;
		$l = max(0, min(1, $l));
		$c = sc_hsl2hex($h, $s, $l);
		if (sc_contrast($c, $bg) >= $min) {
			return $c;
		}
	}
	return $bgDark ? '#ffffff' : '#000000';
}

function sc_best_ink($bg, $light = '#ffffff', $dark = '#101014')
{
	return sc_contrast($bg, $light) >= sc_contrast($bg, $dark) ? $light : $dark;
}

function sc_mix($a, $b, $t)
{
	list($r1, $g1, $b1) = sc_hex2rgb($a);
	list($r2, $g2, $b2) = sc_hex2rgb($b);
	return sc_rgb2hex($r1 + ($r2 - $r1) * $t, $g1 + ($g2 - $g1) * $t, $b1 + ($b2 - $b1) * $t);
}

/* ----------------------------------------------------------------------------
 * Paleta desde el logo
 * ------------------------------------------------------------------------- */

/**
 * Colores dominantes (los que "identifican" la marca) de una imagen.
 * @return array{primary:string,accent:string,colors:array<int,string>}|null
 */
function sc_design_palette_from_image($path)
{
	if (!is_string($path) || !is_file($path) || !function_exists('imagecreatefromstring')) {
		return null;
	}
	$raw = @file_get_contents($path, false, null, 0, 6 * 1024 * 1024);
	if ($raw === false || $raw === '') {
		return null;
	}
	$im = @imagecreatefromstring($raw);
	if (!$im) {
		return null;
	}
	$w = imagesx($im);
	$h = imagesy($im);
	if ($w < 2 || $h < 2) {
		imagedestroy($im);
		return null;
	}
	$tw = 72;
	$th = max(8, (int) round($tw * $h / $w));
	if ($th > 96) {
		$th = 96;
	}
	$sm = imagecreatetruecolor($tw, $th);
	imagealphablending($sm, false);
	imagesavealpha($sm, true);
	imagefill($sm, 0, 0, imagecolorallocatealpha($sm, 0, 0, 0, 127));
	imagecopyresampled($sm, $im, 0, 0, 0, 0, $tw, $th, $w, $h);
	imagedestroy($im);

	$buckets = array();   // clave => [peso, r, g, b]
	$gray = 0;
	$total = 0;
	for ($y = 0; $y < $th; $y++) {
		for ($x = 0; $x < $tw; $x++) {
			$c = imagecolorat($sm, $x, $y);
			$a = ($c >> 24) & 0x7F;
			if ($a > 90) {
				continue;       // transparente
			}
			$r = ($c >> 16) & 0xFF;
			$g = ($c >> 8) & 0xFF;
			$b = $c & 0xFF;
			list($hh, $ss, $ll) = sc_rgb2hsl($r, $g, $b);
			$total++;
			if ($ss < 0.16 || $ll > 0.93 || $ll < 0.07) {
				$gray++;
				continue;       // blanco, negro y grises no son "color de marca"
			}
			$key = ((int) floor($hh / 15)) . ':' . ((int) floor($ss * 3)) . ':' . ((int) floor($ll * 4));
			$wgt = 1 + $ss * 2.2;                    // favorece colores vivos
			if (!isset($buckets[$key])) {
				$buckets[$key] = array(0.0, 0, 0, 0, 0);
			}
			$buckets[$key][0] += $wgt;
			$buckets[$key][1] += $r * $wgt;
			$buckets[$key][2] += $g * $wgt;
			$buckets[$key][3] += $b * $wgt;
			$buckets[$key][4]++;
		}
	}
	imagedestroy($sm);
	if (!$buckets || $total < 20) {
		return null;
	}
	uasort($buckets, function ($a, $b) {
		return $b[0] <=> $a[0];
	});
	$cols = array();
	foreach ($buckets as $bk) {
		if ($bk[4] < 3) {
			continue;
		}
		$hex = sc_rgb2hex($bk[1] / $bk[0], $bk[2] / $bk[0], $bk[3] / $bk[0]);
		$cols[] = array($hex, $bk[0]);
	}
	if (!$cols) {
		return null;
	}
	// Mínimo de "color" en el logo: si casi todo es gris, se descarta (se usará paleta por rubro)
	$colored = array_sum(array_column($cols, 1));
	if ($colored < 6) {
		return null;
	}
	// Colores distintos entre sí (por tono o luminosidad)
	$out = array();
	foreach ($cols as $c) {
		$ok = true;
		list($h1, $s1, $l1) = sc_hex2hsl($c[0]);
		foreach ($out as $o) {
			list($h2, $s2, $l2) = sc_hex2hsl($o);
			$dh = min(abs($h1 - $h2), 360 - abs($h1 - $h2));
			if ($dh < 28 && abs($l1 - $l2) < 0.22) {
				$ok = false;
				break;
			}
		}
		if ($ok) {
			$out[] = $c[0];
		}
		if (count($out) >= 5) {
			break;
		}
	}
	$primary = $out[0];
	$accent = $out[1] ?? null;
	return array('primary' => $primary, 'accent' => $accent, 'colors' => $out);
}

/** Paletas de lujo por rubro cuando no hay logo (o es monocromo). [primario, acento] */
function sc_design_rubro_colors($rubro)
{
	$t = array(
		'abogado'       => array('#1d3a6b', '#c9a45c'),
		'clinica'       => array('#0f6b6e', '#d6b98c'),
		'taller'        => array('#b3261e', '#f0a52b'),
		'ropa'          => array('#a8476a', '#d9b38c'),
		'restaurante'   => array('#8a1c2b', '#d4a24c'),
		'transporte'    => array('#1c4f9c', '#f2a900'),
		'contabilidad'  => array('#0e6b4f', '#d1a954'),
		'importaciones' => array('#16507f', '#c47a3c'),
		'otro'          => array('#3b3f8f', '#cda35a'),
	);
	return $t[$rubro] ?? $t['otro'];
}

/** Ambiente por estilo del formulario (1-5): oscuro/claro, tipografías, héroe. */
function sc_design_style($style)
{
	$s = array(
		1 => array('mood' => 'dark', 'head' => 'cormorant', 'body' => 'manrope', 'hero' => 'center', 'radius' => 3),
		2 => array('mood' => 'light', 'head' => 'fraunces', 'body' => 'inter', 'hero' => 'split', 'radius' => 6),
		3 => array('mood' => 'dark', 'head' => 'sora', 'body' => 'nunito', 'hero' => 'split', 'radius' => 14),
		4 => array('mood' => 'light', 'head' => 'playfair', 'body' => 'lato', 'hero' => 'center', 'radius' => 2),
		5 => array('mood' => 'light', 'head' => 'dmserif', 'body' => 'dmsans', 'hero' => 'full', 'radius' => 10),
	);
	return $s[(int) $style] ?? $s[1];
}

/**
 * Construye la paleta completa y verificada.
 * @param string $primary  color de marca principal (#rrggbb)
 * @param string $accent   color secundario/acento (#rrggbb) o ''
 * @param string $mood     'dark'|'light'
 */
function sc_design_build_palette($primary, $accent, $mood)
{
	list($ph, $ps, $pl) = sc_hex2hsl($primary);
	// Saturación mínima para que se perciba lujo (ni pastel apagado ni neón)
	$ps = max(0.28, min(0.88, $ps));
	if ($accent === '' || $accent === null) {
		// acento: dorado champán si el primario es frío; si es cálido, crema/dorado más claro
		$accent = ($ph > 35 && $ph < 70) ? sc_hsl2hex($ph + 18, 0.5, 0.62) : sc_hsl2hex(40, 0.52, 0.6);
	}
	list($ah, $as, $al) = sc_hex2hsl($accent);
	$as = max(0.3, min(0.9, $as));

	if ($mood === 'dark') {
		$bg      = sc_hsl2hex($ph, min(0.38, $ps * 0.6), 0.065);
		$bg2     = sc_hsl2hex($ph, min(0.34, $ps * 0.55), 0.095);
		$surface = sc_hsl2hex($ph, min(0.3, $ps * 0.5), 0.125);
		$ink     = sc_hsl2hex($ph, 0.14, 0.94);
		$muted   = sc_hsl2hex($ph, 0.1, 0.72);
		$line    = sc_mix($bg, $ink, 0.16);
		$pri     = sc_ensure_contrast(sc_hsl2hex($ph, min($ps, 0.6), max(0.52, min(0.66, $pl < 0.5 ? $pl + 0.2 : $pl))), $bg, 5.5);
		$acc     = sc_ensure_contrast(sc_hsl2hex($ah, min($as, 0.62), max(0.58, min(0.72, $al < 0.55 ? $al + 0.22 : $al))), $bg, 6);
		$dark    = sc_hsl2hex($ph, min(0.4, $ps * 0.6), 0.04);
		$dark_ink = $ink;
	} else {
		$bg      = sc_hsl2hex($ph, 0.22, 0.975);
		$bg2     = sc_hsl2hex($ph, 0.2, 0.945);
		$surface = '#ffffff';
		$ink     = sc_hsl2hex($ph, min(0.45, $ps * 0.7), 0.1);
		$muted   = sc_hsl2hex($ph, 0.14, 0.34);
		$line    = sc_hsl2hex($ph, 0.16, 0.86);
		$pri     = sc_ensure_contrast(sc_hsl2hex($ph, $ps, min(0.42, max(0.24, $pl))), $bg, 5.5);
		$acc     = sc_ensure_contrast(sc_hsl2hex($ah, $as, min(0.46, max(0.3, $al - 0.05))), $bg, 4.5);
		$dark    = sc_hsl2hex($ph, min(0.5, $ps * 0.75), 0.11);
		$dark_ink = sc_hsl2hex($ph, 0.14, 0.95);
	}
	$primary_ink = sc_best_ink($pri, '#ffffff', sc_hsl2hex($ph, 0.4, 0.07));
	$accent_ink  = sc_best_ink($acc, '#ffffff', sc_hsl2hex($ph, 0.4, 0.07));
	// Acento utilizable sobre bandas oscuras (en modo claro se necesita una versión luminosa)
	$acc_on_dark = sc_ensure_contrast(sc_hsl2hex($ah, $as, 0.68), $dark, 6);
	$pri_on_dark = sc_ensure_contrast(sc_hsl2hex($ph, $ps, 0.66), $dark, 5.5);
	return array(
		'bg' => $bg, 'bg2' => $bg2, 'surface' => $surface, 'ink' => $ink, 'muted' => $muted,
		'primary' => $pri, 'primary_ink' => $primary_ink, 'accent' => $acc, 'accent_ink' => $accent_ink,
		'line' => $line, 'dark' => $dark, 'dark_ink' => $dark_ink,
		'glow' => $mood === 'dark' ? $pri : sc_hsl2hex($ph, $ps, 0.5),
		'primary_dark' => $pri_on_dark, 'accent_dark' => $acc_on_dark, 'brand' => $primary,
	);
}

/**
 * Diseño completo del sitio.
 * @param string $logoPath ruta del logo (opcional)
 */
function sc_design_default($rubro, $style, $seed, $logoPath = '')
{
	$st = sc_design_style($style);
	$pal = $logoPath !== '' ? sc_design_palette_from_image($logoPath) : null;
	$source = 'logo';
	if ($pal) {
		$primary = $pal['primary'];
		$accent = $pal['accent'];
		if (!$accent) {
			$rc = sc_design_rubro_colors($rubro);
			// acento de lujo derivado del primario, no del rubro (mantiene la marca)
			$accent = null;
		}
	} else {
		$source = 'rubro';
		$rc = sc_design_rubro_colors($rubro);
		$primary = $rc[0];
		$accent = $rc[1];
		// Variación determinista por semilla para que no todas las webs del rubro sean iguales
		list($h, $s, $l) = sc_hex2hsl($primary);
		$primary = sc_hsl2hex($h + (($seed % 11) - 5) * 2, $s, $l);
	}
	$palette = sc_design_build_palette($primary, $accent === null ? '' : $accent, $st['mood']);
	$motifs = array('abogado' => 'scales', 'clinica' => 'pulse', 'taller' => 'gear', 'ropa' => 'fabric', 'restaurante' => 'plate', 'transporte' => 'route', 'contabilidad' => 'chart', 'importaciones' => 'globe');
	return array(
		'palette' => $palette,
		'fonts' => array('head' => $st['head'], 'body' => $st['body']),
		'mood' => $st['mood'],
		'hero' => $st['hero'],
		'radius' => $st['radius'],
		'motif' => $motifs[$rubro] ?? 'abstract',
		'seed' => (int) $seed,
		'source' => $source,
		'base' => array('primary' => $primary, 'accent' => $accent === null ? '' : $accent),
	);
}

function sc_design_font_stack($key, $kind)
{
	$head = array(
		'cormorant' => "'Cormorant Garamond','Cormorant',Georgia,'Times New Roman',serif",
		'playfair'  => "'Playfair Display',Georgia,'Times New Roman',serif",
		'fraunces'  => "'Fraunces',Georgia,'Times New Roman',serif",
		'dmserif'   => "'DM Serif Display',Georgia,'Times New Roman',serif",
		'sora'      => "'Sora',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif",
	);
	$body = array(
		'inter'   => "'Inter',system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif",
		'manrope' => "'Manrope',system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif",
		'dmsans'  => "'DM Sans',system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif",
		'lato'    => "'Lato',system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif",
		'nunito'  => "'Nunito Sans',system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif",
	);
	$t = $kind === 'head' ? $head : $body;
	return $t[$key] ?? reset($t);
}

function sc_design_rgba($hex, $a)
{
	list($r, $g, $b) = sc_hex2rgb($hex);
	return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . rtrim(rtrim(number_format((float) $a, 3, '.', ''), '0'), '.') . ')';
}

/** CSS (variables --lx-* y puente a --sc-* del tema). */
function sc_design_css(array $d)
{
	$p = $d['palette'];
	$dark = ($d['mood'] ?? 'light') === 'dark';
	$headF = sc_design_font_stack($d['fonts']['head'] ?? 'cormorant', 'head');
	$bodyF = sc_design_font_stack($d['fonts']['body'] ?? 'manrope', 'body');
	$r = (int) ($d['radius'] ?? 6);
	$v = array(
		'bg' => $p['bg'], 'bg2' => $p['bg2'], 'surface' => $p['surface'], 'ink' => $p['ink'], 'muted' => $p['muted'],
		'primary' => $p['primary'], 'primary-ink' => $p['primary_ink'], 'accent' => $p['accent'], 'accent-ink' => $p['accent_ink'],
		'line' => $p['line'], 'dark' => $p['dark'], 'dark-ink' => $p['dark_ink'], 'glow' => $p['glow'],
		'primary-dark' => $p['primary_dark'], 'accent-dark' => $p['accent_dark'],
		'primary-soft' => sc_design_rgba($p['primary'], 0.12), 'primary-soft2' => sc_design_rgba($p['primary'], 0.24),
		'accent-soft' => sc_design_rgba($p['accent'], 0.16), 'glow-soft' => sc_design_rgba($p['glow'], 0.35),
		'shadow' => $dark ? '0 24px 60px -28px rgba(0,0,0,.85)' : '0 24px 60px -30px ' . sc_design_rgba($p['dark'], 0.45),
		'primary-light' => sc_mix($p['primary'], '#ffffff', 0.26), 'primary-deep' => sc_mix($p['primary'], '#000000', 0.2), 'dark-soft' => sc_mix($p['dark'], '#ffffff', 0.12),
		'dark-ink-rgb' => implode(',', sc_hex2rgb($p['dark_ink'])),
		'dark-rgb' => implode(',', sc_hex2rgb($p['dark'])), 'primary-rgb' => implode(',', sc_hex2rgb($p['primary'])),
		'accent-rgb' => implode(',', sc_hex2rgb($p['accent'])), 'bg-rgb' => implode(',', sc_hex2rgb($p['bg'])), 'ink-rgb' => implode(',', sc_hex2rgb($p['ink'])),
		'radius' => $r . 'px', 'radius-lg' => ($r * 2 + 4) . 'px',
		'font-head' => $headF, 'font-body' => $bodyF,
	);
	$css = ':root{';
	foreach ($v as $k => $val) {
		$css .= '--lx-' . $k . ':' . $val . ';';
	}
	// Puente al tema existente (encabezado, pie, botones, formulario, tienda)
	$bridge = array(
		'bg' => $p['bg'], 'bg-alt' => $p['bg2'], 'surface' => $p['surface'], 'text' => $p['ink'], 'muted' => $p['muted'],
		'primary' => $p['primary'], 'primary-ink' => $p['primary_ink'], 'accent' => $p['accent'], 'border' => $p['line'],
		'radius' => $r . 'px', 'font-head' => $headF, 'font-body' => $bodyF,
		'tint' => sc_design_rgba($p['primary'], 0.12), 'tint-2' => sc_design_rgba($p['primary'], 0.26),
		'dark-bg' => $p['dark'], 'dark-ink' => $p['dark_ink'], 'dark-muted' => sc_mix($p['dark_ink'], $p['dark'], 0.3),
		'dark-surface' => sc_mix($p['dark'], $p['dark_ink'], 0.07), 'dark-border' => sc_mix($p['dark'], $p['dark_ink'], 0.18),
		'dark-primary' => $p['primary_dark'], 'dark-primary-ink' => sc_best_ink($p['primary_dark'], '#ffffff', $p['dark']),
		'header-bg' => sc_design_rgba($p['bg'], $dark ? 0.86 : 0.9), 'header-ink' => $p['ink'], 'header-line' => sc_design_rgba($p['primary'], 0.3),
		'footer-bg' => $p['dark'], 'footer-ink' => $p['dark_ink'], 'footer-muted' => sc_mix($p['dark_ink'], $p['dark'], 0.35),
		'footer-accent' => $p['accent_dark'], 'footer-line' => sc_mix($p['dark'], $p['dark_ink'], 0.16),
		'btn-bg' => 'linear-gradient(135deg,' . sc_mix($p['primary'], '#ffffff', $dark ? 0.22 : 0.12) . ' 0%,' . $p['primary'] . ' 55%,' . sc_mix($p['primary'], '#000000', 0.18) . ' 100%)',
		'btn-shadow' => '0 10px 28px -14px ' . sc_design_rgba($p['primary'], 0.7),
		'btn-shadow-hover' => '0 16px 36px -14px ' . sc_design_rgba($p['primary'], 0.85),
		'ghost-border' => sc_design_rgba($p['primary'], 0.6), 'card-border-hover' => sc_design_rgba($p['primary'], 0.55),
		'input-bg' => $dark ? $p['bg2'] : '#ffffff', 'input-border' => $p['line'], 'bar-bg' => $p['dark'], 'shine' => sc_design_rgba($p['primary'], 0.14),
	);
	foreach ($bridge as $k => $val) {
		$css .= '--sc-' . $k . ':' . $val . ';';
	}
	$css .= '}';
	return $css;
}
