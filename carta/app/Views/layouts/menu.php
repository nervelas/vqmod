<?php
/** Layout del menú del comensal. @var \MenuGold\Core\View $view */
use MenuGold\Core\Lang;
use MenuGold\Core\Theme;
use MenuGold\Core\Url;
use MenuGold\Controllers\Admin\SettingsController;

$r = $cfg;
$combo = isset(SettingsController::$fontCombos[$r['font_combo']])
    ? SettingsController::$fontCombos[$r['font_combo']]
    : SettingsController::$fontCombos['editorial'];
$title = $view->section('title', $r['name'] . ' · Menú');
$desc  = $view->section('description', $r['tagline'] !== '' ? $r['tagline'] : ('Menú digital de ' . $r['name'] . '. Pide desde tu mesa escaneando el QR.'));
$og    = $r['cover'] !== '' ? mg_img_src($r['cover'], 1600) : '';
$canonical = $view->section('canonical', Url::abs('/'));
$tema = Theme::uno(isset($r['theme']) ? $r['theme'] : Theme::PREDETERMINADO);
$temaCss = Theme::css(isset($r['theme']) ? $r['theme'] : Theme::PREDETERMINADO,
                      $r['primary_color'], $r['accent_color']);
?><!DOCTYPE html>
<html lang="<?= e(Lang::locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<script>document.documentElement.classList.add('js');</script>
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="<?= e($tema['ink']) ?>">
<meta name="color-scheme" content="<?= $tema['modo'] === 'claro' ? 'light' : 'dark' ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:type" content="restaurant.menu">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($og !== ''): ?><meta property="og:image" content="<?= e($og) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">

<link rel="manifest" href="<?= e(mg_url('/manifest.webmanifest')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e(mb_substr($r['name'], 0, 12)) ?>">
<?php
$iconDir = '/uploads/icons/icon-192.png';
$icon = is_file(MG_ROOT . $iconDir) ? mg_url($iconDir) : mg_url('/assets/icons/icon-192.png');
?>
<link rel="apple-touch-icon" href="<?= e($icon) ?>">
<link rel="icon" href="<?= e($icon) ?>" sizes="192x192">

<link rel="preload" as="font" type="font/woff2" href="<?= e(mg_url('/assets/fonts/fraunces-normal-latin.woff2')) ?>" crossorigin>
<?php
// La portada es el elemento más grande de la primera pantalla: se precarga
// para que el navegador la pida antes de descubrirla en el HTML.
if ($r['cover'] !== '' && \MenuGold\Core\Image::exists($r['cover'])):
    $coverBase = mg_url('/' . ltrim($r['cover'], '/'));
    $webp = function_exists('imagewebp') && is_file(MG_ROOT . '/' . $r['cover'] . '-960.webp');
?>
<link rel="preload" as="image" fetchpriority="high"
      imagesrcset="<?= e($coverBase . '-480.' . ($webp ? 'webp' : 'jpg') . ' 480w, ' . $coverBase . '-960.' . ($webp ? 'webp' : 'jpg') . ' 960w, ' . $coverBase . '-1600.' . ($webp ? 'webp' : 'jpg') . ' 1600w') ?>"
      imagesizes="100vw"
      href="<?= e($coverBase . '-960.' . ($webp ? 'webp' : 'jpg')) ?>"
      type="<?= $webp ? 'image/webp' : 'image/jpeg' ?>">
<?php endif; ?>
<link rel="icon" href="<?= e(mg_url('/favicon.ico')) ?>" sizes="32x32">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/core.css')) ?>">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/menu.css')) ?>">
<style>
  :root{
    <?= $temaCss ?>

    --font-display: "<?= e($combo['display']) ?>", Georgia, serif;
    --font-ui: "<?= e($combo['ui']) ?>", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  }
</style>
<?= $view->section('head') ?>

<script type="application/ld+json"><?= json_encode(array(
    '@context' => 'https://schema.org',
    '@type' => 'Restaurant',
    'name' => $r['name'],
    'description' => $desc,
    'url' => Url::abs('/'),
    'servesCuisine' => $r['tagline'],
    'telephone' => $r['phone'],
    'priceRange' => '$$',
    'address' => array('@type' => 'PostalAddress', 'streetAddress' => $r['address'], 'addressLocality' => $r['city'], 'addressCountry' => 'GT'),
    'hasMenu' => Url::abs('/'),
    'image' => $og !== '' ? $og : null,
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</head>
<body class="menu-body" data-curtain="off">
<div class="grain" aria-hidden="true"></div>
<a class="skip-link" href="#contenido"><?= e(Lang::get('menu.view_menu')) ?></a>

<main id="contenido">
<?= $view->section('content') ?>
</main>

<script>window.MG_MENU = <?= json_encode(array(
    // Cadena para concatenar rutas en JS: vacía en la raíz del dominio,
    // o el subdirectorio si la instalación no está en la raíz.
    'base'     => Url::basePath(),
    'root'     => Url::basePath(),
    'currency' => $r['currency'],
    'csrf'     => \MenuGold\Core\Csrf::token(),
    'lang'     => Lang::locale(),
    'modes'    => isset($modes) ? array_values($modes) : array('dine_in'),
    'orderMode'=> $r['order_mode'],
    'table'    => isset($table) && $table ? array('id' => (int)$table['id'], 'name' => $table['name']) : null,
    'zones'    => isset($zones) ? array_map(function ($z) {
                      return array('id' => (int)$z['id'], 'name' => $z['name'], 'fee' => (float)$z['fee'],
                                   'min_order' => (float)$z['min_total'], 'eta' => (int)$z['minutes']);
                  }, $zones) : array(),
    'tip'      => $r['tip_enabled'] === '1',
    'tipOptions' => array_values(array_filter(array_map('intval', explode(',', $r['tip_options'])))),
    'i18n'     => array(
        'add'         => Lang::get('menu.add'),
        'cart'        => Lang::get('menu.cart'),
        'emptyCart'   => Lang::get('menu.empty_cart'),
        'checkout'    => Lang::get('menu.checkout'),
        'subtotal'    => Lang::get('menu.subtotal'),
        'total'       => Lang::get('menu.total'),
        'tip'         => Lang::get('menu.tip'),
        'delivery'    => Lang::get('menu.delivery'),
        'discount'    => Lang::get('menu.discount'),
        'notes'       => Lang::get('menu.notes'),
        'required'    => Lang::get('menu.required'),
        'chooseUpTo'  => Lang::get('menu.choose_up_to'),
    ),
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= e(mg_asset('assets/js/motion.js')) ?>" defer></script>
<script src="<?= e(mg_asset('assets/js/menu.js')) ?>" defer></script>
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
