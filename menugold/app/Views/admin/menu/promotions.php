<?php
/** Promociones con vigencia. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Promociones');
$cur = $restaurant['currency'];
?>
<?php $view->start('content') ?>
<div class="grid grid-side">
  <div class="card">
    <div class="card-head"><h2>Promociones activas</h2><p>Se aplican solas al precio que ve el comensal.</p></div>
    <?php if ($promotions): ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Nombre</th><th>Descuento</th><th>Alcance</th><th>Vigencia</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($promotions as $p): ?>
            <tr>
              <td><span class="cell-title"><?= e($p['name']) ?></span>
                <?php if ((int)$p['is_active'] === 0): ?><span class="chip chip-dim">Inactiva</span><?php endif; ?></td>
              <td class="gold tabular"><?= $p['type'] === 'percent' ? e(rtrim(rtrim(number_format((float)$p['value'], 2, '.', ''), '0'), '.')) . '%' : e(mg_money($p['value'], $cur)) ?></td>
              <td class="muted"><?= e($p['scope'] === 'all' ? 'Todo el menú' : ($p['scope'] === 'category' ? 'Una categoría' : 'Un platillo')) ?></td>
              <td class="muted" style="font-size:12px"><?= e(($p['starts_at'] ? mg_date($p['starts_at'], 'd/m/y') : 'siempre') . ' → ' . ($p['ends_at'] ? mg_date($p['ends_at'], 'd/m/y') : 'sin fin')) ?></td>
              <td class="num">
                <form method="post" action="<?= e(mg_url('/panel/menu/promocion/' . (int)$p['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar esta promoción?">
                  <?= Csrf::field() ?>
                  <button class="cart-remove" type="submit">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty"><h3>Sin promociones</h3><p>Crea una a la derecha: martes de 2×1, 15 % en postres, precio especial de temporada.</p></div>
    <?php endif; ?>
  </div>

  <form class="card" method="post" action="<?= e(mg_url('/panel/menu/promociones')) ?>">
    <?= Csrf::field() ?>
    <div class="card-head"><h3>Nueva promoción</h3></div>
    <div class="field"><label for="name">Nombre *</label>
      <input type="text" class="input" id="name" name="name" required maxlength="160" placeholder="Martes de parrilla"></div>
    <div class="grid grid-2">
      <div class="field"><label for="type">Tipo</label>
        <select class="select" id="type" name="type"><option value="percent">Porcentaje</option><option value="amount">Monto fijo</option></select></div>
      <div class="field"><label for="value">Valor</label>
        <input class="input" id="value" name="value" type="number" step="0.01" min="0" required value="10"></div>
    </div>
    <div class="field"><label for="scope">Aplica a</label>
      <select class="select" id="scope" name="scope">
        <option value="all">Todo el menú</option>
        <option value="category">Una categoría</option>
        <option value="product">Un platillo</option>
      </select></div>
    <div class="field" data-depends-on="scope" data-depends-value="category,product">
      <label for="scope_id">Elemento</label>
      <select class="select" id="scope_id" name="scope_id">
        <optgroup label="Categorías">
          <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
        </optgroup>
        <optgroup label="Platillos">
          <?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
        </optgroup>
      </select>
      <p class="field-hint">Elige de la lista correspondiente al alcance.</p>
    </div>
    <div class="grid grid-2">
      <div class="field"><label for="starts_at">Desde</label><input class="input" id="starts_at" name="starts_at" type="date"></div>
      <div class="field"><label for="ends_at">Hasta</label><input class="input" id="ends_at" name="ends_at" type="date"></div>
    </div>
    <label class="switch"><input type="checkbox" name="is_active" value="1" checked><span class="switch-track" aria-hidden="true"></span><span>Activa</span></label>
    <button class="btn btn-block mt-2" type="submit">Crear promoción</button>
  </form>
</div>
<?php $view->stop() ?>
