<h1>Recuperar contraseña</h1>
<p class="sub">Le enviaremos un enlace de un solo uso, válido por 30 minutos.</p>
<?php if ($error): ?><div class="alert alert--error"><span aria-hidden="true">!</span><span><?= e($error) ?></span></div><?php endif; ?>
<?php if ($sent): ?>
  <div class="alert alert--ok"><span aria-hidden="true">✓</span><span>Si el correo existe en el sistema, recibirá el enlace en unos minutos. Revise también la carpeta de no deseados.</span></div>
  <a class="btn btn--ghost btn--block" href="<?= e(url('/entrar')) ?>">Volver al acceso</a>
<?php else: ?>
<form method="post" action="<?= e(url('/recuperar')) ?>" class="stack-sm">
  <?= csrf_field() ?>
  <div class="field">
    <label for="email">Su correo</label>
    <input class="input" id="email" name="email" type="email" autocomplete="email" required autofocus>
  </div>
  <button class="btn btn--accent btn--block" type="submit">Enviar enlace <span class="arw" aria-hidden="true">→</span></button>
  <p class="center small" style="margin-top:16px"><a class="linkarrow" href="<?= e(url('/entrar')) ?>">Volver</a></p>
</form>
<?php endif; ?>
