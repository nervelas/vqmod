<?php /** @var string $error */ ?>
<h1 class="serif auth-title">Verificación en dos pasos</h1>
<p class="muted auth-sub">Escribe el código de 6 dígitos que muestra tu aplicación de autenticación.</p>
<?php if ($error !== '') : ?><div class="alert alert-err" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/admin/2fa')) ?>" class="stack" novalidate>
  <?= csrf_field() ?>
  <div class="field">
    <label for="code">Código de verificación</label>
    <input class="input auth-code mono" id="code" name="code" type="text" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" required autofocus>
    <p class="hint">El código cambia cada 30 segundos.</p>
  </div>
  <button class="btn btn-gold btn-lg btn-block" type="submit">Verificar</button>
  <p class="center"><a href="<?= e(url('/admin/login')) ?>">Volver a iniciar sesión</a></p>
</form>
