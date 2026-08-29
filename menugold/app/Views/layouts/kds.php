<?php
use MenuGold\Core\App;
use MenuGold\Core\Security;
use MenuGold\Core\View;
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(View::section('titulo', 'Cocina')) ?> · <?= e((string)($r['nombre'] ?? '')) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#101113">
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<link rel="apple-touch-icon" href="<?= e(url('icono/180')) ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?= e(url('icono/192')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" media="print" onload="this.media='all'">
<link rel="stylesheet" href="<?= e(asset('css/temas.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/panel.css')) ?>">
</head>
<body class="kds">
<?= View::section('contenido') ?>
<div class="tostadas-p" id="tostadasP" role="region" aria-live="polite"></div>
<script nonce="<?= e(Security::nonce()) ?>">
window.MGP = { base: <?= json_encode(App::baseUrl()) ?>, token: <?= json_encode(csrf_token()) ?>,
               simbolo: <?= json_encode((string)($r['simbolo'] ?? 'Q')) ?> };
</script>
<script src="<?= e(guion('panel')) ?>" nonce="<?= e(Security::nonce()) ?>"></script>
<?= View::section('scripts') ?>
</body>
</html>
