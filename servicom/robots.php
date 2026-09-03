<?php
/** robots.txt dinamico (usa la URL real del sitio para el sitemap). */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: text/plain; charset=UTF-8');

$block = Settings::bool('maintenance') || str_contains(Settings::get('seo_robots'), 'noindex');
?>
User-agent: *
<?php if ($block): ?>
Disallow: /
<?php else: ?>
Allow: /

Disallow: /admin/
Disallow: /install/
Disallow: /includes/
Disallow: /config/
Disallow: /database/
Disallow: /storage/
Disallow: /*?preview_theme=

User-agent: Googlebot
Allow: /
Allow: /assets/

User-agent: Googlebot-Image
Allow: /

Sitemap: <?= url('sitemap.xml') ?>
<?php endif; ?>
