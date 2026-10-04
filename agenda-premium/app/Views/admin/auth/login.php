<?php /** @var string $next @var string $email @var string $error @var string $notice */ ?>
<h1 class="serif auth-title">Bienvenido de nuevo</h1>
<p class="muted auth-sub">Inicia sesión para gestionar tu agenda.</p>
<?php if ($notice !== '') : ?><div class="alert alert-ok" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error !== '') : ?><div class="alert alert-err" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/admin/login')) ?>" class="stack" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= e($next) ?>">
  <div class="field">
    <label for="email">Correo electrónico</label>
    <input class="input" id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="username" inputmode="email" required autofocus>
  </div>
  <div class="field">
    <label for="password">Contraseña</label>
    <div class="input-group">
      <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
      <button class="btn btn-ghost btn-icon" type="button" data-toggle-password="#password" aria-label="Mostrar u ocultar la contraseña" aria-pressed="false"><?= icon('eye') ?></button>
    </div>
  </div>
  <button class="btn btn-gold btn-lg btn-block" type="submit">Entrar</button>
  <p class="center"><a href="<?= e(url('/admin/olvide')) ?>">¿Olvidaste tu contraseña?</a></p>
</form>
