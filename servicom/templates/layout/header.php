<?php
declare(strict_types=1);
/** @var string $view */

$theme      = Theme::active();
$siteName   = Settings::get('site_name', 'Servicom');
$menu       = Content::menu();
$headerMenu = array_values(array_filter($menu, static fn($m) => $m['location'] === 'header'));
$phone      = Settings::get('phone');
$waLink     = whatsapp_link(Settings::get('whatsapp', $phone), Settings::get('whatsapp_message'));
$logo       = Theme::mode() === 'dark'
    ? Settings::get('logo_light', Settings::get('logo'))
    : Settings::get('logo', Settings::get('logo_light'));
$fonts      = Theme::googleFontsUrl();
$currentUri = trim(strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/', '/');
if (BASE_PATH !== '') {
    $currentUri = trim(substr('/' . $currentUri, strlen(BASE_PATH)), '/');
}

/** Marca el enlace activo del menu. */
$isActive = static function (string $url) use ($currentUri): bool {
    $url = trim((string) parse_url($url, PHP_URL_PATH), '/');
    if ($url === '' ) {
        return $currentUri === '';
    }
    return $currentUri === $url || str_starts_with($currentUri, $url . '/');
};

/** Resuelve destinos especiales de los botones. */
$resolveUrl = static function (string $url) use ($waLink, $phone): string {
    if ($url === 'whatsapp') {
        return $waLink;
    }
    if ($url === 'tel') {
        return 'tel:+' . digits($phone);
    }
    if (preg_match('~^(https?://|mailto:|tel:|\#)~i', $url) === 1) {
        return $url;
    }
    return base(ltrim($url, '/'));
};
?><!DOCTYPE html>
<html lang="es-GT" data-theme="<?= e($theme['theme_key'] ?? 'obsidiana') ?>" data-mode="<?= e(Theme::mode()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="<?= e($theme['palette']['bg'] ?? '#05070a') ?>">
<?php if (($gv = Settings::get('google_verification')) !== ''): ?>
<meta name="google-site-verification" content="<?= e($gv) ?>">
<?php endif; ?>
<?= Seo::render() ?>
<link rel="icon" href="<?= e(asset_url(Settings::get('favicon', 'assets/img/favicon.svg'))) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(asset_url(Settings::get('favicon', 'assets/img/favicon.svg'))) ?>">
<link rel="alternate" hreflang="es-gt" href="<?= e(Seo::canonical()) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e(Seo::canonical()) ?>">
<?php if ($fonts !== ''): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="<?= e($fonts) ?>">
<link rel="stylesheet" href="<?= e($fonts) ?>" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="<?= e($fonts) ?>"></noscript>
<?php endif; ?>
<style><?= Theme::cssVariables() ?></style>
<link rel="stylesheet" href="<?= e(base('assets/css/app.css?v=1.0.0')) ?>">
</head>
<body class="view-<?= e($view) ?><?= Settings::bool('fx_grain', true) ? '' : ' no-fx' ?>">

<?= icon_sprite() ?>

<a class="skip-link" href="#contenido">Ir al contenido principal</a>

<?php if (Settings::bool('fx_preloader', true)): ?>
<div class="preloader" aria-hidden="true">
  <div class="preloader__mark">
    <img src="<?= e(asset_url($logo)) ?>" alt="" width="200" height="40" style="height:40px;width:auto">
    <div class="preloader__bar"><i></i></div>
    <div class="preloader__word">Cargando</div>
  </div>
</div>
<?php endif; ?>

<div class="scroll-progress" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"><span></span><span></span><span></span></div>
<div class="grain" aria-hidden="true"></div>
<?php if (Settings::bool('fx_cursor', true)): ?>
<div class="cursor-dot" aria-hidden="true"></div>
<div class="cursor-ring" aria-hidden="true"><i></i></div>
<?php endif; ?>

<header class="header" id="inicio-header">
  <div class="wrap wrap-wide header__in">
    <a class="brand" href="<?= e(base('')) ?>" aria-label="<?= e($siteName) ?> — inicio">
      <?php if ($logo !== ''): ?>
        <img src="<?= e(asset_url($logo)) ?>" alt="<?= e($siteName) ?> — <?= e(Settings::get('site_tagline')) ?>" width="220" height="44">
      <?php else: ?>
        <span class="brand__dot"></span><?= e($siteName) ?>
      <?php endif; ?>
    </a>

    <nav class="nav" aria-label="Navegación principal">
      <?php foreach ($headerMenu as $item):
          if ((int) $item['is_button'] === 1) { continue; } ?>
        <a class="nav__link<?= $isActive((string) $item['url']) ? ' is-active' : '' ?>"
           href="<?= e($resolveUrl((string) $item['url'])) ?>"
           <?= $item['target'] === '_blank' ? 'target="_blank" rel="noopener"' : '' ?>>
          <?= icon((string) $item['icon'], 18) ?><span><?= e($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="header__tools">
      <?php foreach ($headerMenu as $item):
          if ((int) $item['is_button'] !== 1) { continue; } ?>
        <a class="btn btn--sm btn--cta" data-magnetic=".2" href="<?= e($resolveUrl((string) $item['url'])) ?>">
          <?= icon((string) $item['icon'], 17) ?><span><?= e($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
      <button class="burger" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="menu-movil">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<nav class="mobile-nav" id="menu-movil" aria-hidden="true" aria-label="Navegación móvil">
  <ul class="mobile-nav__list">
    <?php foreach ($headerMenu as $i => $item): ?>
      <li><a class="mobile-nav__link" style="--i:<?= (int) $i ?>" href="<?= e($resolveUrl((string) $item['url'])) ?>">
        <?= icon((string) $item['icon'], 24) ?><span><?= e($item['label']) ?></span>
      </a></li>
    <?php endforeach; ?>
  </ul>
  <div class="mobile-nav__foot">
    <div class="mobile-nav__contact">
      <?php if ($phone !== ''): ?><a href="tel:+<?= e(digits($phone)) ?>"><?= icon('telefono', 16) ?><?= e($phone) ?></a><?php endif; ?>
      <?php if (($mail = Settings::get('email')) !== ''): ?><a href="mailto:<?= e($mail) ?>"><?= icon('contacto', 16) ?><?= e($mail) ?></a><?php endif; ?>
    </div>
    <a class="btn btn--wa btn--block" href="<?= e($waLink) ?>" target="_blank" rel="noopener">
      <?= icon('whatsapp', 18) ?><span>Escribir por WhatsApp</span>
    </a>
  </div>
</nav>

<main id="contenido">
