<?php use MenuGold\Core\View; View::set('titulo', 'Recuperar contraseña'); ?>
<div class="acceso-cab">
  <div class="acceso-logo"><?= icon('lock') ?></div>
  <h1>Recuperar acceso</h1>
  <p>Te enviaremos un enlace a tu correo</p>
</div>
<div class="acceso-cuerpo">
  <?php foreach (($flashes ?? []) as $f): ?>
    <div class="acceso-aviso acceso-aviso--<?= e($f['tipo'] === 'exito' ? 'exito' : ($f['tipo'] === 'error' ? 'error' : 'aviso')) ?>">
      <?= icon($f['tipo'] === 'error' ? 'alert' : 'info') ?><span><?= e($f['texto']) ?></span>
    </div>
  <?php endforeach; ?>
  <form method="post" action="<?= e(url('recuperar')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="acceso-campo">
      <label for="email">Tu correo electrónico</label>
      <input type="email" id="email" name="email" required autocomplete="email" autofocus maxlength="190">
    </div>
    <div class="acceso-campo">
      <label for="captcha">Verificación: ¿cuánto es <?= e($captcha['pregunta']) ?>?</label>
      <input type="number" id="captcha" name="captcha" required inputmode="numeric" style="max-width:140px">
    </div>
    <button class="acceso-btn" type="submit"><?= icon('send') ?> Enviarme el enlace</button>
  </form>
</div>
<div class="acceso-pie"><a href="<?= e(url('ingresar')) ?>">Volver a ingresar</a></div>
