<?php
/** Cobros, propinas y fidelidad. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Cobros');
$r = $cfg;
$get = function ($k, $d = '') use ($r) { return isset($r[$k]) && $r[$k] !== '' ? $r[$k] : $d; };
?>
<?php $view->start('content') ?>
<?php $view->partial('admin/settings/_tabs'); ?>

<form method="post" action="<?= e(mg_url('/panel/ajustes/pagos')) ?>">
  <?= Csrf::field() ?>
  <div class="grid grid-2">
    <div class="card">
      <div class="card-head"><h2>Propina</h2></div>
      <label class="switch"><input type="checkbox" name="tip_enabled" value="1" <?= $r['tip_enabled'] === '1' ? 'checked' : '' ?>>
        <span class="switch-track" aria-hidden="true"></span><span>Ofrecer propina al confirmar el pedido</span></label>
      <div class="field mt-2"><label for="tip_options">Porcentajes sugeridos</label>
        <input type="text" class="input" id="tip_options" name="tip_options" value="<?= e($r['tip_options']) ?>" placeholder="10,15,20">
        <p class="field-hint">Separados por coma. Siempre se muestra también «Sin propina».</p></div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Pagos</h2></div>
      <div class="field"><label for="bank_info">Datos para transferencia</label>
        <textarea class="textarea" id="bank_info" name="bank_info" rows="4" maxlength="2000"
                  placeholder="Banco Industrial&#10;Cuenta monetaria 123-456789-0&#10;A nombre de …"><?= e($r['bank_info']) ?></textarea>
        <p class="field-hint">Aparecen con un botón para copiar en la pantalla de seguimiento.</p></div>
      <div class="field"><label for="payment_link">Link de pago en línea</label>
        <input type="text" class="input" id="payment_link" name="payment_link" value="<?= e($r['payment_link']) ?>" placeholder="https://…">
        <p class="field-hint">Si lo dejas vacío, el botón no se muestra.</p></div>
      <div class="field"><label for="payment_methods">Formas de pago que acepta</label>
        <input type="text" class="input" id="payment_methods" name="payment_methods_text" value="<?= e($r['payment_methods']) ?>" disabled>
        <div class="row mt-1" style="flex-wrap:wrap;gap:.5rem">
          <?php foreach (array('efectivo','tarjeta','transferencia','link') as $pm): ?>
            <label class="chip-check"><input type="checkbox" name="payment_methods[]" value="<?= e($pm) ?>"
              <?= in_array($pm, explode(',', $r['payment_methods']), true) ? 'checked' : '' ?>> <?= e(ucfirst($pm)) ?></label>
          <?php endforeach; ?>
        </div></div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Fidelidad</h2></div>
      <div class="field"><label for="loyalty_points_per_100">Puntos por cada 100 de consumo</label>
        <input class="input" id="loyalty_points_per_100" name="loyalty_points_per_100" type="number" min="0" max="100"
               value="<?= e($get('loyalty_points_per_100', '0')) ?>">
        <p class="field-hint">0 desactiva los puntos. Se acumulan por teléfono al cobrar.</p></div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Impresión y reseñas</h2></div>
      <div class="field"><label for="printer_width">Ancho del ticket</label>
        <select class="select" id="printer_width" name="printer_width">
          <option value="80" <?= $get('printer_width', '80') === '80' ? 'selected' : '' ?>>80 mm</option>
          <option value="58" <?= $get('printer_width', '80') === '58' ? 'selected' : '' ?>>58 mm</option>
        </select></div>
      <label class="switch"><input type="checkbox" name="kds_sound" value="1" <?= $get('kds_sound', '1') !== '0' ? 'checked' : '' ?>>
        <span class="switch-track" aria-hidden="true"></span><span>Sonido en la pantalla de cocina al entrar un pedido</span></label>
      <div class="field mt-2"><label for="kds_late_min">Alerta de retraso en cocina (minutos)</label>
        <input class="input" id="kds_late_min" name="kds_late_min" type="number" min="3" max="90" value="<?= e($get('kds_late_min', '18')) ?>"></div>
    </div>
  </div>

  <div class="row mt-2"><button class="btn" type="submit">Guardar</button></div>
</form>
<?php $view->stop() ?>
