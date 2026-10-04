<?php
declare(strict_types=1);
/** Genera los PNG de la app (192, 512 y maskable) con GD: reloj-calendario en oro sobre medianoche. Uso: php tests/make-icons.php */

function gold(float $t): array
{
    // 0 = latón oscuro, 0.5 = oro, 1 = oro claro
    $stops = [[0, [0x7A, 0x54, 0x20]], [0.5, [0xC9, 0xA0, 0x50]], [1, [0xF0, 0xD9, 0xA0]]];
    $t = max(0.0, min(1.0, $t));
    for ($i = 0; $i < 2; $i++) {
        if ($t <= $stops[$i + 1][0]) {
            $k = ($t - $stops[$i][0]) / ($stops[$i + 1][0] - $stops[$i][0]);
            return array_map(fn($a, $b) => (int) round($a + ($b - $a) * $k), $stops[$i][1], $stops[$i + 1][1]);
        }
    }
    return $stops[2][1];
}

/** $scale: 1 = icono normal; menor = contenido reducido (zona segura maskable). */
function render(int $size, float $scale, bool $rounded): \GdImage
{
    $S = 4;
    $n = $size * $S;
    $im = imagecreatetruecolor($n, $n);
    imagealphablending($im, true);
    imagesavealpha($im, true);
    $clear = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $clear);
    $ink = imagecolorallocate($im, 0x06, 0x08, 0x0D);
    $obs = imagecolorallocate($im, 0x0E, 0x11, 0x18);
    if ($rounded) {
        $r = (int) ($n * 0.22);
        imagefilledrectangle($im, $r, 0, $n - $r, $n, $ink);
        imagefilledrectangle($im, 0, $r, $n, $n - $r, $ink);
        foreach ([[$r, $r], [$n - $r, $r], [$r, $n - $r], [$n - $r, $n - $r]] as $c) {
            imagefilledellipse($im, $c[0], $c[1], $r * 2, $r * 2, $ink);
        }
    } else {
        imagefilledrectangle($im, 0, 0, $n, $n, $ink);
    }
    $cx = $n / 2;
    $cy = $n * 0.54;
    $R = $n * 0.30 * $scale;
    $cyAdj = $n / 2 + ($cy - $n / 2) * $scale;
    imagefilledellipse($im, (int) $cx, (int) $cyAdj, (int) ($R * 2), (int) ($R * 2), $obs);
    // aro con degradado de oro
    $w = $R * 0.115;
    $steps = 360;
    for ($i = 0; $i < $steps; $i++) {
        $a0 = $i * 2 * M_PI / $steps - M_PI / 2;
        $a1 = ($i + 1.6) * 2 * M_PI / $steps - M_PI / 2;
        $t = 0.5 - 0.5 * cos($a0 + M_PI / 4 + M_PI / 2);
        $col = gold(1 - $t);
        $c = imagecolorallocate($im, $col[0], $col[1], $col[2]);
        $p = [
            $cx + ($R - $w) * cos($a0), $cyAdj + ($R - $w) * sin($a0),
            $cx + $R * cos($a0), $cyAdj + $R * sin($a0),
            $cx + $R * cos($a1), $cyAdj + $R * sin($a1),
            $cx + ($R - $w) * cos($a1), $cyAdj + ($R - $w) * sin($a1),
        ];
        imagefilledpolygon($im, array_map('intval', $p), $c);
    }
    // marcas de horas
    $mark = imagecolorallocate($im, 0xC9, 0xA0, 0x50);
    imagesetthickness($im, max(2, (int) ($R * 0.04)));
    for ($i = 0; $i < 12; $i++) {
        $a = $i * M_PI / 6;
        $len = $i % 3 === 0 ? 0.2 : 0.11;
        imageline($im, (int) ($cx + $R * 0.72 * sin($a)), (int) ($cyAdj - $R * 0.72 * cos($a)),
            (int) ($cx + $R * (0.72 - $len) * sin($a)), (int) ($cyAdj - $R * (0.72 - $len) * cos($a)), $mark);
    }
    // manecillas
    $hand = imagecolorallocate($im, 0xF0, 0xD9, 0xA0);
    imagesetthickness($im, max(3, (int) ($R * 0.085)));
    imageline($im, (int) $cx, (int) $cyAdj, (int) $cx, (int) ($cyAdj - $R * 0.5), $hand);
    imageline($im, (int) $cx, (int) $cyAdj, (int) ($cx + $R * 0.36), (int) ($cyAdj + $R * 0.22), $hand);
    imagefilledellipse($im, (int) $cx, (int) $cyAdj, (int) ($R * 0.2), (int) ($R * 0.2), $hand);
    // anillas de calendario
    imagesetthickness($im, max(4, (int) ($R * 0.16)));
    foreach ([-0.42, 0.42] as $dx) {
        $x = (int) ($cx + $R * $dx);
        $c = gold($dx > 0 ? 0.35 : 0.8);
        $col = imagecolorallocate($im, $c[0], $c[1], $c[2]);
        imageline($im, $x, (int) ($cyAdj - $R * 1.2), $x, (int) ($cyAdj - $R * 0.85), $col);
    }
    imagesetthickness($im, 1);
    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $im, 0, 0, 0, 0, $size, $size, $n, $n);
    return $out;
}

$dir = __DIR__ . '/../assets/img/';
foreach ([['icon-192.png', 192, 1.0, true], ['icon-512.png', 512, 1.0, true], ['icon-maskable-512.png', 512, 0.78, false]] as [$f, $s, $k, $r]) {
    imagepng(render($s, $k, $r), $dir . $f, 9);
    printf("%s %d bytes\n", $f, filesize($dir . $f));
}
