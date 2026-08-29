<?php
namespace MenuGold\Controllers;

use MenuGold\Core\Controller;
use MenuGold\Core\DB;
use MenuGold\Core\Response;
use MenuGold\Core\Url;
use MenuGold\Models\Landing;
use MenuGold\Models\Menu;
use MenuGold\Models\Restaurant;

/** Sitio de venta. */
class LandingController extends Controller
{
    public function index()
    {
        $demo = Landing::demoRestaurant();
        $gallery = array();
        if ($demo) {
            $gallery = Menu::featured((int)$demo['id'], 7);
            if (count($gallery) < 7) {
                $extra = DB::all(
                    'SELECT p.*, c.name AS category_name FROM products p
                     LEFT JOIN categories c ON c.id = p.category_id
                     WHERE p.restaurant_id = :r AND p.is_active = 1 AND p.image <> \'\'
                     ORDER BY p.is_featured DESC, RAND() LIMIT 12',
                    array('r' => (int)$demo['id'])
                );
                $seen = array();
                foreach ($gallery as $g) { $seen[(int)$g['id']] = true; }
                foreach ($extra as $e) {
                    if (count($gallery) >= 7) { break; }
                    if (isset($seen[(int)$e['id']])) { continue; }
                    $gallery[] = $e;
                }
            }
        }

        $demoUrl = $demo ? Url::abs('/r/' . $demo['slug']) : Url::abs('/');

        return $this->view('landing/index', array(
            'demo'      => $demo,
            'demoUrl'   => $demoUrl,
            'gallery'   => $gallery,
            'plans'     => Landing::plans(),
            'quotes'    => Landing::testimonials(),
            'phoneDemo' => $demo ? $this->phoneSample((int)$demo['id']) : array(),
        ));
    }

    /** Cuatro platillos para la maqueta del teléfono. */
    private function phoneSample($restaurantId)
    {
        return DB::all(
            "SELECT p.id, p.name, p.price, p.image, c.name AS category_name
             FROM products p LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.restaurant_id = :r AND p.is_active = 1 AND p.image <> ''
             ORDER BY p.is_featured DESC, p.sort LIMIT 4",
            array('r' => (int)$restaurantId)
        );
    }

    public function demo()
    {
        $demo = Landing::demoRestaurant();
        return $this->redirect($demo ? '/r/' . $demo['slug'] : '/');
    }

    public function pricing()
    {
        return $this->redirect('/#planes');
    }

    public function sitemap()
    {
        $urls = array(
            array('loc' => Url::abs('/'), 'priority' => '1.0', 'freq' => 'weekly'),
        );
        foreach (Restaurant::allActive() as $r) {
            if (!Restaurant::isPublic($r)) { continue; }
            $urls[] = array(
                'loc' => Url::abs('/r/' . $r['slug']),
                'priority' => '0.8',
                'freq' => 'daily',
                'lastmod' => substr((string)$r['updated_at'], 0, 10),
            );
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>'
                  . (isset($u['lastmod']) ? '<lastmod>' . $u['lastmod'] . '</lastmod>' : '')
                  . '<changefreq>' . $u['freq'] . '</changefreq>'
                  . '<priority>' . $u['priority'] . '</priority></url>' . "\n";
        }
        $xml .= '</urlset>';
        return Response::make($xml, 200, array('Content-Type' => 'application/xml; charset=UTF-8'));
    }

    public function robots()
    {
        $body = "User-agent: *\n"
              . "Allow: /\n"
              . "Disallow: /panel\n"
              . "Disallow: /super\n"
              . "Disallow: /install\n"
              . "Disallow: /api\n"
              . "Disallow: /pedido\n\n"
              . 'Sitemap: ' . Url::abs('/sitemap.xml') . "\n";
        return Response::text($body);
    }
}
