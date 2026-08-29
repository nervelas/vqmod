<?php if ($invalid): ?>
  <h1>Enlace no válido</h1>
  <p class="sub">Este enlace ya se usó o venció. Solicite uno nuevo.</p>
  <a class="btn btn--accent btn--block" href="<?= e(url('/recuperar')) ?>">Solicitar otro enlace</a>
<?php else: ?>
  <h1>Nueva contraseña</h1>
  <p class="sub">Use al menos 8 caracteres, con mayúsculas, minúsculas y números.</p>
  <?php if ($error): ?><div class="alert alert--error"><span aria-hidden="true">!</span><span><?= e($error) ?></span></div><?php endif; ?>
  <form method="post" action="<?= e(url('/restablecer/' . $token)) ?>" class="stack-sm">
    <?= csrf_field() ?>
    <div class="field"><label for="password">Nueva contraseña</label>
      <input class="input" id="password" name="password" type="password" autocomplete="new-password" minlength="8" required autofocus></div>
    <div class="field"><label for="password2">Repita la contraseña</label>
      <input class="input" id="password2" name="password2" type="password" autocomplete="new-password" minlength="8" required></div>
    <button class="btn btn--accent btn--block" type="submit">Guardar contraseña <span class="arw" aria-hidden="true">→</span></button>
  </form>
<?php endif; ?>
