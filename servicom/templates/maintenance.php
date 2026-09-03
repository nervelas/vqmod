<?php
declare(strict_types=1);
$theme = Theme::active();
?><!DOCTYPE html>
<html lang="es-GT">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sitio en mantenimiento | <?= e(Settings::get('site_name', 'Servicom')) ?></title>
<meta name="robots" content="noindex, nofollow">
<style><?= Theme::cssVariables() ?>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--text);
font-family:system-ui,-apple-system,'Segoe UI',sans-serif;text-align:center;padding:2rem}
h1{font-size:clamp(1.8rem,5vw,3rem);margin:0 0 1rem}p{color:var(--muted);max-width:34rem;margin:0 auto}
.dot{width:10px;height:10px;border-radius:50%;background:var(--accent);display:inline-block;margin-bottom:1.5rem}
a{color:var(--accent)}</style>
</head>
<body>
<div>
  <span class="dot"></span>
  <h1>Estamos realizando mejoras</h1>
  <p>El sitio de <strong><?= e(Settings::get('site_name', 'Servicom')) ?></strong> estará disponible en unos minutos.
  Mientras tanto puede escribirnos al <a href="tel:+<?= e(digits(Settings::get('phone'))) ?>"><?= e(Settings::get('phone')) ?></a>.</p>
</div>
</body>
</html>
