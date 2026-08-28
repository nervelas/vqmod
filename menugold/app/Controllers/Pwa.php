<?php
declare(strict_types=1);

namespace MenuGold\Controllers;

use MenuGold\Core\App;
use MenuGold\Core\Controller;
use MenuGold\Core\Image;
use MenuGold\Core\Setting;
use MenuGold\Models\Restaurant;

/**
 * Aplicación instalable (PWA).
 *
 * No mostramos ningún botón ni mensaje propio de "descargar aplicación":
 * el aviso de instalación es el nativo del navegador, que aparece cuando
 * se cumplen sus criterios (HTTPS, manifest válido, service worker e iconos).
 * Por eso nunca llamamos a preventDefault sobre beforeinstallprompt.
 */
class Pwa extends Controller
{
    public function manifest(array $p = []): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        $slug = (string)($p['slug'] ?? '');
        $r = null;
        if ($slug !== '') {
            $r = (new Restaurant())->bySlug($slug);
        } else {
            $r = App::restaurantByDomain();
        }

        if ($r) {
            $nombre  = (string)$r['nombre'];
            $corto   = mb_substr($nombre, 0, 12);
            $inicio  = Restaurant::urlMenu($r);
            $ambito  = $slug !== '' ? url('r/' . $slug) . '/' : url('') ;
            $fondo   = (string)($r['color_fondo'] ?? '#141414');
            $tema    = (string)($r['color_fondo'] ?? '#141414');
            $desc    = trim((string)($r['seo_desc'] ?? '')) ?: ('Menú digital de ' . $nombre);
            $base    = $slug !== '' ? 'r/' . $slug . '/icono/' : 'icono/';
        } else {
            $nombre  = (string)Setting::plat('nombre_plataforma', 'MenúGold');
            $corto   = 'MenúGold';
            $inicio  = url('panel');
            $ambito  = rtrim(url(''), '/') . '/';   // sin barra doble
            $fondo   = '#141414';
            $tema    = '#141414';
            $desc    = (string)Setting::plat('eslogan', 'Menús QR con pedidos para restaurantes');
            $base    = 'icono/';
        }

        $tamanos = [72, 96, 128, 144, 152, 192, 256, 384, 512];
        $iconos = [];
        foreach ($tamanos as $t) {
            $iconos[] = [
                'src'     => url($base . $t),
                'sizes'   => $t . 'x' . $t,
                'type'    => 'image/png',
                'purpose' => 'any',
            ];
        }
        foreach ([192, 512] as $t) {
            $iconos[] = [
                'src'     => url($base . $t . '?maskable=1'),
                'sizes'   => $t . 'x' . $t,
                'type'    => 'image/png',
                'purpose' => 'maskable',
            ];
        }

        $manifest = [
            'id'               => $ambito,
            'name'             => $nombre,
            'short_name'       => $corto,
            'description'      => $desc,
            'start_url'        => $inicio,
            'scope'            => $ambito,
            'display'          => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation'      => 'portrait-primary',
            'background_color' => $fondo,
            'theme_color'      => $tema,
            'lang'             => 'es-GT',
            'dir'              => 'ltr',
            'categories'       => ['food', 'business', 'shopping'],
            'icons'            => $iconos,
        ];
        if ($r) {
            $manifest['shortcuts'] = [[
                'name'  => 'Ver el menú',
                'url'   => $inicio,
                'icons' => [['src' => url($base . '96'), 'sizes' => '96x96']],
            ]];
        }

        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    /** Icono PNG generado a partir del logo del restaurante (o del isotipo). */
    public function icono(array $p = []): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        $tam = max(16, min(1024, (int)($p['tam'] ?? 192)));
        $maskable = !empty($_GET['maskable']);
        $slug = (string)($p['slug'] ?? '');
        $r = $slug !== '' ? (new Restaurant())->bySlug($slug) : App::restaurantByDomain();

        $logo   = $r['logo'] ?? '';
        $fondo  = (string)($r['color_fondo'] ?? '#141414');
        $acento = (string)($r['color_primario'] ?? '#D4AF37');
        $letra  = mb_strtoupper(mb_substr((string)($r['nombre'] ?? 'MenuGold'), 0, 1));
        $cacheKey = md5(($r['id'] ?? 0) . '|' . $logo . '|' . $tam . '|' . ($maskable ? 'm' : 'a') . '|' . $fondo . '|' . $acento);
        $cacheFile = MG_ROOT . '/storage/cache/icono-' . $cacheKey . '.png';

        if (is_file($cacheFile) && filemtime($cacheFile) > time() - 604800) {
            $this->salidaPng((string)file_get_contents($cacheFile));
        }

        $img = null;
        if ($logo && is_file(Image::path((string)$logo)) && function_exists('imagecreatetruecolor')) {
            $img = $maskable ? null : Image::square((string)$logo, $tam);
            if ($maskable) {
                $img = imagecreatetruecolor($tam, $tam);
                [$fr, $fg, $fb] = Image::hex2rgb($fondo);
                imagefill($img, 0, 0, imagecolorallocate($img, $fr, $fg, $fb));
                $inner = (int)round($tam * 0.7);
                $li = Image::square((string)$logo, $inner);
                if ($li) {
                    imagealphablending($img, true);
                    imagecopy($img, $li, (int)(($tam - $inner) / 2), (int)(($tam - $inner) / 2), 0, 0, $inner, $inner);
                    imagedestroy($li);
                }
            }
        }
        if (!$img) $img = $this->iconoLetra($tam, $fondo, $acento, $letra);

        ob_start();
        imagepng($img, null, 6);
        $png = (string)ob_get_clean();
        imagedestroy($img);
        @file_put_contents($cacheFile, $png);
        $this->salidaPng($png);
    }

    /** Icono generado con la inicial del restaurante, en dorado sobre negro. */
    private function iconoLetra(int $tam, string $fondo, string $acento, string $letra)
    {
        $img = imagecreatetruecolor($tam, $tam);
        [$fr, $fg, $fb] = Image::hex2rgb($fondo);
        [$ar, $ag, $ab] = Image::hex2rgb($acento);
        imagefill($img, 0, 0, imagecolorallocate($img, $fr, $fg, $fb));

        // Anillo dorado
        $oro = imagecolorallocate($img, $ar, $ag, $ab);
        $grosor = max(2, (int)round($tam * 0.035));
        imagesetthickness($img, $grosor);
        $m = (int)round($tam * 0.12);
        imageellipse($img, (int)($tam / 2), (int)($tam / 2), $tam - 2 * $m, $tam - 2 * $m, $oro);

        // Letra centrada
        $fuente = 5;
        $escala = max(1, (int)round($tam / 28));
        $tmp = imagecreatetruecolor(imagefontwidth($fuente) + 2, imagefontheight($fuente) + 2);
        imagefill($tmp, 0, 0, imagecolorallocate($tmp, $fr, $fg, $fb));
        imagestring($tmp, $fuente, 1, 1, $letra, imagecolorallocate($tmp, $ar, $ag, $ab));
        $w = imagesx($tmp) * $escala;
        $h = imagesy($tmp) * $escala;
        imagecopyresampled($img, $tmp, (int)(($tam - $w) / 2), (int)(($tam - $h) / 2), 0, 0, $w, $h, imagesx($tmp), imagesy($tmp));
        imagedestroy($tmp);
        return $img;
    }

    private function salidaPng(string $png): void
    {
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=604800, immutable');
        header('Content-Length: ' . strlen($png));
        echo $png;
        exit;
    }

    /** Favicon del navegador (evita 404 en los registros). */
    public function favicon(): void
    {
        $this->icono(['tam' => 64, 'slug' => '']);
    }

    /** Service worker generado con la base correcta del sitio. */
    public function serviceWorker(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        $file = MG_ROOT . '/assets/js/sw-plantilla.js';
        $js = is_file($file) ? (string)file_get_contents($file) : '';
        $base = App::basePath() === '' ? '' : App::basePath();
        $version = (string)App::config('version', '1.0.0');

        $js = str_replace(
            ['{{BASE}}', '{{VERSION}}', '{{OFFLINE}}', '{{CSS}}', '{{JS}}'],
            [
                $base,
                $version . '-' . substr(md5($base . filemtime($file ?: __FILE__)), 0, 6),
                url('offline'),
                asset('css/menu.css'),
                asset('js/menu.js'),
            ],
            $js
        );

        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Service-Worker-Allowed: ' . ($base === '' ? '/' : $base . '/'));
        echo $js;
        exit;
    }
}
