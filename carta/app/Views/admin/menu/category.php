<?php
/** Alta y edición de categoría. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$isNew = !$category;
$view->set('title', $isNew ? 'Nueva categoría' : 'Categoría');
$c = $category;
$mask = $c ? (int)$c['days_mask'] : 127;
$days = array('Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb');
?>
<?php $view->start('content') ?>
<form method="post" enctype="multipart/form-data"
      action="<?= e(mg_url('/panel/menu/categoria/' . ($isNew ? 'nueva' : (int)$c['id']))) ?>">
  <?= Csrf::field() ?>
  <div class="grid grid-side">
    <div class="card">
      <div class="card-head"><h2><?= $isNew ? 'Datos de la categoría' : e($c['name']) ?></h2></div>
      <div class="grid grid-2">
        <div class="field"><label for="name">Nombre *</label>
          <input type="text" class="input" id="name" name="name" required maxlength="120" value="<?= e($c ? $c['name'] : '') ?>"></div>
        <div class="field"><label for="name_en">Nombre en inglés</label>
          <input type="text" class="input" id="name_en" name="name_en" maxlength="120" value="<?= e($c ? $c['name_en'] : '') ?>"></div>
      </div>
      <div class="field"><label for="description">Descripción corta</label>
        <input type="text" class="input" id="description" name="description" maxlength="255" value="<?= e($c ? $c['description'] : '') ?>"
               placeholder="Cortes madurados y parrilla de leña"></div>
      <div class="field"><label for="description_en">Descripción en inglés</label>
        <input type="text" class="input" id="description_en" name="description_en" maxlength="255" value="<?= e($c ? $c['description_en'] : '') ?>"></div>
      <div class="field" style="max-width:180px"><label for="roman">Numeral romano</label>
        <input type="text" class="input" id="roman" name="roman" maxlength="8" value="<?= e($c ? $c['roman'] : '') ?>" placeholder="I">
        <p class="field-hint">Se muestra junto al título en el menú.</p></div>
    </div>

    <div class="stack">
      <div class="card">
        <div class="card-head"><h3>Imagen</h3></div>
        <div id="cat-preview"><?= mg_img($c ? $c['image'] : '', array('alt' => '', 'sizes' => '340px', 'ratio' => '4/3', 'class' => 'zoomer')) ?></div>
        <div class="field mt-2"><label for="image">Cambiar imagen</label>
          <input class="input" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-preview="cat-preview"></div>
      </div>

      <div class="card">
        <div class="card-head"><h3>Disponibilidad</h3></div>
        <label class="switch" style="margin-bottom:1rem">
          <input type="checkbox" name="is_active" value="1" <?= (!$c || (int)$c['is_active'] === 1) ? 'checked' : '' ?>>
          <span class="switch-track" aria-hidden="true"></span>
          <span>Visible en el menú</span>
        </label>
        <p class="label">Días</p>
        <div class="row" style="gap:.4rem;margin-bottom:1rem">
          <?php foreach ($days as $i => $d): ?>
            <label class="chip" style="cursor:pointer">
              <input type="checkbox" name="days[]" value="<?= $i ?>" <?= ($mask & (1 << $i)) ? 'checked' : '' ?> style="margin-right:.3rem">
              <?= e($d) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="field-hint">Desmarca los días en que esta categoría no se sirve.</p>
      </div>
    </div>
  </div>

  <div class="row mt-2">
    <button class="btn" type="submit">Guardar categoría</button>
    <a class="btn btn-ghost" href="<?= e(mg_url('/panel/menu')) ?>">Cancelar</a>
  </div>
</form>

<?php if (!$isNew): ?>
  <form class="mt-3" method="post" action="<?= e(mg_url('/panel/menu/categoria/' . (int)$c['id'] . '/eliminar')) ?>"
        data-confirm="¿Eliminar la categoría «<?= e($c['name']) ?>»? Debe estar vacía.">
    <?= Csrf::field() ?>
    <button class="btn btn-ghost btn-sm" type="submit" style="color:var(--ember);border-color:rgba(196,80,43,.4)">Eliminar categoría</button>
  </form>
<?php endif; ?>
<?php $view->stop() ?>
