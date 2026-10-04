<?php
use Aurea\Core\App;
use Aurea\Core\Brand;
use Aurea\Core\View;
$biz = (string)setting('business_name', 'AUREA');
$title = View::$title !== '' ? View::$title . ' · ' . $biz : ((string)setting('seo_title', '') ?: $biz);
$desc = View::$description !== '' ? View::$description : ((string)setting('seo_description', '') ?: (string)setting('hero_subtitle', ''));
$fav = (string)setting('favicon', '');
$logo = Brand::logoUrl();
$embed = App::$embed;
?>
<!doctype html>
<html lang="es" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if ($embed || View::$robots !== ''): ?><meta name="robots" content="<?= e($embed ? 'noindex' : View::$robots) ?>"><?php endif; ?>
<meta name="theme-color" content="#0B0A08">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="es_GT">
<?php if ($fav !== ''): ?><link rel="icon" href="<?= e(url('uploads/' . rawurlencode($fav))) ?>"><?php else: ?><link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>"><?php endif; ?>
<link rel="preload" href="<?= e(url('assets/fonts/bodoni-moda-latin-500-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(url('assets/fonts/hanken-grotesk-latin-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/public.css')) ?>">
<?php $bc = Brand::css(); if ($bc !== ''): ?><style nonce="<?= e(App::$nonce) ?>"><?= $bc ?></style><?php endif; ?>
<?php foreach (View::$head as $h) { echo $h, "\n"; } ?>
</head>
<body class="<?= $embed ? 'embed ' : '' ?><?= e(implode(' ', View::$bodyClass)) ?>">
<a class="skip" href="#main"><?= e(__('Saltar al contenido')) ?></a>
<?php if (!$embed): ?>
<header class="site-header">
  <div class="wrap in">
    <a class="brand" href="<?= e(url('/')) ?>" aria-label="<?= e($biz) ?>">
      <?php if ($logo !== ''): ?><img src="<?= e($logo) ?>" alt="<?= e($biz) ?>" width="120" height="40"><?php else: ?><span class="mono" aria-hidden="true"><?= e(Brand::initial()) ?></span><span><?= e($biz) ?></span><?php endif; ?>
    </a>
    <button class="menu-btn" type="button" aria-expanded="false" aria-controls="nav" aria-label="<?= e(__('Abrir menú')) ?>" data-menu>
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 5h16M2 10h16M2 15h16"/></svg>
    </button>
    <nav class="nav" id="nav" aria-label="<?= e(__('Principal')) ?>">
      <a href="<?= e(url('/#servicios')) ?>"><?= e(__('Servicios')) ?></a>
      <a href="<?= e(url('/equipo')) ?>"><?= e(term('professionals')) ?></a>
      <a href="<?= e(url('/#contacto')) ?>"><?= e(__('Contacto')) ?></a>
      <a class="btn btn-gold btn-sm" href="<?= e(url('/reservar')) ?>"><?= e(__('Reservar %s', mb_strtolower(term('appt')))) ?></a>
    </nav>
  </div>
</header>
<?php endif; ?>
<main id="main"><?= $content ?></main>
<?php if (!$embed): ?>
<footer class="site-footer">
  <div class="wrap in">
    <div>© <?= e(date('Y')) ?> <?= e($biz) ?></div>
    <div class="links">
      <a href="<?= e(url('/privacidad')) ?>"><?= e(__('Aviso de privacidad')) ?></a>
      <a href="<?= e(url('/terminos')) ?>"><?= e(__('Términos')) ?></a>
      <a href="<?= e(url('/reservar')) ?>"><?= e(__('Reservar')) ?></a>
    </div>
    <div class="social">
      <?php foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube'] as $k => $lbl): $u = \Aurea\Core\Util::safeUrl((string)setting('social_' . $k, '')); if ($u !== ''): ?>
        <a href="<?= e($u) ?>" rel="noopener" target="_blank"><?= e($lbl) ?></a>
      <?php endif; endforeach; ?>
    </div>
  </div>
</footer>
<?php $wa = Brand::waUrl(__('Hola, quisiera información.')); if ($wa !== '' && empty($noFab)): ?>
<a class="wa-fab" href="<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="<?= e(__('Escribir por WhatsApp')) ?>">
  <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm5.2 14.2c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.6-4-4.7-4.2-.1-.2-1.100-1.500-1.100-2.800 0-1.400.7-2 .9-2.300.3-.3.6-.3.800-.3h.600c.2 0 .4 0 .6.500.2.600.8 2 .9 2.100.1.100.1.300 0 .500-.1.200-.1.300-.3.500l-.4.500c-.1.100-.3.300-.1.600.2.300.8 1.300 1.700 2.100 1.200 1 2.100 1.300 2.400 1.500.3.100.5.100.7-.1.200-.3.800-.9 1-1.200.2-.3.400-.2.700-.1l1.900.9c.3.100.5.200.6.300.1.200.1.800-.1 1.400Z"/></svg>
  <span>WhatsApp</span>
</a>
<?php endif; endif; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php foreach ((array)($scripts ?? []) as $s): ?><script src="<?= e(strpos($s, 'http') === 0 ? $s : asset($s)) ?>" defer></script><?php endforeach; ?>
</body>
</html>
