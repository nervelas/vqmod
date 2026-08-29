<?php
/** Acceso rápido con PIN. */
use MenuGold\Core\Csrf;
?><!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>PIN · MenúGold</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e(mg_url('/assets/icons/icon-192.png')) ?>" sizes="192x192">
<link rel="icon" href="<?= e(mg_url('/favicon.ico')) ?>" sizes="32x32">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/core.css')) ?>">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/panel.css')) ?>">
</head>
<body class="panel-body" data-curtain="off">
<div class="grain" aria-hidden="true"></div>
<main class="login-screen">
  <div class="login-veil" aria-hidden="true"></div>
  <div class="login-card">
    <p class="eyebrow" style="margin-bottom:1.4rem">Turno</p>
    <h1>Tu PIN</h1>
    <p>Para meseros y cocina, en la tablet del salón.</p>
    <?php if (!empty($error)): ?><div class="alert alert-error" role="alert"><span><?= e($error) ?></span></div><?php endif; ?>
    <form method="post" action="<?= e(mg_url('/panel/pin')) ?>">
      <?= Csrf::field() ?>
      <div class="field">
        <label for="pin">PIN</label>
        <input class="input" id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="8"
               autocomplete="one-time-code" required autofocus style="text-align:center;letter-spacing:.6em;font-size:1.4rem">
      </div>
      <button class="btn btn-block" type="submit">Entrar</button>
    </form>
    <p style="margin-top:1.6rem;text-align:center;font-size:12px">
      <a class="link-line faint" href="<?= e(mg_url('/panel/entrar')) ?>">Entrar con usuario y contraseña</a>
    </p>
  </div>
</main>
</body></html>
