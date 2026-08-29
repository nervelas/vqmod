<?php
declare(strict_types=1);
/**
 * Láminas técnicas de producto (SVG): dibujo de la pieza, cotas, marcas de
 * registro y cajetín, en el lenguaje del catálogo técnico.
 *
 *   php tools/generate-plates.php
 */

require __DIR__ . '/../app/bootstrap.php';

$OUT = BASE_PATH . '/assets/img/plates';
@mkdir($OUT, 0755, true);

const W = 900;
const H = 675;
const INK = '#1C1F22';
const STEEL = '#5A6470';
const HAIR = '#C9CDC4';
const ACC = '#E8590C';

function head(string $title): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . W . ' ' . H . '" width="' . W . '" height="' . H . '" role="img" aria-label="' . htmlspecialchars($title, ENT_QUOTES) . '">'
        . '<title>' . htmlspecialchars($title, ENT_QUOTES) . '</title>'
        . '<defs>'
        . '<linearGradient id="steel" x1="0" y1="0" x2="0.6" y2="1">'
        . '<stop offset="0" stop-color="#E4E7E2"/><stop offset=".38" stop-color="#B7BDB8"/>'
        . '<stop offset=".62" stop-color="#8E969A"/><stop offset="1" stop-color="#C3C8C3"/></linearGradient>'
        . '<linearGradient id="steel2" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0" stop-color="#CFD4CF"/><stop offset=".5" stop-color="#9BA3A6"/><stop offset="1" stop-color="#D8DCD7"/></linearGradient>'
        . '<linearGradient id="rubber" x1="0" y1="0" x2="0.4" y2="1">'
        . '<stop offset="0" stop-color="#4A4F55"/><stop offset=".5" stop-color="#26292D"/><stop offset="1" stop-color="#3C4147"/></linearGradient>'
        . '<linearGradient id="ptfe" x1="0" y1="0" x2="0.3" y2="1">'
        . '<stop offset="0" stop-color="#FFFFFF"/><stop offset=".55" stop-color="#E9EBE6"/><stop offset="1" stop-color="#D3D7D1"/></linearGradient>'
        . '<linearGradient id="brass" x1="0" y1="0" x2="0.5" y2="1">'
        . '<stop offset="0" stop-color="#E8D9A8"/><stop offset=".5" stop-color="#B9974F"/><stop offset="1" stop-color="#DCC98E"/></linearGradient>'
        . '<linearGradient id="cloth" x1="0" y1="0" x2="0.4" y2="1">'
        . '<stop offset="0" stop-color="#3E5A78"/><stop offset=".55" stop-color="#27405A"/><stop offset="1" stop-color="#37536F"/></linearGradient>'
        . '<pattern id="hatch" width="7" height="7" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">'
        . '<line x1="0" y1="0" x2="0" y2="7" stroke="' . STEEL . '" stroke-width="1.1" opacity=".55"/></pattern>'
        . '<pattern id="grid" width="18" height="18" patternUnits="userSpaceOnUse">'
        . '<path d="M18 0H0v18" fill="none" stroke="' . INK . '" stroke-width="1" opacity=".055"/></pattern>'
        . '</defs>'
        . '<rect width="' . W . '" height="' . H . '" fill="#EFF0EC"/>'
        . '<rect width="' . W . '" height="' . H . '" fill="url(#grid)"/>';
}

function marks(): string
{
    $m = 26; $l = 20;
    $s = '<g stroke="' . STEEL . '" stroke-width="1.3" opacity=".55">';
    $s .= '<path d="M' . $m . ' ' . ($m + $l) . 'V' . $m . 'h' . $l . '"/>';
    $s .= '<path d="M' . (W - $m - $l) . ' ' . $m . 'h' . $l . 'v' . $l . '"/>';
    $s .= '<path d="M' . $m . ' ' . (H - $m - $l) . 'V' . (H - $m) . 'h' . $l . '"/>';
    $s .= '<path d="M' . (W - $m - $l) . ' ' . (H - $m) . 'h' . $l . 'v-' . $l . '"/>';
    $s .= '</g>';
    return $s;
}

/** Cajetín inferior con código y escala. */
function cartouche(string $code, string $note): string
{
    $y = H - 58;
    $s = '<g font-family="Inter, Helvetica, Arial, sans-serif">';
    $s .= '<line x1="60" y1="' . $y . '" x2="' . (W - 60) . '" y2="' . $y . '" stroke="' . HAIR . '" stroke-width="1.4"/>';
    $s .= '<rect x="60" y="' . ($y + 12) . '" width="4" height="18" fill="' . ACC . '"/>';
    $s .= '<text x="76" y="' . ($y + 26) . '" font-size="17" font-weight="700" fill="' . INK . '" letter-spacing="1.6">' . htmlspecialchars($code, ENT_QUOTES) . '</text>';
    $s .= '<text x="' . (W - 60) . '" y="' . ($y + 26) . '" text-anchor="end" font-size="11.5" fill="' . STEEL . '" letter-spacing="3">' . htmlspecialchars(mb_strtoupper($note), ENT_QUOTES) . '</text>';
    $s .= '</g>';
    return $s;
}

/** Línea de cota horizontal con flechas y etiqueta. */
function dimH(float $x1, float $x2, float $y, string $label): string
{
    $s = '<g stroke="' . STEEL . '" stroke-width="1.2" fill="none">';
    $s .= '<line x1="' . $x1 . '" y1="' . ($y - 9) . '" x2="' . $x1 . '" y2="' . ($y + 9) . '"/>';
    $s .= '<line x1="' . $x2 . '" y1="' . ($y - 9) . '" x2="' . $x2 . '" y2="' . ($y + 9) . '"/>';
    $s .= '<line x1="' . $x1 . '" y1="' . $y . '" x2="' . $x2 . '" y2="' . $y . '"/>';
    $s .= '<path d="M' . ($x1 + 9) . ' ' . ($y - 4) . 'L' . $x1 . ' ' . $y . 'l9 4"/>';
    $s .= '<path d="M' . ($x2 - 9) . ' ' . ($y - 4) . 'L' . $x2 . ' ' . $y . 'l-9 4"/>';
    $s .= '</g>';
    $mid = ($x1 + $x2) / 2;
    $s .= '<rect x="' . ($mid - 40) . '" y="' . ($y - 12) . '" width="80" height="22" fill="#EFF0EC"/>';
    $s .= '<text x="' . $mid . '" y="' . ($y + 4) . '" text-anchor="middle" font-family="Inter, Helvetica, Arial, sans-serif" font-size="13" fill="' . INK . '">' . htmlspecialchars($label, ENT_QUOTES) . '</text>';
    return $s;
}

/** Llamada con línea guía. */
function callout(float $x, float $y, float $tx, float $ty, string $text): string
{
    return '<g><line x1="' . $x . '" y1="' . $y . '" x2="' . $tx . '" y2="' . $ty . '" stroke="' . ACC . '" stroke-width="1.2"/>'
        . '<circle cx="' . $x . '" cy="' . $y . '" r="3.2" fill="' . ACC . '"/>'
        . '<line x1="' . $tx . '" y1="' . $ty . '" x2="' . ($tx + ($tx > $x ? 44 : -44)) . '" y2="' . $ty . '" stroke="' . ACC . '" stroke-width="1.2"/>'
        . '<text x="' . ($tx + ($tx > $x ? 50 : -50)) . '" y="' . ($ty + 4) . '" text-anchor="' . ($tx > $x ? 'start' : 'end') . '" font-family="Inter, Helvetica, Arial, sans-serif" font-size="11.5" letter-spacing="1.4" fill="' . STEEL . '">' . htmlspecialchars(mb_strtoupper($text), ENT_QUOTES) . '</text></g>';
}

function tail(): string { return '</svg>'; }

/* ====================================================================== */
$cx = 430.0; $cy = 300.0;
$plates = [];

// 1 · Sello mecánico de fuelle (tipo 21)
$plates['sello-mecanico'] = static function () use ($cx, $cy) {
    $s = '<g>';
    // Cara estacionaria (cerámica)
    $s .= '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="176" ry="176" fill="url(#steel)" stroke="' . INK . '" stroke-width="2.4"/>';
    $s .= '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="150" ry="150" fill="none" stroke="' . STEEL . '" stroke-width="1.4"/>';
    // Fuelle de hule (ondas concéntricas)
    for ($i = 0; $i < 5; $i++) {
        $r = 138 - $i * 13;
        $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="url(#rubber)" stroke-width="7" opacity="' . (0.92 - $i * 0.07) . '"/>';
        $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . ($r - 3.5) . '" fill="none" stroke="' . INK . '" stroke-width="0.9" opacity=".35"/>';
    }
    // Resorte helicoidal
    $s .= '<g fill="none" stroke="' . STEEL . '" stroke-width="3.2" opacity=".9">';
    for ($a = 0; $a < 360; $a += 18) {
        $r1 = 74; $r2 = 84;
        $x1 = $cx + cos(deg2rad($a)) * $r1; $y1 = $cy + sin(deg2rad($a)) * $r1;
        $x2 = $cx + cos(deg2rad($a + 11)) * $r2; $y2 = $cy + sin(deg2rad($a + 11)) * $r2;
        $s .= '<path d="M' . round($x1, 1) . ' ' . round($y1, 1) . 'Q' . round(($x1 + $x2) / 2 + 6, 1) . ' ' . round(($y1 + $y2) / 2, 1) . ' ' . round($x2, 1) . ' ' . round($y2, 1) . '"/>';
    }
    $s .= '</g>';
    // Cara de carbón
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="66" fill="url(#rubber)" stroke="' . INK . '" stroke-width="2"/>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="44" fill="#EFF0EC" stroke="' . INK . '" stroke-width="1.8"/>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="44" fill="url(#hatch)" opacity=".5"/>';
    // Ejes
    $s .= '<g stroke="' . STEEL . '" stroke-width="1" stroke-dasharray="16 5 3 5" opacity=".8">';
    $s .= '<line x1="' . ($cx - 218) . '" y1="' . $cy . '" x2="' . ($cx + 218) . '" y2="' . $cy . '"/>';
    $s .= '<line x1="' . $cx . '" y1="' . ($cy - 218) . '" x2="' . $cx . '" y2="' . ($cy + 218) . '"/></g>';
    $s .= '</g>';
    $s .= callout($cx + 124, $cy - 124, $cx + 200, $cy - 186, 'cara cerámica');
    $s .= callout($cx - 47, $cy + 47, $cx - 190, $cy + 150, 'anillo de carbón');
    $s .= dimH($cx - 176, $cx + 176, $cy + 236, 'Ø nominal');
    return $s;
};

// 2 · Sello de cartucho
$plates['sello-cartucho'] = static function () use ($cx, $cy) {
    $s = '<g>';
    $s .= '<rect x="' . ($cx - 200) . '" y="' . ($cy - 118) . '" width="400" height="236" rx="8" fill="url(#steel)" stroke="' . INK . '" stroke-width="2.4"/>';
    $s .= '<rect x="' . ($cx - 200) . '" y="' . ($cy - 46) . '" width="400" height="92" fill="url(#steel2)" stroke="' . INK . '" stroke-width="1.6"/>';
    $s .= '<rect x="' . ($cx - 128) . '" y="' . ($cy - 118) . '" width="256" height="236" fill="url(#hatch)" opacity=".45"/>';
    for ($i = -1; $i <= 1; $i += 2) {
        $s .= '<rect x="' . ($cx - 168) . '" y="' . ($cy + $i * 88 - 12) . '" width="336" height="24" rx="4" fill="url(#rubber)" stroke="' . INK . '" stroke-width="1.4"/>';
    }
    $s .= '<rect x="' . ($cx - 60) . '" y="' . ($cy - 152) . '" width="120" height="34" rx="4" fill="url(#brass)" stroke="' . INK . '" stroke-width="1.8"/>';
    $s .= '<g stroke="' . STEEL . '" stroke-width="1" stroke-dasharray="16 5 3 5"><line x1="' . ($cx - 250) . '" y1="' . $cy . '" x2="' . ($cx + 250) . '" y2="' . $cy . '"/></g>';
    $s .= '</g>';
    $s .= callout($cx, $cy - 135, $cx + 190, $cy - 190, 'tornillo de fijación');
    $s .= callout($cx - 150, $cy + 88, $cx - 210, $cy + 168, 'o-ring de eje');
    $s .= dimH($cx - 200, $cx + 200, $cy + 190, 'Longitud total');
    return $s;
};

// 3 · Empaque de brida
$plates['empaque-brida'] = static function () use ($cx, $cy) {
    $s = '<g><circle cx="' . $cx . '" cy="' . $cy . '" r="196" fill="url(#ptfe)" stroke="' . INK . '" stroke-width="2.4"/>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="106" fill="#EFF0EC" stroke="' . INK . '" stroke-width="2.2"/>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="152" fill="none" stroke="' . STEEL . '" stroke-width="1" stroke-dasharray="10 6"/>';
    for ($k = 0; $k < 8; $k++) {
        $a = deg2rad($k * 45 + 22.5);
        $hx = $cx + cos($a) * 152; $hy = $cy + sin($a) * 152;
        $s .= '<circle cx="' . round($hx, 1) . '" cy="' . round($hy, 1) . '" r="19" fill="#EFF0EC" stroke="' . INK . '" stroke-width="1.8"/>';
        $s .= '<path d="M' . round($hx - 24, 1) . ' ' . round($hy, 1) . 'h48M' . round($hx, 1) . ' ' . round($hy - 24, 1) . 'v48" stroke="' . STEEL . '" stroke-width=".9"/>';
    }
    $s .= '<g stroke="' . STEEL . '" stroke-width="1" stroke-dasharray="16 5 3 5">'
        . '<line x1="' . ($cx - 236) . '" y1="' . $cy . '" x2="' . ($cx + 236) . '" y2="' . $cy . '"/>'
        . '<line x1="' . $cx . '" y1="' . ($cy - 236) . '" x2="' . $cx . '" y2="' . ($cy + 236) . '"/></g></g>';
    $s .= callout($cx + 107, $cy - 107, $cx + 210, $cy - 178, 'diámetro interior');
    $s .= dimH($cx - 196, $cx + 196, $cy + 244, 'Ø exterior');
    return $s;
};

// 4 · Empaque espirometálico
$plates['empaque-espirometalico'] = static function () use ($cx, $cy) {
    $s = '<g><circle cx="' . $cx . '" cy="' . $cy . '" r="190" fill="url(#steel2)" stroke="' . INK . '" stroke-width="2.4"/>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="160" fill="#EFF0EC" stroke="' . INK . '" stroke-width="1.6"/>';
    // Espiral de bandas alternas
    $path = '';
    for ($t = 0.0; $t < 30.0; $t += 0.08) {
        $r = 62 + $t * 3.2;
        if ($r > 156) { break; }
        $x = $cx + cos($t) * $r; $y = $cy + sin($t) * $r;
        $path .= ($path === '' ? 'M' : 'L') . round($x, 1) . ' ' . round($y, 1);
    }
    $s .= '<path d="' . $path . '" fill="none" stroke="' . INK . '" stroke-width="3.4" opacity=".85"/>';
    $s .= '<path d="' . $path . '" fill="none" stroke="' . ACC . '" stroke-width="1.2" stroke-dasharray="6 12" opacity=".9"/>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="58" fill="url(#steel)" stroke="' . INK . '" stroke-width="2"/>';
    $s .= '</g>';
    $s .= callout($cx + 110, $cy + 96, $cx + 214, $cy + 178, 'devanado v');
    $s .= dimH($cx - 190, $cx + 190, $cy + 240, 'Ø anillo guía');
    return $s;
};

// 5 · Lámina de teflón / PTFE
$plates['lamina-teflon'] = static function () use ($cx, $cy) {
    $s = '<g>';
    $s .= '<path d="M' . ($cx - 230) . ' ' . ($cy - 130) . 'h420v260h-360l-60-60Z" fill="url(#ptfe)" stroke="' . INK . '" stroke-width="2.4" stroke-linejoin="round"/>';
    $s .= '<path d="M' . ($cx - 230) . ' ' . ($cy + 70) . 'h60v60Z" fill="#DDE0DA" stroke="' . INK . '" stroke-width="1.8"/>';
    // Espesor lateral
    $s .= '<path d="M' . ($cx + 190) . ' ' . ($cy - 130) . 'l26-22h-420l-26 22" fill="#E2E5DF" stroke="' . INK . '" stroke-width="1.8" stroke-linejoin="round"/>';
    $s .= '<path d="M' . ($cx + 190) . ' ' . ($cy + 130) . 'l26-22v-130" fill="none" stroke="' . INK . '" stroke-width="1.8"/>';
    // Textura de superficie
    for ($i = 1; $i < 9; $i++) {
        $y = $cy - 130 + $i * 29;
        $s .= '<line x1="' . ($cx - 214) . '" y1="' . $y . '" x2="' . ($cx + 176) . '" y2="' . $y . '" stroke="' . STEEL . '" stroke-width=".7" opacity=".28"/>';
    }
    $s .= '</g>';
    $s .= callout($cx + 203, $cy - 141, $cx + 250, $cy - 196, 'espesor');
    $s .= dimH($cx - 230, $cx + 190, $cy + 200, 'Ancho de lámina');
    return $s;
};

// 6 · O-ring
$plates['oring'] = static function () use ($cx, $cy) {
    $s = '<g>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="176" fill="none" stroke="url(#rubber)" stroke-width="52"/>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="202" fill="none" stroke="' . INK . '" stroke-width="2"/>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="150" fill="none" stroke="' . INK . '" stroke-width="2"/>';
    // Reflejo especular
    $s .= '<path d="M' . ($cx - 130) . ' ' . ($cy - 118) . 'A176 176 0 0 1 ' . ($cx + 26) . ' ' . ($cy - 174) . '" fill="none" stroke="#FFFFFF" stroke-width="9" opacity=".38" stroke-linecap="round"/>';
    // Sección ampliada
    $s .= '<circle cx="' . ($cx + 268) . '" cy="' . ($cy - 150) . '" r="42" fill="url(#rubber)" stroke="' . INK . '" stroke-width="2"/>';
    $s .= '<circle cx="' . ($cx + 268) . '" cy="' . ($cy - 150) . '" r="42" fill="url(#hatch)" opacity=".4"/>';
    $s .= '<circle cx="' . ($cx + 268) . '" cy="' . ($cy - 150) . '" r="58" fill="none" stroke="' . ACC . '" stroke-width="1.3" stroke-dasharray="5 5"/>';
    $s .= '<g stroke="' . STEEL . '" stroke-width="1" stroke-dasharray="16 5 3 5">'
        . '<line x1="' . ($cx - 240) . '" y1="' . $cy . '" x2="' . ($cx + 240) . '" y2="' . $cy . '"/>'
        . '<line x1="' . $cx . '" y1="' . ($cy - 240) . '" x2="' . $cx . '" y2="' . ($cy + 240) . '"/></g>';
    $s .= '</g>';
    $s .= callout($cx + 176, $cy - 12, $cx + 236, $cy + 96, 'sección Ø');
    $s .= dimH($cx - 202, $cx + 202, $cy + 250, 'Ø interior + 2·sección');
    return $s;
};

// 7 · Retén de aceite
$plates['reten-aceite'] = static function () use ($cx, $cy) {
    $s = '<g>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="188" fill="url(#rubber)" stroke="' . INK . '" stroke-width="2.4"/>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="160" fill="#EFF0EC" stroke="' . INK . '" stroke-width="1.6"/>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="120" fill="url(#rubber)" stroke="' . INK . '" stroke-width="1.6"/>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="96" fill="#EFF0EC" stroke="' . INK . '" stroke-width="2"/>';
    // Resorte de labio
    $s .= '<g fill="none" stroke="' . STEEL . '" stroke-width="2.6">';
    for ($a = 0; $a < 360; $a += 12) {
        $x1 = $cx + cos(deg2rad($a)) * 106; $y1 = $cy + sin(deg2rad($a)) * 106;
        $x2 = $cx + cos(deg2rad($a + 7)) * 114; $y2 = $cy + sin(deg2rad($a + 7)) * 114;
        $s .= '<line x1="' . round($x1, 1) . '" y1="' . round($y1, 1) . '" x2="' . round($x2, 1) . '" y2="' . round($y2, 1) . '"/>';
    }
    $s .= '</g>';
    $s .= '</g>';
    $s .= callout($cx + 110, $cy - 40, $cx + 224, $cy - 150, 'resorte de labio');
    $s .= dimH($cx - 188, $cx + 188, $cy + 240, 'Ø exterior');
    return $s;
};

// 8 · Válvula de compuerta
$plates['valvula'] = static function () use ($cx, $cy) {
    $s = '<g>';
    $s .= '<rect x="' . ($cx - 200) . '" y="' . ($cy + 10) . '" width="400" height="86" rx="6" fill="url(#steel)" stroke="' . INK . '" stroke-width="2.4"/>';
    foreach ([-1, 1] as $sgn) {
        $s .= '<rect x="' . ($cx + $sgn * 200 - ($sgn > 0 ? 0 : 30)) . '" y="' . ($cy - 6) . '" width="30" height="118" fill="url(#steel2)" stroke="' . INK . '" stroke-width="2"/>';
    }
    $s .= '<path d="M' . ($cx - 74) . ' ' . ($cy + 10) . 'L' . ($cx - 44) . ' ' . ($cy - 116) . 'h88l30 126Z" fill="url(#steel2)" stroke="' . INK . '" stroke-width="2.2"/>';
    $s .= '<rect x="' . ($cx - 10) . '" y="' . ($cy - 196) . '" width="20" height="86" fill="url(#steel)" stroke="' . INK . '" stroke-width="1.8"/>';
    $s .= '<ellipse cx="' . $cx . '" cy="' . ($cy - 200) . '" rx="104" ry="26" fill="none" stroke="' . INK . '" stroke-width="7"/>';
    $s .= '<ellipse cx="' . $cx . '" cy="' . ($cy - 200) . '" rx="104" ry="26" fill="none" stroke="url(#steel2)" stroke-width="4"/>';
    for ($k = -2; $k <= 2; $k++) {
        $s .= '<line x1="' . $cx . '" y1="' . ($cy - 200) . '" x2="' . ($cx + $k * 48) . '" y2="' . ($cy - 200 + ($k === 0 ? 0 : 12)) . '" stroke="' . INK . '" stroke-width="3"/>';
    }
    $s .= '<line x1="' . ($cx - 230) . '" y1="' . ($cy + 53) . '" x2="' . ($cx + 230) . '" y2="' . ($cy + 53) . '" stroke="' . STEEL . '" stroke-width="1" stroke-dasharray="16 5 3 5"/>';
    $s .= '</g>';
    $s .= callout($cx, $cy - 214, $cx + 190, $cy - 250, 'volante');
    $s .= callout($cx + 200, $cy + 53, $cx + 268, $cy + 150, 'brida');
    $s .= dimH($cx - 230, $cx + 230, $cy + 190, 'Cara a cara');
    return $s;
};

// 9 · Rodamiento rígido de bolas
$plates['rodamiento'] = static function () use ($cx, $cy) {
    $s = '<g>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="196" fill="url(#steel)" stroke="' . INK . '" stroke-width="2.4"/>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="152" fill="#EFF0EC" stroke="' . INK . '" stroke-width="1.8"/>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="104" fill="url(#steel)" stroke="' . INK . '" stroke-width="2.2"/>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="62" fill="#EFF0EC" stroke="' . INK . '" stroke-width="2.2"/>';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="62" fill="url(#hatch)" opacity=".45"/>';
    for ($k = 0; $k < 11; $k++) {
        $a = deg2rad($k * 360 / 11);
        $bx = $cx + cos($a) * 128; $by = $cy + sin($a) * 128;
        $s .= '<circle cx="' . round($bx, 1) . '" cy="' . round($by, 1) . '" r="21" fill="url(#steel2)" stroke="' . INK . '" stroke-width="1.6"/>';
        $s .= '<circle cx="' . round($bx - 6, 1) . '" cy="' . round($by - 6, 1) . '" r="6" fill="#FFFFFF" opacity=".55"/>';
    }
    $s .= '</g>';
    $s .= callout($cx + 128, $cy - 12, $cx + 244, $cy - 130, 'elemento rodante');
    $s .= dimH($cx - 196, $cx + 196, $cy + 244, 'Ø exterior');
    return $s;
};

// 10 · Manguera industrial
$plates['manguera'] = static function () use ($cx, $cy) {
    $s = '<g>';
    $s .= '<path d="M' . ($cx - 250) . ' ' . ($cy + 60) . 'C' . ($cx - 120) . ' ' . ($cy - 190) . ' ' . ($cx + 120) . ' ' . ($cy + 200) . ' ' . ($cx + 250) . ' ' . ($cy - 40) . '" fill="none" stroke="url(#rubber)" stroke-width="76" stroke-linecap="round"/>';
    $s .= '<path d="M' . ($cx - 250) . ' ' . ($cy + 60) . 'C' . ($cx - 120) . ' ' . ($cy - 190) . ' ' . ($cx + 120) . ' ' . ($cy + 200) . ' ' . ($cx + 250) . ' ' . ($cy - 40) . '" fill="none" stroke="' . INK . '" stroke-width="80" opacity=".18" stroke-linecap="round"/>';
    $s .= '<path d="M' . ($cx - 250) . ' ' . ($cy + 60) . 'C' . ($cx - 120) . ' ' . ($cy - 190) . ' ' . ($cx + 120) . ' ' . ($cy + 200) . ' ' . ($cx + 250) . ' ' . ($cy - 40) . '" fill="none" stroke="#FFFFFF" stroke-width="7" opacity=".22" stroke-dasharray="2 26" stroke-linecap="round"/>';
    // Refuerzo helicoidal
    $s .= '<path d="M' . ($cx - 250) . ' ' . ($cy + 60) . 'C' . ($cx - 120) . ' ' . ($cy - 190) . ' ' . ($cx + 120) . ' ' . ($cy + 200) . ' ' . ($cx + 250) . ' ' . ($cy - 40) . '" fill="none" stroke="' . STEEL . '" stroke-width="72" opacity=".28" stroke-dasharray="4 22"/>';
    // Acoples
    $s .= '<rect x="' . ($cx - 292) . '" y="' . ($cy + 16) . '" width="58" height="90" rx="6" fill="url(#brass)" stroke="' . INK . '" stroke-width="2" transform="rotate(-24 ' . ($cx - 262) . ' ' . ($cy + 60) . ')"/>';
    $s .= '<rect x="' . ($cx + 234) . '" y="' . ($cy - 84) . '" width="58" height="90" rx="6" fill="url(#brass)" stroke="' . INK . '" stroke-width="2" transform="rotate(-24 ' . ($cx + 262) . ' ' . ($cy - 40) . ')"/>';
    $s .= '</g>';
    $s .= callout($cx - 262, $cy + 60, $cx - 300, $cy + 170, 'acople');
    $s .= dimH($cx - 250, $cx + 250, $cy + 224, 'Longitud de tramo');
    return $s;
};

// 11 · Banda en V
$plates['banda-v'] = static function () use ($cx, $cy) {
    $s = '<g>';
    $s .= '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="228" ry="150" fill="none" stroke="url(#rubber)" stroke-width="42"/>';
    $s .= '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="249" ry="171" fill="none" stroke="' . INK . '" stroke-width="1.8"/>';
    $s .= '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="207" ry="129" fill="none" stroke="' . INK . '" stroke-width="1.8"/>';
    // Dentado interior
    $s .= '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="212" ry="134" fill="none" stroke="' . STEEL . '" stroke-width="12" stroke-dasharray="7 11" opacity=".6"/>';
    // Sección trapezoidal ampliada
    $sx = $cx + 268; $sy = $cy - 168;
    $s .= '<path d="M' . ($sx - 44) . ' ' . ($sy - 26) . 'h88l-22 56h-44Z" fill="url(#rubber)" stroke="' . INK . '" stroke-width="2"/>';
    $s .= '<path d="M' . ($sx - 44) . ' ' . ($sy - 26) . 'h88l-22 56h-44Z" fill="url(#hatch)" opacity=".38"/>';
    $s .= '<circle cx="' . $sx . '" cy="' . ($sy + 2) . '" r="62" fill="none" stroke="' . ACC . '" stroke-width="1.3" stroke-dasharray="5 5"/>';
    $s .= '</g>';
    $s .= callout($cx + 228, $cy, $cx + 250, $cy + 120, 'perfil en V');
    $s .= dimH($cx - 249, $cx + 249, $cy + 216, 'Longitud primitiva');
    return $s;
};

// 12 · Acople flexible
$plates['acople'] = static function () use ($cx, $cy) {
    $s = '<g>';
    foreach ([-1, 1] as $sgn) {
        $x = $cx + $sgn * 96;
        $s .= '<ellipse cx="' . $x . '" cy="' . $cy . '" rx="42" ry="150" fill="url(#steel)" stroke="' . INK . '" stroke-width="2.4"/>';
        $s .= '<rect x="' . ($x + $sgn * 42 - ($sgn > 0 ? 118 : 0)) . '" y="' . ($cy - 150) . '" width="118" height="300" fill="url(#steel2)" opacity=".55"/>';
        $s .= '<rect x="' . ($x + $sgn * 42) . '" y="' . ($cy - 46) . '" width="' . (76) . '" height="92" fill="url(#steel)" stroke="' . INK . '" stroke-width="2" transform="translate(' . ($sgn > 0 ? 0 : -76) . ',0)"/>';
    }
    $s .= '<rect x="' . ($cx - 54) . '" y="' . ($cy - 132) . '" width="108" height="264" fill="url(#rubber)" stroke="' . INK . '" stroke-width="2.2" rx="6"/>';
    for ($k = -2; $k <= 2; $k++) {
        $s .= '<line x1="' . ($cx - 54) . '" y1="' . ($cy + $k * 48) . '" x2="' . ($cx + 54) . '" y2="' . ($cy + $k * 48) . '" stroke="' . STEEL . '" stroke-width="2" opacity=".6"/>';
    }
    $s .= '<line x1="' . ($cx - 250) . '" y1="' . $cy . '" x2="' . ($cx + 250) . '" y2="' . $cy . '" stroke="' . STEEL . '" stroke-width="1" stroke-dasharray="16 5 3 5"/>';
    $s .= '</g>';
    $s .= callout($cx, $cy - 132, $cx + 200, $cy - 200, 'elemento elástico');
    $s .= dimH($cx - 214, $cx + 214, $cy + 214, 'Distancia entre ejes');
    return $s;
};

// 13 · Plancha de hule
$plates['plancha-hule'] = static function () use ($cx, $cy) {
    $s = '<g>';
    $s .= '<ellipse cx="' . ($cx - 130) . '" cy="' . $cy . '" rx="52" ry="160" fill="url(#rubber)" stroke="' . INK . '" stroke-width="2.4"/>';
    $s .= '<rect x="' . ($cx - 130) . '" y="' . ($cy - 160) . '" width="300" height="320" fill="url(#rubber)" stroke="none"/>';
    $s .= '<path d="M' . ($cx - 130) . ' ' . ($cy - 160) . 'h300M' . ($cx - 130) . ' ' . ($cy + 160) . 'h300" stroke="' . INK . '" stroke-width="2.2"/>';
    $s .= '<ellipse cx="' . ($cx + 170) . '" cy="' . $cy . '" rx="52" ry="160" fill="url(#rubber)" stroke="' . INK . '" stroke-width="2.4"/>';
    $s .= '<ellipse cx="' . ($cx + 170) . '" cy="' . $cy . '" rx="22" ry="70" fill="#EFF0EC" stroke="' . INK . '" stroke-width="1.8"/>';
    // Hoja desenrollada
    $s .= '<path d="M' . ($cx - 130) . ' ' . ($cy + 160) . 'l-140 44v-38l140-46Z" fill="url(#rubber)" stroke="' . INK . '" stroke-width="2" stroke-linejoin="round"/>';
    for ($i = 1; $i < 7; $i++) {
        $s .= '<line x1="' . ($cx - 110 + $i * 40) . '" y1="' . ($cy - 158) . '" x2="' . ($cx - 110 + $i * 40) . '" y2="' . ($cy + 158) . '" stroke="#FFFFFF" stroke-width="1" opacity=".1"/>';
    }
    $s .= '</g>';
    $s .= callout($cx - 250, $cy + 186, $cx - 300, $cy + 236, 'calibre');
    $s .= dimH($cx - 130, $cx + 170, $cy - 214, 'Ancho de rollo');
    return $s;
};

// 14 · Grasa / lubricante en cartucho
$plates['lubricante'] = static function () use ($cx, $cy) {
    $s = '<g>';
    $s .= '<rect x="' . ($cx - 96) . '" y="' . ($cy - 170) . '" width="192" height="330" rx="10" fill="url(#steel2)" stroke="' . INK . '" stroke-width="2.4"/>';
    $s .= '<rect x="' . ($cx - 96) . '" y="' . ($cy - 60) . '" width="192" height="150" fill="' . ACC . '" opacity=".92"/>';
    $s .= '<rect x="' . ($cx - 96) . '" y="' . ($cy - 60) . '" width="192" height="150" fill="none" stroke="' . INK . '" stroke-width="1.6"/>';
    $s .= '<path d="M' . ($cx - 30) . ' ' . ($cy - 170) . 'v-52h60v52" fill="url(#steel)" stroke="' . INK . '" stroke-width="2"/>';
    $s .= '<rect x="' . ($cx - 62) . '" y="' . ($cy - 244) . '" width="124" height="28" rx="4" fill="url(#brass)" stroke="' . INK . '" stroke-width="1.8"/>';
    for ($i = 0; $i < 4; $i++) {
        $s .= '<line x1="' . ($cx - 70) . '" y1="' . ($cy - 20 + $i * 30) . '" x2="' . ($cx + 40) . '" y2="' . ($cy - 20 + $i * 30) . '" stroke="#FFFFFF" stroke-width="4" opacity=".7"/>';
    }
    $s .= '<rect x="' . ($cx - 96) . '" y="' . ($cy + 108) . '" width="192" height="34" fill="#EFF0EC" stroke="' . INK . '" stroke-width="1.4"/>';
    $s .= '</g>';
    $s .= callout($cx, $cy - 244, $cx + 190, $cy - 250, 'boquilla');
    $s .= dimH($cx - 96, $cx + 96, $cy + 214, 'Ø cartucho');
    return $s;
};

// 15 · Camisa de uniforme
$plates['uniforme-camisa'] = static function () use ($cx, $cy) {
    $s = '<g>';
    $s .= '<path d="M' . ($cx - 60) . ' ' . ($cy - 190) . 'l-120 42-46 116 62 26 22-56v250h264v-250l22 56 62-26-46-116-120-42-30 34Z" fill="url(#cloth)" stroke="' . INK . '" stroke-width="2.4" stroke-linejoin="round"/>';
    $s .= '<path d="M' . ($cx - 60) . ' ' . ($cy - 190) . 'l60 66 60-66" fill="#EFF0EC" stroke="' . INK . '" stroke-width="2"/>';
    $s .= '<line x1="' . $cx . '" y1="' . ($cy - 124) . '" x2="' . $cx . '" y2="' . ($cy + 188) . '" stroke="' . INK . '" stroke-width="1.6"/>';
    for ($i = 0; $i < 5; $i++) {
        $s .= '<circle cx="' . $cx . '" cy="' . ($cy - 92 + $i * 60) . '" r="6" fill="#EFF0EC" stroke="' . INK . '" stroke-width="1.4"/>';
    }
    $s .= '<rect x="' . ($cx - 108) . '" y="' . ($cy - 68) . '" width="60" height="52" rx="3" fill="none" stroke="' . INK . '" stroke-width="1.6"/>';
    $s .= '<rect x="' . ($cx + 46) . '" y="' . ($cy - 74) . '" width="66" height="26" rx="3" fill="' . ACC . '" opacity=".9"/>';
    $s .= '</g>';
    $s .= callout($cx + 79, $cy - 61, $cx + 240, $cy - 130, 'bordado');
    $s .= dimH($cx - 132, $cx + 132, $cy + 236, 'Ancho de pecho');
    return $s;
};

// 16 · Casco de seguridad
$plates['casco'] = static function () use ($cx, $cy) {
    $s = '<g>';
    $s .= '<path d="M' . ($cx - 200) . ' ' . ($cy + 70) . 'a200 200 0 0 1 400 0Z" fill="' . ACC . '" stroke="' . INK . '" stroke-width="2.4"/>';
    $s .= '<path d="M' . ($cx - 200) . ' ' . ($cy + 70) . 'a200 200 0 0 1 400 0Z" fill="url(#hatch)" opacity=".12"/>';
    $s .= '<ellipse cx="' . $cx . '" cy="' . ($cy + 70) . '" rx="240" ry="34" fill="' . ACC . '" stroke="' . INK . '" stroke-width="2.4"/>';
    $s .= '<ellipse cx="' . $cx . '" cy="' . ($cy + 62) . '" rx="200" ry="24" fill="none" stroke="' . INK . '" stroke-width="1.4" opacity=".5"/>';
    $s .= '<path d="M' . $cx . ' ' . ($cy - 130) . 'v190" stroke="' . INK . '" stroke-width="2.4"/>';
    $s .= '<path d="M' . ($cx - 62) . ' ' . ($cy - 112) . 'q62 -30 124 0" fill="none" stroke="' . INK . '" stroke-width="1.6"/>';
    $s .= '<path d="M' . ($cx - 150) . ' ' . ($cy + 12) . 'q150 -80 300 0" fill="none" stroke="#FFFFFF" stroke-width="8" opacity=".28"/>';
    $s .= '</g>';
    $s .= callout($cx, $cy - 110, $cx + 224, $cy - 176, 'cresta de refuerzo');
    $s .= dimH($cx - 240, $cx + 240, $cy + 176, 'Ø ala');
    return $s;
};

$meta = [
    'sello-mecanico'          => ['Sello mecánico tipo fuelle', 'SM-01', 'vista frontal · escala 1:1'],
    'sello-cartucho'          => ['Sello mecánico de cartucho', 'SC-02', 'corte longitudinal'],
    'empaque-brida'           => ['Empaque de brida', 'EB-03', 'vista frontal · 8 perforaciones'],
    'empaque-espirometalico'  => ['Empaque espirometálico', 'EE-04', 'devanado en espiral'],
    'lamina-teflon'           => ['Lámina de PTFE', 'LT-05', 'vista isométrica'],
    'oring'                   => ['Anillo O-ring', 'OR-06', 'frontal + detalle de sección'],
    'reten-aceite'            => ['Retén de aceite', 'RA-07', 'vista frontal'],
    'valvula'                 => ['Válvula de compuerta', 'VC-08', 'alzado con bridas'],
    'rodamiento'              => ['Rodamiento rígido de bolas', 'RB-09', 'vista frontal'],
    'manguera'                => ['Manguera industrial', 'MI-10', 'tramo con acoples'],
    'banda-v'                 => ['Banda en V', 'BV-11', 'frontal + perfil'],
    'acople'                  => ['Acople flexible', 'AF-12', 'alzado'],
    'plancha-hule'            => ['Plancha de hule en rollo', 'PH-13', 'isométrica'],
    'lubricante'              => ['Grasa industrial en cartucho', 'GR-14', 'alzado'],
    'uniforme-camisa'         => ['Camisa industrial', 'UC-15', 'patrón frontal'],
    'casco'                   => ['Casco de seguridad', 'CS-16', 'alzado'],
];

foreach ($plates as $key => $draw) {
    [$title, $code, $note] = $meta[$key];
    $svg = head($title) . marks() . $draw() . cartouche($code, $note) . tail();
    file_put_contents($OUT . '/' . $key . '.svg', $svg);
    echo "· {$key}.svg  " . number_format(strlen($svg) / 1024, 1) . " KB\n";
}
echo 'Listo: ' . count($plates) . " láminas en {$OUT}\n";
