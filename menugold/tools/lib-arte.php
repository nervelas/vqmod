<?php
/**
 * MenúGold · imágenes ambientales de demostración.
 *
 * Genera fondos cinematográficos (salón en penumbra, luces desenfocadas,
 * grano de película) para portadas y secciones cuando todavía no hay
 * fotografía real. NO intenta dibujar platillos: los platillos sin foto
 * usan el marcador tipográfico del propio diseño, que se ve intencional.
 *
 * Para fotografía real: tools/descargar-fotos.php (una sola orden).
 */

function mg_rng($seed)
{
    $state = crc32((string)$seed) ?: 12345;
    return function ($min = 0.0, $max = 1.0) use (&$state) {
        $state = ($state * 1103515245 + 12345) & 0x7FFFFFFF;
        return $min + ($state / 0x7FFFFFFF) * ($max - $min);
    };
}

function mg_hex($im, $hex, $alpha = 0)
{
    $hex = ltrim($hex, '#');
    return imagecolorallocatealpha($im,
        (int)hexdec(substr($hex, 0, 2)),
        (int)hexdec(substr($hex, 2, 2)),
        (int)hexdec(substr($hex, 4, 2)),
        (int)$alpha);
}

function mg_mix($a, $b, $t)
{
    $a = ltrim($a, '#'); $b = ltrim($b, '#');
    $out = '';
    for ($i = 0; $i < 3; $i++) {
        $ca = hexdec(substr($a, $i * 2, 2));
        $cb = hexdec(substr($b, $i * 2, 2));
        $out .= str_pad(dechex((int)round($ca + ($cb - $ca) * $t)), 2, '0', STR_PAD_LEFT);
    }
    return '#' . $out;
}

function mg_blur($im, $veces)
{
    for ($i = 0; $i < $veces; $i++) { imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR); }
}

/** Ambientes disponibles, cada uno con su temperatura de color. */
function mg_ambientes()
{
    return array(
        'brasa'    => array('#1A0F07', '#C87A2E', '#F0C271'),
        'noche'    => array('#0C0B09', '#8A6A3A', '#E8CE9C'),
        'cobre'    => array('#150E09', '#B8632A', '#EBA45E'),
        'verde'    => array('#0B120D', '#4E7A4A', '#A9CE8C'),
        'vino'     => array('#140A0C', '#8C2F3C', '#E0A0A8'),
        'marfil'   => array('#12100C', '#9C8355', '#F0E3C8'),
        'humo'     => array('#0A0A0B', '#5C5A56', '#C9C4BA'),
    );
}

/**
 * Fondo cinematográfico: degradado cálido + luces desenfocadas + grano.
 *
 * @param array $opts densidad (0.4–1.6), luz (posición 0–1), ambiente
 * @return resource|\GdImage
 */
function mg_arte_ambiente($w, $h, $seed, array $opts = array())
{
    $ambientes = mg_ambientes();
    $clave = isset($opts['ambiente']) && isset($ambientes[$opts['ambiente']]) ? $opts['ambiente'] : 'noche';
    list($oscuro, $medio, $claro) = $ambientes[$clave];
    $rand = mg_rng($seed);
    $densidad = isset($opts['densidad']) ? (float)$opts['densidad'] : 1.0;

    // Se compone pequeño y se amplía: el remuestreo da un desenfoque natural.
    $sw = max(120, (int)($w / 5));
    $sh = max(80, (int)($h / 5));
    $small = imagecreatetruecolor($sw, $sh);
    imagealphablending($small, true);

    // Degradado radial cálido desde la fuente de luz.
    $lx = $sw * (isset($opts['luz_x']) ? (float)$opts['luz_x'] : $rand(0.15, 0.5));
    $ly = $sh * (isset($opts['luz_y']) ? (float)$opts['luz_y'] : $rand(0.05, 0.45));
    $max = sqrt($sw * $sw + $sh * $sh);
    for ($y = 0; $y < $sh; $y++) {
        for ($x = 0; $x < $sw; $x++) {
            $d = sqrt(($x - $lx) * ($x - $lx) + ($y - $ly) * ($y - $ly)) / $max;
            $t = max(0.0, 1.0 - $d * 1.5);
            imagesetpixel($small, $x, $y, mg_hex($small, mg_mix($oscuro, $medio, $t * $t * 0.9)));
        }
    }

    // Luces desenfocadas: círculo sólido y una sola pasada de desenfoque
    // (los anillos concéntricos aparecen si se dibuja capa sobre capa).
    $n = (int)($rand(16, 26) * $densidad);
    for ($i = 0; $i < $n; $i++) {
        $x = $rand(-$sw * 0.1, $sw * 1.1);
        $y = $rand(-$sh * 0.05, $sh * 0.85);
        $r = $rand($sw * 0.012, $sw * 0.075);
        $t = $rand(0.35, 1.0);
        $c = mg_mix($medio, $claro, $t);
        $alpha = (int)$rand(28, 78);
        imagefilledellipse($small, (int)$x, (int)$y, (int)($r * 2), (int)($r * 2), mg_hex($small, $c, $alpha));
    }

    // Formas oscuras en primer plano (mesas, siluetas), muy suaves.
    for ($i = 0; $i < 4; $i++) {
        imagefilledellipse($small,
            (int)$rand(-$sw * 0.1, $sw * 1.1),
            (int)$rand($sh * 0.9, $sh * 1.25),
            (int)$rand($sw * 0.3, $sw * 0.8),
            (int)$rand($sh * 0.35, $sh * 0.7),
            mg_hex($small, $oscuro, 34));
    }

    mg_blur($small, 3);

    $im = imagecreatetruecolor($w, $h);
    imagecopyresampled($im, $small, 0, 0, 0, 0, $w, $h, $sw, $sh);
    imagedestroy($small);
    mg_blur($im, 1);

    mg_vineta($im, $w, $h, 0.95);
    mg_grano($im, $w, $h, $rand, 0.22);
    imagefilter($im, IMG_FILTER_CONTRAST, -4);
    return $im;
}

/** Viñeta cinematográfica. */
function mg_vineta($im, $w, $h, $fuerza = 0.9)
{
    $cx = $w / 2; $cy = $h / 2;
    $max = sqrt($cx * $cx + $cy * $cy);
    $paso = max(1, (int)round(min($w, $h) / 320));
    for ($y = 0; $y < $h; $y += $paso) {
        for ($x = 0; $x < $w; $x += $paso) {
            $d = sqrt(($x - $cx) * ($x - $cx) + ($y - $cy) * ($y - $cy)) / $max;
            $t = max(0.0, ($d - 0.40) / 0.60) * $fuerza;
            if ($t <= 0.01) { continue; }
            imagefilledrectangle($im, $x, $y, $x + $paso - 1, $y + $paso - 1,
                imagecolorallocatealpha($im, 0, 0, 0, (int)max(0, 127 - $t * 116)));
        }
    }
}

/** Grano de película. */
function mg_grano($im, $w, $h, $rand, $densidad = 0.2)
{
    $n = (int)($w * $h * $densidad / 100);
    for ($i = 0; $i < $n; $i++) {
        $v = (int)$rand(0, 255);
        imagesetpixel($im, (int)$rand(0, $w), (int)$rand(0, $h),
            imagecolorallocatealpha($im, $v, $v, $v, 114));
    }
}

/**
 * Marca circular con iniciales.
 * Usa una fuente TrueType si se le indica (nítida); si no, dibuja la «M»
 * del isotipo con polígonos, que también sale limpia por supermuestreo.
 */
function mg_arte_logo($size, $letras, $fondo = '#0C0B09', $tinta = '#D8B26E', $ttf = null)
{
    $S = 4;                       // supermuestreo: se dibuja 4x y se reduce
    $W = $size * $S;
    $im = imagecreatetruecolor($W, $W);
    imagealphablending($im, true);
    imagefilledrectangle($im, 0, 0, $W, $W, mg_hex($im, $fondo));

    // Halo cálido detrás del monograma.
    $c = $W / 2;
    for ($i = 46; $i >= 0; $i--) {
        $t = $i / 46;
        imagefilledellipse($im, (int)$c, (int)$c, (int)($W * 0.98 * $t), (int)($W * 0.98 * $t),
            mg_hex($im, mg_mix($fondo, mg_mix($tinta, $fondo, 0.80), 1 - $t)));
    }
    imagesetthickness($im, max(1, (int)($S * 1.2)));
    imageellipse($im, (int)$c, (int)$c, (int)($W * 0.84), (int)($W * 0.84), mg_hex($im, $tinta, 52));
    imagesetthickness($im, 1);

    $texto = mb_strtoupper(mb_substr($letras, 0, 2));
    $col = mg_hex($im, $tinta);

    if ($ttf !== null && is_file($ttf) && function_exists('imagettftext')) {
        $tam = $W * (mb_strlen($texto) > 1 ? 0.30 : 0.42);
        $caja = imagettfbbox($tam, 0, $ttf, $texto);
        $tw = $caja[2] - $caja[0];
        $th = $caja[1] - $caja[7];
        imagettftext($im, $tam, 0, (int)($c - $tw / 2 - $caja[0]), (int)($c + $th / 2 - ($caja[1] - $caja[7]) + $th * 0.78), $col, $ttf, $texto);
    } elseif ($texto === 'M') {
        mg_glifo_m($im, $c, $c, $W * 0.42, $col);
    } else {
        // Respaldo: fuente interna ampliada (solo si no hay TTF).
        $tw = imagefontwidth(5) * strlen($texto);
        $th = imagefontheight(5);
        $tmp = imagecreatetruecolor($tw, $th);
        imagefilledrectangle($tmp, 0, 0, $tw, $th, mg_hex($tmp, $fondo));
        imagestring($tmp, 5, 0, 0, $texto, mg_hex($tmp, $tinta));
        imagecolortransparent($tmp, mg_hex($tmp, $fondo));
        $dw = (int)($W * 0.34);
        $dh = (int)($dw * ($th / $tw));
        imagecopyresampled($im, $tmp, (int)(($W - $dw) / 2), (int)(($W - $dh) / 2), 0, 0, $dw, $dh, $tw, $th);
        imagedestroy($tmp);
    }

    $out = imagecreatetruecolor($size, $size);
    imagecopyresampled($out, $im, 0, 0, 0, 0, $size, $size, $W, $W);
    imagedestroy($im);
    return $out;
}

/** Isotipo: la «M» de MenúGold dibujada como polígono. */
function mg_glifo_m($im, $cx, $cy, $alto, $color)
{
    $w = $alto * 0.94;
    $x0 = $cx - $w / 2;
    $y0 = $cy - $alto / 2;
    // Contorno normalizado de una M de asta gruesa.
    $p = array(
        0.00, 1.00,  0.00, 0.00,  0.235, 0.00,  0.50, 0.545,  0.765, 0.00,
        1.00, 0.00,  1.00, 1.00,  0.815, 1.00,  0.815, 0.345,
        0.565, 0.79, 0.435, 0.79, 0.185, 0.345, 0.185, 1.00,
    );
    $pts = array();
    for ($i = 0; $i < count($p); $i += 2) {
        $pts[] = (int)round($x0 + $p[$i] * $w);
        $pts[] = (int)round($y0 + $p[$i + 1] * $alto);
    }
    imagefilledpolygon($im, $pts, $color);
}
