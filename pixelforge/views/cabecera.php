<?php
/** Cabecera común. Variables: $titulo, $base, $clase (opcional). */
$titulo = $titulo ?? 'PixelForge';
$clase = $clase ?? '';
$base = $base ?? '';
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0a0a0b">
<title><?= Support::e($titulo) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%230a0a0b'/%3E%3Ccircle cx='16' cy='16' r='7' fill='none' stroke='%23f0a12e' stroke-width='2.5'/%3E%3Ccircle cx='16' cy='16' r='2' fill='%23f0a12e'/%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=JetBrains+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="<?= Support::e($base) ?>/assets/css/app.css?v=<?= Support::e(PF_VERSION) ?>">
</head>
<body class="<?= Support::e($clase) ?>">
