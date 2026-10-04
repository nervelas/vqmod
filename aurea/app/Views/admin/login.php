<span class="eyebrow"><?= e((string)setting('business_name', 'AUREA')) ?></span>
<h1><?= e(__('Ingresar al panel')) ?></h1>
<?php if ($error !== ''): ?><div class="alert alert-err" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/admin/login')) ?>">
  <?= csrf_field() ?>
  <div class="field"><label for="email"><?= e(__('Correo electrónico')) ?></label><input id="email" name="email" type="email" required autocomplete="username" value="<?= e($email) ?>" autofocus></div>
  <div class="field"><label for="password"><?= e(__('Contraseña')) ?></label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
  <button class="btn btn-gold" style="width:100%" type="submit"><?= e(__('Ingresar')) ?></button>
</form>
<p style="margin:18px 0 0;font-size:.9rem"><a href="<?= e(url('/admin/olvide')) ?>"><?= e(__('Olvidé mi contraseña')) ?></a></p>
