<?php $e = $edit; ?>
<div class="cols cols--sidebar">
  <div class="card">
    <div class="card__head"><span class="secnum">01/</span><h2>Planes de la plataforma</h2></div>
    <div class="card__body card__body--flush tablescroll">
      <table class="datatable" style="border:0;border-radius:0">
        <caption class="sr-only">Planes</caption>
        <thead><tr><th scope="col">Plan</th><th scope="col" class="num">Q/mes</th><th scope="col" class="num">Productos</th>
          <th scope="col" class="num">Usuarios</th><th scope="col" class="num">Cotiz./mes</th><th scope="col" class="num">Empresas</th><th scope="col">Estado</th><th scope="col"></th></tr></thead>
        <tbody>
          <?php foreach ($plans as $p): ?>
            <tr>
              <td><strong><?= e($p['name']) ?></strong><?= $p['highlight'] ? ' <span class="badge badge--accent">Destacado</span>' : '' ?>
                <br><span class="small muted"><?= e($p['tagline']) ?></span></td>
              <td class="num"><?= e(number_format((float) $p['price_month'], 0)) ?></td>
              <td class="num"><?= (int) $p['max_products'] > 0 ? e(number_format((int) $p['max_products'])) : '∞' ?></td>
              <td class="num"><?= (int) $p['max_users'] > 0 ? e($p['max_users']) : '∞' ?></td>
              <td class="num"><?= (int) $p['max_quotes_month'] > 0 ? e(number_format((int) $p['max_quotes_month'])) : '∞' ?></td>
              <td class="num"><?= e((int) ($counts[$p['id']] ?? 0)) ?></td>
              <td><span class="badge<?= $p['active'] ? ' badge--ok' : '' ?>"><?= $p['active'] ? 'Activo' : 'Oculto' ?></span></td>
              <td class="nowrap">
                <a class="btn btn--ghost btn--xs" href="<?= e(url('/super/planes?editar=' . $p['id'])) ?>">Editar</a>
                <?php if ((int) ($counts[$p['id']] ?? 0) === 0): ?>
                  <button class="btn btn--ghost btn--xs" type="submit" form="delp<?= e($p['id']) ?>">Eliminar</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <form class="card" method="post" action="<?= e(url('/super/planes')) ?>">
    <?= csrf_field() ?>
    <?php if ($e): ?><input type="hidden" name="id" value="<?= e($e['id']) ?>"><?php endif; ?>
    <div class="card__head"><span class="secnum">02/</span><h2><?= $e ? 'Editar plan' : 'Nuevo plan' ?></h2></div>
    <div class="card__body">
      <div class="field"><label for="name">Nombre *</label><input class="input" id="name" name="name" maxlength="80" required value="<?= e($e['name'] ?? '') ?>"></div>
      <div class="field"><label for="code">Código</label><input class="input" id="code" name="code" maxlength="40" value="<?= e($e['code'] ?? '') ?>" placeholder="basico"></div>
      <div class="field"><label for="tagline">Frase corta</label><input class="input" id="tagline" name="tagline" maxlength="160" value="<?= e($e['tagline'] ?? '') ?>"></div>
      <div class="row-2">
        <div class="field"><label for="price_month">Precio mensual (Q)</label><input class="input" id="price_month" name="price_month" type="number" step="0.01" min="0" value="<?= e((float) ($e['price_month'] ?? 0)) ?>"></div>
        <div class="field"><label for="price_year">Precio anual (Q)</label><input class="input" id="price_year" name="price_year" type="number" step="0.01" min="0" value="<?= e((float) ($e['price_year'] ?? 0)) ?>"></div>
      </div>
      <div class="row-3">
        <div class="field"><label for="max_products">Máx. productos</label><input class="input" id="max_products" name="max_products" type="number" min="0" value="<?= e((int) ($e['max_products'] ?? 200)) ?>"></div>
        <div class="field"><label for="max_users">Máx. usuarios</label><input class="input" id="max_users" name="max_users" type="number" min="0" value="<?= e((int) ($e['max_users'] ?? 3)) ?>"></div>
        <div class="field"><label for="max_quotes_month">Cotiz./mes</label><input class="input" id="max_quotes_month" name="max_quotes_month" type="number" min="0" value="<?= e((int) ($e['max_quotes_month'] ?? 200)) ?>"></div>
      </div>
      <p class="hint" style="margin-top:-6px">0 significa ilimitado.</p>
      <div class="field"><label for="features">Características (una por línea)</label>
        <textarea class="textarea" id="features" name="features" rows="6"><?= e(implode("\n", $e ? \App\Models\Plan::features($e) : [])) ?></textarea></div>
      <div class="field"><label for="sort">Orden</label><input class="input" id="sort" name="sort" type="number" value="<?= e((int) ($e['sort'] ?? 0)) ?>"></div>
      <label class="check"><input type="checkbox" name="highlight" value="1"<?= ($e && $e['highlight']) ? ' checked' : '' ?>><span>Destacar en la landing</span></label>
      <label class="check"><input type="checkbox" name="active" value="1"<?= (!$e || $e['active']) ? ' checked' : '' ?>><span>Visible en la landing</span></label>
      <div class="flex" style="gap:8px">
        <button class="btn btn--accent" type="submit"><?= $e ? 'Guardar' : 'Crear plan' ?></button>
        <?php if ($e): ?><a class="btn btn--ghost" href="<?= e(url('/super/planes')) ?>">Cancelar</a><?php endif; ?>
      </div>
    </div>
  </form>
</div>
<?php foreach ($plans as $p): ?>
  <form id="delp<?= e($p['id']) ?>" method="post" action="<?= e(url('/super/planes/' . $p['id'] . '/eliminar')) ?>" class="hide" data-confirm="¿Eliminar el plan «<?= e($p['name']) ?>»?"><?= csrf_field() ?></form>
<?php endforeach; ?>
