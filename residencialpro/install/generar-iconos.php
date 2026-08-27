<?php
/**
 * Genera los iconos de la aplicación (PWA) a partir de la identidad del sistema.
 * Lo invoca el instalador y también el panel al cambiar el logotipo.
 */
$dir = (defined('RUTA_BASE') ? RUTA_BASE : dirname(__DIR__)) . '/assets/img';
@mkdir($dir, 0755, true);
$tam = [48, 72, 96, 128, 144, 152, 167, 180, 192, 256, 384, 512];

function dibujar(int $n, bool $maskable = false): \GdImage {
    $img = imagecreatetruecolor($n, $n);
    imagealphablending($img, true);
    imagesavealpha($img, true);
    // Fondo degradado verde bosque
    for ($y = 0; $y < $n; $y++) {
        $t = $y / max(1, $n - 1);
        $r = (int) round(0x0F + (0x16 - 0x0F) * $t);
        $g = (int) round(0x2E + (0x40 - 0x2E) * $t);
        $b = (int) round(0x24 + (0x32 - 0x24) * $t);
        imagefilledrectangle($img, 0, $y, $n, $y, imagecolorallocate($img, $r, $g, $b));
    }
    $oro   = imagecolorallocate($img, 0xC9, 0xA9, 0x61);
    $oro2  = imagecolorallocate($img, 0xE0, 0xC4, 0x89);
    $esc   = $maskable ? 0.62 : 0.78;          // zona segura para iconos maskable
    $c     = $n / 2;
    $w     = $n * $esc;

    // Silueta de casa con arco (identidad del residencial)
    $puntos = [
        $c,             $c - $w * 0.40,
        $c + $w * 0.40, $c - $w * 0.05,
        $c + $w * 0.40, $c + $w * 0.40,
        $c - $w * 0.40, $c + $w * 0.40,
        $c - $w * 0.40, $c - $w * 0.05,
    ];
    imagefilledpolygon($img, array_map('intval', $puntos), $oro);

    // Puerta en arco (color del fondo)
    $fondo = imagecolorallocate($img, 0x0F, 0x2E, 0x24);
    $pw = $w * 0.24;
    $ph = $w * 0.34;
    imagefilledrectangle($img, (int) ($c - $pw / 2), (int) ($c + $w * 0.40 - $ph), (int) ($c + $pw / 2), (int) ($c + $w * 0.40), $fondo);
    imagefilledellipse($img, (int) $c, (int) ($c + $w * 0.40 - $ph), (int) $pw, (int) $pw, $fondo);

    // Remate superior dorado claro
    imagefilledellipse($img, (int) $c, (int) ($c - $w * 0.46), (int) ($w * 0.13), (int) ($w * 0.13), $oro2);
    return $img;
}

foreach ($tam as $n) {
    $img = dibujar($n, false);
    imagepng($img, $dir . '/icono-' . $n . '.png', 8);
    imagedestroy($img);
}
foreach ([192, 512] as $n) {
    $img = dibujar($n, true);
    imagepng($img, $dir . '/icono-maskable-' . $n . '.png', 8);
    imagedestroy($img);
}
// Favicon 32
$img = dibujar(32, false);
imagepng($img, $dir . '/favicon.png', 8);
imagedestroy($img);
echo "iconos generados\n";
