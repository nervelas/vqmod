<?php
/** Combos. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Combos');
$cur = $restaurant['currency'];
?>
<?php $view->start('content') ?>
<div class="grid grid-side">
  <div class="card">
    <div class="card-head"><h2>Combos</h2><p>Paquetes con precio cerrado.</p></div>
    <?php if ($combos): ?>
      <div class="grid grid-2">
        <?php foreach ($combos as $c): ?>
          <div class="card" style="background:var(--carbon)">
            <div class="card-head">
              <div><h3><?= e($c['name']) ?></h3><p class="gold tabular"><?= e(mg_money($c['price'], $cur)) ?></p></div>
              <form method="post" action="<?= e(mg_url('/panel/menu/combo/' . (int)$c['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar el combo?">
                <?= Csrf::field() ?><button class="cart-remove" type="submit">Eliminar</button>
              </form>
            </div>
            <?php if ($c['description'] !== ''): ?><p class="muted" style="font-size:var(--step--1)"><?= e($c['description']) ?></p><?php endif; ?>
            <ul class="mt-1" style="font-size:12px">
              <?php foreach ($c['items_list'] as $it): ?><li class="faint">· <?= e($it['name']) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty"><h3>Sin combos</h3><p>Ejemplo: entrada + fuerte + postre por un precio.</p></div>
    <?php endif; ?>
  </div>

  <form class="card" method="post" enctype="multipart/form-data" action="<?= e(mg_url('/panel/menu/combos')) ?>">
    <?= Csrf::field() ?>
    <div class="card-head"><h3>Nuevo combo</h3></div>
    <div class="field"><label for="name">Nombre *</label><input type="text" class="input" id="name" name="name" required maxlength="160"></div>
    <div class="field"><label for="description">Descripción</label><textarea class="textarea" id="description" name="description" rows="2"></textarea></div>
    <div class="field"><label for="price">Precio (<?= e($cur) ?>) *</label>
      <input class="input" id="price" name="price" type="number" step="0.01" min="0" required></div>
    <div class="field"><label for="items">Platillos incluidos</label>
      <select class="select" id="items" name="items[]" multiple size="7">
        <?php foreach ($products as $p): ?>
          <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> · <?= e(mg_money($p['price'], $cur)) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="field-hint">Mantén Ctrl (o Cmd) para elegir varios.</p></div>
    <div class="grid grid-2">
      <div class="field"><label for="starts_at">Desde</label><input class="input" id="starts_at" name="starts_at" type="date"></div>
      <div class="field"><label for="ends_at">Hasta</label><input class="input" id="ends_at" name="ends_at" type="date"></div>
    </div>
    <div class="field"><label for="image">Imagen</label><input class="input" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp"></div>
    <label class="switch"><input type="checkbox" name="is_active" value="1" checked><span class="switch-track" aria-hidden="true"></span><span>Activo</span></label>
    <button class="btn btn-block mt-2" type="submit">Crear combo</button>
  </form>
</div>
<?php $view->stop() ?>
