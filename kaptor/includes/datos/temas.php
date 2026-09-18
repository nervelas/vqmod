<?php
/**
 * Kaptor - Paletas de color listas para usar.
 *
 * Cada tema define los colores que el sitio inyecta como variables CSS:
 * el fondo, el acento principal ('oro'), el segundo color del degradado
 * ('oro2'), el acento secundario ('neon') y el color del texto. El modo claro se
 * deriva solo a partir de ellos, así que basta con cuidar el modo oscuro.
 *
 * Para añadir un tema propio basta con copiar una entrada y cambiar los
 * valores: aparecerá automáticamente en Ajustes > Apariencia.
 */
declare(strict_types=1);

return [
    'obsidiana' => [
        'nombre' => 'Obsidiana y oro',
        'pista'  => 'El original: negro volcánico, oro champán y verde fósforo.',
        'fondo'  => '#07080A',
        'oro'    => '#D8B36A',
        'oro2'   => '#F3D89A',
        'neon'   => '#6EF3A5',
        'texto'  => '#F3F4F6',
    ],
    'magenta' => [
        'nombre' => 'Magenta eléctrico',
        'pista'  => 'Rosa de neón sobre tinta morada. Muy llamativo.',
        'fondo'  => '#0B0410',
        'oro'    => '#FF2E88',
        'oro2'   => '#A855F7',
        'neon'   => '#00E5FF',
        'texto'  => '#F9F2FB',
    ],
    'cian' => [
        'nombre' => 'Cian nitro',
        'pista'  => 'Azul eléctrico y verde lima. Aire de pantalla de radar.',
        'fondo'  => '#03121A',
        'oro'    => '#00E5FF',
        'oro2'   => '#4BFF9E',
        'neon'   => '#8BFF3D',
        'texto'  => '#EAF7FB',
    ],
    'lava' => [
        'nombre' => 'Lava',
        'pista'  => 'Naranja incandescente con destellos ámbar.',
        'fondo'  => '#120604',
        'oro'    => '#FF6B1A',
        'oro2'   => '#FFD400',
        'neon'   => '#FFD400',
        'texto'  => '#FFF2EA',
    ],
    'violeta' => [
        'nombre' => 'Violeta ultra',
        'pista'  => 'Púrpura intenso y turquesa. Elegante y moderno.',
        'fondo'  => '#0A0614',
        'oro'    => '#B472FF',
        'oro2'   => '#FF6EC7',
        'neon'   => '#22D3EE',
        'texto'  => '#F2EEFF',
    ],
    'neon' => [
        'nombre' => 'Verde neón',
        'pista'  => 'Verde fluorescente puro sobre negro. Estilo terminal.',
        'fondo'  => '#04120A',
        'oro'    => '#4BFF5A',
        'oro2'   => '#00E5FF',
        'neon'   => '#00E5FF',
        'texto'  => '#EAFBEF',
    ],
    'carmesi' => [
        'nombre' => 'Carmesí',
        'pista'  => 'Rojo encendido con ámbar. Urgente y potente.',
        'fondo'  => '#120306',
        'oro'    => '#FF3B5C',
        'oro2'   => '#FF9A3D',
        'neon'   => '#FFB020',
        'texto'  => '#FFEDF0',
    ],
    'cobalto' => [
        'nombre' => 'Cobalto',
        'pista'  => 'Azul corporativo saturado con acento turquesa.',
        'fondo'  => '#04091A',
        'oro'    => '#5B9BFF',
        'oro2'   => '#22D3EE',
        'neon'   => '#22D3EE',
        'texto'  => '#ECF2FF',
    ],
    'voltaje' => [
        'nombre' => 'Alto voltaje',
        'pista'  => 'Amarillo señal y naranja. Máxima visibilidad.',
        'fondo'  => '#0D0C02',
        'oro'    => '#FFE100',
        'oro2'   => '#FF6A00',
        'neon'   => '#FF6A00',
        'texto'  => '#FFFCE6',
    ],
    'synthwave' => [
        'nombre' => 'Synthwave',
        'pista'  => 'Rosa chicle y violeta de los ochenta.',
        'fondo'  => '#0C0418',
        'oro'    => '#FF5FDC',
        'oro2'   => '#8B6BFF',
        'neon'   => '#8B6BFF',
        'texto'  => '#FBEEFF',
    ],
];
