<?php
/** Layout de pantallas de acceso (login, recuperación). Variables: $content, $title */
$brand = (string) setting('business_name', 'Agenda Premium');
?>
<!doctype html>
<html lang="es" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($title ?? 'Acceso') . ' · ' . $brand) ?></title>
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/core.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<?php if (is_file(APP_ROOT . '/assets/css/admin-p1.css')) : ?><link rel="stylesheet" href="<?= e(asset('css/admin-p1.css')) ?>"><?php endif; ?>
<style nonce="<?= e(nonce()) ?>">:root{--gold-user:<?= e((string) setting('color_gold', '#C9A050')) ?>}</style>
<script src="<?= e(asset('js/theme-init.js')) ?>"></script>
</head>
<body class="auth">
<?= $content ?>
<script src="<?= e(asset('js/ui.js')) ?>" defer></script>
</body>
</html>
