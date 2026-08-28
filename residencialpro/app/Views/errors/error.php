<?php
/** Vista de error genérica. Variables: $codigoHttp, $titulo, $mensaje */
use App\Core\Ajustes;
use App\Core\Url;
$codigoHttp = $codigoHttp ?? 500;
$titulo     = $titulo ?? 'Error';
$mensaje    = $mensaje ?? '';
?><!DOCTYPE html>
<html lang="es" data-tema="<?= e(Ajustes::get('tema', 'verde-oro')) ?>" data-modo="claro" data-base="<?= e(Url::basePath()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/png" href="<?= e(url('/assets/img/favicon.png')) ?>">
<link rel="stylesheet" href="<?= e(url('/assets/css/fuentes-locales.css')) ?>">
<link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
</head>
<body>
<div style="min-height:100dvh;display:grid;place-items:center;padding:24px;background:linear-gradient(160deg,var(--petroleo-3),var(--petroleo))">
  <div style="text-align:center;max-width:520px;color:#E9EEE9">
    <div style="font-family:var(--f-titulo);font-size:5.2rem;line-height:1;color:var(--arcilla-3);font-weight:600"><?= (int) $codigoHttp ?></div>
    <h1 style="color:#fff;font-size:1.7rem;margin-top:10px"><?= e($titulo) ?></h1>
    <?php if ($mensaje !== ''): ?>
      <p style="color:rgba(233,238,233,.78)"><?= e($mensaje) ?></p>
    <?php endif; ?>
    <div class="fila" style="justify-content:center;margin-top:24px">
      <a class="btn btn-oro" href="<?= e(url('/')) ?>">Ir al inicio</a>
      <a class="btn btn-fantasma" style="color:#D9E0DA;border-color:rgba(255,255,255,.24)" href="javascript:history.back()">Volver atrás</a>
    </div>
  </div>
</div>
</body>
</html>
