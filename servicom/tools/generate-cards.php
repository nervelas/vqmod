<?php
declare(strict_types=1);
/**
 * Ilustraciones de servicio (SVG) para el catálogo de Servicom.
 * Vectores propios, sin dependencias ni fotografía de terceros.
 *
 *   php tools/generate-cards.php
 */

require __DIR__ . '/../app/bootstrap.php';

$OUT = BASE_PATH . '/assets/img/cards';
@mkdir($OUT, 0755, true);

const W    = 900;
const H    = 675;
const INK  = '#0A1024';
const SOFT = '#3A4667';
const HAIR = '#C6D0E4';
const ACC  = '#1D5BFF';
const ACC2 = '#00C2A8';
const PAPR = '#F1F5FC';
const CARD = '#FFFFFF';

function head(string $title): string
{
    $t = htmlspecialchars($title, ENT_QUOTES);
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . W . ' ' . H . '" width="' . W . '" height="' . H . '" role="img" aria-label="' . $t . '">'
        . '<title>' . $t . '</title>'
        . '<defs>'
        . '<linearGradient id="sky" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0" stop-color="#EDF3FF"/><stop offset="1" stop-color="#E4ECFA"/></linearGradient>'
        . '<linearGradient id="acc" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0" stop-color="' . ACC . '"/><stop offset="1" stop-color="#4B8CFF"/></linearGradient>'
        . '<linearGradient id="teal" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0" stop-color="' . ACC2 . '"/><stop offset="1" stop-color="#4BE0C8"/></linearGradient>'
        . '<linearGradient id="glass" x1="0" y1="0" x2="0.3" y2="1">'
        . '<stop offset="0" stop-color="#FFFFFF"/><stop offset="1" stop-color="#F2F6FD"/></linearGradient>'
        . '<pattern id="dots" width="22" height="22" patternUnits="userSpaceOnUse">'
        . '<circle cx="1.6" cy="1.6" r="1.6" fill="' . SOFT . '" opacity=".16"/></pattern>'
        . '<filter id="lift" x="-20%" y="-20%" width="140%" height="150%">'
        . '<feDropShadow dx="0" dy="14" stdDeviation="16" flood-color="#0A1024" flood-opacity=".13"/></filter>'
        . '<filter id="soft" x="-20%" y="-20%" width="140%" height="150%">'
        . '<feDropShadow dx="0" dy="6" stdDeviation="7" flood-color="#0A1024" flood-opacity=".10"/></filter>'
        . '</defs>'
        . '<rect width="' . W . '" height="' . H . '" fill="url(#sky)"/>'
        . '<rect width="' . W . '" height="' . H . '" fill="url(#dots)"/>';
}

/** Marcas de encuadre discretas en las esquinas. */
function marks(): string
{
    $m = 30; $l = 22;
    $s = '<g stroke="' . SOFT . '" stroke-width="1.4" opacity=".38" fill="none" stroke-linecap="round">';
    $s .= '<path d="M' . $m . ' ' . ($m + $l) . 'V' . $m . 'h' . $l . '"/>';
    $s .= '<path d="M' . (W - $m - $l) . ' ' . $m . 'h' . $l . 'v' . $l . '"/>';
    $s .= '<path d="M' . $m . ' ' . (H - $m - $l) . 'V' . (H - $m) . 'h' . $l . '"/>';
    $s .= '<path d="M' . (W - $m - $l) . ' ' . (H - $m) . 'h' . $l . 'v-' . $l . '"/>';
    $s .= '</g>';
    return $s;
}

function foot(string $label): string
{
    return marks()
        . '<line x1="70" y1="' . (H - 86) . '" x2="' . (W - 70) . '" y2="' . (H - 86) . '" stroke="' . HAIR . '" stroke-width="1"/>'
        . '<text x="70" y="' . (H - 58) . '" font-family="Inter, Helvetica, Arial, sans-serif" font-size="14" letter-spacing="3.6" fill="' . SOFT . '">'
        . htmlspecialchars(mb_strtoupper($label), ENT_QUOTES) . '</text>'
        . '<text x="' . (W - 70) . '" y="' . (H - 58) . '" text-anchor="end" font-family="Inter, Helvetica, Arial, sans-serif" font-weight="700" font-size="14" letter-spacing="3.6" fill="' . ACC . '">SERVICOM</text>'
        . '</svg>';
}

/** Ventana de navegador con barra, semáforo y URL. */
function browser(float $x, float $y, float $w, float $h, string $url = '', string $inner = '', float $r = 14): string
{
    $bar = 40;
    $s = '<g filter="url(#lift)">';
    $s .= '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="' . $r . '" fill="' . CARD . '"/>';
    $s .= '</g>';
    $s .= '<path d="M' . $x . ' ' . ($y + $bar) . 'h' . $w . '" stroke="' . HAIR . '" stroke-width="1.2"/>';
    $s .= '<g fill="' . HAIR . '">';
    foreach ([0, 1, 2] as $i) {
        $s .= '<circle cx="' . ($x + 22 + $i * 17) . '" cy="' . ($y + 20) . '" r="4.6"/>';
    }
    $s .= '</g>';
    if ($url !== '') {
        $uw = min($w - 130, 300);
        $s .= '<rect x="' . ($x + 82) . '" y="' . ($y + 10) . '" width="' . $uw . '" height="20" rx="10" fill="' . PAPR . '"/>';
        $s .= '<text x="' . ($x + 96) . '" y="' . ($y + 24) . '" font-family="Inter, Helvetica, Arial, sans-serif" font-size="11" fill="' . SOFT . '">'
            . htmlspecialchars($url, ENT_QUOTES) . '</text>';
    }
    return $s . $inner;
}

function bar(float $x, float $y, float $w, float $h, string $fill, float $r = 4, float $op = 1): string
{
    return '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="' . $r . '" fill="' . $fill . '" opacity="' . $op . '"/>';
}

$cards = [];

/* ------------------------------------------------- sitio web corporativo */
$in  = bar(210, 250, 300, 16, INK, 4, .88) . bar(210, 276, 210, 16, INK, 4, .5);
$in .= bar(210, 312, 128, 34, ACC, 8);
$in .= '<rect x="470" y="238" width="220" height="120" rx="10" fill="url(#acc)" opacity=".14"/>';
$in .= '<circle cx="580" cy="284" r="26" fill="url(#acc)" opacity=".9"/>';
$in .= '<path d="M568 284h24M580 272v24" stroke="#fff" stroke-width="3" stroke-linecap="round"/>';
foreach ([0, 1, 2] as $i) {
    $x = 210 + $i * 163;
    $in .= '<rect x="' . $x . '" y="382" width="140" height="86" rx="10" fill="' . PAPR . '"/>';
    $in .= bar($x + 16, 404, 60, 9, ACC, 4, .8) . bar($x + 16, 421, 108, 8, INK, 4, .28) . bar($x + 16, 435, 84, 8, INK, 4, .18);
}
$cards['sitio-corporativo'] = head('Sitio web corporativo')
    . browser(180, 150, 540, 380, 'suempresa.com', $in) . foot('Sitio corporativo');

/* -------------------------------------------------------- landing page */
$in  = bar(230, 230, 260, 20, INK, 5, .9) . bar(230, 260, 190, 20, ACC, 5, .85);
$in .= bar(230, 300, 300, 10, INK, 4, .3) . bar(230, 318, 250, 10, INK, 4, .22);
$in .= '<rect x="230" y="348" width="150" height="40" rx="20" fill="url(#acc)"/>';
$in .= '<text x="305" y="374" text-anchor="middle" font-family="Inter, Helvetica, Arial, sans-serif" font-weight="700" font-size="14" fill="#fff">Cotizar</text>';
$in .= '<rect x="560" y="230" width="130" height="230" rx="12" fill="' . PAPR . '"/>';
$in .= '<circle cx="625" cy="292" r="30" fill="url(#teal)" opacity=".85"/>';
$in .= bar(584, 340, 82, 9, INK, 4, .28) . bar(584, 357, 60, 9, INK, 4, .2);
$in .= bar(584, 386, 82, 9, INK, 4, .28) . bar(584, 403, 66, 9, INK, 4, .2);
$cards['landing-page'] = head('Landing page de campaña')
    . browser(180, 150, 540, 380, 'suempresa.com/oferta', $in) . foot('Landing de campaña');

/* ------------------------------------------------------- tienda virtual */
$in = '';
foreach ([0, 1, 2, 3, 4, 5] as $i) {
    $x = 208 + ($i % 3) * 152;
    $y = 232 + intdiv($i, 3) * 140;
    $in .= '<rect x="' . $x . '" y="' . $y . '" width="130" height="122" rx="10" fill="' . PAPR . '"/>';
    $in .= '<rect x="' . ($x + 14) . '" y="' . ($y + 14) . '" width="102" height="56" rx="7" fill="' . ($i % 2 ? '#DCE6FA' : '#D6EFEA') . '"/>';
    $in .= bar($x + 14, $y + 80, 74, 8, INK, 4, .3) . bar($x + 14, $y + 94, 44, 10, ACC, 4, .9);
}
$in .= '<g filter="url(#soft)"><circle cx="668" cy="212" r="30" fill="url(#acc)"/></g>';
$in .= '<path d="M655 203h5l4 17h13l4-11" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>';
$in .= '<circle cx="666" cy="226" r="2.6" fill="#fff"/><circle cx="678" cy="226" r="2.6" fill="#fff"/>';
$cards['tienda-virtual'] = head('Tienda virtual')
    . browser(170, 140, 560, 400, 'suempresa.com/tienda', $in) . foot('Tienda virtual');

/* --------------------------------------------------- cuentas de correo */
$c  = '<g filter="url(#lift)"><rect x="250" y="196" width="400" height="250" rx="16" fill="' . CARD . '"/></g>';
$c .= '<path d="M250 212 L450 344 L650 212" fill="none" stroke="' . ACC . '" stroke-width="3.2" stroke-linejoin="round"/>';
$c .= '<path d="M250 430 L392 316M650 430 L508 316" fill="none" stroke="' . HAIR . '" stroke-width="2"/>';
$c .= '<g filter="url(#soft)"><circle cx="640" cy="416" r="40" fill="url(#teal)"/></g>';
$c .= '<text x="640" y="432" text-anchor="middle" font-family="Inter, Helvetica, Arial, sans-serif" font-weight="700" font-size="34" fill="#fff">@</text>';
foreach ([0, 1, 2] as $i) {
    $c .= bar(292, 470 + $i * 24, 200 - $i * 46, 10, INK, 5, .22 - $i * 0.05);
}
$cards['correo-corporativo'] = head('Cuentas de correo corporativo') . $c . foot('Correo corporativo');

/* ------------------------------------------------------------ dominio */
$c  = '<circle cx="450" cy="318" r="128" fill="url(#glass)" filter="url(#lift)"/>';
$c .= '<circle cx="450" cy="318" r="128" fill="none" stroke="' . ACC . '" stroke-width="2.2" opacity=".55"/>';
$c .= '<ellipse cx="450" cy="318" rx="52" ry="128" fill="none" stroke="' . SOFT . '" stroke-width="1.5" opacity=".45"/>';
$c .= '<path d="M322 318h256M340 254h220M340 382h220" stroke="' . SOFT . '" stroke-width="1.5" opacity=".45"/>';
$c .= '<g filter="url(#soft)"><rect x="342" y="404" width="216" height="60" rx="30" fill="url(#acc)"/></g>';
$c .= '<text x="450" y="443" text-anchor="middle" font-family="Inter, Helvetica, Arial, sans-serif" font-weight="700" font-size="26" letter-spacing="1" fill="#fff">suempresa.gt</text>';
$cards['dominio'] = head('Registro de dominio') . $c . foot('Dominio');

/* ------------------------------------------------------------ hosting */
$c = '';
foreach ([0, 1, 2] as $i) {
    $y = 218 + $i * 84;
    $c .= '<g filter="url(#soft)"><rect x="288" y="' . $y . '" width="324" height="62" rx="12" fill="' . CARD . '"/></g>';
    $c .= '<circle cx="322" cy="' . ($y + 31) . '" r="7" fill="' . ($i === 0 ? ACC2 : ACC) . '"/>';
    $c .= bar(346, $y + 20, 150, 9, INK, 4, .28) . bar(346, $y + 36, 96, 9, INK, 4, .18);
    foreach ([0, 1, 2, 3] as $k) {
        $c .= bar(520 + $k * 18, $y + 22, 8, 20, ACC, 3, .25 + $k * 0.18);
    }
}
$c .= '<path d="M450 480v34M424 500l26 26 26-26" fill="none" stroke="' . ACC . '" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>';
$cards['hosting'] = head('Hosting administrado') . $c . foot('Hosting');

/* -------------------------------------------------------- certificado */
$c  = '<g filter="url(#lift)"><path d="M450 176 L596 226 V344 c0 84 -62 140 -146 168 c-84 -28 -146 -84 -146 -168 V226 Z" fill="' . CARD . '"/></g>';
$c .= '<path d="M450 176 L596 226 V344 c0 84 -62 140 -146 168 c-84 -28 -146 -84 -146 -168 V226 Z" fill="none" stroke="' . ACC . '" stroke-width="2.6" opacity=".7"/>';
$c .= '<rect x="404" y="306" width="92" height="74" rx="12" fill="url(#acc)"/>';
$c .= '<path d="M422 306v-22a28 28 0 0 1 56 0v22" fill="none" stroke="' . ACC . '" stroke-width="9" stroke-linecap="round"/>';
$c .= '<circle cx="450" cy="338" r="8" fill="#fff"/><rect x="446" y="342" width="8" height="18" rx="4" fill="#fff"/>';
$c .= '<text x="450" y="470" text-anchor="middle" font-family="Inter, Helvetica, Arial, sans-serif" font-weight="700" font-size="17" letter-spacing="3" fill="' . SOFT . '">HTTPS · SSL</text>';
$cards['certificado-ssl'] = head('Certificado SSL') . $c . foot('Certificado SSL');

/* ----------------------------------------------------------------- SEO */
$c = '<g filter="url(#lift)"><rect x="228" y="178" width="444" height="300" rx="16" fill="' . CARD . '"/></g>';
$hs = [46, 82, 68, 128, 158, 210];
foreach ($hs as $i => $h) {
    $x = 268 + $i * 62;
    $c .= '<rect x="' . $x . '" y="' . (430 - $h) . '" width="38" height="' . $h . '" rx="7" fill="' . ($i >= 4 ? 'url(#acc)' : SOFT) . '" opacity="' . ($i >= 4 ? '1' : '.28') . '"/>';
}
$pts = [];
foreach ($hs as $i => $h) {
    $pts[] = (268 + $i * 62 + 19) . ',' . (430 - $h - 22);
}
$c .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . ACC2 . '" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"/>';
foreach ($pts as $p) {
    [$px, $py] = explode(',', $p);
    $c .= '<circle cx="' . $px . '" cy="' . $py . '" r="4.6" fill="#fff" stroke="' . ACC2 . '" stroke-width="2.6"/>';
}
$c .= '<circle cx="612" cy="238" r="34" fill="none" stroke="' . ACC . '" stroke-width="5"/>';
$c .= '<path d="M636 262l30 30" stroke="' . ACC . '" stroke-width="7" stroke-linecap="round"/>';
$cards['posicionamiento-seo'] = head('Posicionamiento en buscadores') . $c . foot('SEO');

/* -------------------------------------------------------- mantenimiento */
$in  = bar(220, 240, 180, 14, INK, 4, .28) . bar(220, 264, 120, 14, INK, 4, .18);
$in .= '<circle cx="560" cy="330" r="74" fill="none" stroke="' . HAIR . '" stroke-width="14"/>';
$in .= '<path d="M560 256a74 74 0 0 1 62 114" fill="none" stroke="url(#acc)" stroke-width="14" stroke-linecap="round"/>';
$in .= '<circle cx="560" cy="330" r="26" fill="url(#teal)"/>';
$in .= '<path d="M552 330h16M560 322v16" stroke="#fff" stroke-width="3.4" stroke-linecap="round"/>';
foreach ([0, 1, 2] as $i) {
    $in .= '<rect x="220" y="' . (312 + $i * 40) . '" width="220" height="28" rx="8" fill="' . PAPR . '"/>';
    $in .= '<path d="M236 ' . (326 + $i * 40) . 'l7 7 13 -14" fill="none" stroke="' . ACC2 . '" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>';
    $in .= bar(268, 321 + $i * 40, 120 - $i * 22, 10, INK, 4, .22);
}
$cards['mantenimiento'] = head('Mantenimiento y soporte')
    . browser(180, 160, 540, 360, 'panel.suempresa.com', $in) . foot('Mantenimiento');

/* --------------------------------------------------------- redes sociales */
$c  = '<g filter="url(#soft)"><rect x="238" y="200" width="286" height="132" rx="22" fill="' . CARD . '"/></g>';
$c .= '<path d="M300 332l-8 40 46-40z" fill="' . CARD . '"/>';
$c .= bar(272, 236, 190, 12, INK, 5, .26) . bar(272, 260, 140, 12, INK, 5, .18);
$c .= '<g filter="url(#soft)"><rect x="376" y="352" width="286" height="132" rx="22" fill="url(#acc)"/></g>';
$c .= '<path d="M600 484l8 40-46-40z" fill="' . ACC . '"/>';
$c .= bar(412, 388, 190, 12, '#FFFFFF', 5, .85) . bar(412, 412, 120, 12, '#FFFFFF', 5, .6);
$c .= '<circle cx="640" cy="252" r="34" fill="url(#teal)"/>';
$c .= '<path d="M628 252l8 8 16 -18" fill="none" stroke="#fff" stroke-width="3.6" stroke-linecap="round" stroke-linejoin="round"/>';
$cards['redes-sociales'] = head('Gestión de redes sociales') . $c . foot('Redes sociales');

/* ------------------------------------------------------------- diseño */
$c  = '<g filter="url(#lift)"><rect x="250" y="182" width="400" height="300" rx="16" fill="' . CARD . '"/></g>';
$c .= '<circle cx="352" cy="286" r="58" fill="url(#acc)" opacity=".92"/>';
$c .= '<rect x="426" y="228" width="176" height="30" rx="8" fill="' . INK . '" opacity=".82"/>';
$c .= bar(426, 272, 140, 12, INK, 5, .3) . bar(426, 294, 176, 12, INK, 5, .22) . bar(426, 316, 108, 12, INK, 5, .16);
$c .= '<rect x="290" y="374" width="320" height="1.6" fill="' . HAIR . '"/>';
foreach ([ACC, ACC2, '#7C4DFF', '#FF9F1C', INK] as $i => $col) {
    $c .= '<circle cx="' . (318 + $i * 56) . '" cy="' . 424 . '" r="20" fill="' . $col . '" opacity=".9"/>';
}
$cards['diseno-grafico'] = head('Diseño gráfico y marca') . $c . foot('Diseño y marca');

/* ------------------------------------------------------------ genérico */
$c  = '<rect x="300" y="216" width="300" height="220" rx="16" fill="' . CARD . '" filter="url(#soft)"/>';
$c .= '<rect x="300" y="216" width="300" height="220" rx="16" fill="none" stroke="' . HAIR . '" stroke-width="2" stroke-dasharray="9 8"/>';
$c .= '<circle cx="450" cy="326" r="34" fill="none" stroke="' . ACC . '" stroke-width="3"/>';
$c .= '<path d="M436 326h28M450 312v28" stroke="' . ACC . '" stroke-width="3" stroke-linecap="round"/>';
$c .= '<text x="450" y="472" text-anchor="middle" font-family="Inter, Helvetica, Arial, sans-serif" font-size="14" letter-spacing="3.4" fill="' . SOFT . '">SIN IMAGEN CARGADA</text>';
$cards['generico'] = head('Servicio sin imagen') . $c . foot('Servicio');

foreach ($cards as $name => $svg) {
    file_put_contents($OUT . '/' . $name . '.svg', $svg);
}
printf("%d ilustraciones generadas en assets/img/cards/\n", count($cards));
