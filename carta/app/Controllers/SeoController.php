<?php
namespace MenuGold\Controllers;

use MenuGold\Core\Controller;
use MenuGold\Core\DB;
use MenuGold\Core\Response;
use MenuGold\Core\Url;

/** sitemap.xml y robots.txt del menú. */
class SeoController extends Controller
{
    public function sitemap()
    {
        $urls = array(array('loc' => Url::abs('/'), 'prio' => '1.0', 'freq' => 'daily'));
        foreach (DB::all('SELECT id FROM mg_products WHERE is_active = 1 ORDER BY sort, id LIMIT 500') as $p) {
            $urls[] = array('loc' => Url::abs('/producto/' . (int)$p['id']), 'prio' => '0.7', 'freq' => 'weekly');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>'
                  . '<changefreq>' . $u['freq'] . '</changefreq>'
                  . '<priority>' . $u['prio'] . '</priority></url>' . "\n";
        }
        $xml .= '</urlset>';

        return Response::make($xml, 200, array('Content-Type' => 'application/xml; charset=UTF-8'));
    }

    public function robots()
    {
        $txt = "User-agent: *\n"
             . "Allow: /\n"
             . "Disallow: /panel\n"
             . "Disallow: /install\n"
             . "Disallow: /pedido/\n"
             . "\nSitemap: " . Url::abs('/sitemap.xml') . "\n";
        return Response::text($txt);
    }
}
