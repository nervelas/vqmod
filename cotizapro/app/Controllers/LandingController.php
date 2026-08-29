<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Captcha;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\ErrorHandler;
use App\Core\Mailer;
use App\Core\RateLimit;
use App\Core\Request;
use App\Core\Security;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Setting;

final class LandingController extends Controller
{
    public function home(array $params = []): void
    {
        // Si el dominio está mapeado a una empresa, se muestra su sitio.
        $mapped = Company::byDomain(App::host());
        if ($mapped) {
            (new SiteController())->home(['slug' => $mapped['slug']]);
            return;
        }
        $s = Setting::all();
        $blocks = [];
        foreach (DB::all('SELECT * FROM landing_blocks WHERE active = 1 ORDER BY section, sort, id') as $b) {
            $blocks[$b['section']][] = $b;
        }
        $demo = Company::bySlug(Setting::get('demo_slug', 'industrial-perez'));
        $counters = [
            'empresas'    => (int) DB::value('SELECT COUNT(*) FROM companies WHERE status IN ("activa","prueba")', [], 0),
            'productos'   => (int) DB::value('SELECT COUNT(*) FROM products WHERE active = 1', [], 0),
            'cotizaciones' => (int) DB::value('SELECT COUNT(*) FROM quotes', [], 0),
        ];
        $this->view('site/landing', [
            'title'       => Setting::get('seo_title', Setting::get('platform_name', 'CotizaPro B2B') . ' — Catálogo y cotizador en línea para empresas industriales'),
            'description' => Setting::get('seo_description', 'Catálogo técnico, cotizador en línea y seguimiento de cotizaciones para empresas que venden por cotización, no con tarjeta.'),
            's'        => $s,
            'blocks'   => $blocks,
            'plans'    => Plan::all(true),
            'demo'     => $demo,
            'counters' => $counters,
            'captcha'  => Captcha::make(),
        ], 'layout/landing');
    }

    public function plans(array $params = []): void
    {
        redirect('/#planes');
    }

    public function demo(array $params = []): void
    {
        $demo = Company::bySlug(Setting::get('demo_slug', 'industrial-perez'));
        if (!$demo) {
            ErrorHandler::render(404);
        }
        redirect('/e/' . $demo['slug']);
    }

    public function contact(array $params = []): void
    {
        $this->guardPost();
        if (!RateLimit::hit('lead', App::ip(), 5, 3600)) {
            \App\Core\Flash::error('Recibimos varias solicitudes desde su conexión. Intente más tarde.');
            redirect('/#contacto');
        }
        if (!Captcha::check(Request::str('captcha'), Request::str('captcha_stamp'))) {
            \App\Core\Flash::error('La respuesta de verificación no es correcta. Inténtelo de nuevo.');
            redirect('/#contacto');
        }
        $name  = mb_substr(Request::str('name'), 0, 120);
        $email = Request::email('email');
        $phone = mb_substr(Request::str('phone'), 0, 40);
        $co    = mb_substr(Request::str('company'), 0, 160);
        $msg   = mb_substr(Request::str('message'), 0, 1500);
        if ($name === '' || $email === '') {
            \App\Core\Flash::error('Escriba su nombre y un correo válido.');
            redirect('/#contacto');
        }
        $to = Setting::get('contact_email', Setting::get('smtp_from', 'info@' . App::host()));
        $body = '<p><strong>' . e($name) . '</strong> (' . e($co ?: 'sin empresa') . ') solicita información.</p>'
              . '<p>Correo: ' . e($email) . '<br>Teléfono: ' . e($phone ?: '—') . '</p>'
              . '<p>' . nl2br(e($msg)) . '</p>';
        Mailer::send($to, 'Nueva solicitud de demostración', Mailer::template('Solicitud desde la landing', $body), null, [], $email, $name);
        \App\Core\Flash::ok('¡Gracias! Le escribiremos muy pronto.');
        redirect('/#contacto');
    }

    // ------------------------------------------------------------------ SEO
    public function sitemap(array $params = []): void
    {
        $mapped = Company::byDomain(App::host());
        if ($mapped) {
            (new SiteController())->sitemap(['slug' => $mapped['slug']]);
            return;
        }
        header('Content-Type: application/xml; charset=utf-8');
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $out .= self::urlNode(absUrl('/'), date('Y-m-d'), '1.0');
        foreach (DB::all('SELECT slug, updated_at FROM companies WHERE status IN ("activa","prueba")') as $c) {
            $out .= self::urlNode(absUrl('/e/' . $c['slug']), substr((string) ($c['updated_at'] ?: nowSql()), 0, 10), '0.8');
            $out .= self::urlNode(absUrl('/e/' . $c['slug'] . '/catalogo'), date('Y-m-d'), '0.7');
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
        $mapped = Company::byDomain(App::host());
        $sitemap = $mapped ? absUrl('/e/' . $mapped['slug'] . '/sitemap.xml') : absUrl('/sitemap.xml');
        echo "User-agent: *\n";
        echo "Disallow: /panel\n";
        echo "Disallow: /super\n";
        echo "Disallow: /entrar\n";
        echo "Disallow: /c/\n";
        echo "Disallow: /install\n";
        echo "Disallow: /cron\n";
        echo "Allow: /\n\n";
        echo "Sitemap: {$sitemap}\n";
        exit;
    }

    public function manifest(array $params = []): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        $name = Setting::get('platform_name', 'CotizaPro B2B');
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
            'background_color' => '#F5F6F4',
            'theme_color'      => '#1C1F22',
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
        echo str_replace(
            ['__BASE__', '__VERSION__'],
            [App::basePath(), $version],
            $js
        );
        exit;
    }

    public function offline(array $params = []): void
    {
        Security::headers();
        $this->view('site/offline', ['title' => 'Sin conexión'], 'layout/blank');
    }
}
