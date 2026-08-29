<?php
/** Restaurante suspendido o con plan vencido. */
use MenuGold\Core\Url;
?><!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Menú no disponible</title>
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
    <p class="eyebrow is-centered">Menú no disponible</p>
    <h1 class="display" style="font-size:var(--step-3);margin:1.2rem 0"><?= e($restaurant['name']) ?></h1>
    <p class="lead" style="margin-inline:auto">Este menú digital está temporalmente fuera de servicio. Pregunta al personal por la carta impresa.</p>
    <p style="margin-top:2rem"><a class="btn btn-ghost" href="<?= e(mg_url('/')) ?>">Conocer MenúGold</a></p>
  </div>
</main>
</body></html>
