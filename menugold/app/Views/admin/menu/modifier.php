<?php
/** Alta y edición de un grupo de modificadores. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$isNew = !$group;
$g = $group;
$view->set('title', $isNew ? 'Nuevo grupo' : $g['name']);
$cur = $restaurant['currency'];
?>
<?php $view->start('content') ?>
<form method="post" action="<?= e(mg_url('/panel/menu/modificador/' . ($isNew ? 'nuevo' : (int)$g['id']))) ?>">
  <?= Csrf::field() ?>
  <div class="grid grid-side">
    <div class="card">
      <div class="card-head"><h2>Opciones</h2><p>El precio se suma al del platillo.</p></div>
      <div id="option-rows">
        <?php
        $rows = $options ? $options : array(array('id' => 0, 'name' => '', 'price_delta' => ''));
        foreach ($rows as $o): ?>
          <div class="row" style="flex-wrap:nowrap;margin-bottom:.5rem">
            <input type="hidden" name="option_id[]" value="<?= (int)$o['id'] ?>">
            <input type="text" class="input" name="option_name[]" maxlength="120" placeholder="Ej. Término medio / Tocino extra" value="<?= e($o['name']) ?>">
            <input class="input" name="option_delta[]" type="number" step="0.01" style="max-width:130px" placeholder="0.00"
                   value="<?= $o['price_delta'] !== '' ? e($o['price_delta']) : '' ?>">
          </div>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-ghost btn-sm" type="button" id="add-option">Agregar opción</button>
    </div>

    <div class="card">
      <div class="card-head"><h3>Reglas</h3></div>
      <div class="field"><label for="name">Nombre del grupo *</label>
        <input type="text" class="input" id="name" name="name" required maxlength="120" value="<?= e($g ? $g['name'] : '') ?>" placeholder="Término de la carne"></div>
      <div class="field"><label for="name_en">Nombre en inglés</label>
        <input type="text" class="input" id="name_en" name="name_en" maxlength="120" value="<?= e($g ? $g['name_en'] : '') ?>"></div>
      <div class="field"><label for="help">Texto de ayuda</label>
        <input type="text" class="input" id="help" name="help" maxlength="180" value="<?= e($g ? $g['help'] : '') ?>" placeholder="Elige uno"></div>
      <div class="field"><label for="type">Tipo</label>
        <select class="select" id="type" name="type">
          <option value="single" <?= $g && $g['type'] === 'single' ? 'selected' : '' ?>>Una sola opción</option>
          <option value="multi" <?= $g && $g['type'] === 'multi' ? 'selected' : '' ?>>Varias opciones</option>
        </select></div>
      <div class="grid grid-2">
        <div class="field"><label for="min_select">Mínimo</label>
          <input class="input" id="min_select" name="min_select" type="number" min="0" max="20" value="<?= e($g ? $g['min_select'] : 0) ?>"></div>
        <div class="field"><label for="max_select">Máximo</label>
          <input class="input" id="max_select" name="max_select" type="number" min="0" max="20" value="<?= e($g ? $g['max_select'] : 3) ?>"></div>
      </div>
      <label class="switch"><input type="checkbox" name="is_required" value="1" <?= $g && (int)$g['is_required'] === 1 ? 'checked' : '' ?>>
        <span class="switch-track" aria-hidden="true"></span><span>Obligatorio para pedir</span></label>
    </div>
  </div>

  <div class="row mt-2">
    <button class="btn" type="submit">Guardar grupo</button>
    <a class="btn btn-ghost" href="<?= e(mg_url('/panel/menu/modificadores')) ?>">Cancelar</a>
  </div>
</form>

<?php if (!$isNew): ?>
  <form class="mt-3" method="post" action="<?= e(mg_url('/panel/menu/modificador/' . (int)$g['id'] . '/eliminar')) ?>"
        data-confirm="¿Eliminar el grupo «<?= e($g['name']) ?>»? Se quitará de todos los platillos.">
    <?= Csrf::field() ?>
    <button class="btn btn-ghost btn-sm" type="submit" style="color:var(--ember);border-color:rgba(196,80,43,.4)">Eliminar grupo</button>
  </form>
<?php endif; ?>
<?php $view->stop() ?>

<?php $view->start('scripts') ?>
<script>
document.getElementById('add-option').addEventListener('click', function () {
  var box = document.getElementById('option-rows');
  var row = document.createElement('div');
  row.className = 'row';
  row.style.cssText = 'flex-wrap:nowrap;margin-bottom:.5rem';
  row.innerHTML = '<input type="hidden" name="option_id[]" value="0">'
    + '<input type="text" class="input" name="option_name[]" maxlength="120" placeholder="Nueva opción">'
    + '<input class="input" name="option_delta[]" type="number" step="0.01" style="max-width:130px" placeholder="0.00">';
  box.appendChild(row);
  row.querySelector('input[name="option_name[]"]').focus();
});
</script>
<?php $view->stop() ?>
