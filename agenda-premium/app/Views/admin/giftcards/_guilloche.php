<?php
/** Rosetas tipo guilloché generadas con curvas de Lissajous. Variables: $seed (int), $w, $h, $curves (int) */
$seed = (int) ($seed ?? 7);
$w = (int) ($w ?? 600);
$h = (int) ($h ?? 380);
$curves = (int) ($curves ?? 14);
$cx = $w / 2;
$cy = $h / 2;
$a = 5 + ($seed % 4);
$b = 7 + ($seed % 5);
$paths = [];
for ($k = 0; $k < $curves; $k++) {
    $phase = $k * (M_PI / $curves);
    $r = 0.46 - $k * 0.012;
    $d = '';
    $steps = 360;
    for ($i = 0; $i <= $steps; $i++) {
        $t = $i / $steps * 2 * M_PI;
        $x = $cx + $r * $w * sin($a * $t + $phase) * (0.82 + 0.18 * cos($b * $t));
        $y = $cy + $r * $h * 1.9 * cos(($a + 1) * $t) * (0.82 + 0.18 * sin($b * $t + $phase));
        $d .= ($i === 0 ? 'M' : 'L') . round($x, 1) . ' ' . round($y, 1);
    }
    $paths[] = $d . 'Z';
}
?>
<svg class="p3-guilloche" viewBox="0 0 <?= $w ?> <?= $h ?>" preserveAspectRatio="xMidYMid slice" role="presentation" aria-hidden="true" focusable="false">
  <?php foreach ($paths as $i => $d) : ?><path d="<?= e($d) ?>" fill="none" stroke="currentColor" stroke-width="0.5" opacity="<?= e((string) round(0.25 + 0.5 * ($i / max(1, $curves)), 2)) ?>"/><?php endforeach; ?>
</svg>
