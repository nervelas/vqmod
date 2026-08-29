<?php
/** @var array|null $company */
/** @var string|null $title */
$theme  = $theme ?? ['accent' => '#E8590C', 'ink' => '#1C1F22', 'paper' => '#F5F6F4'];
$pTitle = $title ?? ($appName ?? 'CotizaPro B2B');
$desc   = $description ?? 'Catálogo técnico, cotizador en línea y seguimiento de cotizaciones.';
$ogImg  = $ogImage ?? (isset($company['og_image']) && $company['og_image'] ? upload($company['og_image']) : (isset($company['hero_image']) && $company['hero_image'] ? upload($company['hero_image']) : absUrl('/assets/img/industry/og-default.jpg')));
$noindex = $noindex ?? false;
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pTitle) ?></title>
<meta name="description" content="<?= e(str_limit($desc, 158)) ?>">
<?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php else: ?><meta name="robots" content="index, follow, max-image-preview:large"><?php endif; ?>
<link rel="canonical" href="<?= e(absUrl(\App\Core\Request::path())) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($company['name'] ?? ($appName ?? 'CotizaPro B2B')) ?>">
<meta property="og:title" content="<?= e($pTitle) ?>">
<meta property="og:description" content="<?= e(str_limit($desc, 158)) ?>">
<meta property="og:url" content="<?= e(absUrl(\App\Core\Request::path())) ?>">
<meta property="og:locale" content="es_GT">
<meta property="og:image" content="<?= e($ogImg) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="<?= e($theme['ink']) ?>">
<meta name="color-scheme" content="light">
<meta name="format-detection" content="telephone=no">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e(mb_substr((string) ($company['name'] ?? ($appName ?? 'CotizaPro')), 0, 14)) ?>">
<meta name="mobile-web-app-capable" content="yes">
<link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
<link rel="apple-touch-icon" href="<?= e(url('/assets/img/icons/icon-192.png')) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(url('/assets/img/icons/icon-72.png')) ?>">
<link rel="preload" as="font" type="font/woff2" href="<?= e(url('/assets/fonts/Inter-400.woff2')) ?>" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="<?= e(url('/assets/fonts/SpaceGrotesk-700.woff2')) ?>" crossorigin>
<?php if (!empty($preloadImage)): ?>
<link rel="preload" as="image" href="<?= e($preloadImage['src']) ?>"<?= !empty($preloadImage['srcset']) ? ' imagesrcset="' . e($preloadImage['srcset']) . '" imagesizes="100vw"' : '' ?> fetchpriority="high">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php if (!empty($withPanelCss)): ?><link rel="stylesheet" href="<?= e(asset('css/panel.css')) ?>"><?php endif; ?>
<?php
$rgbA = \App\Core\Img::hex2rgb($theme['accent']);
$rgbP = \App\Core\Img::hex2rgb($theme['paper']);
$rgbI = \App\Core\Img::hex2rgb($theme['ink']);
// Texto sobre el acento: se elige tinta o blanco según el contraste real.
[$btnBg, $btnFg] = \App\Core\Color::accessiblePair($rgbA, $rgbI);
// Acento legible como texto pequeño sobre el fondo claro.
$rgbP3 = [$rgbP[0] * .925, $rgbP[1] * .925, $rgbP[2] * .925];
$accText = \App\Core\Color::darkenUntil($rgbA, $rgbP3, 4.6);
$shade = static function (array $rgb, float $f): string {
    return sprintf('#%02X%02X%02X', (int) max(0, min(255, $rgb[0] * $f)), (int) max(0, min(255, $rgb[1] * $f)), (int) max(0, min(255, $rgb[2] * $f)));
};
?>
<style nonce="<?= e($nonce ?? '') ?>">:root{
--accent:<?= e($theme['accent']) ?>;
--ink:<?= e($theme['ink']) ?>;
--paper:<?= e($theme['paper']) ?>;
--paper-2:<?= e($shade($rgbP, .965)) ?>;
--paper-3:<?= e($shade($rgbP, .925)) ?>;
--line:<?= e($shade($rgbP, .875)) ?>;
--line-strong:<?= e($shade($rgbP, .79)) ?>;
--accent-wash:rgba(<?= (int) $rgbA[0] ?>,<?= (int) $rgbA[1] ?>,<?= (int) $rgbA[2] ?>,.09);
--accent-btn:<?= e(\App\Core\Color::hex($btnBg)) ?>;
--accent-ink:<?= e(\App\Core\Color::hex($btnFg)) ?>;
--accent-text:<?= e(\App\Core\Color::hex($accText)) ?>;
}</style>
<script nonce="<?= e($nonce ?? '') ?>">document.documentElement.className+=' js';</script>
