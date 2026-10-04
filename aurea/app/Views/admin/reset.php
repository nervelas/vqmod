<span class="eyebrow"><?= e(__('Nueva contraseña')) ?></span>
<h1><?= e(__('Crear contraseña')) ?></h1>
<?php if (!$valid): ?>
  <div class="alert alert-err"><?= e(__('El enlace no es válido o ya venció.')) ?></div><a href="<?= e(url('/admin/olvide')) ?>"><?= e(__('Solicitar uno nuevo')) ?></a>
<?php else: ?>
  <?php if ($error !== ''): ?><div class="alert alert-err" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="k" value="<?= e($k) ?>">
    <div class="field"><label for="p1"><?= e(__('Nueva contraseña')) ?></label><input id="p1" name="password" type="password" required autocomplete="new-password"><div class="help"><?= e(__('Mínimo 10 caracteres con mayúsculas, minúsculas y números.')) ?></div></div>
    <div class="field"><label for="p2"><?= e(__('Repite la contraseña')) ?></label><input id="p2" name="password2" type="password" required autocomplete="new-password"></div>
    <button class="btn btn-gold" style="width:100%" type="submit"><?= e(__('Guardar')) ?></button>
  </form>
<?php endif; ?>
