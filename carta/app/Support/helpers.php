<?php
// Este archivo solo se carga desde el arranque de la aplicación.
if (!defined('MG_ROOT')) { http_response_code(404); exit; }
/**
 * Funciones cortas de uso frecuente en vistas y controladores.
 */

use MenuGold\Core\Image;
use MenuGold\Core\Money;
use MenuGold\Core\Security;
use MenuGold\Core\Url;

if (!function_exists('e')) {
    function e($v) { return Security::e($v); }
}

if (!function_exists('mg_url')) {
    function mg_url($p = '/') { return Url::to($p); }
}

if (!function_exists('mg_asset')) {
    function mg_asset($p) { return Url::asset($p); }
}

if (!function_exists('mg_money')) {
    function mg_money($v, $c = null) { return Money::format($v, $c); }
}

if (!function_exists('mg_img')) {
    /**
     * Etiqueta <picture> responsiva con WebP, respaldo JPG y blur-up.
     *
     * @param string $base   ruta base sin sufijo, ej. "uploads/1/platillos/lomo-ab12"
     * @param array  $opts   alt, sizes, class, ratio, loading, fetchpriority
     */
    function mg_img($base, array $opts = array())
    {
        $o = array_merge(array(
            'alt' => '', 'sizes' => '100vw', 'class' => '', 'loading' => 'lazy',
            'fetchpriority' => '', 'decoding' => 'async', 'ratio' => '', 'mark' => '',
        ), $opts);

        if (!$base || !Image::exists($base)) {
            // Sin fotografía: marcador tipográfico deliberado, no un hueco roto.
            $mark = isset($o['mark']) && $o['mark'] !== ''
                ? $o['mark']
                : \MenuGold\Core\Str::initials($o['alt'] !== '' ? $o['alt'] : 'Menú');
            // Sin texto alternativo es decorativo: anunciarlo como imagen vacía
            // solo estorba a quien navega con lector de pantalla.
            $rol = $o['alt'] !== ''
                ? ' role="img" aria-label="' . e($o['alt']) . '"'
                : ' aria-hidden="true"';
            return '<span class="ph-img ' . e($o['class']) . '"' . $rol
                 . ($o['ratio'] !== '' ? ' style="aspect-ratio:' . e($o['ratio']) . '"' : '') . '>'
                 . '<span class="ph-mark" aria-hidden="true">' . e($mark) . '</span></span>';
        }

        $b = Url::to('/' . ltrim($base, '/'));
        $w = Image::WIDTHS;
        $webp = array();
        $jpg  = array();
        foreach ($w as $width) {
            $webp[] = $b . '-' . $width . '.webp ' . $width . 'w';
            $jpg[]  = $b . '-' . $width . '.jpg ' . $width . 'w';
        }
        $lqip = Image::lqip($base);
        $style = array();
        if ($o['ratio'] !== '') { $style[] = 'aspect-ratio:' . $o['ratio']; }
        if ($lqip !== '')       { $style[] = 'background-image:url(' . $lqip . ')'; }

        return '<picture class="mg-pic ' . e($o['class']) . '"'
             . ($style ? ' style="' . e(implode(';', $style)) . '"' : '') . '>'
             . '<source type="image/webp" srcset="' . e(implode(', ', $webp)) . '" sizes="' . e($o['sizes']) . '">'
             . '<img src="' . e($b . '-960.jpg') . '" srcset="' . e(implode(', ', $jpg)) . '" sizes="' . e($o['sizes']) . '"'
             . ' alt="' . e($o['alt']) . '" loading="' . e($o['loading']) . '" decoding="' . e($o['decoding']) . '"'
             . ($o['fetchpriority'] !== '' ? ' fetchpriority="' . e($o['fetchpriority']) . '"' : '')
             . ' onload="this.closest(\'.mg-pic\').classList.add(\'is-loaded\')">'
             . '</picture>';
    }
}

if (!function_exists('mg_img_src')) {
    /** URL directa de una variante (para OpenGraph o correos). */
    function mg_img_src($base, $width = 960, $ext = 'jpg')
    {
        if (!$base || !Image::exists($base)) { return ''; }
        return Url::abs('/' . ltrim($base, '/') . '-' . (int)$width . '.' . $ext);
    }
}

if (!function_exists('mg_wa')) {
    /** Enlace de WhatsApp con mensaje precargado. */
    function mg_wa($phone, $message = '')
    {
        $digits = \MenuGold\Core\Str::phoneDigits($phone);
        if ($digits === '') { return '#'; }
        return 'https://wa.me/' . $digits . ($message !== '' ? '?text=' . rawurlencode($message) : '');
    }
}

if (!function_exists('mg_roman')) {
    function mg_roman($n)
    {
        $n = (int)$n;
        if ($n < 1 || $n > 3999) { return (string)$n; }
        $map = array('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90,
                     'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
        $out = '';
        foreach ($map as $sym => $val) {
            while ($n >= $val) { $out .= $sym; $n -= $val; }
        }
        return $out;
    }
}

if (!function_exists('mg_ago')) {
    /** "hace 4 min" en español, sin dependencias de intl. */
    function mg_ago($datetime)
    {
        $ts = is_numeric($datetime) ? (int)$datetime : strtotime((string)$datetime);
        if (!$ts) { return ''; }
        $d = time() - $ts;
        if ($d < 60)    { return 'hace un momento'; }
        if ($d < 3600)  { $m = (int)floor($d / 60);    return 'hace ' . $m . ' min'; }
        if ($d < 86400) { $h = (int)floor($d / 3600);  return 'hace ' . $h . ($h === 1 ? ' hora' : ' horas'); }
        $dd = (int)floor($d / 86400);
        if ($dd < 30)   { return 'hace ' . $dd . ($dd === 1 ? ' día' : ' días'); }
        return date('d/m/Y', $ts);
    }
}

if (!function_exists('mg_date')) {
    function mg_date($datetime, $format = 'd/m/Y H:i')
    {
        $ts = is_numeric($datetime) ? (int)$datetime : strtotime((string)$datetime);
        return $ts ? date($format, $ts) : '';
    }
}

if (!function_exists('mg_old')) {
    /** Repuebla un formulario tras un error de validación. */
    function mg_old($key, $default = '')
    {
        $old = \MenuGold\Core\Session::get('_old', array());
        return isset($old[$key]) ? $old[$key] : $default;
    }
}

if (!function_exists('mg_tag_label')) {
    function mg_tag_label($tag)
    {
        $map = array(
            'popular' => 'Popular', 'nuevo' => 'Nuevo', 'picante' => 'Picante',
            'vegano' => 'Vegano', 'vegetariano' => 'Vegetariano', 'sin_gluten' => 'Sin gluten',
            'recomendado' => 'Recomendado', 'para_compartir' => 'Para compartir',
        );
        return isset($map[$tag]) ? $map[$tag] : ucfirst(str_replace('_', ' ', $tag));
    }
}
