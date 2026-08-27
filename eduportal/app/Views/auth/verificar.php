<h1>Verificación en dos pasos</h1>
<p class="sub">Le enviamos un código de 6 dígitos a su correo. Vence en 10 minutos.</p>
<form method="post" action="<?= e(url('verificar')) ?>">
  <?= csrf_field() ?>
  <div class="campo">
    <label for="codigo">Código de verificación</label>
    <input type="text" id="codigo" name="codigo" required inputmode="numeric" pattern="[0-9]{6}"
           maxlength="6" autocomplete="one-time-code" autofocus
           style="letter-spacing:.5em;text-align:center;font-size:1.4rem;font-weight:700">
  </div>
  <button type="submit" class="btn btn--bloque">Verificar e ingresar</button>
</form>
<div class="acceso__ayuda"><a href="<?= e(url('ingresar')) ?>">Volver al inicio de sesión</a></div>
