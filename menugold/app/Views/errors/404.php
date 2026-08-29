<?php
/** Página no encontrada */
?><!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Aquí no hay nada servido</title>
<meta name="robots" content="noindex">
<link rel="icon" href="<?= e(mg_url('/assets/icons/icon-192.png')) ?>" sizes="192x192">
<link rel="icon" href="<?= e(mg_url('/favicon.ico')) ?>" sizes="32x32">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/core.css')) ?>">
</head>
<body>
<div class="grain" aria-hidden="true"></div>
<main style="min-height:100svh;display:grid;place-items:center;text-align:center;padding:2rem">
  <div class="shell-narrow">
    <p class="numeral" style="font-size:var(--step-4);letter-spacing:.1em">404</p>
    <h1 class="display" style="font-size:var(--step-3);margin:1rem 0">Aquí no hay nada servido</h1>
    <p class="lead" style="margin-inline:auto"><?= e(isset($message) ? $message : 'La página que buscas no existe o cambió de dirección.') ?></p>

    <p style="margin-top:2.4rem"><a class="btn btn-ghost" href="<?= e(mg_url('/')) ?>">Volver al inicio</a></p>
  </div>
</main>
</body></html>
