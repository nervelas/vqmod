<?php $e = $edit; $sym = (string) $company['currency_symbol']; ?>
<div class="cols cols--sidebar">
  <div class="card">
    <div class="card__head"><span class="secnum">01/</span><h2>Listas de precios</h2></div>
    <div class="card__body card__body--flush tablescroll">
      <?php if (!$lists): ?>
        <p class="muted" style="padding:36px;text-align:center;margin:0">Cree listas para dar precios distintos por tipo de cliente.</p>
      <?php else: ?>
        <table class="datatable" style="border:0;border-radius:0">
          <caption class="sr-only">Listas de precios</caption>
          <thead><tr><th scope="col">Lista</th><th scope="col" class="num">Descuento</th><th scope="col" class="num">Clientes</th><th scope="col">Predeterminada</th><th scope="col"></th></tr></thead>
          <tbody>
            <?php foreach ($lists as $l): ?>
              <tr>
                <td><strong><?= e($l['name']) ?></strong></td>
                <td class="num"><?= e(qty((float) $l['discount_pct'])) ?>%</td>
                <td class="num"><?= e((int) $l['clientes']) ?></td>
                <td><?= $l['is_default'] ? '<span class="badge badge--accent">Sí</span>' : '—' ?></td>
                <td class="nowrap">
                  <a class="btn btn--ghost btn--xs" href="<?= e(url('/panel/listas-precios?editar=' . $l['id'])) ?>">Editar</a>
                  <button class="btn btn--ghost btn--xs" type="submit" form="delpl<?= e($l['id']) ?>">Eliminar</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
  <form class="card" method="post" action="<?= e(url('/panel/listas-precios')) ?>">
    <?= csrf_field() ?>
    <?php if ($e): ?><input type="hidden" name="id" value="<?= e($e['id']) ?>"><?php endif; ?>
    <div class="card__head"><span class="secnum">02/</span><h2><?= $e ? 'Editar lista' : 'Nueva lista' ?></h2></div>
    <div class="card__body">
      <div class="field"><label for="name">Nombre *</label><input class="input" id="name" name="name" maxlength="90" required value="<?= e($e['name'] ?? '') ?>" placeholder="Mayorista"></div>
      <div class="field"><label for="discount_pct">Descuento sobre el precio de lista (%)</label>
        <input class="input" id="discount_pct" name="discount_pct" type="number" step="0.01" min="0" max="90" value="<?= e((float) ($e['discount_pct'] ?? 0)) ?>">
        <p class="hint">Se aplica cuando el producto no tiene un precio específico para esta lista.</p></div>
      <label class="check"><input type="checkbox" name="is_default" value="1"<?= ($e && $e['is_default']) ? ' checked' : '' ?>><span>Usar como lista predeterminada</span></label>
      <div class="flex" style="gap:8px">
        <button class="btn btn--accent" type="submit"><?= $e ? 'Guardar' : 'Crear lista' ?></button>
        <?php if ($e): ?><a class="btn btn--ghost" href="<?= e(url('/panel/listas-precios')) ?>">Cancelar</a><?php endif; ?>
      </div>
    </div>
  </form>
</div>
<?php foreach ($lists as $l): ?>
  <form id="delpl<?= e($l['id']) ?>" method="post" action="<?= e(url('/panel/listas-precios/' . $l['id'] . '/eliminar')) ?>" class="hide" data-confirm="¿Eliminar la lista «<?= e($l['name']) ?>»?"><?= csrf_field() ?></form>
<?php endforeach; ?>
