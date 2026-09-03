<?php
declare(strict_types=1);
/**
 * Iconos PWA por defecto (72→512, maskable) con la marca de la plataforma.
 * Al subir el logo de una empresa, Img::pwaIcons() regenera los suyos.
 *
 *   php tools/generate-icons.php
 */
require __DIR__ . '/../app/bootstrap.php';

$OUT = BASE_PATH . '/assets/img/icons';
@mkdir($OUT, 0755, true);

$INK = [28, 31, 34];
$ACC = [232, 89, 12];

function iconAt(int $s, array $ink, array $acc): GdImage
{
    $im = imagecreatetruecolor($s, $s);
    imageantialias($im, true);
    $bg = imagecolorallocate($im, $ink[0], $ink[1], $ink[2]);
    imagefilledrectangle($im, 0, 0, $s, $s, $bg);

    // Retícula sutil de plano
    $grid = imagecolorallocatealpha($im, 255, 255, 255, 118);
    $step = max(4, (int) round($s / 12));
    for ($i = $step; $i < $s; $i += $step) {
        imageline($im, $i, 0, $i, $s, $grid);
        imageline($im, 0, $i, $s, $i, $grid);
    }

    // Zona segura maskable: el arte vive dentro del 62 % central.
    $c  = $s / 2;
    $r  = $s * 0.235;
    $acCol = imagecolorallocate($im, $acc[0], $acc[1], $acc[2]);
    $wh    = imagecolorallocate($im, 255, 255, 255);

    // Anillo exterior (pieza torneada)
    $th = max(2, (int) round($s * 0.045));
    imagesetthickness($im, $th);
    imageellipse($im, (int) $c, (int) $c, (int) ($r * 2), (int) ($r * 2), $wh);
    imagesetthickness($im, max(1, (int) round($s * 0.022)));
    imageellipse($im, (int) $c, (int) $c, (int) ($r * 1.36), (int) ($r * 1.36), $acCol);

    // Cruz de ejes
    imagesetthickness($im, max(1, (int) round($s * 0.018)));
    imageline($im, (int) ($c - $r * 1.42), (int) $c, (int) ($c - $r * 1.05), (int) $c, $wh);
    imageline($im, (int) ($c + $r * 1.05), (int) $c, (int) ($c + $r * 1.42), (int) $c, $wh);
    imageline($im, (int) $c, (int) ($c - $r * 1.42), (int) $c, (int) ($c - $r * 1.05), $wh);
    imageline($im, (int) $c, (int) ($c + $r * 1.05), (int) $c, (int) ($c + $r * 1.42), $wh);

    // Núcleo naranja
    imagefilledellipse($im, (int) $c, (int) $c, (int) ($r * 0.62), (int) ($r * 0.62), $acCol);
    imagesetthickness($im, 1);
    return $im;
}

foreach ([72, 96, 128, 144, 152, 180, 192, 384, 512] as $s) {
    $im = iconAt($s, $INK, $ACC);
    imagepng($im, "{$OUT}/icon-{$s}.png", 8);
    imagedestroy($im);
}

// Favicon .ico sencillo (32 px) y apple-touch-icon
$im = iconAt(32, $INK, $ACC);
imagepng($im, "{$OUT}/icon-32.png", 8);
imagedestroy($im);
copy("{$OUT}/icon-180.png", BASE_PATH . '/apple-touch-icon.png');
copy("{$OUT}/icon-32.png", BASE_PATH . '/favicon.png');

echo "Iconos PWA generados en {$OUT}\n";
