<?php
/**
 * Layout público. Variables: $content, $title (string), $description, $styles (array de rutas en assets/), $scripts (array),
 * $bodyClass, $embed (bool), $noindex (bool), $canonical
 */
$styles = $styles ?? [];
$scripts = $scripts ?? [];
$embed = !empty($embed);
$brand = (string) setting('business_name', 'Agenda Premium');
$fullTitle = isset($title) && $title !== '' ? $title . ' · ' . $brand : $brand;
?>
<!doctype html>
<html lang="es" data-theme="dark"<?= $embed ? ' class="is-embed"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($fullTitle) ?></title>
<?php if (!empty($description)) : ?><meta name="description" content="<?= e($description) ?>"><?php endif; ?>
<?php if (!empty($noindex) || $embed) : ?><meta name="robots" content="noindex"><?php endif; ?>
<meta name="theme-color" content="#06080D">
<meta name="color-scheme" content="dark light">
<link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
<?php $fav = \App\Core\Upload::url((int) setting('favicon_file_id', 0)); ?>
<link rel="icon" href="<?= e($fav !== '' ? $fav : asset('img/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/core.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/public.css')) ?>">
<?php foreach ($styles as $css) : ?><link rel="stylesheet" href="<?= e(asset($css)) ?>">
<?php endforeach; ?>
<style nonce="<?= e(nonce()) ?>">:root{--gold-user:<?= e((string) setting('color_gold', '#C9A050')) ?>}</style>
<script src="<?= e(asset('js/theme-init.js')) ?>"></script>
</head>
<body class="public <?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#main">Saltar al contenido</a>
<?= $content ?>
<?php foreach (array_merge(['js/ui.js'], $scripts) as $js) : ?><script src="<?= e(asset($js)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
