<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\DB;
use App\Core\Security;
use App\Models\Company;
use App\Models\Setting;

/** Recursos técnicos del sitio: sitemap, robots, manifiesto, service worker. */
final class SystemController extends Controller
{
    public function sitemap(array $params = []): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        $out .= self::urlNode(absUrl('/'), date('Y-m-d'), '1.0');
        $out .= self::urlNode(absUrl('/catalogo'), date('Y-m-d'), '0.9');
        $out .= self::urlNode(absUrl('/nosotros'), date('Y-m-d'), '0.5');
        $out .= self::urlNode(absUrl('/contacto'), date('Y-m-d'), '0.5');
        foreach (DB::all('SELECT slug FROM categories WHERE active = 1') as $r) {
            $out .= self::urlNode(absUrl('/categoria/' . $r['slug']), date('Y-m-d'), '0.7');
        }
        foreach (DB::all('SELECT slug, updated_at FROM products WHERE active = 1 LIMIT 5000') as $r) {
            $out .= self::urlNode(absUrl('/producto/' . $r['slug']), substr((string) ($r['updated_at'] ?: nowSql()), 0, 10), '0.6');
        }
        echo $out . '</urlset>';
        exit;
    }

    public static function urlNode(string $loc, string $lastmod, string $priority): string
    {
        return "  <url><loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc><lastmod>{$lastmod}</lastmod><priority>{$priority}</priority></url>\n";
    }

    public function robots(array $params = []): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\n";
        echo "Disallow: /panel\n";
        echo "Disallow: /entrar\n";
        echo "Disallow: /c/\n";
        echo "Disallow: /install\n";
        echo "Disallow: /cron\n";
        echo "Allow: /\n\n";
        echo 'Sitemap: ' . absUrl('/sitemap.xml') . "\n";
        exit;
    }

    public function manifest(array $params = []): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        $c    = Company::get();
        $name = (string) ($c['name'] ?? Setting::get('app_name', 'CotizaPro B2B'));
        $theme = $c ? Company::theme($c) : ['ink' => '#1C1F22', 'paper' => '#F5F6F4'];
        $icons = [];
        foreach ([72, 96, 128, 144, 152, 192, 384, 512] as $s) {
            $file = '/assets/img/icons/icon-' . $s . '.png';
            if (is_file(BASE_PATH . $file)) {
                $icons[] = ['src' => url($file), 'sizes' => $s . 'x' . $s, 'type' => 'image/png', 'purpose' => 'any maskable'];
            }
        }
        echo json_encode([
            'name'             => $name,
            'short_name'       => mb_substr(trim(explode(' ', $name)[0]), 0, 12),
            'description'      => 'Catálogo técnico, cotizador en línea y seguimiento de cotizaciones.',
            'lang'             => 'es',
            'dir'              => 'ltr',
            'start_url'        => url('/panel'),
            'scope'            => url('/'),
            'id'               => url('/'),
            'display'          => 'standalone',
            'orientation'      => 'any',
            'background_color' => $theme['paper'],
            'theme_color'      => $theme['ink'],
            'categories'       => ['business', 'productivity'],
            'icons'            => $icons,
            'shortcuts'        => [
                ['name' => 'Tablero de cotizaciones', 'url' => url('/panel/tablero')],
                ['name' => 'Nueva cotización', 'url' => url('/panel/cotizaciones/nueva')],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function serviceWorker(array $params = []): void
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Service-Worker-Allowed: ' . url('/'));
        $file = BASE_PATH . '/assets/js/sw-template.js';
        $js = is_file($file) ? (string) file_get_contents($file) : '';
        $version = substr(hash('sha256', (string) @filemtime($file) . App::basePath()), 0, 10);
        echo str_replace(['__BASE__', '__VERSION__'], [App::basePath(), $version], $js);
        exit;
    }

    public function offline(array $params = []): void
    {
        Security::headers();
        $this->view('site/offline', ['title' => 'Sin conexión'], 'layout/blank');
    }
}
