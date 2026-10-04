<?php /** @var int $status @var string $title @var string $message */ ?>
<!doctype html>
<html lang="es" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/core.css')) ?>">
<script src="<?= e(asset('js/theme-init.js')) ?>"></script>
</head>
<body class="page-error">
<main class="error-screen" id="main">
  <p class="error-code mono"><?= e((string) $status) ?></p>
  <h1 class="serif"><?= e($title) ?></h1>
  <p class="muted"><?= e($message) ?></p>
  <p><a class="btn btn-gold" href="<?= e(url('/')) ?>">Volver al inicio</a></p>
</main>
</body>
</html>
