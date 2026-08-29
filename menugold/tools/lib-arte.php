<?php
/**
 * MenúGold · generación de logotipos para la demostración.
 *
 * Aquí NO se generan fotografías. Las fotos de los platillos son reales y las
 * descarga el propio sistema de bancos con licencia libre (Wikimedia Commons y
 * Openverse) al instalarse: ver app/Models/PhotoJob.php y
 * tools/importar-fotos.php --descargar.
 *
 * Lo único que se dibuja aquí es el monograma tipográfico del logotipo, que es
 * tipografía, no fotografía.
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
