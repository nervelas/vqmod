<?php
declare(strict_types=1);
/**
 * Herramienta de construcción (NO se incluye en el ZIP).
 * Uso: php tools/build_content.php <carpeta_descargas_originales>
 * - Valida y reprocesa con GD cada imagen original -> site/uploads/*.webp
 * - Remuxea los videos (sin metadatos) -> site/media/*.mp4 y extrae un póster
 * - Escribe site/_core/seed/content.json con el contenido exacto del inventario
 */
define('GX', 1);
define('GX_ROOT', dirname(__DIR__) . '/site');
require GX_ROOT . '/_core/lib/bootstrap.php';
require GX_ROOT . '/_core/lib/images.php';

$src = $argv[1] ?? '';
if (!is_dir($src)) {
    fwrite(STDERR, "Falta la carpeta de originales\n");
    exit(1);
}
@mkdir(GX_ROOT . '/uploads', 0755, true);
@mkdir(GX_ROOT . '/media', 0755, true);
foreach (glob(GX_ROOT . '/uploads/*.webp') ?: [] as $f) {
    unlink($f);
}

$alt = [
    'hero-inicio' => ['1.jpeg', 'Grúa de plataforma de Grúas Express Towing en la calle'],
    'hero-sobre' => ['sn4.png', 'Grúas Express Towing – grúa de plataforma y asesor con la mano extendida. Sobre nosotros'],
    'hero-servicio' => ['SDG.png', 'Servicio de grúa 24/7 – atención inmediata – Grúas Express Towing'],
    'hero-contacto' => ['ctc2.png', 'Grúas Express Towing – grúa de plataforma y asesor con la mano extendida. Contáctenos'],
    'logo' => ['Logo.png', 'Grúas Express Towing Guatemala'],
    'logo-mobile' => ['LM3.png', 'Grúas Express Towing Guatemala'],
    'sg-1' => ['SG-1.jpeg', 'Camión rojo de plataforma con carga estacionado en un patio'],
    'sg-2' => ['SG-2.jpeg', 'Grúa de plataforma amarilla transportando un automóvil negro'],
    'sg-3' => ['SG-3.jpeg', 'Grúa de plataforma amarilla con un automóvil negro sobre la plataforma'],
    'sg-4' => ['SG-4.jpeg', 'Camión de plataforma transportando un compresor industrial'],
    'sg-5' => ['SG-5.jpeg', 'Grúa de plataforma subiendo un automóvil clásico rojo'],
    'sg-6' => ['SG-6.jpeg', 'Camión de plataforma transportando un tractor azul'],
    'sg-7' => ['SG-7.jpeg', 'Camión de plataforma gris y grúa de arrastre pesada en un patio'],
    'sg-8' => ['SG-8.jpeg', 'Camión de plataforma blanco transportando un tractor azul en la calle'],
    'sg-9' => ['SG-9.jpeg', 'Grúa de plataforma con un tractor azul sobre la plataforma'],
    'sg-10' => ['SG-10.jpeg', 'Camión de plataforma transportando una camioneta todoterreno blanca'],
    'sg-11' => ['SG-11.jpeg', 'Grúa de arrastre pesada roja y blanca junto a un camión'],
    'sg-12' => ['SG-12.jpeg', 'Grúa de plataforma blanca transportando un automóvil'],
    'sg-13' => ['SG-13.jpeg', 'Grúa de arrastre pesada remolcando un camión en carretera'],
    'sg-14' => ['SG-14.jpeg', 'Camión con grúa hidráulica izando una carga'],
    'sg-15' => ['SG-15.jpeg', 'Camión pequeño frente a un edificio hospitalario'],
    'sg-16' => ['SG-16.jpeg', 'Grúa de arrastre pesada roja y blanca en carretera'],
    'wa-2022' => ['WhatsApp-Image-2022-10-04-at-19.24.40.jpeg', 'Grúa de plataforma amarilla transportando un automóvil negro'],
    'izaje-2' => ['IMG-20240927-WA0002.jpg', 'Camión grúa hidráulica en maniobra de izaje'],
    'izaje-3' => ['IMG-20240927-WA0003.jpg', 'Camión grúa hidráulica extendida durante una maniobra de izaje'],
    'izaje-4' => ['IMG-20240927-WA0004.jpg', 'Camión grúa hidráulica izando una carga junto a una estructura'],
    'izaje-5' => ['IMG-20240927-WA0005.jpg', 'Camión grúa hidráulica trabajando dentro de una bodega industrial'],
    'izaje-6' => ['IMG-20240927-WA0006.jpg', 'Camión grúa hidráulica sobre una plataforma en la ciudad'],
    'izaje-7' => ['IMG-20240927-WA0007.jpg', 'Camión grúa roja con la pluma extendida al atardecer'],
];

$media = [];
foreach ($alt as $key => [$file, $text]) {
    $path = rtrim($src, '/') . '/' . $file;
    $r = gx_process_image($path, $file, GX_ROOT . '/uploads', $key);
    if (is_string($r)) {
        fwrite(STDERR, "ERROR $file: $r\n");
        exit(1);
    }
    $media[$key] = $r + ['alt' => $text, 'name' => $file];
    echo "img  $file -> {$r['file']} {$r['w']}x{$r['h']}\n";
}

// ---- videos ----
$videos = [];
foreach (['vid-1' => 'VID-20240927-WA0004.mp4', 'vid-2' => 'VID-20240927-WA0001.mp4'] as $k => $f) {
    $in = rtrim($src, '/') . '/' . $f;
    $head = (string)file_get_contents($in, false, null, 4, 4);
    if ($head !== 'ftyp') {
        fwrite(STDERR, "Video no válido $f\n");
        exit(1);
    }
    $out = GX_ROOT . '/media/' . $k . '.mp4';
    $cmd = 'ffmpeg -v error -y -i ' . escapeshellarg($in) . ' -map_metadata -1 -c copy -movflags +faststart ' . escapeshellarg($out) . ' 2>&1';
    passthru($cmd, $rc);
    if ($rc !== 0 || !is_file($out)) {
        fwrite(STDERR, "ffmpeg falló en $f\n");
        exit(1);
    }
    $probe = json_decode((string)shell_exec('ffprobe -v error -select_streams v:0 -show_entries stream=width,height:stream_tags=rotate:stream_side_data=rotation -of json ' . escapeshellarg($out)), true);
    $st = $probe['streams'][0] ?? [];
    $w = (int)($st['width'] ?? 0);
    $h = (int)($st['height'] ?? 0);
    $rot = (int)($st['side_data_list'][0]['rotation'] ?? ($st['tags']['rotate'] ?? 0));
    if (abs($rot) === 90 || abs($rot) === 270) {
        [$w, $h] = [$h, $w];
    }
    $tmpPoster = sys_get_temp_dir() . '/' . $k . '-poster.jpg';
    passthru('ffmpeg -v error -y -ss 0.6 -i ' . escapeshellarg($out) . ' -frames:v 1 -q:v 3 ' . escapeshellarg($tmpPoster) . ' 2>&1');
    $pr = gx_process_image($tmpPoster, 'poster.jpg', GX_ROOT . '/uploads', 'poster-' . $k);
    if (is_string($pr)) {
        fwrite(STDERR, "poster $k: $pr\n");
        exit(1);
    }
    $media['poster-' . $k] = $pr + ['alt' => 'Video del servicio de grúas', 'name' => 'poster-' . $k];
    $videos[$k] = ['src' => 'media/' . $k . '.mp4', 'poster' => '@m:poster-' . $k, 'w' => $w, 'h' => $h];
    echo "vid  $f -> media/$k.mp4 {$w}x{$h}\n";
}

/* ---------------- contenido exacto del inventario ---------------- */
$G = static function (array $keys): array {
    $o = [];
    foreach ($keys as $k) {
        $o[] = ['media' => '@m:' . $k];
    }
    return $o;
};

$cardsBlock = ['type' => 'cards'];
$hero = static function (string $img, string $title, string $sub): array {
    return ['title' => $title, 'subtitle' => $sub, 'text' => 'Servicio de Grúas 24/7', 'btn_text' => 'Llámenos: {phone}', 'btn_link' => 'tel:{tel}', 'image' => '@m:' . $img];
};

$homeDesc = 'Somos una empresa de servicio de grúas guatemalteca. Contamos con equipo de remolque terrestre, pesado y liviano las 24 horas los 7 días de la semana. Servicio de grúas a toda la República de Guatemala desde Q250.';

$pages = [
    ['slug' => 'inicio', 'sort' => 1, 'data' => [
        'label' => 'Inicio', 'path' => '/',
        'seo_title' => 'Grúas Express Towing Guatemala',
        'seo_desc' => $homeDesc,
        'og_image' => '@m:hero-inicio',
        'hero' => $hero('hero-inicio', 'Grúas Express', 'Towing Guatemala'),
        'blocks' => [
            ['type' => 'text', 'variant' => 'welcome', 'html' => '<p><strong>Bienvenidos a Express Towing</strong></p><p>Somos una empresa de servicio de grúas guatemalteca.</p><p>Contamos con equipo de remolque terrestre, pesado y liviano las 24 horas los 7 días de la semana.</p>'],
            ['type' => 'videos', 'items' => [$videos['vid-1'], $videos['vid-2']]],
            ['type' => 'heading', 'html' => 'Servicio de grúas las 24 horas <br>a toda la República de Guatemala desde Q250'],
            $cardsBlock,
            ['type' => 'text', 'variant' => 'closing', 'html' => '<p><strong>Te garantizamos un excelente servicio.</strong></p>'],
            ['type' => 'gallery', 'items' => $G(['izaje-2', 'izaje-7', 'izaje-6', 'izaje-5', 'izaje-4', 'izaje-3', 'sg-11', 'sg-10', 'sg-4', 'sg-5', 'sg-6', 'sg-7', 'sg-8', 'sg-9', 'sg-3', 'sg-2', 'sg-1', 'sg-16', 'sg-15', 'wa-2022', 'sg-12', 'sg-13'])],
        ],
    ]],
    ['slug' => 'sobre-nosotros', 'sort' => 2, 'data' => [
        'label' => 'Sobre nosotros', 'path' => '/sobre-nosotros/',
        'seo_title' => 'Sobre nosotros - Grúas Express Towing Guatemala',
        'seo_desc' => 'Somos una empresa guatemalteca que brinda servicio de grúas. Contamos con equipo de remolque, terrestre, pesado y liviano las 24 horas los 7 días de la semana.',
        'og_image' => '@m:hero-sobre',
        'hero' => $hero('hero-sobre', 'Grúas Express', 'Towing Guatemala'),
        'blocks' => [
            ['type' => 'text', 'variant' => 'plain', 'html' => '<p>Somos una empresa guatemalteca que brinda servicio de grúas.</p><p>Contamos con equipo de remolque, terrestre, pesado y liviano las 24 horas los 7 días de la semana.</p>'],
            ['type' => 'features', 'items' => [
                ['icon' => 'target', 'title' => 'MISIÓN', 'text' => 'Proporcionar un servicio de alta calidad en el transporte y manejo de vehículos livianos y pesados así mismo todo tipo de maquinaria industrial dentro y fuera del país contamos con personal capacitado y equipo acorde al trabajo que se nos presenta.'],
                ['icon' => 'eye', 'title' => 'VISIÓN', 'text' => 'Ser una empresa reconocida a nivel nacional por el servicio de remolque de todo tipo de vehículos y maquinaria y que esto motive a los clientes a darnos la oportunidad de servirles.'],
            ]],
            $cardsBlock,
        ],
    ]],
    ['slug' => 'servicio-de-gruas-24-7', 'sort' => 3, 'data' => [
        'label' => 'Servicio de grúas 24/7', 'path' => '/servicio-de-gruas-24-7/',
        'seo_title' => 'Servicio de grúas 24/7 - Grúas Express Towing Guatemala',
        'seo_desc' => 'Servicio de grúas en Guatemala, urgentes, eficientes y a un precio justo. Hacemos todo lo posible para auxiliarlo.',
        'og_image' => '@m:hero-servicio',
        'hero' => $hero('hero-servicio', '', 'Grúas Express Towing'),
        'blocks' => [
            ['type' => 'text', 'variant' => 'plain', 'html' => '<p>Servicio de grúas en Guatemala, urgentes, eficientes y a un precio justo. </p><p>Ofrecemos el servicio de guas en toda Guatemala, adaptados a sus necesidades y a un precio competitivo. </p><p>Sabemos la importancia de brindar seguridad y calidad en cada unos de nuestros servicios, es por eso que nos adaptamos a las necesidades de nuestros clientes.</p><p><strong>HACEMOS TODO LO POSIBLE PARA AUXILIARLO</strong></p><p>Además, ofrecemos diversos servicios a toda la República Mexicana las 24 horas, todos los días, para cubrir las necesidades de nuestros clientes.</p>'],
            ['type' => 'gallery', 'items' => $G(['sg-12', 'sg-13', 'sg-14', 'sg-15', 'sg-16', 'sg-1', 'sg-2', 'sg-3', 'sg-4', 'sg-5', 'sg-6', 'sg-7', 'sg-8', 'sg-9', 'sg-10', 'sg-11'])],
            $cardsBlock,
        ],
    ]],
    ['slug' => 'contactenos', 'sort' => 4, 'data' => [
        'label' => 'Contáctenos', 'path' => '/contactenos/',
        'seo_title' => 'Contáctenos - Grúas Express Towing Guatemala',
        'seo_desc' => 'Contáctenos. Teléfono: 3556-7904. Whatsapp: 3556-7904. Email: info@gruasexpresstowing.gt. Envíenos un mensaje.',
        'og_image' => '@m:hero-contacto',
        'hero' => $hero('hero-contacto', 'Grúas Express', 'Towing Guatemala'),
        'blocks' => [
            $cardsBlock,
            ['type' => 'form', 'title' => 'Envíenos un mensaje',
                'labels' => ['name' => 'Nombre *', 'phone' => 'Teléfono *', 'email' => 'Correo electrónico', 'service' => 'Tipo de servicio *', 'service_ph' => 'Seleccione una opción', 'place' => 'Ubicación / destino', 'message' => 'Mensaje *', 'send' => 'Enviar mensaje'],
                'services' => ['Remolque de vehículo liviano', 'Remolque de vehículo pesado', 'Transporte de maquinaria industrial', 'Otro servicio'],
                'wa_intro' => 'Hola, necesito el servicio de grúas.'],
        ],
    ]],
];

$settings = [
    'site_name' => 'Grúas Express Towing Guatemala',
    'site_url' => 'https://gruasexpresstowing.gt',
    'phone_display' => '3556-7904', 'phone_tel' => '35567904',
    'wa_display' => '3556-7904', 'wa_number' => '50235567904',
    'email_display' => 'info@gruasexpresstowing.gt', 'email_link' => 'info@agroinco.com',
    'form_to' => 'info@gruasexpresstowing.gt',
    'alert_email' => 'info@gruasexpresstowing.gt',
    'address' => '', 'hours' => '', 'socials' => [],
    'topbar_on' => true, 'topbar_phone_label' => 'Teléfono: ', 'topbar_email_label' => 'Email: ',
    'footer_text' => 'Copyright 2026 - Grúas Express Towing Guatemala',
    'logo' => '@m:logo', 'logo_mobile' => '@m:logo-mobile', 'favicon' => '@m:logo', 'favicon_dirty' => false,
    'menu' => [
        ['label' => 'INICIO', 'link' => '/', 'visible' => true],
        ['label' => 'SOBRE NOSOTROS', 'link' => '/sobre-nosotros/', 'visible' => true],
        ['label' => 'SERVICIO DE GRÚAS 24/7', 'link' => '/servicio-de-gruas-24-7/', 'visible' => true],
        ['label' => 'CONTÁCTENOS', 'link' => '/contactenos/', 'visible' => true],
    ],
    'cards' => ['call_title' => 'LLÁMENOS', 'call_label' => 'Teléfono: ', 'wa_title' => 'ESCRÍBANOS', 'wa_label' => 'Whatsapp: ', 'mail_title' => 'ENVÍE UN EMAIL'],
    'colors' => ['gold' => '#d6a93c', 'gold_hi' => '#f3dc9b', 'gold_lo' => '#8a6417', 'bg' => '#0a0a0b', 'graphite' => '#17191c', 'ink' => '#f3efe6'],
    'ld_description' => $homeDesc, 'ld_price' => 'Desde Q250',
    'smtp' => ['enabled' => false, 'host' => '', 'port' => 587, 'secure' => 'tls', 'user' => '', 'pass' => '', 'from' => ''],
    'admin_ips' => '',
    'videos' => [],
    'pending_publish' => false,
];
foreach ($videos as $v) {
    $settings['videos'][$v['src']] = ['w' => $v['w'], 'h' => $v['h'], 'poster' => $v['poster']];
}

$mediaList = [];
foreach ($media as $key => $m) {
    $mediaList[] = ['key' => $key, 'file' => $m['file'], 'thumb' => $m['thumb'], 'w' => $m['w'], 'h' => $m['h'], 'tw' => $m['tw'], 'th' => $m['th'], 'alt' => $m['alt'], 'name' => $m['name']];
}
@mkdir(GX_ROOT . '/_core/seed', 0755, true);
gx_json_write(GX_ROOT . '/_core/seed/content.json', ['media' => $mediaList, 'settings' => $settings, 'pages' => $pages]);
echo "seed OK: " . count($mediaList) . " medios, " . count($pages) . " páginas\n";
