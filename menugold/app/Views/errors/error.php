<?php
/** @var int $code; string $mensaje, $titulo */
$titulo  = $titulo  ?? 'Error';
$mensaje = $mensaje ?? 'Ocurrió un problema.';
$code    = $code    ?? 500;
$base    = \MenuGold\Core\App::baseUrl();
?><!doctype html>
<html lang="es" data-tema="negro-oro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars((string)$code) ?> · <?= htmlspecialchars($titulo) ?></title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/temas.css">
<link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/base.css">
<style>
  body { display:grid; place-items:center; min-height:100vh; margin:0; text-align:center; padding:32px 22px; }
  .caja { max-width: 460px; }
  .num { font-family: var(--fuente-titulo); font-size: 92px; line-height:1; color: var(--acento); margin:0 0 8px; }
  h1 { font-family: var(--fuente-titulo); font-size:26px; margin:0 0 10px; }
  p { color: var(--texto-suave); margin: 0 0 24px; }
  a.btn { display:inline-flex; align-items:center; gap:8px; min-height:48px; padding:13px 24px;
          border-radius:999px; background: var(--acento); color: var(--acento-texto); font-weight:600; text-decoration:none; }
</style>
</head>
<body>
  <div class="caja">
    <p class="num"><?= htmlspecialchars((string)$code) ?></p>
    <h1><?= htmlspecialchars($titulo) ?></h1>
    <p><?= htmlspecialchars($mensaje) ?></p>
    <a class="btn" href="<?= htmlspecialchars($base) ?>/">Volver al inicio</a>
  </div>
</body>
</html>
