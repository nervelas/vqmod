<?php
/** Alta y edición de platillo. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$isNew = !$product;
$p = $product;
$view->set('title', $isNew ? 'Nuevo platillo' : $p['name']);
$cur = $cfg['currency'];
$mask = $p ? (int)$p['days_mask'] : 127;
$days = array('Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb');
$tags = $p ? array_filter(array_map('trim', explode(',', $p['tags']))) : array();
$allTags = array('popular', 'nuevo', 'picante', 'vegano', 'vegetariano', 'sin_gluten', 'recomendado', 'para_compartir');
?>
<?php $view->start('content') ?>
<form method="post" enctype="multipart/form-data"
      action="<?= e(mg_url('/panel/menu/producto/' . ($isNew ? 'nuevo' : (int)$p['id']))) ?>">
  <?= Csrf::field() ?>

  <div class="grid grid-side">
    <div class="stack">
      <div class="card">
        <div class="card-head"><h2>Lo que ve el comensal</h2></div>
        <div class="grid grid-2">
          <div class="field"><label for="name">Nombre *</label>
            <input type="text" class="input" id="name" name="name" required maxlength="160" value="<?= e($p ? $p['name'] : '') ?>"
                   placeholder="Rib eye maduración 45 días"></div>
          <div class="field"><label for="name_en">Nombre en inglés</label>
            <input type="text" class="input" id="name_en" name="name_en" maxlength="160" value="<?= e($p ? $p['name_en'] : '') ?>"></div>
        </div>
        <div class="field"><label for="description">Descripción</label>
          <textarea class="textarea" id="description" name="description" rows="3" maxlength="2000"
                    placeholder="400 g a la parrilla de leña, mantequilla de hierbas y sal de Maldon."><?= e($p ? $p['description'] : '') ?></textarea>
          <p class="field-hint">Una o dos líneas apetitosas. En el menú se recortan a 92 caracteres.</p></div>
        <div class="field"><label for="description_en">Descripción en inglés</label>
          <textarea class="textarea" id="description_en" name="description_en" rows="2" maxlength="2000"><?= e($p ? $p['description_en'] : '') ?></textarea></div>

        <p class="label">Etiquetas</p>
        <div class="row" style="gap:.4rem">
          <?php foreach ($allTags as $t): ?>
            <label class="chip" style="cursor:pointer">
              <input type="checkbox" name="tags[]" value="<?= e($t) ?>" <?= in_array($t, $tags, true) ? 'checked' : '' ?> style="margin-right:.3rem">
              <?= e(mg_tag_label($t)) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h3>Presentaciones</h3><p>Tamaños o términos con diferencia de precio.</p></div>
        <div id="variant-rows">
          <?php
          $rows = $variants ? $variants : array();
          if (!$rows) { $rows = array(array('id' => 0, 'name' => '', 'price_delta' => '')); }
          foreach ($rows as $i => $v): ?>
            <div class="row" style="flex-wrap:nowrap;margin-bottom:.5rem">
              <input type="hidden" name="variant_id[]" value="<?= (int)$v['id'] ?>">
              <input type="text" class="input" name="variant_name[]" maxlength="80" placeholder="Ej. 250 g / Término medio" value="<?= e($v['name']) ?>">
              <input class="input" name="variant_delta[]" type="number" step="0.01" style="max-width:130px"
                     placeholder="+0.00" value="<?= $v['price_delta'] !== '' ? e($v['price_delta']) : '' ?>">
            </div>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-ghost btn-sm" type="button" id="add-variant">Agregar presentación</button>
      </div>

      <div class="card">
        <div class="card-head"><h3>Modificadores</h3><p>Reutiliza grupos definidos en la sección Modificadores.</p></div>
        <?php if ($groups): ?>
          <div class="grid grid-2">
            <?php foreach ($groups as $g): ?>
              <label class="opt">
                <input type="checkbox" name="groups[]" value="<?= (int)$g['id'] ?>" <?= in_array((int)$g['id'], array_map('intval', $linked), true) ? 'checked' : '' ?>>
                <span class="opt-mark is-square" aria-hidden="true"></span>
                <span class="opt-name"><?= e($g['name']) ?><?= (int)$g['is_required'] === 1 ? ' · obligatorio' : '' ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="muted" style="font-size:var(--step--1)">Todavía no creaste grupos.
            <a class="link-line gold" href="<?= e(mg_url('/panel/menu/modificadores')) ?>">Crear el primero</a>.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="stack">
      <div class="card">
        <div class="card-head"><h3>Fotografía</h3></div>
        <div id="prod-preview"><?= mg_img($p ? $p['image'] : '', array('alt' => '', 'sizes' => '340px', 'ratio' => '4/3')) ?></div>
        <div class="field mt-2"><label for="image">Imagen principal</label>
          <input class="input" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-preview="prod-preview">
          <p class="field-hint">Se recorta y comprime sola a WebP en tres tamaños. Ideal: horizontal, fondo oscuro.</p></div>
        <div class="field"><label for="images">Imágenes adicionales</label>
          <input class="input" id="images" name="images[]" type="file" multiple accept="image/jpeg,image/png,image/webp"></div>
        <?php if (!empty($images)): ?>
          <div class="row mt-1">
            <?php foreach ($images as $im): ?>
              <span style="position:relative">
                <span class="cell-thumb" style="width:64px;height:64px"><?= mg_img($im['path'], array('alt' => '', 'sizes' => '64px')) ?></span>
                <button class="cart-remove" type="button" data-quick="<?= e(mg_url('/panel/menu/imagen/' . (int)$im['id'] . '/eliminar')) ?>">Quitar</button>
              </span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-head"><h3>Precio y cocina</h3></div>
        <div class="grid grid-2">
          <div class="field"><label for="price">Precio (<?= e($cur) ?>) *</label>
            <input class="input" id="price" name="price" type="number" step="0.01" min="0" required value="<?= e($p ? $p['price'] : '') ?>"></div>
          <div class="field"><label for="prep_minutes">Minutos de preparación</label>
            <input class="input" id="prep_minutes" name="prep_minutes" type="number" min="0" max="240" value="<?= e($p ? $p['prep_minutes'] : 15) ?>"></div>
        </div>
        <div class="field"><label for="category_id">Categoría</label>
          <select class="select" id="category_id" name="category_id">
            <option value="0">Sin categoría</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $p && (int)$p['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>

      <div class="card">
        <div class="card-head"><h3>Visibilidad</h3></div>
        <div class="stack" style="gap:.8rem">
          <label class="switch"><input type="checkbox" name="is_active" value="1" <?= (!$p || (int)$p['is_active'] === 1) ? 'checked' : '' ?>><span class="switch-track" aria-hidden="true"></span><span>Visible en el menú</span></label>
          <label class="switch"><input type="checkbox" name="is_featured" value="1" <?= $p && (int)$p['is_featured'] === 1 ? 'checked' : '' ?>><span class="switch-track" aria-hidden="true"></span><span>Destacado</span></label>
          <label class="switch"><input type="checkbox" name="is_sold_out" value="1" <?= $p && (int)$p['is_sold_out'] === 1 ? 'checked' : '' ?>><span class="switch-track" aria-hidden="true"></span><span>Agotado hoy</span></label>
        </div>

        <p class="label mt-2">Días disponible</p>
        <div class="row" style="gap:.4rem">
          <?php foreach ($days as $i => $d): ?>
            <label class="chip" style="cursor:pointer">
              <input type="checkbox" name="days[]" value="<?= $i ?>" <?= ($mask & (1 << $i)) ? 'checked' : '' ?> style="margin-right:.3rem"><?= e($d) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="grid grid-2 mt-2">
          <div class="field"><label for="available_from">Desde</label>
            <input class="input" id="available_from" name="available_from" type="time" value="<?= e($p && $p['available_from'] ? substr($p['available_from'], 0, 5) : '') ?>"></div>
          <div class="field"><label for="available_to">Hasta</label>
            <input class="input" id="available_to" name="available_to" type="time" value="<?= e($p && $p['available_to'] ? substr($p['available_to'], 0, 5) : '') ?>"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="row mt-2">
    <button class="btn" type="submit">Guardar platillo</button>
    <a class="btn btn-ghost" href="<?= e(mg_url('/panel/menu')) ?>">Volver al menú</a>
  </div>
</form>

<?php if (!$isNew): ?>
  <div class="row mt-3">
    <button class="btn btn-ghost btn-sm" type="button" data-quick="<?= e(mg_url('/panel/menu/producto/' . (int)$p['id'] . '/duplicar')) ?>">Duplicar platillo</button>
    <form method="post" action="<?= e(mg_url('/panel/menu/producto/' . (int)$p['id'] . '/eliminar')) ?>"
          data-confirm="¿Eliminar «<?= e($p['name']) ?>»? No se puede deshacer.">
      <?= Csrf::field() ?>
      <button class="btn btn-ghost btn-sm" type="submit" style="color:var(--ember);border-color:rgba(196,80,43,.4)">Eliminar</button>
    </form>
  </div>
<?php endif; ?>
<?php $view->stop() ?>

<?php $view->start('scripts') ?>
<script>
document.getElementById('add-variant').addEventListener('click', function () {
  var box = document.getElementById('variant-rows');
  var row = document.createElement('div');
  row.className = 'row';
  row.style.cssText = 'flex-wrap:nowrap;margin-bottom:.5rem';
  row.innerHTML = '<input type="hidden" name="variant_id[]" value="0">'
    + '<input type="text" class="input" name="variant_name[]" maxlength="80" placeholder="Ej. 250 g / Término medio">'
    + '<input class="input" name="variant_delta[]" type="number" step="0.01" style="max-width:130px" placeholder="+0.00">';
  box.appendChild(row);
  row.querySelector('input[name="variant_name[]"]').focus();
});
</script>
<?php $view->stop() ?>
