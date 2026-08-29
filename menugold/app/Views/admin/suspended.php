<?php /** Restaurante suspendido: aviso al dueño. */ ?>
<!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cuenta suspendida</title><meta name="robots" content="noindex">
<link rel="icon" href="<?= e(mg_url('/assets/icons/icon-192.png')) ?>" sizes="192x192">
<link rel="icon" href="<?= e(mg_url('/favicon.ico')) ?>" sizes="32x32">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/core.css')) ?>">
</head>
<body>
<div class="grain" aria-hidden="true"></div>
<main style="min-height:100svh;display:grid;place-items:center;text-align:center;padding:2rem">
  <div class="shell-narrow">
    <p class="eyebrow is-centered">Cuenta suspendida</p>
    <h1 class="display" style="font-size:var(--step-3);margin:1.2rem 0"><?= e($restaurant['name']) ?></h1>
    <p class="lead" style="margin-inline:auto">Tu cuenta está suspendida temporalmente. Escríbenos y la reactivamos el mismo día.</p>
    <p style="margin-top:2rem">
      <a class="btn" href="<?= e(mg_wa(\MenuGold\Models\Landing::v('whatsapp'), 'Hola, mi restaurante ' . $restaurant['name'] . ' aparece suspendido en MenúGold.')) ?>" target="_blank" rel="noopener">Escribir por WhatsApp</a>
      <a class="btn btn-ghost" href="<?= e(mg_url('/panel/salir')) ?>">Cerrar sesión</a>
    </p>
  </div>
</main>
</body></html>
