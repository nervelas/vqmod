<?php use MenuGold\Core\View; View::set('titulo', 'Nueva contraseña'); ?>
<div class="acceso-cab">
  <div class="acceso-logo"><?= icon('shield') ?></div>
  <h1>Nueva contraseña</h1>
  <p>Elige una que combine letras y números</p>
</div>
<div class="acceso-cuerpo">
  <?php foreach (($flashes ?? []) as $f): ?>
    <div class="acceso-aviso acceso-aviso--<?= e($f['tipo'] === 'error' ? 'error' : 'aviso') ?>">
      <?= icon('alert') ?><span><?= e($f['texto']) ?></span>
    </div>
  <?php endforeach; ?>
  <form method="post" action="<?= e(url('restablecer/' . $token)) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="acceso-campo">
      <label for="password">Nueva contraseña</label>
      <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
      <button type="button" class="ver-clave" data-para="password" aria-label="Mostrar contraseña"><?= icon('eye') ?></button>
    </div>
    <div class="acceso-campo">
      <label for="password2">Repite la contraseña</label>
      <input type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password">
    </div>
    <button class="acceso-btn" type="submit"><?= icon('save') ?> Guardar y continuar</button>
  </form>
</div>
<div class="acceso-pie"><a href="<?= e(url('ingresar')) ?>">Volver a ingresar</a></div>
