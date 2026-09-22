<?php
/**
 * Kaptor - Manifiesto de la aplicación instalable (PWA).
 *
 * Se genera con PHP, y no como archivo estático, para que el nombre y los
 * colores sean los que el administrador tenga puestos en Ajustes.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$nombre = Ajustes::obtener('sitio_nombre', 'Kaptor');
$modo   = Ajustes::obtener('tema_por_defecto', 'oscuro') === 'claro' ? 'claro' : 'oscuro';
$fondo  = $modo === 'claro'
    ? Ajustes::obtener('color_fondo_claro', '#FFFFFF')
    : Ajustes::obtener('color_fondo', '#06070A');

$base = rtrim(cr_url_base(), '/') . '/';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

echo json_encode([
    'id'                => $base,
    'name'              => $nombre . ' · ' . Ajustes::obtener('sitio_lema', 'Extracción web inteligente'),
    'short_name'        => mb_substr($nombre, 0, 12),
    'description'       => Ajustes::obtener('sitio_descripcion', 'Extrae correos y números de WhatsApp de cualquier página web.'),
    'lang'              => 'es',
    'dir'               => 'ltr',
    'start_url'         => $base,
    'scope'             => $base,
    'display'           => 'standalone',
    'display_override'  => ['window-controls-overlay', 'standalone', 'minimal-ui'],
    'orientation'       => 'any',
    'background_color'  => $fondo,
    'theme_color'       => $fondo,
    'categories'        => ['productivity', 'business', 'utilities'],
    'icons' => [
        ['src' => $base . 'assets/img/icono-192.png',          'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . 'assets/img/icono-512.png',          'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . 'assets/img/icono-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts' => [
        [
            'name'  => 'Extraer contactos',
            'url'   => $base,
            'icons' => [['src' => $base . 'assets/img/icono-192.png', 'sizes' => '192x192']],
        ],
        [
            'name'  => 'Depurar lista',
            'url'   => $base . 'depurar.php',
            'icons' => [['src' => $base . 'assets/img/icono-192.png', 'sizes' => '192x192']],
        ],
        [
            'name'  => 'Mis extracciones',
            'url'   => $base . 'mis-extracciones.php',
            'icons' => [['src' => $base . 'assets/img/icono-192.png', 'sizes' => '192x192']],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
