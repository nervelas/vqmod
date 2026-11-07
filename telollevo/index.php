<?php
/**
 * Te Lo Llevo - Página principal.
 */
define('TLL_DEPTH', 0);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/render.php';
security_headers();

$C = get_content();
$S = $C['site'];
$layout = $C['layout'];
$title = $S['meta_title'] !== '' ? $S['meta_title'] : $S['brand'];
$share = media_url($S['share_image']);
$rates = $C['rates'];
$boot = [
    'wa'       => preg_replace('/\D/', '', $C['contact']['whatsapp']),
    'waMsg'    => $C['contact']['whatsapp_msg'],
    'currency' => $rates['currency'],
    'distances'=> $rates['distances'],
    'sizes'    => $rates['sizes'],
    'speeds'   => $rates['speeds'],
    'tokenUrl' => url('contact.php?token=1'),
    'brand'    => $S['brand'],
];
header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($S['meta_description']) ?>">
<meta name="theme-color" content="<?= e($C['theme']['navy']) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($S['meta_description']) ?>">
<?php if ($share): ?><meta property="og:image" content="<?= e(site_origin() . $share) ?>"><?php endif; ?>
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('img/favicon-32.png')) ?>">
<link rel="icon" type="image/png" sizes="64x64" href="<?= e(asset('img/favicon.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('img/apple-touch-icon.png')) ?>">
<link rel="preload" href="<?= e(url('assets/fonts/outfit-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(url('assets/fonts/plus-jakarta-sans-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<style><?= theme_css($C['theme']) ?></style>
</head>
<body>
<a class="skip" href="#main">Saltar al contenido</a>
<?= render_header($C) ?>
<main id="main">
<?php
foreach ($layout['order'] as $id) {
    if (!empty($layout['enabled'][$id])) echo render_section($id, $C);
}
?>
</main>
<?= render_footer($C) ?>
<?= render_floating($C) ?>
<script type="application/json" id="tll-data"><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= e(asset('js/site.js')) ?>" defer></script>
</body>
</html>
