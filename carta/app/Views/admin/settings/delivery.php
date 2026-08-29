<?php
/** Zonas de entrega. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Entregas');
$cur = $cfg['currency'];
?>
<?php $view->start('content') ?>
<?php $view->partial('admin/settings/_tabs'); ?>

<form method="post" action="<?= e(mg_url('/panel/ajustes/entrega')) ?>">
  <?= Csrf::field() ?>
  <div class="card">
    <div class="card-head"><h2>Zonas de entrega</h2><p>El comensal elige su zona y el costo se suma solo al total.</p></div>

    <div id="zone-rows">
      <?php
      $rows = $zones ? $zones : array(array('id' => 0, 'name' => '', 'fee' => '', 'min_order' => '', 'eta_minutes' => 40));
      foreach ($rows as $z): ?>
        <div class="grid grid-4" style="margin-bottom:.6rem;align-items:end">
          <input type="hidden" name="zone_id[]" value="<?= (int)$z['id'] ?>">
          <div class="field" style="margin:0"><label>Zona</label>
            <input type="text" class="input" name="zone_name[]" maxlength="120" value="<?= e($z['name']) ?>" placeholder="Zona 10"></div>
          <div class="field" style="margin:0"><label>Costo (<?= e($cur) ?>)</label>
            <input class="input" name="zone_fee[]" type="number" step="0.01" min="0" value="<?= e($z['fee']) ?>"></div>
          <div class="field" style="margin:0"><label>Pedido mínimo</label>
            <input class="input" name="zone_min[]" type="number" step="0.01" min="0" value="<?= e($z['min_total']) ?>"></div>
          <div class="field" style="margin:0"><label>Minutos</label>
            <input class="input" name="zone_eta[]" type="number" min="5" max="240" value="<?= e($z['minutes']) ?>"></div>
        </div>
      <?php endforeach; ?>
    </div>

    <button class="btn btn-ghost btn-sm" type="button" id="add-zone">Agregar zona</button>
    <div class="row mt-2"><button class="btn" type="submit">Guardar zonas</button></div>
  </div>
</form>

<?php if ($zones): ?>
  <div class="card mt-2">
    <div class="card-head"><h3>Eliminar una zona</h3></div>
    <div class="row">
      <?php foreach ($zones as $z): ?>
        <form method="post" action="<?= e(mg_url('/panel/ajustes/entrega')) ?>" data-confirm="¿Eliminar la zona «<?= e($z['name']) ?>»?">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$z['id'] ?>">
          <button class="btn btn-ghost btn-sm" type="submit"><?= e($z['name']) ?> ×</button>
        </form>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
<?php $view->stop() ?>

<?php $view->start('scripts') ?>
<script>
document.getElementById('add-zone').addEventListener('click', function () {
  var box = document.getElementById('zone-rows');
  var row = document.createElement('div');
  row.className = 'grid grid-4';
  row.style.cssText = 'margin-bottom:.6rem;align-items:end';
  row.innerHTML = '<input type="hidden" name="zone_id[]" value="0">'
    + '<div class="field" style="margin:0"><label>Zona</label><input type="text" class="input" name="zone_name[]" maxlength="120" placeholder="Zona nueva"></div>'
    + '<div class="field" style="margin:0"><label>Costo</label><input class="input" name="zone_fee[]" type="number" step="0.01" min="0" value="0"></div>'
    + '<div class="field" style="margin:0"><label>Pedido mínimo</label><input class="input" name="zone_min[]" type="number" step="0.01" min="0" value="0"></div>'
    + '<div class="field" style="margin:0"><label>Minutos</label><input class="input" name="zone_eta[]" type="number" min="5" max="240" value="40"></div>';
  box.appendChild(row);
  row.querySelector('input[name="zone_name[]"]').focus();
});
</script>
<?php $view->stop() ?>
