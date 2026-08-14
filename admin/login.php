<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }

if (Auth::check()) {
    redirect(base_url('admin/index.php'));
}

$error = null;
if (is_post()) {
    Security::requireCsrf();
    $ip = client_ip();
    $id = str_input('identifier');
    $pw = (string)post('password', '');
    if (Auth::rateLimited($ip)) {
        $error = 'Demasiados intentos fallidos. Espere unos minutos e intente de nuevo.';
    } elseif ($id === '' || $pw === '') {
        $error = 'Ingrese su usuario y contraseña.';
    } elseif (Auth::attempt($id, $pw)) {
        Auth::recordAttempt($ip, $id, true);
        Audit::log('login', 'auth');
        redirect(base_url('admin/index.php'));
    } else {
        Auth::recordAttempt($ip, $id, false);
        $error = 'Credenciales incorrectas.';
    }
}
$theme = Theme::resolve((int)Settings::get('default_theme_id', 1));
$site  = Settings::get('site_name', 'Ligas de Fútbol');
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Acceso · <?= e($site) ?></title>
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/admin.css')) ?>">
<style><?= Theme::styleBlock($theme) ?></style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card card card-pad-lg">
        <div class="login-logo">⚽</div>
        <h1 class="text-center" style="margin-bottom:.2rem"><?= e($site) ?></h1>
        <p class="text-center muted mb-3">Panel de administración</p>
        <?php if ($error): ?>
            <div class="alert alert-danger"><span><?= e($error) ?></span></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <?= Security::csrfField() ?>
            <div class="field">
                <label for="identifier">Usuario o correo</label>
                <input class="input" type="text" id="identifier" name="identifier" required autofocus autocomplete="username">
            </div>
            <div class="field">
                <label for="password">Contraseña</label>
                <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button class="btn btn-block btn-lg" type="submit">Ingresar</button>
        </form>
    </div>
</div>
</body>
</html>
