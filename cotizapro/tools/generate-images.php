<?php
declare(strict_types=1);
/**
 * Generador de la fotografía industrial del tema "Precisión industrial".
 *
 * Renderiza por píxel (modelo de iluminación propio) escenas de nave
 * industrial, taller, acero cepillado, pieza torneada, placa remachada,
 * plano de ingeniería y concreto; luego exporta JPG + WebP en tres tamaños
 * con su miniatura blur-up.
 *
 *   php tools/generate-images.php
 *
 * Para sustituirlas por fotografía real de Unsplash/Pexels desde su hosting:
 *   php tools/fetch-photos.php
 */

require __DIR__ . '/../app/bootstrap.php';
@set_time_limit(0);
ini_set('memory_limit', '512M');

$OUT = BASE_PATH . '/assets/img/industry';
@mkdir($OUT, 0755, true);

// ------------------------------------------------------------ ruido base
mt_srand(20260829);
const NW = 256;
$NOISE = [];
for ($i = 0; $i < NW * NW; $i++) {
    $NOISE[$i] = mt_rand(0, 10000) / 10000;
}

function vnoise(float $x, float $y): float
{
    global $NOISE;
    $x = fmod($x, NW); $y = fmod($y, NW);
    if ($x < 0) { $x += NW; }
    if ($y < 0) { $y += NW; }
    $x0 = (int) $x; $y0 = (int) $y;
    $x1 = ($x0 + 1) % NW; $y1 = ($y0 + 1) % NW;
    $fx = $x - $x0; $fy = $y - $y0;
    $sx = $fx * $fx * (3 - 2 * $fx);
    $sy = $fy * $fy * (3 - 2 * $fy);
    $a = $NOISE[$y0 * NW + $x0]; $b = $NOISE[$y0 * NW + $x1];
    $c = $NOISE[$y1 * NW + $x0]; $d = $NOISE[$y1 * NW + $x1];
    $top = $a + ($b - $a) * $sx;
    $bot = $c + ($d - $c) * $sx;
    return $top + ($bot - $top) * $sy;
}

function fbm(float $x, float $y, int $oct = 4): float
{
    $v = 0.0; $amp = 0.5; $f = 1.0;
    for ($i = 0; $i < $oct; $i++) {
        $v += $amp * vnoise($x * $f, $y * $f);
        $f *= 2.03; $amp *= 0.5;
    }
    return $v;
}

function sat(float $v): float { return $v < 0 ? 0.0 : ($v > 1 ? 1.0 : $v); }
function smooth(float $a, float $b, float $t): float { $t = sat(($t - $a) / max(1e-6, $b - $a)); return $t * $t * (3 - 2 * $t); }

/** Renderiza una escena con una función de sombreado por píxel. */
function render(int $w, int $h, callable $shade): GdImage
{
    $im = imagecreatetruecolor($w, $h);
    for ($y = 0; $y < $h; $y++) {
        $v = $y / $h;
        for ($x = 0; $x < $w; $x++) {
            [$r, $g, $b] = $shade($x / $w, $v);
            imagesetpixel($im, $x, $y, imagecolorallocate($im, (int) (sat($r) * 255), (int) (sat($g) * 255), (int) (sat($b) * 255)));
        }
    }
    return $im;
}

/* ====================================================================== */

/** Nave industrial: estructura, columnas, maquinaria y haces de luz cenital. */
function sceneWarehouse(float $u, float $v): array
{
    $horizon = 0.615;
    $beams = [[0.11, 0.15], [0.33, 0.11], [0.55, 0.14], [0.78, 0.10], [0.97, 0.13]];

    // Bruma volumétrica de los tragaluces.
    $haze = 0.0;
    foreach ($beams as [$bx, $bw]) {
        $lead = $bx + ($v - 0.05) * 0.26;
        $d = ($u - $lead) / ($bw * (0.5 + $v * 0.85));
        $haze += 0.30 * exp(-$d * $d * 2.4) * (1.0 - smooth(0.50, 1.0, $v)) * (0.30 + 0.80 * $v);
    }

    if ($v < $horizon) {
        $I = 0.045 + 0.085 * smooth(0.0, $horizon, $v) + $haze * 0.85;
        // Cerchas del techo
        if ($v > 0.09 && $v < 0.185) {
            $zig = abs(fmod($u * 15.0 + ($v - 0.09) * 11.0, 2.0) - 1.0);
            $I -= 0.045 * smooth(0.74, 1.0, $zig);
        }
        if ($v > 0.185 && $v < 0.215) { $I -= 0.06; }
        // Columnas en perspectiva
        for ($i = 0; $i <= 6; $i++) {
            $cx = 0.5 + ($i / 6.0 - 0.5) * (1.0 + ($v - $horizon) * 0.30);
            if ($v > 0.20 && abs($u - $cx) < 0.0115) { $I -= 0.062; }
        }
        // Perfil de maquinaria y racks: bahías rectangulares de alturas distintas.
        $bay = (int) floor($u * 11.0);
        $hgt = 0.030 + 0.115 * vnoise($bay * 7.3 + 0.5, 3.7);
        $topY = $horizon - $hgt;
        if ($v > $topY) {
            $I -= 0.052 + 0.020 * vnoise($bay * 3.1, 11.2);
            // Montantes verticales del rack
            if (abs(fmod($u * 33.0, 1.0) - 0.5) < 0.08) { $I -= 0.010; }
            // Larguero horizontal
            if (abs(fmod(($v - $topY) * 26.0, 1.0) - 0.5) < 0.09) { $I -= 0.008; }
            // Canto superior iluminado
            if ($v - $topY < 0.004) { $I += 0.045; }
        }
        $I += 0.026 * (fbm($u * 260, $v * 260, 3) - 0.5);
        $I *= 1.0 - 0.34 * ((($u - 0.5) ** 2 * 0.8 + ($v - 0.45) ** 2) ** 0.85);
        return [max(0.0, $I) * 0.97, max(0.0, $I), max(0.0, $I) * 1.11];
    }

    // Piso pulido: reflejo difuso de los haces y de las siluetas.
    $f = ($v - $horizon) / (1.0 - $horizon);
    $mirror = (1.0 - $f) ** 1.6;
    $R = 0.0;
    foreach ($beams as [$bx, $bw]) {
        $lead = $bx + ($horizon - 0.05) * 0.26;
        $d = ($u - $lead) / ($bw * 2.4);
        $R += 0.115 * exp(-$d * $d * 1.0) * $mirror;
    }
    $I = 0.038 + 0.026 * (1.0 - $f) + $R;
    $I *= 0.55 + 0.45 * smooth(0.0, 0.045, $f);   // sombra al pie de la nave
    $I += 0.016 * (fbm($u * 200, $v * 340, 3) - 0.5);
    if (abs(fmod($v * 6.0 + 0.3, 1.0) - 0.5) < 0.006) { $I -= 0.010; }
    if (abs(fmod($u * 5.0, 1.0) - 0.5) < 0.004) { $I -= 0.008; }
    $I *= 1.0 - 0.30 * ((($u - 0.5) ** 2 * 0.8 + ($v - 0.45) ** 2) ** 0.85);
    return [max(0.0, $I), max(0.0, $I), max(0.0, $I) * 1.10];
}

/** Bodega de repuestos: estantería de gavetas bajo luz cálida rasante. */
function sceneWorkshop(float $u, float $v): array
{
    $cols = 7.0; $rows = 4.0;
    $cu = fmod($u * $cols, 1.0);
    $cv = fmod($v * $rows, 1.0);
    $ci = (int) floor($u * $cols);
    $ri = (int) floor($v * $rows);
    $seed = vnoise($ci * 5.7 + 0.3, $ri * 9.1 + 0.7);

    // Luz de trabajo cálida, desde arriba a la izquierda.
    $key = exp(-((($u - 0.34) / 0.62) ** 2 + (($v - 0.24) / 0.66) ** 2));
    $amb = 0.055 + 0.30 * $key;

    // Separación entre gavetas (perfiles de la estantería).
    $frame = 0.055;
    if ($cu < $frame || $cu > 1 - $frame || $cv < $frame * 1.4 || $cv > 1 - $frame * 1.4) {
        $I = $amb * 0.42;
        if ($cv < $frame * 0.5) { $I += 0.10 * $key; }      // canto superior iluminado
        return [$I * 1.24, $I * 1.02, $I * 0.80];
    }

    // Cara frontal de la gaveta: degradado interior + tono propio.
    $tone = 0.72 + 0.55 * $seed;
    $depth = 1.0 - smooth(0.05, 0.42, $cv) * 0.45;          // sombra superior interior
    $I = $amb * $tone * $depth;

    // Tirador metálico
    if ($cv > 0.60 && $cv < 0.72 && $cu > 0.32 && $cu < 0.68) {
        $I += 0.18 * $key + 0.05;
    }
    // Etiqueta de código
    if ($cv > 0.16 && $cv < 0.34 && $cu > 0.14 && $cu < 0.60) {
        $I = $amb * 1.45;
        if (fmod($cu * 26.0, 1.0) < 0.55 && $cv > 0.22 && $cv < 0.28) { $I *= 0.42; }
    }
    // Piezas asomando en algunas gavetas abiertas
    if ($seed > 0.80 && $cv > 0.74) {
        $I *= 0.5;
        if (abs(fmod($cu * 9.0, 1.0) - 0.5) < 0.22) { $I += 0.16 * $key; }
    }
    $I += 0.035 * (fbm($u * 340, $v * 340, 3) - 0.5);
    $I *= 1.0 - 0.30 * ((($u - 0.42) ** 2 * 0.9 + ($v - 0.42) ** 2) ** 0.9);
    $I = max(0.0, $I);
    return [$I * 1.26, $I * 1.02, $I * 0.78];
}

/** Acero cepillado: vetas horizontales anisotrópicas. */
function sceneBrushed(float $u, float $v): array
{
    $grain = fbm($u * 3.0, $v * 900.0, 3);
    $micro = vnoise($u * 1400.0, $v * 1600.0);
    $spec = exp(-((($v - 0.34) / 0.30) ** 2)) * 0.34;
    $I = 0.30 + 0.24 * (1.0 - $v) + $spec + ($grain - 0.5) * 0.16 + ($micro - 0.5) * 0.05;
    $I *= 1.0 - 0.20 * (((($u - 0.5) ** 2) + (($v - 0.5) ** 2)) ** 0.9);
    return [$I * 0.99, $I * 1.01, $I * 1.07];
}

/** Macro de pieza torneada: surcos concéntricos y reflejo anisotrópico. */
function sceneMachined(float $u, float $v): array
{
    $ar = 1.57;
    $x = ($u - 0.60) * $ar; $y = $v - 0.50;
    $r = sqrt($x * $x + $y * $y);
    $th = atan2($y, $x);
    $groove = sin($r * 210.0 + fbm($r * 40, $th * 6, 2) * 2.0);
    $lobe = pow(max(0.0, cos($th - 2.55)), 5) + pow(max(0.0, cos($th + 0.59)), 5);
    $I = 0.20 + 0.34 * $lobe * smooth(0.02, 0.30, $r) + 0.055 * $groove * smooth(0.02, 0.10, $r);
    $I *= 1.0 - smooth(0.55, 1.10, $r) * 0.55;
    if ($r < 0.085) {
        $I = 0.045 + 0.10 * smooth(0.03, 0.085, $r) + 0.03 * $groove;
    } elseif ($r < 0.10) {
        $I += 0.20 * (1.0 - ($r - 0.085) / 0.015);
    }
    for ($k = 0; $k < 6; $k++) {
        $a = $k * M_PI / 3 + 0.3;
        $hd = sqrt(($x - cos($a) * 0.30) ** 2 + ($y - sin($a) * 0.30) ** 2);
        if ($hd < 0.042) { $I = 0.05 + 0.14 * smooth(0.02, 0.042, $hd); }
    }
    $I += 0.022 * (vnoise($u * 900, $v * 900) - 0.5);
    return [$I * 0.97, $I, $I * 1.09];
}

/** Placa de acero remachada con luz rasante. */
function sceneRivet(float $u, float $v): array
{
    $ar = 1.57;
    $I = 0.17 + 0.13 * (1.0 - $v) + 0.10 * exp(-(((($u - 0.30) - $v * 0.4) / 0.30) ** 2));
    $I += 0.05 * (fbm($u * 260, $v * 260, 4) - 0.5);
    $gx = 8.0; $gy = 5.0;
    $cx = (fmod($u * $gx, 1.0) - 0.5) / $gx * $ar;
    $cy = (fmod($v * $gy, 1.0) - 0.5) / $gy;
    $rr = 0.020;
    $d = sqrt($cx * $cx + $cy * $cy);
    if ($d < $rr) {
        $nz = sqrt(max(0.0, $rr * $rr - $d * $d)) / $rr;
        $lam = max(0.0, ($cx / $rr) * -0.5 + ($cy / $rr) * -0.62 + $nz * 0.60);
        $I = 0.10 + 0.62 * pow($lam, 1.5) + 0.10 * pow($lam, 12);
    } elseif ($d < $rr * 1.22) {
        $I *= 0.62;
    }
    if (abs(fmod($v * 2.0, 1.0) - 0.5) < 0.004) { $I *= 0.55; }
    return [$I * 1.02, $I, $I];
}

/** Plano de ingeniería oscuro con una pieza acotada. */
function sceneBlueprint(float $u, float $v): array
{
    $ar = 1.57;
    $I = 0.055 + 0.035 * (1.0 - $v);
    if (abs(fmod($u * 46.0, 1.0) - 0.5) < 0.035 || abs(fmod($v * 30.0, 1.0) - 0.5) < 0.035) { $I += 0.045; }
    if (abs(fmod($u * 5.75, 1.0) - 0.5) < 0.006 || abs(fmod($v * 3.75, 1.0) - 0.5) < 0.006) { $I += 0.075; }
    $x = ($u - 0.63) * $ar; $y = $v - 0.50;
    $r = sqrt($x * $x + $y * $y);
    $acc = 0.0;
    foreach ([0.115, 0.205, 0.275, 0.375] as $rr) {
        if (abs($r - $rr) < 0.0022) { $acc = 1.0; }
    }
    if (abs($y) < 0.0018 && abs($x) < 0.46) { $acc = max($acc, 0.75); }
    if (abs($x) < 0.0018 && abs($y) < 0.44) { $acc = max($acc, 0.75); }
    if (abs($y - 0.42) < 0.0018 && abs($x) < 0.375) { $acc = max($acc, 0.9); }
    $b = [$I * 0.80, $I * 1.30, $I * 1.75];
    if ($acc > 0) { $b[0] += 0.62 * $acc; $b[1] += 0.30 * $acc; $b[2] += 0.10 * $acc; }
    return $b;
}

/** Concreto pulido de bodega. */
function sceneConcrete(float $u, float $v): array
{
    $n = fbm($u * 26.0, $v * 26.0, 5);
    $spk = vnoise($u * 900.0, $v * 900.0);
    $I = 0.60 + ($n - 0.5) * 0.30 + ($spk - 0.5) * 0.10;
    if ($spk > 0.965) { $I -= 0.22; }
    if ($spk < 0.022) { $I += 0.18; }
    $I *= 1.0 - 0.16 * ((($u - 0.5) ** 2 + ($v - 0.5) ** 2) ** 0.85);
    return [$I, $I * 0.995, $I * 0.965];
}

/* ====================================================================== */
$scenes = [
    'hero-planta'     => 'sceneWarehouse',
    'hero-taller'     => 'sceneWorkshop',
    'acero-cepillado' => 'sceneBrushed',
    'pieza-torneada'  => 'sceneMachined',
    'placa-remachada' => 'sceneRivet',
    'plano-tecnico'   => 'sceneBlueprint',
    'concreto'        => 'sceneConcrete',
];
$sizes = [['', 2200, 1400], ['-md', 1200, 764], ['-sm', 640, 408]];
$manifest = [];

foreach ($scenes as $name => $fn) {
    $t0 = microtime(true);
    $src = render(1100, 700, $fn);
    foreach ($sizes as [$suffix, $w, $h]) {
        $im = imagecreatetruecolor($w, $h);
        imagecopyresampled($im, $src, 0, 0, 0, 0, $w, $h, 1100, 700);
        imagejpeg($im, "{$OUT}/{$name}{$suffix}.jpg", $w > 1500 ? 78 : 82);
        if (function_exists('imagewebp')) {
            imagewebp($im, "{$OUT}/{$name}{$suffix}.webp", $w > 1500 ? 74 : 80);
        }
        imagedestroy($im);
    }
    $tiny = imagecreatetruecolor(24, 15);
    imagecopyresampled($tiny, $src, 0, 0, 0, 0, 24, 15, 1100, 700);
    ob_start(); imagejpeg($tiny, null, 44); $blur = (string) ob_get_clean();
    imagedestroy($tiny);
    $manifest[$name] = 'data:image/jpeg;base64,' . base64_encode($blur);
    imagedestroy($src);
    printf("· %-18s %.1fs\n", $name, microtime(true) - $t0);
}

$og = render(1200, 630, 'sceneBlueprint');
imagejpeg($og, "{$OUT}/og-default.jpg", 84);
if (function_exists('imagewebp')) { imagewebp($og, "{$OUT}/og-default.webp", 82); }
imagedestroy($og);

file_put_contents($OUT . '/blur.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo 'Listo: ' . count($scenes) . " escenas en {$OUT}\n";
