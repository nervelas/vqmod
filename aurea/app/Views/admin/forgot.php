<span class="eyebrow"><?= e(__('Recuperar acceso')) ?></span>
<h1><?= e(__('Restablecer contraseña')) ?></h1>
<?php if ($sent): ?>
  <div class="alert alert-ok" role="status"><?= e(__('Si el correo existe, enviamos un enlace de un solo uso (vence en 1 hora).')) ?></div>
  <a href="<?= e(url('/admin/login')) ?>">← <?= e(__('Volver a ingresar')) ?></a>
<?php else: ?>
  <form method="post" action="<?= e(url('/admin/olvide')) ?>">
    <?= csrf_field() ?>
    <div class="field"><label for="email"><?= e(__('Correo electrónico')) ?></label><input id="email" name="email" type="email" required autofocus></div>
    <button class="btn btn-gold" style="width:100%" type="submit"><?= e(__('Enviar enlace')) ?></button>
  </form>
  <p style="margin-top:18px"><a href="<?= e(url('/admin/login')) ?>">← <?= e(__('Volver')) ?></a></p>
<?php endif; ?>
