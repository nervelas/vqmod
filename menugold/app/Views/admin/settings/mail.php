<?php
/** Configuración SMTP. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Correo');
$s = $settings;
$get = function ($k, $d = '') use ($s) { return isset($s[$k]) ? $s[$k] : $d; };
?>
<?php $view->start('content') ?>
<?php $view->partial('admin/settings/_tabs'); ?>

<div class="grid grid-side">
  <form class="card" method="post" action="<?= e(mg_url('/panel/ajustes/correo')) ?>">
    <?= Csrf::field() ?>
    <div class="card-head"><h2>Servidor SMTP</h2><p>Los datos te los da tu hosting (cPanel → Cuentas de correo).</p></div>
    <div class="grid grid-2">
      <div class="field"><label for="smtp_host">Servidor</label><input type="text" class="input" id="smtp_host" name="smtp_host" value="<?= e($get('smtp_host')) ?>" placeholder="mail.tudominio.com"></div>
      <div class="field"><label for="smtp_port">Puerto</label><input class="input" id="smtp_port" name="smtp_port" type="number" value="<?= e($get('smtp_port', '587')) ?>"></div>
      <div class="field"><label for="smtp_user">Usuario</label><input type="text" class="input" id="smtp_user" name="smtp_user" value="<?= e($get('smtp_user')) ?>" autocomplete="off"></div>
      <div class="field"><label for="smtp_pass">Contraseña</label><input class="input" id="smtp_pass" name="smtp_pass" type="password" autocomplete="new-password" placeholder="<?= $get('smtp_pass') !== '' ? 'Sin cambios' : '' ?>"></div>
      <div class="field"><label for="smtp_secure">Cifrado</label>
        <select class="select" id="smtp_secure" name="smtp_secure">
          <option value="tls" <?= $get('smtp_secure', 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
          <option value="ssl" <?= $get('smtp_secure') === 'ssl' ? 'selected' : '' ?>>SSL</option>
          <option value="none" <?= $get('smtp_secure') === 'none' ? 'selected' : '' ?>>Sin cifrado</option>
        </select></div>
      <div class="field"><label for="smtp_from">Remitente</label><input class="input" id="smtp_from" name="smtp_from" type="email" value="<?= e($get('smtp_from')) ?>"></div>
    </div>
    <div class="field"><label for="smtp_from_name">Nombre del remitente</label>
      <input type="text" class="input" id="smtp_from_name" name="smtp_from_name" value="<?= e($get('smtp_from_name', $restaurant['name'])) ?>"></div>
    <button class="btn" type="submit">Guardar</button>
  </form>

  <form class="card" method="post" action="<?= e(mg_url('/panel/ajustes/correo')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="test">
    <div class="card-head"><h3>Probar el envío</h3></div>
    <div class="field"><label for="test_email">Enviar a</label>
      <input class="input" id="test_email" name="test_email" type="email" value="<?= e($restaurant['email']) ?>"></div>
    <button class="btn btn-ghost btn-block" type="submit">Enviar prueba</button>
    <p class="field-hint">Si falla, el detalle queda en /storage/logs.</p>
  </form>
</div>
<?php $view->stop() ?>
