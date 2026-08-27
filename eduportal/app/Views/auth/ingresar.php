<h1>Bienvenido</h1>
<p class="sub">Ingrese con su correo y contraseña para acceder al portal.</p>

<?php if (!empty($expirada)): ?>
  <div class="aviso aviso--warn"><?= icono('reloj', 18) ?><span>Su sesión se cerró por inactividad. Vuelva a ingresar.</span></div>
<?php endif; ?>

<form method="post" action="<?= e(url('ingresar')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="campo">
    <label for="email">Correo electrónico</label>
    <input type="email" id="email" name="email" required autocomplete="username" autofocus
           inputmode="email" placeholder="nombre@colegio.gt">
  </div>
  <div class="campo">
    <label for="password">Contraseña</label>
    <input type="password" id="password" name="password" required autocomplete="current-password" minlength="1">
  </div>
  <button type="submit" class="btn btn--bloque">Ingresar al portal</button>
</form>

<div class="acceso__ayuda">
  <a href="<?= e(url('recuperar')) ?>">¿Olvidó su contraseña?</a>
  <a href="<?= e(url('/')) ?>" class="txt-2">Volver al sitio</a>
</div>
