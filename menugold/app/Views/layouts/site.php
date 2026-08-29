<?php
/** Layout del sitio de venta. @var \MenuGold\Core\View $view */
use MenuGold\Core\Url;
use MenuGold\Models\Landing;

$brand   = Landing::v('brand_name');
$seoT    = $view->section('title', Landing::v('seo_title'));
$seoD    = $view->section('description', Landing::v('seo_description'));
$ogImage = Landing::get('seo_og_image', '');
$ogUrl   = $ogImage !== '' ? mg_img_src($ogImage, 1600) : '';
?><!DOCTYPE html>
<html lang="es" prefix="og: https://ogp.me/ns#">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<script>document.documentElement.classList.add('js');</script>
<title><?= e($seoT) ?></title>
<meta name="description" content="<?= e($seoD) ?>">
<meta name="theme-color" content="#0C0B09">
<meta name="color-scheme" content="dark">
<link rel="canonical" href="<?= e(Url::current()) ?>">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($brand) ?>">
<meta property="og:title" content="<?= e($seoT) ?>">
<meta property="og:description" content="<?= e($seoD) ?>">
<meta property="og:url" content="<?= e(Url::abs('/')) ?>">
<meta property="og:locale" content="es_GT">
<?php if ($ogUrl !== ''): ?><meta property="og:image" content="<?= e($ogUrl) ?>">
<meta property="og:image:width" content="1600">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($seoT) ?>">
<meta name="twitter:description" content="<?= e($seoD) ?>">

<link rel="manifest" href="<?= e(mg_url('/manifest.webmanifest')) ?>">
<link rel="apple-touch-icon" href="<?= e(mg_url('/assets/icons/icon-192.png')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e($brand) ?>">
<link rel="icon" href="<?= e(mg_url('/assets/icons/icon-192.png')) ?>" sizes="192x192">

<link rel="preload" as="font" type="font/woff2" href="<?= e(mg_url('/assets/fonts/fraunces-normal-latin.woff2')) ?>" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="<?= e(mg_url('/assets/fonts/inter-normal-latin.woff2')) ?>" crossorigin>
<link rel="icon" href="<?= e(mg_url('/favicon.ico')) ?>" sizes="32x32">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/core.css')) ?>">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/landing.css')) ?>">
<?= $view->section('head') ?>

<script type="application/ld+json"><?= json_encode(array(
    '@context' => 'https://schema.org',
    '@graph' => array(
        array(
            '@type' => 'SoftwareApplication',
            'name' => $brand,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web, iOS, Android',
            'description' => $seoD,
            'url' => Url::abs('/'),
            'offers' => array(
                '@type' => 'Offer',
                'priceCurrency' => 'GTQ',
                'price' => preg_replace('/[^0-9.]/', '', (string)(Landing::plans() ? Landing::plans()[0]['price'] : '0')) ?: '0',
                'availability' => 'https://schema.org/InStock',
            ),
        ),
        array(
            '@type' => 'Product',
            'name' => $brand . ' · Menú digital QR con pedidos',
            'description' => $seoD,
            'brand' => array('@type' => 'Brand', 'name' => $brand),
            'url' => Url::abs('/'),
        ),
        array(
            '@type' => 'Organization',
            'name' => $brand,
            'url' => Url::abs('/'),
            'email' => Landing::v('contact_email'),
            'telephone' => Landing::v('contact_phone'),
            'address' => array('@type' => 'PostalAddress', 'addressLocality' => Landing::v('contact_city'), 'addressCountry' => 'GT'),
        ),
    ),
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</head>
<body>
<div class="grain" aria-hidden="true"></div>
<a class="skip-link" href="#contenido">Ir al contenido</a>

<header class="nav">
  <div class="shell nav-inner">
    <a class="brand" href="<?= e(mg_url('/')) ?>" data-no-curtain>
      <span class="brand-mark" aria-hidden="true">M</span>
      <span><?= e($brand) ?></span>
    </a>
    <nav class="nav-links" aria-label="Secciones">
      <a href="#experiencia">La experiencia</a>
      <a href="#menu">El menú</a>
      <a href="#pasos">Cómo funciona</a>
      <a href="#planes">Precios</a>
    </nav>
    <div class="nav-cta">
      <a class="btn btn-sm" href="<?= e(mg_url('/demo')) ?>">Ver demo</a>
    </div>
  </div>
</header>

<main id="contenido">
<?= $view->section('content') ?>
</main>

<footer class="footer">
  <div class="shell">
    <div class="footer-grid">
      <div>
        <a class="brand" href="<?= e(mg_url('/')) ?>"><span class="brand-mark" aria-hidden="true">M</span><span><?= e($brand) ?></span></a>
        <p class="muted" style="margin-top:1rem;max-width:34ch;font-size:var(--step--1)"><?= e(Landing::v('seo_description')) ?></p>
      </div>
      <div>
        <h2 class="footer-title">Contacto</h2>
        <ul>
          <li><a href="mailto:<?= e(Landing::v('contact_email')) ?>"><?= e(Landing::v('contact_email')) ?></a></li>
          <li><a href="<?= e(mg_wa(Landing::v('whatsapp'), Landing::v('whatsapp_message'))) ?>" target="_blank" rel="noopener">WhatsApp <?= e(Landing::v('contact_phone')) ?></a></li>
          <li><span class="faint"><?= e(Landing::v('contact_city')) ?></span></li>
        </ul>
      </div>
      <div>
        <h2 class="footer-title">Plataforma</h2>
        <ul>
          <li><a href="<?= e(mg_url('/demo')) ?>">Ver el menú de demostración</a></li>
          <li><a href="#planes">Planes y precios</a></li>
          <li><a href="<?= e(mg_url('/panel/entrar')) ?>">Acceso al panel</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> <?= e($brand) ?>. Hecho en Guatemala.</span>
      <span>Menú digital QR con pedidos · sin comisiones por pedido</span>
    </div>
  </div>
</footer>

<script src="<?= e(mg_asset('assets/js/motion.js')) ?>" defer></script>
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register(<?= json_encode(mg_url('/sw.js')) ?>, { scope: <?= json_encode(Url::basePath() === '' ? '/' : Url::basePath() . '/') ?> }).catch(function () {});
  });
}
</script>
<?= $view->section('scripts') ?>
</body>
</html>
