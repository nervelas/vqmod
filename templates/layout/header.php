<?php
/** Public layout header. */
if (!defined('BASE_PATH')) { exit; }

$menu       = Content::menu();
$siteName   = Settings::get('site_name', 'Fuente de Vida');
$shortName  = Settings::get('site_short_name', $siteName);
$logo       = Settings::get('logo', 'assets/img/logo.svg');
$favicon    = Settings::get('favicon', 'assets/img/favicon.svg');
$primary    = Settings::get('color_primary', '#0f5a3c');
$secondary  = Settings::get('color_secondary', '#f6a800');
$dark       = Settings::get('color_dark', '#0b3d2a');

// SEO values (page overrides fall back to global defaults).
$pg = $page ?? [];
$seoTitle = $pg['seo_title'] ?? '';
if ($seoTitle === '') { $seoTitle = ($pg['title'] ?? '') !== '' ? $pg['title'] . ' | ' . $shortName : Settings::get('seo_default_title', $siteName); }
$seoDesc  = $pg['seo_description'] ?? '';
if ($seoDesc === '') { $seoDesc = Settings::get('seo_default_description'); }
$ogTitle  = ($pg['og_title'] ?? '') ?: $seoTitle;
$ogDesc   = ($pg['og_description'] ?? '') ?: $seoDesc;
$ogImage  = ($pg['og_image'] ?? '') ?: Settings::get('seo_og_image', 'assets/img/og-default.jpg');
$canonical = ($pg['seo_canonical'] ?? '') ?: base_url(current_slug());
$activeSlug = trim(current_slug(), '/') ?: 'inicio';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($seoTitle) ?></title>
<meta name="description" content="<?= e($seoDesc) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta name="theme-color" content="<?= e($primary) ?>">
<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($ogTitle) ?>">
<meta property="og:description" content="<?= e($ogDesc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e(asset_url($ogImage)) ?>">
<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($ogTitle) ?>">
<meta name="twitter:description" content="<?= e($ogDesc) ?>">
<meta name="twitter:image" content="<?= e(asset_url($ogImage)) ?>">
<link rel="icon" href="<?= e(asset_url($favicon)) ?>">
<link rel="apple-touch-icon" href="<?= e(asset_url($favicon)) ?>">
<link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>?v=1">
<style>
:root{
  --c-primary: <?= e($primary) ?>;
  --c-secondary: <?= e($secondary) ?>;
  --c-dark: <?= e($dark) ?>;
}
</style>
<script type="application/ld+json">
<?= json_encode([
  '@context' => 'https://schema.org',
  '@type' => 'EducationalOrganization',
  'name' => $siteName,
  'url' => base_url(),
  'logo' => asset_url($logo),
  'email' => Settings::get('email'),
  'telephone' => '+502 ' . Settings::get('phone'),
  'address' => [
    '@type' => 'PostalAddress',
    'streetAddress' => Settings::get('address'),
    'addressCountry' => 'GT',
  ],
  'sameAs' => array_values(array_filter([Settings::get('facebook'), Settings::get('instagram'), Settings::get('tiktok'), Settings::get('youtube')])),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<?= Settings::raw('analytics_head') /* trusted admin-entered snippet */ ?>
</head>
<body class="page-<?= e($activeSlug) ?>">
<a class="skip-link" href="#main">Saltar al contenido</a>

<!-- Top bar -->
<div class="topbar">
  <div class="container topbar__inner">
    <div class="topbar__contact">
      <a href="tel:+502<?= e(Settings::get('phone_link', '50222775656')) ?>"><span class="i i-phone"></span> <?= e(Settings::get('phone')) ?></a>
      <a href="mailto:<?= e(Settings::get('email')) ?>"><span class="i i-mail"></span> <?= e(Settings::get('email')) ?></a>
    </div>
    <div class="topbar__social">
      <?php foreach (['facebook'=>'Facebook','instagram'=>'Instagram','tiktok'=>'TikTok','youtube'=>'YouTube'] as $net=>$label):
        $u = Settings::get($net); if ($u==='') continue; ?>
        <a href="<?= e($u) ?>" target="_blank" rel="noopener" aria-label="<?= e($label) ?>" class="i i-<?= e($net) ?>"></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Header / navigation -->
<header class="site-header" id="siteHeader">
  <div class="container site-header__inner">
    <a class="brand" href="<?= e(base_url()) ?>">
      <img src="<?= e(asset_url($logo)) ?>" alt="<?= e($siteName) ?>" class="brand__logo">
      <span class="brand__name"><?= e($shortName) ?></span>
    </a>
    <button class="nav-toggle" id="navToggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="mainNav">
      <span></span><span></span><span></span>
    </button>
    <nav class="main-nav" id="mainNav" aria-label="Menú principal">
      <ul>
        <?php foreach ($menu as $m):
          $isActive = trim($m['url'],'/') === $activeSlug; ?>
          <li>
            <a href="<?= e(Content::url($m['url'])) ?>"
               <?= $m['target']==='_blank' ? 'target="_blank" rel="noopener"' : '' ?>
               class="<?= $isActive ? 'is-active' : '' ?>"><?= e($m['label']) ?></a>
          </li>
        <?php endforeach; ?>
        <li class="main-nav__cta">
          <a href="<?= e(base_url('admisiones')) ?>" class="btn btn--secondary btn--sm">Admisiones</a>
        </li>
      </ul>
    </nav>
  </div>
</header>

<main id="main">
<?php foreach (take_flashes() as $f): ?>
  <div class="flash flash--<?= e($f['type']) ?>"><div class="container"><?= e($f['message']) ?></div></div>
<?php endforeach; ?>
