<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Controlador;
use App\Core\Url;

final class PwaControlador extends Controlador
{
    /** Manifiesto de la aplicación instalable. */
    public function manifest(): void
    {
        $nombre = Ajustes::get('nombre', 'ResidencialPro');
        $color  = Ajustes::get('color_primario', '#0E4C5A');
        $base   = Url::basePath();

        $iconos = [];
        foreach ([48, 72, 96, 128, 144, 152, 192, 256, 384, 512] as $t) {
            $iconos[] = [
                'src'     => $base . '/assets/img/icono-' . $t . '.png',
                'sizes'   => $t . 'x' . $t,
                'type'    => 'image/png',
                'purpose' => 'any',
            ];
        }
        foreach ([192, 512] as $t) {
            $iconos[] = [
                'src'     => $base . '/assets/img/icono-maskable-' . $t . '.png',
                'sizes'   => $t . 'x' . $t,
                'type'    => 'image/png',
                'purpose' => 'maskable',
            ];
        }

        $manifest = [
            'id'                => $base . '/',
            'name'              => $nombre,
            'short_name'        => mb_substr($nombre, 0, 12),
            'description'       => Ajustes::get('descripcion', 'Administración del residencial'),
            'lang'              => 'es-GT',
            'dir'               => 'ltr',
            'start_url'         => $base . '/portal',
            'scope'             => $base . '/',
            'display'           => 'standalone',
            'display_override'  => ['standalone', 'minimal-ui'],
            'orientation'       => 'any',
            'background_color'  => '#F1EEE6',
            'theme_color'       => $color,
            'categories'        => ['productivity', 'utilities', 'business'],
            'icons'             => $iconos,
            'shortcuts'         => [
                ['name' => 'Estado de cuenta', 'short_name' => 'Mi cuenta',
                 'url' => $base . '/portal/estado-cuenta',
                 'icons' => [['src' => $base . '/assets/img/icono-96.png', 'sizes' => '96x96']]],
                ['name' => 'Autorizar una visita', 'short_name' => 'Visitas',
                 'url' => $base . '/portal/visitas/nueva',
                 'icons' => [['src' => $base . '/assets/img/icono-96.png', 'sizes' => '96x96']]],
                ['name' => 'Reservar un área', 'short_name' => 'Reservas',
                 'url' => $base . '/portal/reservas',
                 'icons' => [['src' => $base . '/assets/img/icono-96.png', 'sizes' => '96x96']]],
            ],
        ];

        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    public function offline(): void
    {
        $this->mostrar('errors/sin-conexion', ['tituloPagina' => 'Sin conexión'], 'limpio');
    }
}
