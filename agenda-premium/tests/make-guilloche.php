<?php
declare(strict_types=1);
/** Genera los fondos guilloché estáticos de assets/img (uso: php tests/make-guilloche.php). */
require __DIR__ . '/../app/Core/Guilloche.php';
use App\Core\Guilloche;

$dir = __DIR__ . '/../assets/img/';
$gold = ['#7A5420', '#C9A050', '#F0D9A0'];

// Fondo de página: líneas muy finas y espaciadas
file_put_contents($dir . 'guilloche-bg.svg', Guilloche::svg([
    'width' => 1600, 'height' => 1000, 'mode' => 'lissajous', 'seed' => 11, 'curves' => 5, 'points' => 240,
    'colors' => $gold, 'opacity' => 0.55, 'stroke' => 0.7,
]));
// Borde/ornamento de tarjeta: franja entrelazada (apaisada, se estira en ancho)
file_put_contents($dir . 'guilloche-card.svg', Guilloche::svg([
    'width' => 1200, 'height' => 120, 'mode' => 'weave', 'seed' => 5, 'curves' => 7, 
    'colors' => $gold, 'opacity' => 0.85, 'stroke' => 0.8,
]));
// Panel de acceso: rosetas grandes
$auth = Guilloche::fragment('rosette', 900, 900, ['seed' => 21, 'curves' => 14, 'petals' => 12, 'depth' => 0.2, 'colors' => $gold, 'opacity' => 0.7, 'stroke' => 0.6])
    . Guilloche::fragment('lissajous', 900, 900, ['seed' => 4, 'curves' => 3, 'points' => 360, 'colors' => $gold, 'opacity' => 0.35, 'stroke' => 0.6]);
file_put_contents($dir . 'guilloche-auth.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 900" width="900" height="900" fill="none" stroke-linejoin="round">' . $auth . '</svg>');
// Rosetón central
file_put_contents($dir . 'guilloche-rosette.svg', Guilloche::svg([
    'width' => 600, 'height' => 600, 'mode' => 'rosette', 'seed' => 33, 'curves' => 20, 'petals' => 14, 'depth' => 0.2,
    'colors' => $gold, 'opacity' => 0.9, 'stroke' => 0.6,
]));
foreach (glob($dir . 'guilloche-*.svg') as $f) {
    printf("%s %d bytes\n", basename($f), filesize($f));
}
