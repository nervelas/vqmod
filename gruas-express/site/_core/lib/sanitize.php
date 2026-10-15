<?php
declare(strict_types=1);
if (!defined('GX')) { http_response_code(403); exit; }

/** Texto plano: sin caracteres de control, recortado. */
function gx_text($v, int $max = 500): string
{
    if (!is_string($v)) {
        return '';
    }
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr(trim($v), 0, $max);
}

/** Texto de una sola línea (sin saltos) apto para cabeceras. */
function gx_line($v, int $max = 200): string
{
    $v = gx_text($v, $max);
    return trim(preg_replace('/[\r\n\t]+/', ' ', $v) ?? '');
}

/**
 * URL permitida: http(s), mailto, tel, ruta relativa o ancla. Todo lo demás
 * (javascript:, data:, vbscript:, etc.) se descarta.
 */
function gx_safe_url($v, int $max = 500): string
{
    $v = gx_line($v, $max);
    $v = preg_replace('/[\s\x00-\x1F\x7F]+/u', '', $v) ?? '';
    if ($v === '') {
        return '';
    }
    if (preg_match('/^(https?:\/\/|mailto:|tel:)/i', $v)) {
        if (preg_match('/^https?:\/\//i', $v) && filter_var($v, FILTER_VALIDATE_URL) === false) {
            return '';
        }
        return $v;
    }
    if ($v[0] === '/' && !str_starts_with($v, '//')) {
        return $v;
    }
    if ($v[0] === '#') {
        return $v;
    }
    return '';
}

/**
 * HTML de textos: lista blanca estricta. Etiquetas permitidas sin atributos
 * (p, strong, b, em, i, br, ul, ol, li, h3, h4) y enlaces con href validado.
 * Todo lo demás se elimina o se escapa.
 */
function gx_clean_html($in, int $max = 20000): string
{
    if (!is_string($in)) {
        return '';
    }
    $in = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $in);
    $in = mb_substr($in, 0, $max);
    // bloques activos: se eliminan con su contenido (no solo la etiqueta)
    $in = preg_replace('~<\s*(script|style|iframe|object|embed|template|noscript|svg|math)\b[^>]*>.*?<\s*/\s*\1\s*>~is', '', $in) ?? '';
    $parts = preg_split('/(<[^>]*>)/u', $in, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    if ($parts === false) {
        return e($in);
    }
    $out = '';
    $stack = [];
    $simple = ['p', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'h3', 'h4'];
    foreach ($parts as $p) {
        $len = strlen($p);
        if ($len > 1 && $p[0] === '<' && $p[$len - 1] === '>') {
            if (preg_match('~^<\s*br\s*/?\s*>$~i', $p)) {
                $out .= '<br>';
            } elseif (preg_match('~^<\s*(p|strong|b|em|i|ul|ol|li|h3|h4)\s*>$~i', $p, $m)) {
                $t = strtolower($m[1]);
                if (in_array($t, $simple, true)) {
                    $out .= '<' . $t . '>';
                    $stack[] = $t;
                }
            } elseif (preg_match('~^<\s*/\s*(p|strong|b|em|i|ul|ol|li|h3|h4|a)\s*>$~i', $p, $m)) {
                $t = strtolower($m[1]);
                $idx = array_search($t, array_reverse($stack, true), true);
                if ($idx !== false) {
                    while (!empty($stack)) {
                        $top = array_pop($stack);
                        $out .= '</' . $top . '>';
                        if ($top === $t) {
                            break;
                        }
                    }
                }
            } elseif (preg_match('~^<\s*a\s+href\s*=\s*(?:"([^"]*)"|\'([^\']*)\')(?:\s+target="_blank")?(?:\s+rel="noopener noreferrer")?\s*>$~i', $p, $m)) {
                $href = gx_safe_url(html_entity_decode($m[1] !== '' ? $m[1] : ($m[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($href !== '') {
                    $ext = preg_match('/^https?:\/\//i', $href) ? ' target="_blank" rel="noopener noreferrer"' : '';
                    $out .= '<a href="' . e($href) . '"' . $ext . '>';
                    $stack[] = 'a';
                }
            }
            // cualquier otra etiqueta se descarta
        } else {
            $dec = html_entity_decode($p, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $dec = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $dec) ?? '';
            $out .= e($dec);
        }
    }
    while (!empty($stack)) {
        $out .= '</' . array_pop($stack) . '>';
    }
    return $out;
}


function gx_color($v, string $default): string
{
    return (is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v)) ? strtolower($v) : $default;
}

function gx_int($v, int $min, int $max, int $default = 0): int
{
    if (is_string($v) && preg_match('/^-?\d{1,9}$/', $v)) {
        $n = (int)$v;
        return max($min, min($max, $n));
    }
    return $default;
}
