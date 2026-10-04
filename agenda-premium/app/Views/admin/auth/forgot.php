<?php /** @var bool $sent @var string $email @var string $error */ ?>
<?php if ($sent) : ?>
  <div class="auth-seal"><?= icon('mail') ?></div>
  <h1 class="serif auth-title">Revisa tu correo</h1>
  <p class="muted">Si hay una cuenta con ese correo, te enviamos un enlace para crear una contraseña nueva. Funciona una sola vez y caduca en 1 hora.</p>
  <p class="muted">¿No llega? Revisa la carpeta de correo no deseado.</p>
  <p class="center mt-3"><a class="btn btn-outline" href="<?= e(url('/admin/login')) ?>">Volver a iniciar sesión</a></p>
<?php else : ?>
  <h1 class="serif auth-title">Recupera tu acceso</h1>
  <p class="muted auth-sub">Escribe tu correo y te enviaremos un enlace para elegir una contraseña nueva.</p>
  <?php if ($error !== '') : ?><div class="alert alert-err" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('/admin/olvide')) ?>" class="stack" novalidate>
    <?= csrf_field() ?>
    <div class="field">
      <label for="email">Correo electrónico</label>
      <input class="input" id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" inputmode="email" required autofocus>
    </div>
    <button class="btn btn-gold btn-lg btn-block" type="submit">Enviar enlace</button>
    <p class="center"><a href="<?= e(url('/admin/login')) ?>">Volver a iniciar sesión</a></p>
  </form>
<?php endif; ?>
