<span class="eyebrow"><?= e(__('Verificación en dos pasos')) ?></span>
<h1><?= e(__('Código de seguridad')) ?></h1>
<p class="hint"><?= e(__('Abre tu app de autenticación e ingresa el código de 6 dígitos.')) ?></p>
<?php if ($error !== ''): ?><div class="alert alert-err" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/admin/2fa')) ?>">
  <?= csrf_field() ?>
  <div class="field"><label for="code"><?= e(__('Código')) ?></label><input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" required autofocus></div>
  <button class="btn btn-gold" style="width:100%" type="submit"><?= e(__('Verificar')) ?></button>
</form>
