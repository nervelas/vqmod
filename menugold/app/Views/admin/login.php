<?php
/** Acceso al panel. */
use MenuGold\Core\Csrf;
?><!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Acceso · MenúGold</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0C0B09">
<link rel="icon" href="<?= e(mg_url('/assets/icons/icon-192.png')) ?>" sizes="192x192">
<link rel="icon" href="<?= e(mg_url('/favicon.ico')) ?>" sizes="32x32">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/core.css')) ?>">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/panel.css')) ?>">
</head>
<body class="panel-body" data-curtain="off">
<div class="grain" aria-hidden="true"></div>
<main class="login-screen">
  <div class="login-bg"><span class="ph-img" style="height:100%"></span></div>
  <div class="login-veil" aria-hidden="true"></div>

  <div class="login-card">
    <p class="eyebrow" style="margin-bottom:1.4rem">MenúGold</p>
    <h1>Entra a tu panel</h1>
    <p>Gestiona tu menú, tus pedidos y tus números.</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error" role="alert"><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <form method="post" action="<?= e(mg_url('/panel/entrar')) ?>" autocomplete="on">
      <?= Csrf::field() ?>
      <div class="field">
        <label for="username">Usuario o correo</label>
        <input type="text" class="input" id="username" name="username" autocomplete="username" required autofocus
               value="<?= e(isset($_POST['username']) ? $_POST['username'] : '') ?>">
      </div>
      <div class="field">
        <label for="password">Contraseña</label>
        <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
      </div>
      <button class="btn btn-block" type="submit">Entrar</button>
    </form>

    <p style="margin-top:1.6rem;text-align:center;font-size:12px">
      <a class="link-line faint" href="<?= e(mg_url('/panel/pin')) ?>">Entrar con PIN (mesero o cocina)</a>
    </p>
    <p style="margin-top:.8rem;text-align:center;font-size:12px">
      <a class="link-line faint" href="<?= e(mg_url('/')) ?>">Volver al sitio</a>
    </p>
  </div>
</main>
</body></html>
