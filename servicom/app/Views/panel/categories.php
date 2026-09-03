<?php $e = $edit; ?>
<div class="cols cols--sidebar">
  <div class="card">
    <div class="card__head"><span class="secnum">01/</span><h2>Estructura del catálogo</h2>
      <span class="small muted ml-auto">Arrastre el asa para reordenar</span></div>
    <div class="card__body card__body--flush">
      <div data-sortable>
        <?php
        $render = static function (array $nodes, int $depth) use (&$render, $company) {
            foreach ($nodes as $n) { ?>
              <div data-row data-id="<?= e($n['id']) ?>" data-parent="<?= e((int) ($n['parent_id'] ?? 0)) ?>"
                   style="display:flex;align-items:center;gap:12px;padding:11px 18px 11px <?= e(18 + $depth * 26) ?>px;border-bottom:1px solid var(--paper-2)">
                <span data-handle style="cursor:grab;color:var(--steel-2);touch-action:none" aria-hidden="true">⠿</span>
                <?php if ($n['image']): ?><img class="thumb" src="<?= e(upload($n['image'])) ?>" alt="" aria-hidden="true" width="46" height="36"><?php endif; ?>
                <span style="flex:1"><strong><?= e($n['name']) ?></strong>
                  <?php if (!$n['active']): ?> <span class="badge">Oculta</span><?php endif; ?>
                  <br><span class="small muted"><?= e((int) $n['product_count']) ?> productos · /<?= e($n['slug']) ?></span></span>
                <a class="btn btn--ghost btn--xs" href="<?= e(url('/panel/categorias?editar=' . $n['id'])) ?>">Editar</a>
                <?php if (\App\Core\Auth::isAdmin()): ?>
                  <button class="btn btn--ghost btn--xs" type="submit" form="delcat<?= e($n['id']) ?>">Eliminar</button>
                <?php endif; ?>
              </div>
              <?php
              if ($n['children']) { $render($n['children'], $depth + 1); }
            }
        };
        if (!$tree) { echo '<p class="muted" style="padding:36px;text-align:center;margin:0">Aún no hay categorías. Cree la primera a la derecha.</p>'; }
        $render($tree, 0);
        ?>
      </div>
    </div>
  </div>

  <form class="card" method="post" action="<?= e(url('/panel/categorias')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if ($e): ?><input type="hidden" name="id" value="<?= e($e['id']) ?>"><?php endif; ?>
    <div class="card__head"><span class="secnum">02/</span><h2><?= $e ? 'Editar categoría' : 'Nueva categoría' ?></h2></div>
    <div class="card__body">
      <div class="field"><label for="name">Nombre *</label>
        <input class="input" id="name" name="name" maxlength="140" required value="<?= e($e['name'] ?? '') ?>"></div>
      <div class="field"><label for="parent_id">Categoría padre</label>
        <select class="select" id="parent_id" name="parent_id">
          <option value="">— Raíz —</option>
          <?php foreach ($options as $o): if ($e && (int) $o['id'] === (int) $e['id']) { continue; } ?>
            <option value="<?= e($o['id']) ?>"<?= (int) ($e['parent_id'] ?? 0) === $o['id'] ? ' selected' : '' ?>><?= e($o['label']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="field"><label for="code">Código de línea</label>
        <input class="input" id="code" name="code" maxlength="20" value="<?= e($e['code'] ?? '') ?>" placeholder="SM"></div>
      <div class="field"><label for="description">Descripción</label>
        <textarea class="textarea" id="description" name="description" rows="3" maxlength="3000"><?= e($e['description'] ?? '') ?></textarea></div>
      <div class="field"><label for="image">Imagen de portada</label>
        <input class="input" id="image" name="image" type="file" accept="image/*"></div>
      <div class="field"><label for="seo_title">Título SEO</label>
        <input class="input" id="seo_title" name="seo_title" maxlength="190" value="<?= e($e['seo_title'] ?? '') ?>"></div>
      <div class="field"><label for="seo_description">Descripción SEO</label>
        <input class="input" id="seo_description" name="seo_description" maxlength="300" value="<?= e($e['seo_description'] ?? '') ?>"></div>
      <label class="check"><input type="checkbox" name="active" value="1"<?= (!$e || $e['active']) ? ' checked' : '' ?>><span>Visible en el sitio</span></label>
      <div class="flex" style="gap:8px">
        <button class="btn btn--accent" type="submit"><?= $e ? 'Guardar cambios' : 'Crear categoría' ?></button>
        <?php if ($e): ?><a class="btn btn--ghost" href="<?= e(url('/panel/categorias')) ?>">Cancelar</a><?php endif; ?>
      </div>
    </div>
  </form>
</div>

<?php
$flat = static function (array $nodes) use (&$flat): array {
    $out = [];
    foreach ($nodes as $n) { $out[] = $n; $out = array_merge($out, $flat($n['children'])); }
    return $out;
};
foreach ($flat($tree) as $n): ?>
  <form id="delcat<?= e($n['id']) ?>" method="post" action="<?= e(url('/panel/categorias/' . $n['id'] . '/eliminar')) ?>" class="hide"
        data-confirm="¿Eliminar la categoría «<?= e($n['name']) ?>»? Sus productos quedarán sin categoría."><?= csrf_field() ?></form>
<?php endforeach; ?>
