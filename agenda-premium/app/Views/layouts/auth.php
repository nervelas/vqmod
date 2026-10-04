<?php
/** Layout de pantallas de acceso (login, 2FA, recuperación, invitación). Variables: $content, $title */
use App\Core\Session;

$brand = (string) setting('business_name', 'Agenda Premium');
$logo = \App\Core\Upload::url((int) setting('logo_file_id', 0));
$flash = Session::started() ? Session::pullFlash() : [];
?>
<!doctype html>
<html lang="es" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="base-path" content="<?= e(BASE_PATH) ?>">
<meta name="theme-color" content="#06080D">
<title><?= e(($title ?? 'Acceso') . ' · ' . $brand) ?></title>
<link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/core.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<?php if (is_file(APP_ROOT . '/assets/css/admin-p1.css')) : ?><link rel="stylesheet" href="<?= e(asset('css/admin-p1.css')) ?>">
<?php endif; ?>
<style nonce="<?= e(nonce()) ?>">:root{--gold-user:<?= e((string) setting('color_gold', '#C9A050')) ?>}</style>
<script src="<?= e(asset('js/theme-init.js')) ?>"></script>
</head>
<body class="auth">
<a class="skip-link" href="#main">Saltar al contenido</a>
<div class="auth-stage" aria-hidden="true"></div>
<main class="auth-shell" id="main">
  <a class="auth-brand" href="<?= e(url('/')) ?>">
    <?php if ($logo !== '') : ?><img class="auth-logo" src="<?= e($logo) ?>" alt="" width="44" height="44"><?php else : ?><span class="auth-mark"><?= icon('sparkle') ?></span><?php endif; ?>
    <span class="serif auth-name"><?= e($brand) ?></span>
  </a>
  <section class="auth-card">
    <?php foreach ($flash as $f) : ?>
      <div class="alert alert-<?= e($f['type'] === 'error' ? 'err' : ($f['type'] === 'success' ? 'ok' : $f['type'])) ?>" role="status"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </section>
  <p class="auth-foot muted">Panel privado · <?= e($brand) ?></p>
</main>
<script src="<?= e(asset('js/ui.js')) ?>" defer></script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
