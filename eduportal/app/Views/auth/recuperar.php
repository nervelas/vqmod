<h1>Recuperar contraseña</h1>
<p class="sub">Escriba su correo y le enviaremos un enlace para crear una contraseña nueva.</p>
<form method="post" action="<?= e(url('recuperar')) ?>">
  <?= csrf_field() ?>
  <div class="campo">
    <label for="email">Correo electrónico</label>
    <input type="email" id="email" name="email" required autocomplete="username" autofocus>
  </div>
  <button type="submit" class="btn btn--bloque">Enviar enlace de recuperación</button>
</form>
<div class="acceso__ayuda"><a href="<?= e(url('ingresar')) ?>">Volver al inicio de sesión</a></div>
