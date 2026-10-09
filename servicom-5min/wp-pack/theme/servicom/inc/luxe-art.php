<?php
/**
 * Servicom LUXE - arte generado (SVG en linea) para slots de imagen sin foto.
 *
 * API publica:
 *   sc_art(int $seed, string $motif, array $palette, string $variant = 'cover'): string
 *   sc_art_motifs(): array
 *   sc_art_contrast_hint(array $palette): string   // 'dark' = el arte es oscuro (usar texto claro); 'light' = arte claro (texto oscuro)
 *
 * PHP 8.0, sin WordPress, sin scripts/enlaces/fuentes/imagenes externas.
 * Determinista por seed (los ids llevan un contador estatico para no colisionar;
 * sc_art_reset_counter() lo reinicia, util en pruebas).
 */

if (!defined('SC_LUXE_ART_LOADED')) {
define('SC_LUXE_ART_LOADED', 1);

function sc_art_motifs(): array
{
    return array('scales', 'pulse', 'gear', 'fabric', 'plate', 'route', 'chart', 'globe', 'abstract');
}

function sc_art_variants(): array
{
    // nombre => array(ancho, alto) del viewBox
    return array(
        'cover'    => array(1600, 900),
        'portrait' => array(1000, 1250),
        'square'   => array(1000, 1000),
        'card'     => array(1200, 800),
        'wide'     => array(2100, 900),
    );
}

function sc_art_reset_counter(): void
{
    sc__art_uid(true);
}

/* ------------------------------------------------------------------ utilidades */

function sc__art_uid(bool $reset = false): int
{
    static $n = 0;
    if ($reset) {
        $n = 0;
        return 0;
    }
    return ++$n;
}

function sc__art_n($v): string
{
    $s = number_format((float)$v, 1, '.', '');
    if (substr($s, -2) === '.0') {
        $s = substr($s, 0, -2);
    }
    return ($s === '-0') ? '0' : $s;
}

function sc__art_rng_init(int $seed, string $salt): int
{
    $s = (($seed * 2654435761) + crc32($salt) + 12345) & 0xFFFFFFFF;
    if ($s === 0) {
        $s = 0x9E3779B9;
    }
    for ($i = 0; $i < 6; $i++) {
        sc__art_rnd($s);
    }
    return $s;
}

/** xorshift32 -> float [0,1) */
function sc__art_rnd(int &$s): float
{
    $s ^= ($s << 13) & 0xFFFFFFFF;
    $s ^= ($s >> 17);
    $s ^= ($s << 5) & 0xFFFFFFFF;
    $s &= 0xFFFFFFFF;
    return $s / 4294967296.0;
}

function sc__art_rr(int &$s, float $a, float $b): float
{
    return $a + sc__art_rnd($s) * ($b - $a);
}

function sc__art_hex($v, string $def): string
{
    $v = is_string($v) ? trim($v) : '';
    if (preg_match('/^#([0-9a-fA-F]{3})$/', $v, $m)) {
        $h = $m[1];
        $v = '#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
        return $def;
    }
    return strtolower($v);
}

function sc__art_rgb(string $h): array
{
    return array(hexdec(substr($h, 1, 2)), hexdec(substr($h, 3, 2)), hexdec(substr($h, 5, 2)));
}

function sc__art_tohex(array $c): string
{
    $o = '#';
    foreach ($c as $x) {
        $o .= str_pad(dechex(max(0, min(255, (int)round($x)))), 2, '0', STR_PAD_LEFT);
    }
    return $o;
}

function sc__art_mix(string $a, string $b, float $t): string
{
    $A = sc__art_rgb($a);
    $B = sc__art_rgb($b);
    return sc__art_tohex(array(
        $A[0] + ($B[0] - $A[0]) * $t,
        $A[1] + ($B[1] - $A[1]) * $t,
        $A[2] + ($B[2] - $A[2]) * $t,
    ));
}

function sc__art_lum(string $h): float
{
    $c = sc__art_rgb($h);
    $f = function ($v) {
        $v = $v / 255;
        return ($v <= 0.03928) ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * $f($c[0]) + 0.7152 * $f($c[1]) + 0.0722 * $f($c[2]);
}

function sc__art_palette(array $p): array
{
    $d = array(
        'bg'      => '#101114',
        'bg2'     => '#1c1e25',
        'primary' => '#b8893c',
        'accent'  => '#d9b86a',
        'ink'     => '#f4efe6',
        'dark'    => '#09090b',
        'glow'    => '#f1d28a',
    );
    $o = array();
    foreach ($d as $k => $def) {
        $o[$k] = sc__art_hex($p[$k] ?? null, $def);
    }
    return $o;
}

/** Colores derivados segun el tono (oscuro/claro) del fondo. */
function sc__art_colors(array $P): array
{
    $dark = sc__art_lum(sc__art_mix($P['bg'], $P['bg2'], 0.4)) < 0.30;
    $K = array('dark' => $dark, 'P' => $P);
    $K['b1'] = $dark ? sc__art_mix($P['bg'], $P['dark'], 0.35) : sc__art_mix($P['bg'], '#ffffff', 0.35);
    $K['b2'] = $dark ? $P['bg2'] : sc__art_mix($P['bg2'], $P['primary'], 0.06);
    if ($dark) {
        $K['s1'] = $P['glow'];
        $K['s2'] = $P['accent'];
        $K['f1'] = sc__art_mix($P['primary'], $P['ink'], 0.12);
        $K['f2'] = $P['primary'];
        $K['hi'] = sc__art_mix($P['glow'], '#ffffff', 0.55);
        $K['m'] = array($P['primary'], $P['accent'], $P['glow'], sc__art_mix($P['bg2'], $P['primary'], 0.35), $P['primary']);
        $K['mo'] = array(0.62, 0.36, 0.30, 0.9, 0.38);
        $K['vig'] = $P['dark'];
        $K['vo'] = 0.62;
    } else {
        $K['s1'] = sc__art_mix($P['primary'], $P['dark'], 0.30);
        $K['s2'] = sc__art_mix($P['accent'], $P['primary'], 0.5);
        $K['f1'] = $P['primary'];
        $K['f2'] = sc__art_mix($P['primary'], $P['dark'], 0.4);
        $K['hi'] = '#ffffff';
        $K['m'] = array($P['accent'], $P['primary'], $P['glow'], '#ffffff', $P['accent']);
        $K['mo'] = array(0.55, 0.30, 0.55, 0.80, 0.35);
        $K['vig'] = sc__art_mix($P['dark'], $P['primary'], 0.4);
        $K['vo'] = 0.26;
    }
    return $K;
}

function sc_art_contrast_hint(array $palette): string
{
    $P = sc__art_palette($palette);
    $K = sc__art_colors($P);
    $base = sc__art_mix($K['b1'], $K['b2'], 0.5);
    $mesh = $K['dark']
        ? sc__art_mix($P['primary'], $P['accent'], 0.5)
        : sc__art_mix('#ffffff', $P['accent'], 0.4);
    $mixed = sc__art_mix($base, $mesh, 0.22);
    $c = sc__art_rgb($mixed);
    $k = $K['dark'] ? 0.82 : 0.93; // vineta
    $v = sc__art_tohex(array($c[0] * $k, $c[1] * $k, $c[2] * $k));
    return sc__art_lum($v) < 0.30 ? 'dark' : 'light';
}

/* ------------------------------------------------------------------ trazos */

/** Catmull-Rom -> bezier cubico. */
function sc__art_smooth(array $pts, bool $closed): string
{
    $n = count($pts);
    if ($n < 3) {
        return '';
    }
    $d = 'M' . sc__art_n($pts[0][0]) . ' ' . sc__art_n($pts[0][1]);
    $last = $closed ? $n : $n - 1;
    for ($i = 0; $i < $last; $i++) {
        $p0 = $pts[($i - 1 + $n) % $n];
        $p1 = $pts[$i];
        $p2 = $pts[($i + 1) % $n];
        $p3 = $pts[($i + 2) % $n];
        if (!$closed) {
            if ($i === 0) {
                $p0 = $p1;
            }
            if ($i + 2 >= $n) {
                $p3 = $p2;
            }
        }
        $d .= 'C' . sc__art_n($p1[0] + ($p2[0] - $p0[0]) / 6) . ' ' . sc__art_n($p1[1] + ($p2[1] - $p0[1]) / 6)
            . ' ' . sc__art_n($p2[0] - ($p3[0] - $p1[0]) / 6) . ' ' . sc__art_n($p2[1] - ($p3[1] - $p1[1]) / 6)
            . ' ' . sc__art_n($p2[0]) . ' ' . sc__art_n($p2[1]);
    }
    return $d . ($closed ? 'Z' : '');
}

function sc__art_blob(int &$r, float $cx, float $cy, float $rad, float $wob, int $n = 7): string
{
    $pts = array();
    $ph = sc__art_rnd($r) * 6.283;
    for ($i = 0; $i < $n; $i++) {
        $a = $ph + $i * 6.2832 / $n;
        $rr = $rad * (1 + ($wob * (sc__art_rnd($r) - 0.5) * 2));
        $pts[] = array($cx + cos($a) * $rr, $cy + sin($a) * $rr);
    }
    return sc__art_smooth($pts, true);
}

function sc__art_star(float $x, float $y, float $s): string
{
    $q = 'Q' . sc__art_n($x) . ' ' . sc__art_n($y) . ' ';
    return 'M' . sc__art_n($x) . ' ' . sc__art_n($y - $s) . $q . sc__art_n($x + $s) . ' ' . sc__art_n($y)
        . $q . sc__art_n($x) . ' ' . sc__art_n($y + $s) . $q . sc__art_n($x - $s) . ' ' . sc__art_n($y)
        . $q . sc__art_n($x) . ' ' . sc__art_n($y - $s) . 'Z';
}

function sc__art_gear(float $cx, float $cy, float $ro, int $t, float $rot, float $depth): string
{
    $ri = $ro - $depth;
    $step = 6.283185 / $t;
    $d = '';
    for ($k = 0; $k < $t; $k++) {
        $a = $rot + $k * $step;
        $o = array(array(-0.34, $ri), array(-0.17, $ro), array(0.17, $ro), array(0.34, $ri));
        foreach ($o as $j => $e) {
            $x = $cx + cos($a + $e[0] * $step) * $e[1];
            $y = $cy + sin($a + $e[0] * $step) * $e[1];
            $d .= ($k === 0 && $j === 0 ? 'M' : 'L') . sc__art_n($x) . ' ' . sc__art_n($y);
        }
    }
    return $d . 'Z';
}

function sc__art_circ(float $x, float $y, float $r, string $attr): string
{
    return '<circle cx="' . sc__art_n($x) . '" cy="' . sc__art_n($y) . '" r="' . sc__art_n($r) . '" ' . $attr . '/>';
}

/* ------------------------------------------------------------------ motivos (coordenadas locales -500..500) */

function sc__art_m_scales(array $K, string $P, string &$D, int &$r): string
{
    $S1 = 'url(#' . $P . 's1)';
    $S2 = $K['s2'];
    $F = 'url(#' . $P . 'f1)';
    $o = '<g opacity=".62">';
    // templo: frontón + columnas + peldaños
    $o .= '<path d="M-500-300L0-450L500-300Z" fill="' . $F . '" stroke="' . $S2 . '" stroke-width="2" stroke-opacity=".7" stroke-linejoin="round"/>';
    $o .= '<path d="M-400-320L0-420L400-320" fill="none" stroke="' . $S2 . '" stroke-width="1.2" stroke-opacity=".4"/>';
    $o .= '<rect x="-500" y="-296" width="1000" height="22" fill="none" stroke="' . $S2 . '" stroke-width="1.6" stroke-opacity=".6"/>';
    foreach (array(-345, -115, 115, 345) as $x) {
        $o .= '<rect x="' . ($x - 54) . '" y="-270" width="108" height="30" rx="3" fill="none" stroke="' . $S2 . '" stroke-width="1.6" stroke-opacity=".7"/>';
        $o .= '<rect x="' . ($x - 42) . '" y="-240" width="84" height="440" fill="' . $F . '" stroke="' . $S2 . '" stroke-width="1.4" stroke-opacity=".5"/>';
        for ($k = -2; $k <= 2; $k++) {
            $o .= '<path d="M' . ($x + $k * 16) . ' -232V192" stroke="' . $S2 . '" stroke-opacity=".3" stroke-width="1.2"/>';
        }
        $o .= '<rect x="' . ($x - 54) . '" y="200" width="108" height="26" rx="3" fill="none" stroke="' . $S2 . '" stroke-width="1.6" stroke-opacity=".7"/>';
    }
    foreach (array(0, 1, 2) as $i) {
        $o .= '<rect x="' . (-500 + $i * 26) . '" y="' . (226 + $i * 24) . '" width="' . (1000 - $i * 52) . '" height="24" fill="none" stroke="' . $S2 . '" stroke-width="1.6" stroke-opacity="' . (0.65 - $i * 0.15) . '"/>';
    }
    $o .= '</g>';
    // balanza
    $a = (sc__art_rnd($r) - 0.5) * 0.14;
    $L = 310;
    $cy = -150;
    $ex = $L * cos($a);
    $ey = $L * sin($a);
    $x1 = -$ex;
    $y1 = $cy - $ey;
    $x2 = $ex;
    $y2 = $cy + $ey;
    $o .= '<path d="M0-250V250" stroke="' . $S1 . '" stroke-width="8" stroke-linecap="round"/>';
    $o .= '<path d="M-90 310Q0 240 90 310Z" fill="' . $F . '" stroke="' . $S1 . '" stroke-width="3" stroke-linejoin="round"/>';
    $o .= '<path d="M-150 316H150" stroke="' . $S1 . '" stroke-width="6" stroke-linecap="round"/>';
    $o .= '<path d="M' . sc__art_n($x1) . ' ' . sc__art_n($y1 - 5) . 'L0 ' . ($cy - 12) . 'L' . sc__art_n($x2) . ' ' . sc__art_n($y2 - 5)
        . 'L' . sc__art_n($x2) . ' ' . sc__art_n($y2 + 5) . 'L0 ' . ($cy + 12) . 'L' . sc__art_n($x1) . ' ' . sc__art_n($y1 + 5) . 'Z" fill="' . $S1 . '"/>';
    $o .= sc__art_circ(0, $cy, 22, 'fill="' . $K['P']['bg'] . '" stroke="' . $S1 . '" stroke-width="5"');
    $o .= sc__art_circ(0, -262, 12, 'fill="' . $K['hi'] . '"');
    foreach (array(array($x1, $y1), array($x2, $y2)) as $e) {
        $px = $e[0];
        $py = $e[1] + 235;
        $o .= '<path d="M' . sc__art_n($e[0]) . ' ' . sc__art_n($e[1]) . 'L' . sc__art_n($px - 118) . ' ' . sc__art_n($py)
            . 'M' . sc__art_n($e[0]) . ' ' . sc__art_n($e[1]) . 'L' . sc__art_n($px + 118) . ' ' . sc__art_n($py)
            . 'M' . sc__art_n($e[0]) . ' ' . sc__art_n($e[1]) . 'V' . sc__art_n($py + 20)
            . '" stroke="' . $S1 . '" stroke-width="1.8" stroke-opacity=".8" fill="none"/>';
        $o .= '<path d="M' . sc__art_n($px - 135) . ' ' . sc__art_n($py) . 'H' . sc__art_n($px + 135) . 'Q' . sc__art_n($px + 110) . ' ' . sc__art_n($py + 85)
            . ' ' . sc__art_n($px) . ' ' . sc__art_n($py + 95) . 'Q' . sc__art_n($px - 110) . ' ' . sc__art_n($py + 85) . ' ' . sc__art_n($px - 135) . ' ' . sc__art_n($py)
            . 'Z" fill="url(#' . $P . 'f2)" stroke="' . $S1 . '" stroke-width="4" stroke-linejoin="round"/>';
        $o .= sc__art_circ($e[0], $e[1], 11, 'fill="' . $K['hi'] . '"');
    }
    return $o;
}

function sc__art_m_pulse(array $K, string $P, string &$D, int &$r): string
{
    $S1 = 'url(#' . $P . 's1)';
    $S2 = $K['s2'];
    $F = 'url(#' . $P . 'f1)';
    $o = '';
    $dash = array('', '', '2 12', '', '40 14 4 14', '');
    for ($i = 1; $i <= 6; $i++) {
        $da = $dash[$i - 1] !== '' ? ' stroke-dasharray="' . $dash[$i - 1] . '"' : '';
        $o .= sc__art_circ(0, 0, 90 + $i * 62, 'fill="none" stroke="' . $S2 . '" stroke-width="' . ($i === 2 ? 3 : 1.4) . '" stroke-opacity="' . sc__art_n(0.62 - $i * 0.085) . '"' . $da);
    }
    $o .= sc__art_circ(0, 0, 190, 'fill="url(#' . $P . 'rg)" stroke="none"');
    $o .= sc__art_circ(0, 0, 150, 'fill="none" stroke="' . $S1 . '" stroke-width="2.4"');
    $o .= '<path d="M-40-135H40V-40H135V40H40V135H-40V40H-135V-40H-40Z" transform="scale(.82)" fill="' . $F . '" stroke="' . $S1 . '" stroke-width="7" stroke-linejoin="round"/>';
    $o .= '<path d="M-26-96H26V-26H96V26H26V96H-26V26H-96V-26H-26Z" fill="none" stroke="' . $K['hi'] . '" stroke-opacity=".5" stroke-width="1.5" stroke-linejoin="round"/>';
    $j = sc__art_rr($r, -30, 30);
    $y = 215;
    $o .= '<path d="M-500 ' . $y . 'H-350q14-34 28 0H-250L-212 ' . ($y + 46) . 'L-160 ' . ($y - 130 + $j) . 'L-108 ' . ($y + 110) . 'L-72 ' . $y . 'H20q24-62 52 0H110L142 ' . ($y + 34) . 'L180 ' . ($y - 70) . 'L214 ' . $y . 'H500"'
        . ' fill="none" stroke="' . $S1 . '" stroke-width="4.5" stroke-linejoin="round" stroke-linecap="round"/>';
    $o .= '<path d="M-500 ' . $y . 'H500" stroke="' . $S2 . '" stroke-width="1" stroke-opacity=".3" stroke-dasharray="3 9"/>';
    $o .= sc__art_circ(-160, $y - 130 + $j, 9, 'fill="' . $K['hi'] . '"');
    $o .= sc__art_circ(-160, $y - 130 + $j, 26, 'fill="none" stroke="' . $S1 . '" stroke-opacity=".5" stroke-width="2"');
    return $o;
}

function sc__art_m_gear(array $K, string $P, string &$D, int &$r): string
{
    $S1 = 'url(#' . $P . 's1)';
    $S2 = $K['s2'];
    $F = 'url(#' . $P . 'f1)';
    $o = '';
    $th = sc__art_rr($r, -0.9, -0.3);
    $c1 = array(-110, 60);
    $r1 = 285;
    $t1 = 18;
    $r2 = 175;
    $t2 = 11;
    $dist = $r1 + $r2 - 30;
    $c2 = array($c1[0] + cos($th) * $dist, $c1[1] + sin($th) * $dist);
    $th2 = $th + 2.0;
    $r3 = 105;
    $t3 = 7;
    $dist3 = $r1 + $r3 - 30;
    $c3 = array($c1[0] + cos($th2) * $dist3, $c1[1] + sin($th2) * $dist3);
    // rayos
    for ($i = 0; $i < 24; $i++) {
        $a = $i * 0.2618;
        $len = 360 + (($i * 37) % 11) * 16 + sc__art_rnd($r) * 40;
        $o .= '<path d="M' . sc__art_n($c1[0] + cos($a) * 335) . ' ' . sc__art_n($c1[1] + sin($a) * 335) . 'L' . sc__art_n($c1[0] + cos($a) * $len) . ' ' . sc__art_n($c1[1] + sin($a) * $len)
            . '" stroke="' . $S2 . '" stroke-width="' . ($i % 6 === 0 ? 2.4 : 1) . '" stroke-opacity="' . ($i % 6 === 0 ? '.55' : '.2') . '"/>';
    }
    $defs = array(array($c1, $r1, $t1, $th, 1), array($c2, $r2, $t2, $th + 3.14159 - 3.14159 / $t2, 0.8), array($c3, $r3, $t3, $th2 + 3.14159 - 3.14159 / $t3, 0.7));
    foreach ($defs as $gi => $g) {
        $cx = $g[0][0];
        $cy = $g[0][1];
        $ro = $g[1];
        $o .= '<path d="' . sc__art_gear($cx, $cy, $ro, $g[2], $g[3], $ro * 0.11) . '" fill="' . $F . '" stroke="' . $S1 . '" stroke-width="' . ($gi === 0 ? 4 : 3) . '" stroke-linejoin="round"/>';
        $o .= sc__art_circ($cx, $cy, $ro * 0.74, 'fill="none" stroke="' . $S2 . '" stroke-width="1.6" stroke-opacity=".7"');
        $o .= sc__art_circ($cx, $cy, $ro * 0.56, 'fill="none" stroke="' . $S2 . '" stroke-width="1" stroke-opacity=".4" stroke-dasharray="3 7"');
        $sp = $gi === 0 ? 6 : 5;
        $d = '';
        for ($k = 0; $k < $sp; $k++) {
            $a = $g[3] + $k * 6.2832 / $sp;
            $d .= 'M' . sc__art_n($cx + cos($a) * $ro * 0.2) . ' ' . sc__art_n($cy + sin($a) * $ro * 0.2) . 'L' . sc__art_n($cx + cos($a) * $ro * 0.74) . ' ' . sc__art_n($cy + sin($a) * $ro * 0.74);
        }
        $o .= '<path d="' . $d . '" stroke="' . $S1 . '" stroke-width="' . ($gi === 0 ? 9 : 6) . '" stroke-opacity=".55" stroke-linecap="round"/>';
        $o .= sc__art_circ($cx, $cy, $ro * 0.2, 'fill="' . $K['P']['bg'] . '" stroke="' . $S1 . '" stroke-width="4"');
        $o .= sc__art_circ($cx, $cy, $ro * 0.07, 'fill="' . $K['hi'] . '"');
    }
    return $o;
}

function sc__art_m_fabric(array $K, string $P, string &$D, int &$r): string
{
    $S1 = 'url(#' . $P . 's1)';
    $S2 = $K['s2'];
    $o = '';
    $ph = sc__art_rr($r, 0, 6.28);
    $A = sc__art_rr($r, 300, 400);
    $N = 20;
    $curves = array();
    for ($i = 0; $i <= $N; $i++) {
        $t = $i / $N;
        $y = -840 + $i * 84;
        $a1 = $A * sin($ph + $t * 5.2);
        $a2 = $A * 0.9 * sin($ph * 1.3 + $t * 4.4 + 1.7);
        $b1 = $A * 1.2 * cos($ph + $t * 3.6);
        $b2 = $A * 1.1 * sin($ph + 2.2 + $t * 4.0);
        $curves[] = array(array(-1300, $y + $a1 * 0.6), array(-420, $y + $b1), array(380, $y + $b2), array(1300, $y + $a2 * 0.6));
    }
    for ($i = 0; $i < $N; $i++) {
        $c = $curves[$i];
        $d = $curves[$i + 1];
        $path = 'M' . sc__art_n($c[0][0]) . ' ' . sc__art_n($c[0][1]) . 'C' . sc__art_n($c[1][0]) . ' ' . sc__art_n($c[1][1]) . ' ' . sc__art_n($c[2][0]) . ' ' . sc__art_n($c[2][1]) . ' ' . sc__art_n($c[3][0]) . ' ' . sc__art_n($c[3][1])
            . 'L' . sc__art_n($d[3][0]) . ' ' . sc__art_n($d[3][1]) . 'C' . sc__art_n($d[2][0]) . ' ' . sc__art_n($d[2][1]) . ' ' . sc__art_n($d[1][0]) . ' ' . sc__art_n($d[1][1]) . ' ' . sc__art_n($d[0][0]) . ' ' . sc__art_n($d[0][1]) . 'Z';
        $m = $i % 4;
        $f = ($m === 0) ? 'url(#' . $P . 'f1)' : (($m === 1) ? $K['P']['dark'] : (($m === 2) ? 'url(#' . $P . 'f2)' : $K['hi']));
        $op = ($m === 1) ? '.22' : (($m === 3) ? '.11' : '1');
        $o .= '<path d="' . $path . '" fill="' . $f . '" fill-opacity="' . $op . '"/>';
    }
    foreach ($curves as $i => $c) {
        $main = ($i % 4 === 0);
        $o .= '<path d="M' . sc__art_n($c[0][0]) . ' ' . sc__art_n($c[0][1]) . 'C' . sc__art_n($c[1][0]) . ' ' . sc__art_n($c[1][1]) . ' ' . sc__art_n($c[2][0]) . ' ' . sc__art_n($c[2][1]) . ' ' . sc__art_n($c[3][0]) . ' ' . sc__art_n($c[3][1])
            . '" fill="none" stroke="' . ($main ? $S1 : $S2) . '" stroke-width="' . ($main ? 2.6 : 1) . '" stroke-opacity="' . ($main ? '.9' : '.4') . '"/>';
    }
    return $o;
}

function sc__art_m_plate(array $K, string $P, string &$D, int &$r): string
{
    $S1 = 'url(#' . $P . 's1)';
    $S2 = $K['s2'];
    $F = 'url(#' . $P . 'f1)';
    $o = '';
    // vapor
    for ($i = 0; $i < 4; $i++) {
        $x = -150 + $i * 100 + sc__art_rr($r, -14, 14);
        $o .= '<path d="M' . sc__art_n($x) . ' -320C' . sc__art_n($x + 60) . ' -370 ' . sc__art_n($x - 60) . ' -420 ' . sc__art_n($x) . ' -470" fill="none" stroke="' . $K['hi'] . '" stroke-width="' . (8 + $i % 2 * 4) . '" stroke-opacity=".12" stroke-linecap="round"/>';
        $o .= '<path d="M' . sc__art_n($x) . ' -320C' . sc__art_n($x + 60) . ' -370 ' . sc__art_n($x - 60) . ' -420 ' . sc__art_n($x) . ' -470" fill="none" stroke="' . $S1 . '" stroke-width="1.6" stroke-opacity=".7" stroke-linecap="round"/>';
    }
    // cubiertos
    $o .= '<g stroke="' . $S1 . '" fill="none" stroke-linecap="round" stroke-linejoin="round">';
    $o .= '<path d="M-448-300V-170M-424-300V-170M-400-300V-170M-376-300V-170" stroke-width="5"/>';
    $o .= '<path d="M-448-170Q-448-110-412-100Q-376-110-376-170" stroke-width="5" fill="url(#' . $P . 'f1)"/>';
    $o .= '<path d="M-412-100V250" stroke-width="12"/><path d="M-412-100V250" stroke="' . $K['hi'] . '" stroke-opacity=".35" stroke-width="2"/>';
    $o .= '<ellipse cx="-412" cy="300" rx="22" ry="56" stroke-width="4" fill="url(#' . $P . 'f2)"/>';
    $o .= '<path d="M420-310C480-280 490-110 462 40L418 40V-310Z" stroke-width="4.5" fill="url(#' . $P . 'f1)"/>';
    $o .= '<rect x="418" y="40" width="44" height="270" rx="22" stroke-width="4" fill="url(#' . $P . 'f2)"/>';
    $o .= '</g>';
    // plato
    $o .= sc__art_circ(0, 0, 300, 'fill="url(#' . $P . 'rg)" stroke="none"');
    $o .= sc__art_circ(0, 0, 300, 'fill="none" stroke="' . $S1 . '" stroke-width="5"');
    $o .= sc__art_circ(0, 0, 262, 'fill="none" stroke="' . $S2 . '" stroke-width="1.6" stroke-opacity=".7"');
    $o .= sc__art_circ(0, 0, 200, 'fill="' . $F . '" stroke="' . $S2 . '" stroke-width="2" stroke-opacity=".8"');
    $o .= sc__art_circ(0, 0, 180, 'fill="none" stroke="' . $S2 . '" stroke-width="1" stroke-opacity=".4" stroke-dasharray="2 8"');
    $o .= '<path d="M10-125C30-70 95-40 80 40C70 100 25 118-5 118C-60 112-85 60-60 5C-45-28-20-40-12-70C-8-90 0-108 10-125Z" fill="url(#' . $P . 'f2)" stroke="' . $S1 . '" stroke-width="3.5" stroke-linejoin="round"/>';
    $o .= '<path d="M6-40C26-5 50 20 38 62C30 90 8 98-6 96C-34 90-44 55-28 28C-18 10 0-12 6-40Z" fill="' . $K['hi'] . '" fill-opacity=".42"/>';
    $o .= '<path d="M-250-110A280 280 0 0 1 -110-250" fill="none" stroke="' . $K['hi'] . '" stroke-opacity=".55" stroke-width="3" stroke-linecap="round"/>';
    return $o;
}

function sc__art_m_route(array $K, string $P, string &$D, int &$r): string
{
    $S1 = 'url(#' . $P . 's1)';
    $S2 = $K['s2'];
    $o = '';
    for ($i = 0; $i < 7; $i++) {
        $o .= '<path d="' . sc__art_blob($r, sc__art_rr($r, -30, 30), sc__art_rr($r, -30, 30), 80 + $i * 60, 0.13, 7) . '" fill="none" stroke="' . $S2 . '" stroke-width="' . ($i % 3 === 0 ? 1.8 : 1) . '" stroke-opacity="' . sc__art_n(0.5 - $i * 0.045) . '"/>';
    }
    for ($i = -4; $i <= 4; $i++) {
        $o .= '<path d="M' . ($i * 125) . ' -500V500M-500 ' . ($i * 125) . 'H500" stroke="' . $S2 . '" stroke-width=".8" stroke-opacity=".12"/>';
    }
    $n = 7;
    $pts = array();
    $slope = sc__art_rr($r, -0.3, 0.3);
    $ph = sc__art_rr($r, 0, 6.28);
    $am = sc__art_rr($r, 110, 170);
    for ($i = 0; $i < $n; $i++) {
        $x = -420 + $i * 140 + sc__art_rr($r, -14, 14);
        $pts[] = array($x, -$slope * $x + $am * sin($i * 0.62 + $ph) + sc__art_rr($r, -22, 22));
    }
    $path = sc__art_smooth($pts, false);
    $o .= '<path d="' . $path . '" fill="none" stroke="' . $K['hi'] . '" stroke-opacity=".10" stroke-width="46" stroke-linecap="round" stroke-linejoin="round"/>';
    $o .= '<path d="' . $path . '" fill="none" stroke="' . $S1 . '" stroke-width="4" stroke-linecap="round" stroke-dasharray="26 16"/>';
    $alt = array(array($pts[0][0], $pts[0][1] + 40), array($pts[1][0] + 40, $pts[1][1] + 230), array($pts[3][0] - 60, $pts[3][1] + 210), array($pts[5][0] + 20, $pts[5][1] + 250), array($pts[6][0], $pts[6][1] + 40));
    $o .= '<path d="' . sc__art_smooth($alt, false) . '" fill="none" stroke="' . $S2 . '" stroke-width="2.4" stroke-dasharray="2 13" stroke-linecap="round" stroke-opacity=".8"/>';
    foreach ($pts as $i => $p) {
        if ($i === $n - 1) {
            continue;
        }
        $o .= sc__art_circ($p[0], $p[1], 46, 'fill="none" stroke="' . $S2 . '" stroke-opacity=".35" stroke-width="1.5"');
        $o .= sc__art_circ($p[0], $p[1], 24, 'fill="' . $K['P']['bg'] . '" stroke="' . $S1 . '" stroke-width="4"');
        $o .= sc__art_circ($p[0], $p[1], 8, 'fill="' . $K['hi'] . '"');
    }
    $e = $pts[$n - 1];
    $o .= '<ellipse cx="' . sc__art_n($e[0]) . '" cy="' . sc__art_n($e[1]) . '" rx="62" ry="20" fill="' . $S1 . '" fill-opacity=".25"/>';
    $o .= '<path transform="translate(' . sc__art_n($e[0]) . ' ' . sc__art_n($e[1]) . ')" d="M0 0C-44-62-62-102-62-146A62 62 0 1 1 62-146C62-102 44-62 0 0Z" fill="url(#' . $P . 'f2)" stroke="' . $S1 . '" stroke-width="5" stroke-linejoin="round"/>';
    $o .= sc__art_circ($e[0], $e[1] - 146, 22, 'fill="' . $K['hi'] . '"');
    return $o;
}

function sc__art_m_chart(array $K, string $P, string &$D, int &$r): string
{
    $S1 = 'url(#' . $P . 's1)';
    $S2 = $K['s2'];
    $o = '';
    for ($i = 0; $i <= 6; $i++) {
        $o .= '<path d="M-500 ' . (300 - $i * 100) . 'H500" stroke="' . $S2 . '" stroke-width="' . ($i === 0 ? 2 : 1) . '" stroke-opacity="' . ($i === 0 ? '.7' : '.2') . '"' . ($i ? ' stroke-dasharray="2 8"' : '') . '/>';
    }
    $nb = 8;
    $w = 76;
    $gap = 44;
    $x0 = -($nb * ($w + $gap) - $gap) / 2;
    $pts = array();
    for ($i = 0; $i < $nb; $i++) {
        $h = 90 + $i * 42 + sc__art_rr($r, -34, 56);
        $x = $x0 + $i * ($w + $gap);
        $o .= '<rect x="' . sc__art_n($x) . '" y="' . sc__art_n(300 - $h) . '" width="' . $w . '" height="' . sc__art_n($h) . '" rx="3" fill="url(#' . $P . 'f1)" stroke="' . $S2 . '" stroke-width="1.2" stroke-opacity=".55"/>';
        $o .= '<path d="M' . sc__art_n($x) . ' ' . sc__art_n(300 - $h) . 'H' . sc__art_n($x + $w) . '" stroke="' . $S1 . '" stroke-width="4" stroke-linecap="round"/>';
        $pts[] = array($x + $w / 2, 300 - $h - 70 - $i * 14);
    }
    $path = sc__art_smooth($pts, false);
    $last = $pts[$nb - 1];
    $o .= '<path d="' . $path . 'L' . sc__art_n($last[0]) . ' 300L' . sc__art_n($pts[0][0]) . ' 300Z" fill="url(#' . $P . 'f2)" fill-opacity=".5"/>';
    $o .= '<path d="' . $path . '" fill="none" stroke="' . $K['hi'] . '" stroke-opacity=".2" stroke-width="14" stroke-linecap="round"/>';
    $o .= '<path d="' . $path . '" fill="none" stroke="' . $S1 . '" stroke-width="5" stroke-linecap="round"/>';
    foreach ($pts as $i => $p) {
        $o .= sc__art_circ($p[0], $p[1], $i === $nb - 1 ? 15 : 9, 'fill="' . ($i === $nb - 1 ? $K['hi'] : $K['P']['bg']) . '" stroke="' . $S1 . '" stroke-width="4"');
    }
    $o .= sc__art_circ($last[0], $last[1], 42, 'fill="none" stroke="' . $S1 . '" stroke-opacity=".4" stroke-width="2"');
    return $o;
}

function sc__art_m_globe(array $K, string $P, string &$D, int &$r): string
{
    $S1 = 'url(#' . $P . 's1)';
    $S2 = $K['s2'];
    $R = 370;
    $o = '<ellipse cx="0" cy="0" rx="500" ry="140" transform="rotate(-24)" fill="none" stroke="' . $S2 . '" stroke-width="1.6" stroke-opacity=".5"/>';
    $o .= '<g transform="rotate(' . sc__art_n(sc__art_rr($r, -22, -8)) . ')">';
    $o .= sc__art_circ(0, 0, $R + 36, 'fill="url(#' . $P . 'rg)" stroke="none"');
    $o .= sc__art_circ(0, 0, $R, 'fill="url(#' . $P . 'sp)" stroke="none"');
    foreach (array(-60, -30, 0, 30, 60) as $lat) {
        $a = deg2rad($lat);
        $o .= '<ellipse cx="0" cy="' . sc__art_n($R * sin($a)) . '" rx="' . sc__art_n($R * cos($a)) . '" ry="' . sc__art_n($R * cos($a) * 0.2) . '" fill="none" stroke="' . $S2 . '" stroke-width="1.3" stroke-opacity=".5"/>';
    }
    $rot = sc__art_rr($r, 0, 0.5);
    for ($k = 0; $k < 6; $k++) {
        $o .= '<ellipse cx="0" cy="0" rx="' . sc__art_n(abs($R * cos($rot + $k * 0.5236))) . '" ry="' . $R . '" fill="none" stroke="' . $S2 . '" stroke-width="1.3" stroke-opacity=".5"/>';
    }
    $o .= sc__art_circ(0, 0, $R, 'fill="none" stroke="' . $S1 . '" stroke-width="4"');
    $o .= '<path d="M-250-190A315 315 0 0 1 -60-306" fill="none" stroke="' . $K['hi'] . '" stroke-opacity=".6" stroke-width="3" stroke-linecap="round"/>';
    $nodes = array();
    for ($i = 0; $i < 5; $i++) {
        $lat = deg2rad(sc__art_rr($r, -40, 55));
        $lon = deg2rad(-65 + $i * 32 + sc__art_rr($r, -10, 10));
        $nodes[] = array($R * cos($lat) * sin($lon), -$R * sin($lat) * 0.92 + $R * 0.12 * sin($lat));
    }
    foreach (array(array(0, 2), array(1, 4), array(2, 3), array(0, 3)) as $pr) {
        $a = $nodes[$pr[0]];
        $b = $nodes[$pr[1]];
        $mx = ($a[0] + $b[0]) / 2;
        $my = ($a[1] + $b[1]) / 2;
        $o .= '<path d="M' . sc__art_n($a[0]) . ' ' . sc__art_n($a[1]) . 'Q' . sc__art_n($mx * 1.5) . ' ' . sc__art_n($my * 1.5 - 70) . ' ' . sc__art_n($b[0]) . ' ' . sc__art_n($b[1]) . '" fill="none" stroke="' . $S1 . '" stroke-width="3" stroke-dasharray="10 9" stroke-linecap="round"/>';
    }
    foreach ($nodes as $nd) {
        $o .= sc__art_circ($nd[0], $nd[1], 22, 'fill="none" stroke="' . $S1 . '" stroke-opacity=".4" stroke-width="2"');
        $o .= sc__art_circ($nd[0], $nd[1], 9, 'fill="' . $K['hi'] . '" stroke="' . $S1 . '" stroke-width="3"');
    }
    $o .= '</g>';
    $o .= sc__art_circ(396, -222, 10, 'fill="' . $K['hi'] . '"');
    return $o;
}

function sc__art_m_abstract(array $K, string $P, string &$D, int &$r): string
{
    $S1 = 'url(#' . $P . 's1)';
    $S2 = $K['s2'];
    $o = '';
    $fills = array('url(#' . $P . 'f1)', 'url(#' . $P . 'f2)', $K['hi']);
    for ($i = 0; $i < 4; $i++) {
        $d = sc__art_blob($r, sc__art_rr($r, -150, 150), sc__art_rr($r, -150, 150), sc__art_rr($r, 170, 300), 0.3, 7);
        $o .= '<path d="' . $d . '" fill="' . $fills[$i % 3] . '" fill-opacity="' . ($i % 3 === 2 ? '.09' : '.85') . '" stroke="' . ($i % 2 ? $S2 : $S1) . '" stroke-width="' . ($i % 2 ? 1.4 : 2.6) . '" stroke-opacity=".75"/>';
    }
    for ($i = 0; $i < 5; $i++) {
        $rad = 160 + $i * 62;
        $a0 = sc__art_rr($r, 0, 6.28);
        $len = sc__art_rr($r, 1.0, 3.2);
        $x0 = cos($a0) * $rad;
        $y0 = sin($a0) * $rad;
        $x1 = cos($a0 + $len) * $rad;
        $y1 = sin($a0 + $len) * $rad;
        $o .= '<path d="M' . sc__art_n($x0) . ' ' . sc__art_n($y0) . 'A' . $rad . ' ' . $rad . ' 0 ' . ($len > 3.14159 ? 1 : 0) . ' 1 ' . sc__art_n($x1) . ' ' . sc__art_n($y1) . '" fill="none" stroke="' . ($i % 2 ? $S2 : $S1) . '" stroke-width="' . ($i % 2 ? 1.6 : 3.4) . '" stroke-linecap="round" stroke-opacity=".9"/>';
        $o .= sc__art_circ($x1, $y1, 7, 'fill="' . $K['hi'] . '"');
    }
    $o .= sc__art_circ(sc__art_rr($r, -60, 60), sc__art_rr($r, -60, 60), 46, 'fill="' . $K['hi'] . '" fill-opacity=".22" stroke="' . $S1 . '" stroke-width="2.4"');
    return $o;
}

/* ------------------------------------------------------------------ composicion */

function sc_art(int $seed, string $motif, array $palette, string $variant = 'cover'): string
{
    $variants = sc_art_variants();
    if (!isset($variants[$variant])) {
        $variant = 'cover';
    }
    if (!in_array($motif, sc_art_motifs(), true)) {
        $motif = 'abstract';
    }
    $W = $variants[$variant][0];
    $H = $variants[$variant][1];
    $Pal = sc__art_palette($palette);
    $K = sc__art_colors($Pal);
    $r = sc__art_rng_init($seed, $motif . $variant);

    $uid = sc__art_uid();
    $P = 'a' . base_convert((string)(abs($seed) % 1679616), 10, 36) . substr($variant, 0, 1) . $uid . '-';
    $dark = $K['dark'];

    $D = '';  // defs
    $B = '';  // cuerpo

    // fondo
    $ang = sc__art_rr($r, 0, 1);
    $D .= '<linearGradient id="' . $P . 'bg" x1="' . sc__art_n($ang) . '" y1="0" x2="' . sc__art_n(1 - $ang) . '" y2="1"><stop offset="0" stop-color="' . $K['b1'] . '"/><stop offset="1" stop-color="' . $K['b2'] . '"/></linearGradient>';
    $B .= '<rect width="' . $W . '" height="' . $H . '" fill="url(#' . $P . 'bg)"/>';

    // malla de degradados (mesh)
    $nm = 5;
    for ($i = 0; $i < $nm; $i++) {
        $cx = sc__art_rr($r, -0.05, 1.05) * $W;
        $cy = sc__art_rr($r, -0.05, 1.05) * $H;
        $rad = sc__art_rr($r, 0.38, 0.72) * max($W * 0.55, $H);
        $id = $P . 'm' . $i;
        $D .= '<radialGradient id="' . $id . '"><stop offset="0" stop-color="' . $K['m'][$i] . '" stop-opacity="' . sc__art_n($K['mo'][$i]) . '"/><stop offset=".55" stop-color="' . $K['m'][$i] . '" stop-opacity="' . sc__art_n($K['mo'][$i] * 0.3) . '"/><stop offset="1" stop-color="' . $K['m'][$i] . '" stop-opacity="0"/></radialGradient>';
        $B .= sc__art_circ($cx, $cy, $rad, 'fill="url(#' . $id . ')"');
    }

    // haz de luz diagonal
    $D .= '<linearGradient id="' . $P . 'bm"><stop offset="0" stop-color="' . $K['hi'] . '" stop-opacity="0"/><stop offset=".5" stop-color="' . $K['hi'] . '" stop-opacity="' . ($dark ? '.13' : '.34') . '"/><stop offset="1" stop-color="' . $K['hi'] . '" stop-opacity="0"/></linearGradient>';
    $bw = sc__art_rr($r, 0.10, 0.2) * $W;
    $bx = sc__art_rr($r, 0.1, 0.8) * $W;
    $B .= '<rect x="' . sc__art_n($bx) . '" y="' . sc__art_n(-$H * 0.4) . '" width="' . sc__art_n($bw) . '" height="' . sc__art_n($H * 1.8) . '" fill="url(#' . $P . 'bm)" transform="rotate(' . sc__art_n(sc__art_rr($r, 18, 34)) . ' ' . sc__art_n($W / 2) . ' ' . sc__art_n($H / 2) . ')"/>';

    // gradientes compartidos de los motivos
    $D .= '<linearGradient id="' . $P . 's1" gradientUnits="userSpaceOnUse" x1="-500" y1="-500" x2="500" y2="500"><stop offset="0" stop-color="' . $K['s2'] . '" stop-opacity=".7"/><stop offset=".5" stop-color="' . $K['s1'] . '"/><stop offset="1" stop-color="' . $K['s2'] . '" stop-opacity=".6"/></linearGradient>';
    $D .= '<linearGradient id="' . $P . 'f1" gradientUnits="userSpaceOnUse" x1="0" y1="-500" x2="0" y2="500"><stop offset="0" stop-color="' . $K['f1'] . '" stop-opacity="' . ($dark ? '.42' : '.34') . '"/><stop offset="1" stop-color="' . $K['f2'] . '" stop-opacity=".05"/></linearGradient>';
    $D .= '<linearGradient id="' . $P . 'f2" gradientUnits="userSpaceOnUse" x1="-500" y1="500" x2="500" y2="-500"><stop offset="0" stop-color="' . $K['f2'] . '" stop-opacity="' . ($dark ? '.55' : '.45') . '"/><stop offset="1" stop-color="' . $K['f1'] . '" stop-opacity=".08"/></linearGradient>';
    $D .= '<radialGradient id="' . $P . 'rg"><stop offset="0" stop-color="' . $K['P']['glow'] . '" stop-opacity="' . ($dark ? '.34' : '.5') . '"/><stop offset=".6" stop-color="' . $K['P']['glow'] . '" stop-opacity=".1"/><stop offset="1" stop-color="' . $K['P']['glow'] . '" stop-opacity="0"/></radialGradient>';
    $D .= '<radialGradient id="' . $P . 'sp" cx=".36" cy=".3" r=".8"><stop offset="0" stop-color="' . $K['hi'] . '" stop-opacity="' . ($dark ? '.34' : '.55') . '"/><stop offset=".45" stop-color="' . $K['f1'] . '" stop-opacity=".16"/><stop offset="1" stop-color="' . ($dark ? $K['P']['dark'] : $K['f2']) . '" stop-opacity="' . ($dark ? '.6' : '.3') . '"/></radialGradient>';

    // bokeh / profundidad
    $D .= '<radialGradient id="' . $P . 'bk"><stop offset="0" stop-color="' . $K['hi'] . '" stop-opacity=".30"/><stop offset=".7" stop-color="' . $K['hi'] . '" stop-opacity=".12"/><stop offset="1" stop-color="' . $K['hi'] . '" stop-opacity="0"/></radialGradient>';
    $nb = ($variant === 'wide' || $variant === 'cover') ? 9 : 6;
    for ($i = 0; $i < $nb; $i++) {
        $B .= sc__art_circ(sc__art_rr($r, 0, 1) * $W, sc__art_rr($r, 0, 1) * $H, sc__art_rr($r, 0.02, 0.085) * $H, 'fill="url(#' . $P . 'bk)" opacity="' . sc__art_n(sc__art_rr($r, 0.35, 0.9)) . '"');
    }

    // geometria elegante semitransparente
    $ref = min($W, $H * 1.4);
    $gx = sc__art_rr($r, 0.15, 0.85) * $W;
    $gy = sc__art_rr($r, 0.2, 0.8) * $H;
    for ($i = 0; $i < 3; $i++) {
        $B .= sc__art_circ($gx, $gy, $ref * (0.34 + $i * 0.17), 'fill="none" stroke="' . $K['s2'] . '" stroke-opacity="' . sc__art_n(0.2 - $i * 0.045) . '" stroke-width="' . ($i === 0 ? 1.8 : 1.1) . '"');
    }
    $pa = sc__art_rr($r, 0, 360);
    $ps = $ref * sc__art_rr($r, 0.36, 0.55);
    $px = sc__art_rr($r, 0.0, 1.0) * $W;
    $py = sc__art_rr($r, 0.0, 1.0) * $H;
    $B .= '<rect x="' . sc__art_n($px - $ps / 2) . '" y="' . sc__art_n($py - $ps / 2) . '" width="' . sc__art_n($ps) . '" height="' . sc__art_n($ps) . '" fill="' . $K['f1'] . '" fill-opacity="' . ($dark ? '.06' : '.08') . '" stroke="' . $K['s2'] . '" stroke-opacity=".22" stroke-width="1.2" transform="rotate(' . sc__art_n($pa) . ' ' . sc__art_n($px) . ' ' . sc__art_n($py) . ')"/>';

    // lineas finas doradas
    $D .= '<linearGradient id="' . $P . 'ln"><stop offset="0" stop-color="' . $K['s1'] . '" stop-opacity="0"/><stop offset=".5" stop-color="' . $K['s1'] . '" stop-opacity=".8"/><stop offset="1" stop-color="' . $K['s1'] . '" stop-opacity="0"/></linearGradient>';
    $ly = sc__art_rr($r, 0.1, 0.22) * $H;
    $ly2 = $H - sc__art_rr($r, 0.08, 0.2) * $H;
    $B .= '<rect x="' . sc__art_n($W * 0.04) . '" y="' . sc__art_n($ly) . '" width="' . sc__art_n($W * 0.92) . '" height="1.4" fill="url(#' . $P . 'ln)" opacity=".7"/>';
    $B .= '<rect x="' . sc__art_n($W * 0.12) . '" y="' . sc__art_n($ly2) . '" width="' . sc__art_n($W * 0.76) . '" height="1" fill="url(#' . $P . 'ln)" opacity=".5"/>';
    $lx = sc__art_rr($r, 0.05, 0.95) * $W;
    $B .= '<rect x="' . sc__art_n($lx) . '" y="' . sc__art_n($H * 0.05) . '" width="1" height="' . sc__art_n($H * 0.9) . '" fill="' . $K['s1'] . '" opacity=".16"/>';

    // motivo
    switch ($variant) {
        case 'portrait':
            $mx = 0.5 + sc__art_rr($r, -0.04, 0.04);
            $my = 0.44;
            $ms = 0.98 * $W;
            break;
        case 'square':
            $mx = 0.5 + sc__art_rr($r, -0.05, 0.05);
            $my = 0.5 + sc__art_rr($r, -0.03, 0.03);
            $ms = 0.9 * $W;
            break;
        case 'card':
            $mx = 0.5 + sc__art_rr($r, -0.1, 0.12);
            $my = 0.5;
            $ms = 1.0 * $H;
            break;
        case 'wide':
            $mx = (sc__art_rnd($r) < 0.7) ? sc__art_rr($r, 0.62, 0.74) : sc__art_rr($r, 0.24, 0.36);
            $my = 0.5;
            $ms = 1.0 * $H;
            break;
        default:
            $mx = (sc__art_rnd($r) < 0.7) ? sc__art_rr($r, 0.6, 0.72) : sc__art_rr($r, 0.26, 0.38);
            $my = 0.5;
            $ms = 1.02 * $H;
    }
    $cx = $mx * $W;
    $cy = $my * $H;
    $rotm = ($motif === 'gear' || $motif === 'abstract') ? sc__art_rr($r, -25, 25) : sc__art_rr($r, -6, 6);
    if ($motif === 'fabric') {
        $rotm = sc__art_rr($r, -9, 9);
        $cx = $W * 0.5;
        $cy = $H * 0.5;
    }
    if ($motif === 'scales' || $motif === 'plate' || $motif === 'pulse' || $motif === 'chart') {
        $rotm = sc__art_rr($r, -3, 3);
    }
    $fn = 'sc__art_m_' . $motif;
    // halo de luz tras el motivo
    $B .= sc__art_circ($cx, $cy, $ms * 0.78, 'fill="url(#' . $P . 'rg)"');
    $body = $fn($K, $P, $D, $r);
    $B .= '<g transform="translate(' . sc__art_n($cx) . ' ' . sc__art_n($cy) . ') rotate(' . sc__art_n($rotm) . ') scale(' . sc__art_n($ms / 1000 * 1.0) . ')">' . $body . '</g>';

    // destellos
    $ns = ($variant === 'wide' || $variant === 'cover') ? 3 : 2;
    for ($i = 0; $i < $ns; $i++) {
        $sx = sc__art_rr($r, 0.05, 0.95) * $W;
        $sy = sc__art_rr($r, 0.08, 0.92) * $H;
        $ss = sc__art_rr($r, 0.008, 0.022) * $H;
        $B .= '<path d="' . sc__art_star($sx, $sy, $ss * 2) . '" fill="' . $K['hi'] . '" opacity="' . sc__art_n(sc__art_rr($r, 0.4, 0.8)) . '"/>';
        $B .= sc__art_circ($sx, $sy, $ss * 3.5, 'fill="url(#' . $P . 'bk)"');
    }

    // marco fino (solo formatos de tarjeta/retrato)
    if ($variant !== 'cover' && $variant !== 'wide') {
        $in = round(min($W, $H) * 0.028);
        $B .= '<rect x="' . $in . '" y="' . $in . '" width="' . ($W - 2 * $in) . '" height="' . ($H - 2 * $in) . '" fill="none" stroke="' . $K['s1'] . '" stroke-opacity=".28" stroke-width="1.2"/>';
    }

    // vineta
    $D .= '<radialGradient id="' . $P . 'vg" cx=".5" cy=".5" r=".75"><stop offset=".5" stop-color="' . $K['vig'] . '" stop-opacity="0"/><stop offset="1" stop-color="' . $K['vig'] . '" stop-opacity="' . sc__art_n($K['vo']) . '"/></radialGradient>';
    $B .= '<rect width="' . $W . '" height="' . $H . '" fill="url(#' . $P . 'vg)"/>';

    // grano fino
    $gcol = $dark ? '1 1 1' : '0 0 0';
    $gc = explode(' ', $gcol);
    $D .= '<filter id="' . $P . 'gr" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB"><feTurbulence type="fractalNoise" baseFrequency=".8" numOctaves="2" seed="' . (abs($seed) % 97 + 1) . '" stitchTiles="stitch"/>'
        . '<feColorMatrix values="0 0 0 0 ' . $gc[0] . ' 0 0 0 0 ' . $gc[1] . ' 0 0 0 0 ' . $gc[2] . ' 0 0 0 1.1 -.42"/></filter>';
    $B .= '<rect width="' . $W . '" height="' . $H . '" filter="url(#' . $P . 'gr)" opacity="' . ($dark ? '.3' : '.2') . '"/>';

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $W . ' ' . $H . '" preserveAspectRatio="xMidYMid slice" class="sc-art sc-art--'
        . htmlspecialchars($variant, ENT_QUOTES) . '" role="img" aria-label="" aria-hidden="true" focusable="false" style="display:block;width:100%;height:100%">'
        . '<defs>' . $D . '</defs>' . $B . '</svg>';
}

} // SC_LUXE_ART_LOADED
