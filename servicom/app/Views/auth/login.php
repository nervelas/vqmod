<h1>Acceso al sistema</h1>
<p class="sub">Ingrese con su correo o nombre de usuario.</p>
<?php if ($error): ?><div class="alert alert--error"><span aria-hidden="true">!</span><span><?= e($error) ?></span></div><?php endif; ?>
<form method="post" action="<?= e(url('/entrar')) ?>" class="stack-sm" novalidate>
  <?= csrf_field() ?>
  <div class="field">
    <label for="identity">Correo o usuario</label>
    <input class="input" id="identity" name="identity" type="text" autocomplete="username" required autofocus
           value="<?= e(old('identity')) ?>" placeholder="usted@empresa.gt">
  </div>
  <div class="field">
    <label for="password">Contraseña</label>
    <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
  </div>
  <button class="btn btn--accent btn--block" type="submit">Entrar <span class="arw" aria-hidden="true">→</span></button>
  <p class="center small" style="margin-top:18px"><a class="linkarrow" href="<?= e(url('/recuperar')) ?>">Olvidé mi contraseña</a></p>
</form>
