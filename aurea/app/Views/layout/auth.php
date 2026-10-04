<?php use Aurea\Core\App; use Aurea\Core\Brand; $biz = (string)setting('business_name', 'AUREA'); ?>
<!doctype html>
<html lang="es" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e(__('Ingresar')) ?> · <?= e($biz) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/public.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<?php $bc = Brand::css(); if ($bc !== ''): ?><style nonce="<?= e(App::$nonce) ?>"><?= $bc ?></style><?php endif; ?>
</head>
<body class="admin">
<div class="auth">
  <div class="art grain"><?php include __DIR__ . '/../partials/hero_art.php'; ?></div>
  <div class="box-in"><main class="card" id="main">
    <?php foreach (($_SESSION['flash'] ?? []) as [$ft, $fm]): ?><div class="flash <?= e($ft) ?>" role="status"><?= e($fm) ?></div><?php endforeach; unset($_SESSION['flash']); ?>
    <?= $content ?>
  </main></div>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
