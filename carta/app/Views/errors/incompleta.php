<?php
/** La base de datos existe y conecta, pero le faltan tablas de MenúGold. */
?><!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Falta preparar la base de datos</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/core.css')) ?>">
</head>
<body>
<main style="min-height:100svh;display:grid;place-items:center;padding:2rem">
  <div class="shell-narrow" style="max-width:640px">
    <p class="numeral" style="letter-spacing:.2em">CASI LISTO</p>
    <h1 class="display" style="font-size:var(--step-3);margin:.8rem 0 1rem">Falta preparar la base de datos</h1>
    <p class="lead">
      MenúGold conecta bien con tu base de datos, pero todavía no están sus tablas.
      Suele pasar cuando se sube el sistema a un dominio donde antes había otra versión.
    </p>
    <p style="color:var(--text-dim);margin-top:1.2rem">
      Se arregla en un clic y <strong>no se borra nada</strong> de lo que ya tengas en esa base:
      MenúGold solo crea las tablas que empiezan por <code>mg_</code>.
    </p>

    <p style="margin-top:2rem">
      <a class="btn" href="<?= e(mg_url('/install/')) ?>">Preparar la base de datos</a>
    </p>

    <?php if (!empty($faltan)): ?>
      <details style="margin-top:2.4rem">
        <summary style="cursor:pointer;color:var(--text-faint);font-size:var(--step--1)">
          Detalle técnico (<?= count($faltan) ?> tablas)
        </summary>
        <p style="font-size:12px;color:var(--text-faint);line-height:1.8;margin-top:.8rem;word-break:break-all">
          <?= e(implode(' · ', $faltan)) ?>
        </p>
      </details>
    <?php endif; ?>
  </div>
</main>
</body></html>
