<section class="auth"><div class="auth-card card reveal in">
  <h1>Nueva contraseña</h1>
  <?= flash_html() ?>
  <form method="post" class="form"><?= csrf_field() ?>
    <?= f_input('pass', 'Nueva contraseña (8+ caracteres, letras y números)', '', 'password', 'required minlength="8" autocomplete="new-password"') ?>
    <button class="btn btn-gold btn-block">Guardar contraseña</button>
  </form>
</div></section>
