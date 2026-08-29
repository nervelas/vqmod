<?php
/** Cobros, propinas y fidelidad. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Cobros');
$r = $restaurant;
$s = $settings;
$get = function ($k, $d = '') use ($s) { return isset($s[$k]) ? $s[$k] : $d; };
?>
<?php $view->start('content') ?>
<?php $view->partial('admin/settings/_tabs'); ?>

<form method="post" action="<?= e(mg_url('/panel/ajustes/pagos')) ?>">
  <?= Csrf::field() ?>
  <div class="grid grid-2">
    <div class="card">
      <div class="card-head"><h2>Propina</h2></div>
      <label class="switch"><input type="checkbox" name="tip_enabled" value="1" <?= (int)$r['tip_enabled'] === 1 ? 'checked' : '' ?>>
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
      <div class="field"><label for="payment_url">Link de pago en línea</label>
        <input type="text" class="input" id="payment_url" name="payment_url" value="<?= e($r['payment_url']) ?>" placeholder="https://…">
        <p class="field-hint">Si lo dejas vacío, el botón no se muestra.</p></div>
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
      <label class="switch"><input type="checkbox" name="review_prompt" value="1" <?= $get('review_prompt', '1') !== '0' ? 'checked' : '' ?>>
        <span class="switch-track" aria-hidden="true"></span><span>Invitar a dejar reseña al final del pedido</span></label>
    </div>
  </div>

  <div class="row mt-2"><button class="btn" type="submit">Guardar</button></div>
</form>
<?php $view->stop() ?>
