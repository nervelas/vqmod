<?php
/** Dynamic sitemap.xml generated from active pages + albums. */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/Content.php';

header('Content-Type: application/xml; charset=utf-8');

$urls = [];
foreach (Database::all('SELECT slug, updated_at FROM pages WHERE is_active = 1 ORDER BY sort') as $p) {
    $loc = $p['slug'] === 'inicio' ? base_url() : base_url($p['slug']);
    $urls[] = ['loc' => $loc, 'lastmod' => substr((string)$p['updated_at'], 0, 10)];
}
foreach (Database::all('SELECT slug, created_at FROM albums WHERE is_active = 1') as $a) {
    $urls[] = ['loc' => base_url('galeria/' . $a['slug']), 'lastmod' => substr((string)$a['created_at'], 0, 10)];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n    <loc>" . e($u['loc']) . "</loc>\n";
    if (!empty($u['lastmod'])) { echo "    <lastmod>" . e($u['lastmod']) . "</lastmod>\n"; }
    echo "  </url>\n";
}
echo '</urlset>';
