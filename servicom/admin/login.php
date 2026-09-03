<?php
declare(strict_types=1);
require __DIR__ . '/includes/admin.php';

if (Auth::check()) {
    redirect('admin/index.php');
}

$error = '';
if (is_post()) {
    Csrf::verify();
    $u = post('usuario');
    $p = (string) ($_POST['clave'] ?? '');

    if (Auth::isLocked()) {
        $error = 'Demasiados intentos fallidos. Espere ' . ceil(Auth::lockRemaining() / 60) . ' minuto(s) e intente de nuevo.';
    } elseif ($u === '' || $p === '') {
        $error = 'Escriba su usuario y su contraseña.';
    } elseif (Auth::attempt($u, $p)) {
        redirect('admin/index.php');
    } else {
        $error = 'Usuario o contraseña incorrectos.';
    }
}
?><!DOCTYPE html>
<html lang="es" data-admin-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Acceso al panel · <?= e(Settings::get('site_name', 'Servicom')) ?></title>
<link rel="icon" href="<?= e(asset_url(Settings::get('favicon', 'assets/img/favicon.svg'))) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(base('admin/assets/admin.css?v=1.0.0')) ?>">
</head>
<body class="login">
  <?= icon_sprite() ?>
  <main class="login__box">
    <div class="login__logo">
      <img src="<?= e(asset_url(Settings::get('logo', 'assets/img/logo.svg'))) ?>" alt="<?= e(Settings::get('site_name')) ?>">
    </div>
    <h1>Panel de administración</h1>
    <p class="login__sub">Ingrese sus datos para administrar el sitio.</p>

    <?php if ($error !== ''): ?>
      <div class="notice notice--error"><?= icon('cerrar', 19) ?><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <form method="post" class="form-grid" style="grid-template-columns:1fr">
      <?= Csrf::field() ?>
      <div class="f">
        <label for="usuario">Usuario o correo electrónico</label>
        <input type="text" id="usuario" name="usuario" required autocomplete="username" autofocus value="<?= e(post('usuario')) ?>">
      </div>
      <div class="f">
        <label for="clave">Contraseña</label>
        <input type="password" id="clave" name="clave" required autocomplete="current-password">
      </div>
      <button class="btn" type="submit" style="width:100%"><?= icon('candado', 17) ?><span>Entrar al panel</span></button>
    </form>

    <p class="login__foot"><a href="<?= e(base('')) ?>">← Volver al sitio</a></p>
  </main>
</body>
</html>
