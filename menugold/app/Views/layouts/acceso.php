<?php
use MenuGold\Core\App;
use MenuGold\Core\Security;
use MenuGold\Core\Setting;
use MenuGold\Core\View;
$marca = (string)Setting::plat('nombre_plataforma', 'MenúGold');
?><!doctype html>
<html lang="es" data-tema="negro-oro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(View::section('titulo', 'Ingresar')) ?> · <?= e($marca) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#141414">
<link rel="icon" type="image/png" sizes="192x192" href="<?= e(url('icono/192')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Inter:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
<link rel="stylesheet" href="<?= e(asset('css/temas.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/panel.css')) ?>">
<style>
  body.acceso {
    min-height: 100vh; display: grid; place-items: center;
    padding: 24px 18px; margin: 0;
    background: radial-gradient(120% 120% at 50% 0%, #262114 0%, #141414 58%);
    color: #F7F3EA;
  }
  .acceso-caja {
    width: min(420px, 100%);
    background: #1C1C1C;
    border: 1px solid rgba(247,243,234,.12);
    border-radius: 22px;
    box-shadow: 0 22px 60px rgba(0,0,0,.5);
    overflow: hidden;
    animation: subir 420ms cubic-bezier(.16,.84,.44,1) both;
  }
  .acceso-cab { padding: 32px 30px 22px; text-align: center; border-bottom: 1px solid rgba(247,243,234,.08); }
  .acceso-logo {
    width: 62px; height: 62px; margin: 0 auto 14px;
    border-radius: 50%; border: 2px solid #D4AF37; color: #D4AF37;
    display: grid; place-items: center;
    font-family: var(--fuente-titulo); font-size: 26px; font-weight: 700;
  }
  .acceso-cab h1 { font-family: var(--fuente-titulo); font-size: 25px; margin: 0 0 5px; color: #F7F3EA; }
  .acceso-cab p { margin: 0; color: #A9A398; font-size: 13.5px; }
  .acceso-cuerpo { padding: 26px 30px 30px; }
  .acceso-cuerpo label { color: #B5AE9F; font-size: 12.5px; font-weight: 600; display:block; margin-bottom:6px; }
  .acceso-cuerpo input {
    width: 100%; min-height: 48px; padding: 12px 14px;
    border-radius: 11px; border: 1px solid rgba(247,243,234,.16);
    background: #232323; color: #F7F3EA; font-size: 15px;
  }
  .acceso-cuerpo input:focus { outline: none; border-color: #D4AF37; box-shadow: 0 0 0 3px rgba(212,175,55,.18); }
  .acceso-btn {
    width: 100%; min-height: 50px; margin-top: 6px;
    border-radius: 11px; background: #D4AF37; color: #141414;
    font-weight: 700; font-size: 15px;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    transition: all 200ms ease;
  }
  .acceso-btn:hover { background: #E8CC6A; transform: translateY(-1px); }
  .acceso-pie { text-align: center; padding: 16px; border-top: 1px solid rgba(247,243,234,.08); font-size: 13px; color: #837C6E; }
  .acceso-pie a { color: #D4AF37; font-weight: 600; }
  .acceso-campo { margin-bottom: 15px; position: relative; }
  .ver-clave {
    position: absolute; right: 8px; bottom: 6px;
    width: 36px; height: 36px; display: grid; place-items: center;
    color: #837C6E; border-radius: 8px;
  }
  .ver-clave:hover { color: #D4AF37; }
  .acceso-aviso {
    display: flex; gap: 10px; align-items: flex-start;
    padding: 12px 14px; border-radius: 11px; margin-bottom: 18px; font-size: 13.5px; line-height: 1.5;
  }
  .acceso-aviso svg { width: 18px; height: 18px; flex: 0 0 auto; margin-top: 1px; }
  .acceso-aviso--error { background: rgba(217,97,79,.15); color: #F3B6AC; }
  .acceso-aviso--exito { background: rgba(79,169,124,.15); color: #A8E6C3; }
  .acceso-aviso--aviso { background: rgba(224,166,60,.15); color: #F0CE8E; }
  .acceso-marca { text-align: center; margin-top: 22px; font-size: 12px; color: #6E685C; }
</style>
</head>
<body class="acceso">
<main class="acceso-caja">
  <?= View::section('contenido') ?>
</main>
<p class="acceso-marca"><?= e($marca) ?> · Menús QR con pedidos</p>
<script nonce="<?= e(Security::nonce()) ?>">
document.addEventListener('click', function (ev) {
  var b = ev.target.closest('.ver-clave');
  if (!b) return;
  var inp = document.getElementById(b.dataset.para);
  if (!inp) return;
  inp.type = inp.type === 'password' ? 'text' : 'password';
  b.setAttribute('aria-label', inp.type === 'password' ? 'Mostrar contraseña' : 'Ocultar contraseña');
});
</script>
</body>
</html>
