<?php
/** Planes de la plataforma. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Planes');
?>
<?php $view->start('content') ?>
<div class="grid grid-side">
  <div class="card">
    <div class="card-head"><h2>Planes</h2><p>Los límites se aplican solos en el panel de cada restaurante.</p></div>
    <?php if ($plans): ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Plan</th><th class="num">Precio</th><th class="num">Platillos</th><th class="num">Mesas</th><th class="num">Pedidos/mes</th><th class="num">En uso</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($plans as $p): ?>
              <tr>
                <td><span class="cell-title"><?= e($p['name']) ?></span>
                  <?php if ((int)$p['is_active'] === 0): ?><span class="chip chip-dim">Inactivo</span><?php endif; ?></td>
                <td class="num tabular">Q<?= number_format((float)$p['price_month'], 2) ?></td>
                <td class="num tabular"><?= (int)$p['max_products'] ?: '∞' ?></td>
                <td class="num tabular"><?= (int)$p['max_tables'] ?: '∞' ?></td>
                <td class="num tabular"><?= (int)$p['max_orders_month'] ?: '∞' ?></td>
                <td class="num tabular"><?= (int)$p['used_by'] ?></td>
                <td class="num">
                  <?php if ((int)$p['used_by'] === 0): ?>
                    <form method="post" action="<?= e(mg_url('/super/plan/' . (int)$p['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar el plan <?= e($p['name']) ?>?">
                      <?= Csrf::field() ?><button class="cart-remove" type="submit">Eliminar</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty"><h3>Sin planes</h3><p>Crea Básico, Pro y Premium a la derecha.</p></div>
    <?php endif; ?>
  </div>

  <form class="card" method="post" action="<?= e(mg_url('/super/planes')) ?>">
    <?= Csrf::field() ?>
    <div class="card-head"><h3>Nuevo plan</h3></div>
    <div class="field"><label for="name">Nombre *</label><input type="text" class="input" id="name" name="name" required maxlength="60" placeholder="Pro"></div>
    <div class="grid grid-2">
      <div class="field"><label for="price_month">Precio mensual</label><input class="input" id="price_month" name="price_month" type="number" step="0.01" min="0" value="0"></div>
      <div class="field"><label for="sort">Orden</label><input class="input" id="sort" name="sort" type="number" value="0"></div>
      <div class="field"><label for="max_products">Máx. platillos</label><input class="input" id="max_products" name="max_products" type="number" min="0" value="80"></div>
      <div class="field"><label for="max_tables">Máx. mesas</label><input class="input" id="max_tables" name="max_tables" type="number" min="0" value="20"></div>
      <div class="field"><label for="max_orders_month">Máx. pedidos/mes</label><input class="input" id="max_orders_month" name="max_orders_month" type="number" min="0" value="2000"></div>
      <div class="field"><label for="max_users">Máx. usuarios</label><input class="input" id="max_users" name="max_users" type="number" min="0" value="6"></div>
    </div>
    <p class="field-hint">Un 0 significa «sin límite».</p>
    <div class="field mt-1"><label for="features">Características (una por línea)</label>
      <textarea class="textarea" id="features" name="features" rows="4"></textarea></div>
    <label class="switch"><input type="checkbox" name="is_active" value="1" checked><span class="switch-track" aria-hidden="true"></span><span>Activo</span></label>
    <button class="btn btn-block mt-2" type="submit">Crear plan</button>
  </form>
</div>
<?php $view->stop() ?>
