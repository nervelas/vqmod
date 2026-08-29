<h1>Verificación en dos pasos</h1>
<p class="sub">Enviamos un código de 6 dígitos a su correo. Vence en 15 minutos.</p>
<?php if ($error): ?><div class="alert alert--error"><span aria-hidden="true">!</span><span><?= e($error) ?></span></div><?php endif; ?>
<form method="post" action="<?= e(url('/verificar')) ?>" class="stack-sm">
  <?= csrf_field() ?>
  <div class="field">
    <label for="code">Código de verificación</label>
    <input class="input" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus
           style="text-align:center;letter-spacing:.6em;font-size:1.4rem;font-family:var(--f-display)">
  </div>
  <button class="btn btn--accent btn--block" type="submit">Verificar <span class="arw" aria-hidden="true">→</span></button>
  <p class="center small" style="margin-top:16px"><a class="linkarrow" href="<?= e(url('/entrar')) ?>">Volver al inicio de sesión</a></p>
</form>
