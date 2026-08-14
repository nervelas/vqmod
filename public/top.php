<?php
/**
 * Public layout header. Expects: $theme (array), $pageTitle, $metaDesc,
 * optional $activeNav, $extraHead.
 */
if (!defined('FL_APP')) { exit; }
$siteName = Settings::get('site_name', 'Ligas de Fútbol');
$logo = Settings::get('logo');
$fav  = Settings::get('favicon');
$nav = [
    'inicio'    => ['Inicio', base_url('index.php')],
    'ligas'     => ['Ligas', base_url('index.php#ligas')],
    'noticias'  => ['Noticias', base_url('index.php#noticias')],
];
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? $siteName) ?></title>
<meta name="description" content="<?= e($metaDesc ?? Settings::get('seo_description', '')) ?>">
<meta property="og:title" content="<?= e($pageTitle ?? $siteName) ?>">
<meta property="og:description" content="<?= e($metaDesc ?? Settings::get('seo_description', '')) ?>">
<meta property="og:type" content="website">
<?php if ($fav): ?><link rel="icon" href="<?= e(base_url($fav)) ?>"><?php else: ?>
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="46" fill="#111"/><text x="50" y="70" font-size="56" text-anchor="middle">⚽</text></svg>') ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
<style><?= Theme::styleBlock($theme) ?></style>
<?= $extraHead ?? '' ?>
</head>
<body>
<header class="site-header">
    <div class="container">
        <a class="brand" href="<?= e(base_url('index.php')) ?>">
            <?php if ($logo): ?><img src="<?= e(base_url($logo)) ?>" alt="" style="height:38px;width:auto;border-radius:8px">
            <?php else: ?><span class="brand-mark">⚽</span><?php endif; ?>
            <span><?= e($siteName) ?></span>
        </a>
        <button class="nav-toggle" aria-label="Menú">☰</button>
        <nav class="nav">
            <?php foreach ($nav as $key => $item): ?>
                <a href="<?= e($item[1]) ?>" class="<?= ($activeNav ?? '') === $key ? 'active' : '' ?>"><?= e($item[0]) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
<main>
