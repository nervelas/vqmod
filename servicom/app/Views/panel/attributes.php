<?php $e = $edit; ?>
<div class="cols cols--sidebar">
  <div class="card">
    <div class="card__head"><span class="secnum">01/</span><h2><?= e(count($attrs)) ?> atributos técnicos</h2></div>
    <div class="card__body card__body--flush tablescroll">
      <?php if (!$attrs): ?>
        <p class="muted" style="padding:36px;text-align:center;margin:0">Defina los atributos con los que el cliente filtrará su catálogo (material, medida, norma…).</p>
      <?php else: ?>
        <table class="datatable" style="border:0;border-radius:0">
          <caption class="sr-only">Atributos técnicos</caption>
          <thead><tr><th scope="col">Etiqueta</th><th scope="col">Código</th><th scope="col">Tipo</th><th scope="col">Aplica a</th><th scope="col">Filtro</th><th scope="col"></th></tr></thead>
          <tbody>
            <?php foreach ($attrs as $a): ?>
              <tr>
                <td><strong><?= e($a['label']) ?></strong><?= $a['unit'] ? ' <span class="small muted">(' . e($a['unit']) . ')</span>' : '' ?></td>
                <td><span class="code-chip"><?= e($a['code']) ?></span></td>
                <td class="small"><?= e(ucfirst((string) $a['type'])) ?></td>
                <td class="small muted"><?= e($a['category_name'] ?: 'Todas las categorías') ?></td>
                <td><?= $a['filterable'] ? '<span class="badge badge--ok">Sí</span>' : '<span class="badge">No</span>' ?></td>
                <td class="nowrap">
                  <a class="btn btn--ghost btn--xs" href="<?= e(url('/panel/atributos?editar=' . $a['id'])) ?>">Editar</a>
                  <?php if (\App\Core\Auth::isAdmin()): ?><button class="btn btn--ghost btn--xs" type="submit" form="dela<?= e($a['id']) ?>">Eliminar</button><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <form class="card" method="post" action="<?= e(url('/panel/atributos')) ?>">
    <?= csrf_field() ?>
    <?php if ($e): ?><input type="hidden" name="id" value="<?= e($e['id']) ?>"><?php endif; ?>
    <div class="card__head"><span class="secnum">02/</span><h2><?= $e ? 'Editar atributo' : 'Nuevo atributo' ?></h2></div>
    <div class="card__body">
      <div class="field"><label for="label">Etiqueta *</label>
        <input class="input" id="label" name="label" maxlength="90" required value="<?= e($e['label'] ?? '') ?>" placeholder="Material"></div>
      <div class="row-2">
        <div class="field"><label for="code">Código interno</label>
          <input class="input" id="code" name="code" maxlength="50" value="<?= e($e['code'] ?? '') ?>" placeholder="material"></div>
        <div class="field"><label for="unit">Unidad</label>
          <input class="input" id="unit" name="unit" maxlength="20" value="<?= e($e['unit'] ?? '') ?>" placeholder="mm, °C, bar"></div>
      </div>
      <div class="field"><label for="type">Tipo de dato</label>
        <select class="select" id="type" name="type">
          <?php foreach (['texto' => 'Texto libre', 'numero' => 'Número', 'lista' => 'Lista de opciones', 'booleano' => 'Sí / No'] as $k => $lbl): ?>
            <option value="<?= e($k) ?>"<?= ($e['type'] ?? '') === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="field"><label for="options">Opciones (una por línea)</label>
        <textarea class="textarea" id="options" name="options" rows="4" placeholder="Carbón / Cerámica&#10;Carburo de silicio&#10;Tungsteno"><?= e(implode("\n", $e ? \App\Models\AttributeDef::options($e) : [])) ?></textarea></div>
      <div class="field"><label for="category_id">Aplica a la categoría</label>
        <select class="select" id="category_id" name="category_id">
          <option value="">Todas las categorías</option>
          <?php foreach ($options as $o): ?>
            <option value="<?= e($o['id']) ?>"<?= (int) ($e['category_id'] ?? 0) === $o['id'] ? ' selected' : '' ?>><?= e($o['label']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="field"><label for="sort">Orden</label><input class="input" id="sort" name="sort" type="number" value="<?= e((int) ($e['sort'] ?? 0)) ?>"></div>
      <label class="check"><input type="checkbox" name="filterable" value="1"<?= (!$e || $e['filterable']) ? ' checked' : '' ?>><span>Usar como filtro en el catálogo</span></label>
      <div class="flex" style="gap:8px">
        <button class="btn btn--accent" type="submit"><?= $e ? 'Guardar' : 'Crear atributo' ?></button>
        <?php if ($e): ?><a class="btn btn--ghost" href="<?= e(url('/panel/atributos')) ?>">Cancelar</a><?php endif; ?>
      </div>
    </div>
  </form>
</div>
<?php foreach ($attrs as $a): ?>
  <form id="dela<?= e($a['id']) ?>" method="post" action="<?= e(url('/panel/atributos/' . $a['id'] . '/eliminar')) ?>" class="hide" data-confirm="¿Eliminar el atributo «<?= e($a['label']) ?>» y sus valores en todos los productos?"><?= csrf_field() ?></form>
<?php endforeach; ?>
