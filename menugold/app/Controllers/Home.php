<?php
declare(strict_types=1);

namespace MenuGold\Controllers;

use MenuGold\Core\App;
use MenuGold\Core\Controller;
use MenuGold\Core\Csrf;
use MenuGold\Core\DB;
use MenuGold\Core\HttpException;
use MenuGold\Core\Mailer;
use MenuGold\Core\RateLimit;
use MenuGold\Core\Request;
use MenuGold\Core\Security;
use MenuGold\Core\Setting;
use MenuGold\Core\Validator;
use MenuGold\Models\Plan;
use MenuGold\Models\Restaurant;

/**
 * Sitio publico de la plataforma (pagina de venta administrable).
 */
class Home extends Controller
{
    public function index(): void
    {
        // Si el dominio esta mapeado a un restaurante, mostramos su menu
        $r = App::restaurantByDomain();
        if ($r) {
            (new \MenuGold\Controllers\Menu())->index(['slug' => (string)$r['slug']]);
            return;
        }
        if (!App::installed()) redirect('install/');

        $this->view('publico/landing', [
            'planes'  => (new Plan())->activos(),
            'demo'    => $this->demoRestaurante(),
            'captcha' => Security::captchaMake(),
        ], 'publico');
    }

    public function planes(): void
    {
        $this->index();
    }

    public function demo(): void
    {
        $r = $this->demoRestaurante();
        if (!$r) throw HttpException::notFound('Aún no hay restaurante de demostración configurado.');
        redirect('r/' . $r['slug']);
    }

    private function demoRestaurante(): ?array
    {
        $slug = (string)Setting::plat('demo_slug', '');
        $m = new Restaurant();
        $r = $slug !== '' ? $m->bySlug($slug) : null;
        if (!$r) {
            $r = DB::one("SELECT * FROM restaurants WHERE demo = 1 AND estado <> 'suspendido' ORDER BY id ASC LIMIT 1");
        }
        return $r ?: null;
    }

    // ---------------------------------------------------------------- contacto
    public function contacto(): void
    {
        Csrf::enforce();
        $ip = client_ip();
        $rl = RateLimit::hit('contacto:' . $ip, 5, 3600);
        if (!$rl['permitido']) {
            flash('error', 'Recibimos varios mensajes desde tu conexión. Intenta más tarde.');
            $this->back('/');
        }
        // Trampa para robots
        if (Request::str('sitio_web') !== '') {
            flash('exito', 'Gracias, te contactaremos pronto.');
            $this->back('/');
        }
        if (!Security::captchaCheck(Request::input('captcha'))) {
            flash('error', 'La suma de verificación no es correcta. Intenta de nuevo.');
            $this->back('/');
        }

        $datos = [
            'nombre'      => Request::str('nombre', '', 120),
            'email'       => Request::email('email'),
            'telefono'    => Request::str('telefono', '', 30),
            'restaurante' => Request::str('restaurante', '', 120),
            'plan'        => Request::str('plan', '', 60),
            'mensaje'     => Request::str('mensaje', '', 2000),
        ];
        $v = Validator::make($datos)
            ->requerido('nombre', 'Tu nombre')->min('nombre', 2, 'Tu nombre')
            ->requerido('email', 'El correo')->email('email')
            ->requerido('mensaje', 'El mensaje')->min('mensaje', 10, 'El mensaje');
        if ($v->falla()) {
            flash('error', $v->primerError());
            $this->keepOld($datos);
            $this->back('/');
        }

        DB::insert('contact_messages', $datos + ['ip' => $ip, 'creado' => date('Y-m-d H:i:s')]);

        $destino = (string)Setting::plat('email_contacto', '');
        if ($destino !== '') {
            $cuerpo = '<p><strong>' . e($datos['nombre']) . '</strong> quiere información de MenúGold.</p>'
                . '<ul style="line-height:1.9">'
                . '<li>Correo: ' . e($datos['email']) . '</li>'
                . ($datos['telefono'] ? '<li>Teléfono: ' . e($datos['telefono']) . '</li>' : '')
                . ($datos['restaurante'] ? '<li>Restaurante: ' . e($datos['restaurante']) . '</li>' : '')
                . ($datos['plan'] ? '<li>Plan de interés: ' . e($datos['plan']) . '</li>' : '')
                . '</ul><p style="white-space:pre-line">' . e($datos['mensaje']) . '</p>';
            Mailer::send($destino, 'Nuevo interesado en MenúGold', $cuerpo);
        }

        flash('exito', '¡Gracias! Te contactaremos muy pronto.');
        $this->back('/');
    }

    // ---------------------------------------------------------------- SEO
    public function sitemap(): void
    {
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $x .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $x .= '  <url><loc>' . e(App::url('')) . '</loc><changefreq>weekly</changefreq><priority>1.0</priority></url>' . "\n";
        try {
            $rs = DB::all("SELECT slug, dominio, actualizado, creado FROM restaurants WHERE estado <> 'suspendido' ORDER BY id ASC LIMIT 500");
            foreach ($rs as $r) {
                $loc = !empty($r['dominio']) ? 'https://' . $r['dominio'] . '/' : App::url('r/' . $r['slug']);
                $fecha = date('Y-m-d', strtotime((string)($r['actualizado'] ?: $r['creado'])));
                $x .= '  <url><loc>' . e($loc) . '</loc><lastmod>' . $fecha . '</lastmod>'
                    . '<changefreq>weekly</changefreq><priority>0.8</priority></url>' . "\n";
            }
        } catch (\Throwable $e) {}
        $x .= '</urlset>';

        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/xml; charset=utf-8');
        echo $x;
        exit;
    }

    /** QR de la demostración para la página de venta. */
    public function qrDemo(): void
    {
        $r = $this->demoRestaurante();
        $destino = $r ? Restaurant::urlMenu($r) : App::url('');
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        $png = \MenuGold\Vendor\QrCode\QrCode::png($destino, 6, 2, '#141414', '#FFFFFF', 'M');
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        echo $png;
        exit;
    }

    public function offline(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        $this->view('publico/offline', [], 'vacio');
    }
}
