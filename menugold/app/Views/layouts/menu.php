<?php
/**
 * Layout del menú del cliente.
 * @var array $r  restaurante
 */
use MenuGold\Core\App;
use MenuGold\Core\Lang;
use MenuGold\Core\Security;
use MenuGold\Core\View;
use MenuGold\Models\Restaurant;

$r = $r ?? App::restaurant() ?? [];
$nonce  = Security::nonce();
$tema   = (string)($r['tema'] ?? 'negro-oro');
$tipo   = (string)($r['tipografia'] ?? 'clasica');
$acento = (string)($r['color_primario'] ?? '#D4AF37');
$titulo = trim((string)($r['seo_title'] ?? '')) ?: (($r['nombre'] ?? 'Menú') . ' · Menú digital');
$desc   = trim((string)($r['seo_desc'] ?? '')) ?: mb_substr(trim(strip_tags((string)($r['descripcion'] ?? ''))) ?: ('Conoce el menú de ' . ($r['nombre'] ?? '') . ' y pide desde tu mesa.'), 0, 180);
$og     = !empty($r['og_image']) ? uploaded((string)$r['og_image']) : (!empty($r['portada']) ? uploaded((string)$r['portada']) : '');
$urlMenu = Restaurant::urlMenu($r);
$manifestUrl = !empty($r['slug']) ? url('r/' . $r['slug'] . '/manifest.webmanifest') : url('manifest.webmanifest');
$iconoBase = !empty($r['slug']) ? 'r/' . $r['slug'] . '/icono/' : 'icono/';
?><!doctype html>
<html lang="<?= e(Lang::current()) ?>" data-tema="<?= e($tema) ?>" data-tipografia="<?= e($tipo) ?>" style="--acento:<?= e($acento) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($titulo) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="<?= e((string)($r['color_fondo'] ?? '#141414')) ?>">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= e($urlMenu) ?>">

<!-- Aplicación instalable (PWA). El aviso de instalación es el nativo del navegador. -->
<link rel="manifest" href="<?= e($manifestUrl) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e(mb_substr((string)($r['nombre'] ?? 'Menú'), 0, 12)) ?>">
<link rel="apple-touch-icon" href="<?= e(url($iconoBase . '180')) ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?= e(url($iconoBase . '192')) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(url($iconoBase . '32')) ?>">

<!-- Redes sociales -->
<meta property="og:type" content="restaurant.menu">
<meta property="og:site_name" content="<?= e((string)($r['nombre'] ?? '')) ?>">
<meta property="og:title" content="<?= e($titulo) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($urlMenu) ?>">
<?php if ($og): ?><meta property="og:image" content="<?= e($og) ?>">
<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630"><?php endif; ?>
<meta name="twitter:card" content="<?= $og ? 'summary_large_image' : 'summary' ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Inter:wght@400;500;600;700&family=Cormorant+Garamond:wght@400;600&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Inter:wght@400;500;600;700&display=swap"></noscript>

<link rel="stylesheet" href="<?= e(asset('css/temas.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/menu.css')) ?>">
<?= View::section('estilos') ?>

<?php if (!empty($r['id'])): ?>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Restaurant',
    'name'     => (string)$r['nombre'],
    'url'      => $urlMenu,
    'image'    => $og ?: null,
    'description' => $desc,
    'servesCuisine' => (string)($r['eslogan'] ?? ''),
    'priceRange'    => 'QQ',
    'telephone'     => (string)($r['telefono'] ?? ''),
    'address'  => (string)($r['direccion'] ?? '') !== '' ? [
        '@type' => 'PostalAddress',
        'streetAddress' => (string)$r['direccion'],
        'addressCountry' => 'GT',
    ] : null,
    'geo' => (!empty($r['mapa_lat']) && !empty($r['mapa_lng'])) ? [
        '@type' => 'GeoCoordinates',
        'latitude' => (float)$r['mapa_lat'], 'longitude' => (float)$r['mapa_lng'],
    ] : null,
    'hasMenu' => $urlMenu,
    'acceptsReservations' => 'False',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?></script>
<?= View::section('jsonld') ?>
<?php endif; ?>
</head>
<body class="menu">
<a class="saltar-al-contenido" href="#contenido">Saltar al menú</a>

<?= View::section('contenido') ?>

<div class="tostadas" id="tostadas" role="region" aria-live="polite" aria-label="Avisos"></div>

<script nonce="<?= e($nonce) ?>">
window.MG = {
  base: <?= json_encode(App::baseUrl()) ?>,
  slug: <?= json_encode((string)($r['slug'] ?? '')) ?>,
  simbolo: <?= json_encode((string)($r['simbolo'] ?? 'Q')) ?>,
  token: <?= json_encode(csrf_token()) ?>,
  lang: <?= json_encode(Lang::current()) ?>,
  textos: <?= json_encode([
      'agregado'   => 'Agregado a tu pedido',
      'quitado'    => 'Se quitó del pedido',
      'vacio'      => 'Tu pedido está vacío',
      'error'      => 'Algo salió mal. Intenta de nuevo.',
      'obligatorio'=> 'Elige las opciones obligatorias',
      'enviando'   => 'Enviando tu pedido...',
      'confirmar_vaciar' => '¿Seguro que quieres vaciar tu pedido?',
      'cerrado'    => 'El restaurante está cerrado en este momento.',
      'cerrar'     => 'Cerrar',
  ], JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= e(guion('menu')) ?>" defer nonce="<?= e($nonce) ?>"></script>
<?= View::section('scripts') ?>
<script nonce="<?= e($nonce) ?>">
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register(<?= json_encode(url('sw.js')) ?>, { scope: <?= json_encode(App::basePath() === '' ? '/' : App::basePath() . '/') ?> })
      .catch(function () { /* sin conexión: se ignora */ });
  });
}
</script>
</body>
</html>
