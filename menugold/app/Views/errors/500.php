<?php
/** Error del servidor */
?><!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Se nos quemó algo en la cocina</title>
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
    <p class="numeral" style="font-size:var(--step-4);letter-spacing:.1em">500</p>
    <h1 class="display" style="font-size:var(--step-3);margin:1rem 0">Se nos quemó algo en la cocina</h1>
    <p class="lead" style="margin-inline:auto"><?= e(isset($message) ? $message : 'Ocurrió un error inesperado. Ya quedó registrado en el servidor.') ?></p>

    <?php if (!empty($detail)): ?>
      <pre style="text-align:left;background:var(--carbon);border:1px solid var(--line-soft);border-radius:12px;padding:1rem;margin-top:1.6rem;overflow:auto;font-size:12px;color:var(--text-dim)"><?= e($detail) ?></pre>
    <?php endif; ?>
    <p style="margin-top:2.4rem"><a class="btn btn-ghost" href="<?= e(mg_url('/')) ?>">Volver al inicio</a></p>
  </div>
</main>
</body></html>
