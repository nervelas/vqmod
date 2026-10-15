<section class="auth"><div class="auth-card card reveal in">
  <h1>Recuperar contraseña</h1><p class="muted">Te enviamos un enlace por correo (vale 1 hora y solo se usa una vez).</p>
  <?= flash_html() ?>
  <form method="post" class="form"><?= csrf_field() ?>
    <?= f_input('email', 'Correo electrónico', '', 'email', 'required autocomplete="email"') ?>
    <button class="btn btn-gold btn-block">Enviar enlace</button>
  </form>
  <p class="center"><a href="<?= e(url('ingresar')) ?>">Volver a ingresar</a></p>
</div></section>
