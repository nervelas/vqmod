<?php
/** La base de datos no responde con los datos de config/config.php. */
?><!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sin conexión a la base de datos</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/core.css')) ?>">
</head>
<body>
<main style="min-height:100svh;display:grid;place-items:center;padding:2rem">
  <div class="shell-narrow" style="max-width:620px">
    <p class="numeral" style="letter-spacing:.2em">BASE DE DATOS</p>
    <h1 class="display" style="font-size:var(--step-3);margin:.8rem 0 1rem">No se pudo conectar</h1>
    <p class="lead">
      El sistema está bien, pero no logra entrar a tu base de datos con los datos
      guardados en <code>config/config.php</code>.
    </p>
    <p style="color:var(--text-dim);margin-top:1.2rem">
      Suele ser el nombre de la base, el usuario o la contraseña, que cambian al
      mudar de hosting o al recrear la base en cPanel.
    </p>
    <p style="margin-top:2rem">
      <a class="btn" href="<?= e(mg_url('/install/')) ?>">Volver a escribir los datos</a>
    </p>
    <p style="color:var(--text-faint);font-size:var(--step--1);margin-top:1.4rem">
      El detalle exacto está en <code>storage/logs/<?= e(date('Y-m')) ?>.log</code>.
    </p>
  </div>
</main>
</body></html>
