<h1>Nueva contraseña</h1>
<p class="sub">Elija una contraseña de al menos 10 caracteres.</p>
<form method="post" action="<?= e(url('restablecer')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <div class="campo">
    <label for="password">Contraseña nueva</label>
    <input type="password" id="password" name="password" required minlength="10" autocomplete="new-password" autofocus>
    <p class="ayuda">Combine mayúsculas, minúsculas, números y símbolos.</p>
  </div>
  <div class="campo">
    <label for="password_confirmacion">Confirme la contraseña</label>
    <input type="password" id="password_confirmacion" name="password_confirmacion" required minlength="10" autocomplete="new-password">
  </div>
  <button type="submit" class="btn btn--bloque">Guardar contraseña</button>
</form>
