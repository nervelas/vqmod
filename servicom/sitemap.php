<?php
/**
 * Sitemap XML dinamico. Accesible en /sitemap.xml gracias a .htaccess
 * (o directamente en /sitemap.php).
 */
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require __DIR__ . '/includes/bootstrap.php';
}

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');

$urls = [];
$add  = static function (string $loc, string $lastmod, string $freq, string $priority) use (&$urls): void {
    $urls[] = ['loc' => $loc, 'lastmod' => $lastmod, 'freq' => $freq, 'priority' => $priority];
};

$fmt = static function (?string $date): string {
    $ts = $date ? strtotime($date) : false;
    return date('Y-m-d', $ts === false ? time() : $ts);
};

foreach (Content::pages() as $p) {
    if ((int) $p['show_in_sitemap'] !== 1) {
        continue;
    }
    $slug = (string) $p['slug'];
    $loc  = $slug === 'inicio' ? url() : url($slug . '/');
    $freq = $slug === 'inicio' ? 'weekly' : 'monthly';
    $add($loc, $fmt((string) $p['updated_at']), $freq, (string) $p['priority']);
}

foreach (Content::services() as $s) {
    $add(url('servicios/' . $s['slug'] . '/'), $fmt(null), 'monthly', '0.9');
}

foreach (Content::posts() as $p) {
    $add(url('actualidad-web/' . $p['slug'] . '/'), $fmt((string) $p['published_at']), 'yearly', '0.6');
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
    echo '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
    echo '    <changefreq>' . $u['freq'] . "</changefreq>\n";
    echo '    <priority>' . $u['priority'] . "</priority>\n";
    echo '    <xhtml:link rel="alternate" hreflang="es-gt" href="' . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "\"/>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
