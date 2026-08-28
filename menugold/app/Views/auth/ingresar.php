<?php
use MenuGold\Core\View;
View::set('titulo', 'Ingresar');
$marca = $plataforma ?? 'MenúGold';
?>
<div class="acceso-cab">
  <div class="acceso-logo"><?= e(mb_strtoupper(mb_substr($marca, 0, 1))) ?></div>
  <h1><?= e($marca) ?></h1>
  <p>Ingresa para administrar tu restaurante</p>
</div>
<div class="acceso-cuerpo">
  <?php foreach (($flashes ?? []) as $f): ?>
    <div class="acceso-aviso acceso-aviso--<?= e($f['tipo'] === 'exito' ? 'exito' : ($f['tipo'] === 'error' ? 'error' : 'aviso')) ?>">
      <?= icon($f['tipo'] === 'exito' ? 'check-circle' : ($f['tipo'] === 'error' ? 'alert' : 'info')) ?>
      <span><?= e($f['texto']) ?></span>
    </div>
  <?php endforeach; ?>

  <form method="post" action="<?= e(url('ingresar')) ?>" autocomplete="on" novalidate>
    <?= csrf_field() ?>
    <div class="acceso-campo">
      <label for="usuario">Correo o usuario</label>
      <input type="text" id="usuario" name="usuario" value="<?= old('usuario') ?>"
             autocomplete="username" required autofocus inputmode="email" maxlength="190">
    </div>
    <div class="acceso-campo">
      <label for="password">Contraseña</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required maxlength="200">
      <button type="button" class="ver-clave" data-para="password" aria-label="Mostrar contraseña"><?= icon('eye') ?></button>
    </div>
    <label class="casilla" style="color:#A9A398;margin-bottom:14px">
      <input type="checkbox" name="recordar" value="1"> Mantener mi sesión abierta
    </label>
    <button class="acceso-btn" type="submit"><?= icon('login') ?> Ingresar</button>
  </form>
</div>
<div class="acceso-pie">
  <a href="<?= e(url('recuperar')) ?>">¿Olvidaste tu contraseña?</a>
</div>
<?php unset($_SESSION['_old']); ?>
