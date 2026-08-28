<?php
/** @var string $titulo */
use App\Core\Ajustes;
use App\Core\Auth;
use App\Core\Url;

$u          = Auth::usuario();
$tema       = $u['tema'] ?? Ajustes::get('tema', 'verde-oro');
$modo       = ($u['modo_oscuro'] ?? 0) ? 'oscuro' : 'claro';
$nombreCond = Ajustes::get('nombre', 'ResidencialPro');
$colorMarca = Ajustes::get('color_primario', '#0E4C5A');
$titulo     = $titulo ?? $nombreCond;
$descripcion = $descripcion ?? Ajustes::get('descripcion', 'Administración integral del residencial: cuotas, visitas, áreas comunes y comunicación con los residentes.');
$logo       = Ajustes::get('logo', '');
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($titulo) ?><?= $titulo !== $nombreCond ? ' · ' . e($nombreCond) : '' ?></title>
<meta name="description" content="<?= e(recortar($descripcion, 160)) ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="theme-color" content="<?= e($modo === 'oscuro' ? '#0E1315' : $colorMarca) ?>">
<meta name="color-scheme" content="light dark">
<meta name="robots" content="<?= e($indexable ?? false ? 'index, follow' : 'noindex, nofollow') ?>">

<link rel="manifest" href="<?= e(url('/manifest.json')) ?>">
<link rel="icon" type="image/png" href="<?= e(url('/assets/img/favicon.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(url('/assets/img/icono-180.png')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e(mb_substr($nombreCond, 0, 22)) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="<?= e(mb_substr($nombreCond, 0, 22)) ?>">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($nombreCond) ?>">
<meta property="og:title" content="<?= e($titulo) ?>">
<meta property="og:description" content="<?= e(recortar($descripcion, 160)) ?>">
<?php if ($logo !== ''): ?>
<meta property="og:image" content="<?= e(Url::absoluta('/uploads/logos/' . $logo)) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">

<?php if (!empty($precargarPortada)): ?>
<link rel="preload" as="image" fetchpriority="high" type="image/webp"
      href="<?= e(url('/assets/img/sitio/portada-900.webp')) ?>"
      imagesrcset="<?= e(url('/assets/img/sitio/portada-700.webp')) ?> 700w, <?= e(url('/assets/img/sitio/portada-900.webp')) ?> 900w, <?= e(url('/assets/img/sitio/portada-1200.webp')) ?> 1200w, <?= e(url('/assets/img/sitio/portada.webp')) ?> 1800w"
      imagesizes="100vw">
<?php endif; ?>
<?php if (!empty($precargarFuentes)): /* Solo en la primera visita pública: después las fuentes ya están en caché. */ ?>
<link rel="preload" as="font" type="font/woff2" crossorigin href="<?= e(url('/assets/fonts/archivo-variable-latin.woff2')) ?>">
<link rel="preload" as="font" type="font/woff2" crossorigin href="<?= e(url('/assets/fonts/fraunces-variable-latin.woff2')) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(url('/assets/css/fuentes-locales.css')) ?>">
<link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>?v=<?= RPRO_VERSION ?>">
<script<?= nonce() ?>>
  (function () {
    var r = document.documentElement;
    r.className += (r.className ? ' ' : '') + 'js';
    try {
      var t = localStorage.getItem('rp_tema');
      var m = localStorage.getItem('rp_modo');
      if (t) r.dataset.tema = t;
      if (m) r.dataset.modo = m;
    } catch (e) {}
  })();
</script>
