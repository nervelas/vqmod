<?php
/** Admin login. */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if (Auth::check()) { redirect('admin/index.php'); }

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verifyPost();
    // basic brute-force throttle per session
    $_SESSION['login_tries'] = ($_SESSION['login_tries'] ?? 0) + 1;
    if (($_SESSION['login_tries'] ?? 0) > 8) {
        $error = 'Demasiados intentos. Espere unos minutos e intente de nuevo.';
    } else {
        $login = post('login');
        $pass  = (string)($_POST['password'] ?? '');
        if (Auth::attempt($login, $pass)) {
            unset($_SESSION['login_tries']);
            redirect('admin/index.php');
        }
        $error = 'Usuario o contraseña incorrectos.';
    }
}
$siteName = Settings::get('site_name', 'Fuente de Vida');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Acceso — Administración</title>
<link rel="icon" href="<?= e(asset_url(Settings::get('favicon','assets/img/favicon.svg'))) ?>">
<link rel="stylesheet" href="<?= e(asset_url('admin/assets/admin.css')) ?>?v=1">
</head>
<body class="login-body">
<div class="login-card">
  <img src="<?= e(asset_url(Settings::get('logo','assets/img/logo.svg'))) ?>" alt="<?= e($siteName) ?>" class="login-logo">
  <h1>Panel de administración</h1>
  <?php if ($error): ?><div class="notice notice--error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="login-form">
    <?= Csrf::field() ?>
    <div class="form-group">
      <label>Usuario o correo</label>
      <input type="text" name="login" autocomplete="username" required autofocus>
    </div>
    <div class="form-group">
      <label>Contraseña</label>
      <input type="password" name="password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="btn btn--primary btn--lg" style="width:100%">Ingresar</button>
  </form>
  <a class="login-back" href="<?= e(base_url()) ?>">← Volver al sitio</a>
</div>
</body>
</html>
