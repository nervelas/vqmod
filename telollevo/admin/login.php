<?php
require __DIR__ . '/_boot.php';

if (auth_check()) { header('Location: ' . url('admin/')); exit; }

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_valid()) {
        $error = 'La sesión expiró. Vuelve a intentarlo.';
    } else {
        $user = trim((string)($_POST['usuario'] ?? ''));
        $pass = (string)($_POST['clave'] ?? '');
        if ($user === '' || $pass === '') {
            $error = 'Escribe tu usuario y contraseña.';
        } else {
            [$ok, $msg] = auth_login(mb_substr($user, 0, 64), mb_substr($pass, 0, 200));
            if ($ok) { header('Location: ' . url('admin/'), true, 303); exit; }
            $error = $msg;
        }
    }
}
$C = get_content();
$logo = media_url($C['site']['logo']);
header('Content-Type: text/html; charset=UTF-8');
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Ingresar · Panel <?= e($C['site']['brand']) ?></title>
<link rel="icon" type="image/png" href="<?= e(asset('img/favicon-32.png')) ?>">
<link rel="stylesheet" href="<?= e(url('admin/assets/admin.css')) ?>?v=<?= (int)@filemtime(__DIR__ . '/assets/admin.css') ?>">
</head>
<body class="login-body">
<main class="login-wrap">
  <div class="login-card">
    <div class="login-brand">
      <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="" width="84" height="84"><?php endif; ?>
      <h1><?= e($C['site']['brand']) ?></h1>
      <p>Panel de administración</p>
    </div>
    <?php if ($error): ?><div class="toast error" role="alert"><?= icon('warning-circle', '') ?><span><?= e($error) ?></span></div><?php endif; ?>
    <form method="post" autocomplete="on" novalidate>
      <?= csrf_field() ?>
      <div class="fld"><label for="u">Usuario</label>
        <div class="input-ic"><?= icon('user-circle', '') ?><input id="u" name="usuario" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus maxlength="64" value="<?= e($_POST['usuario'] ?? '') ?>"></div></div>
      <div class="fld"><label for="p">Contraseña</label>
        <div class="input-ic"><?= icon('lock-key', '') ?><input id="p" name="clave" type="password" autocomplete="current-password" required maxlength="200">
        <button type="button" class="eye" data-toggle-pass aria-label="Mostrar u ocultar contraseña"><?= icon('eye', '') ?></button></div></div>
      <button class="btn btn-yellow btn-lg btn-block" type="submit"><span>Ingresar</span><?= icon('arrow-right', '') ?></button>
    </form>
    <p class="login-foot"><?= icon('shield-check', '') ?> Conexión protegida · Acceso solo para administradores</p>
  </div>
  <a class="login-back" href="<?= e(url()) ?>"><?= icon('arrow-left', '') ?> Volver al sitio</a>
</main>
<script src="<?= e(url('admin/assets/admin.js')) ?>?v=<?= (int)@filemtime(__DIR__ . '/assets/admin.js') ?>" defer></script>
</body>
</html>
