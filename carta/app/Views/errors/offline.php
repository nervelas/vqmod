<?php
/** Página mostrada por el service worker cuando no hay conexión. */
?><!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sin conexión</title>
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
    <p class="eyebrow is-centered">Sin conexión</p>
    <h1 class="display" style="font-size:var(--step-3);margin:1.2rem 0">El menú te está esperando</h1>
    <p class="lead" style="margin-inline:auto">Tu teléfono perdió la señal. En cuanto vuelva, la carta se abre sola.</p>
    <p style="margin-top:2.2rem"><button class="btn" type="button" onclick="location.reload()">Reintentar</button></p>
  </div>
</main>
<script>
window.addEventListener('online', function () { location.reload(); });
</script>
</body></html>
