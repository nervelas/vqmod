<section class="auth"><div class="auth-card card reveal in">
  <h1>Ingresar</h1><p class="muted">Qué bueno verte de nuevo.</p>
  <?= flash_html() ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <?= f_input('email', 'Correo electrónico', $_POST['email'] ?? '', 'email', 'required autocomplete="username" inputmode="email"') ?>
    <?= f_input('pass', 'Contraseña', '', 'password', 'required autocomplete="current-password"') ?>
    <button class="btn btn-gold btn-block">Ingresar</button>
  </form>
  <p class="center"><a href="<?= e(url('recuperar')) ?>">Olvidé mi contraseña</a></p>
  <p class="muted center">¿Primera vez? <a href="<?= e(url('registro')) ?>">Crea tu cuenta gratis</a></p>
</div></section>
