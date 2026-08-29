<?php
/** Historial de pedidos. */
use MenuGold\Models\Order;
$view->extend('layouts/panel');
$view->set('title', 'Pedidos');
$cur = $restaurant['currency'];
?>
<?php $view->start('content') ?>
<form class="filters" method="get" action="<?= e(mg_url('/panel/pedidos')) ?>">
  <div class="field"><label for="q">Buscar</label>
    <input class="input" id="q" name="q" type="search" value="<?= e($filters['q']) ?>" placeholder="Código, nombre o teléfono"></div>
  <div class="field"><label for="estado">Estado</label>
    <select class="select" id="estado" name="estado">
      <option value="">Todos</option>
      <?php foreach (Order::$statusLabels as $k => $label): ?>
        <option value="<?= e($k) ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="field"><label for="modo">Modo</label>
    <select class="select" id="modo" name="modo">
      <option value="">Todos</option>
      <option value="dine_in"  <?= $filters['mode'] === 'dine_in' ? 'selected' : '' ?>>En mesa</option>
      <option value="takeaway" <?= $filters['mode'] === 'takeaway' ? 'selected' : '' ?>>Para llevar</option>
      <option value="delivery" <?= $filters['mode'] === 'delivery' ? 'selected' : '' ?>>A domicilio</option>
    </select></div>
  <div class="field"><label for="desde">Desde</label><input class="input" id="desde" name="desde" type="date" value="<?= e($filters['from']) ?>"></div>
  <div class="field"><label for="hasta">Hasta</label><input class="input" id="hasta" name="hasta" type="date" value="<?= e($filters['to']) ?>"></div>
  <button class="btn btn-sm" type="submit">Filtrar</button>
  <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/panel/pedidos')) ?>">Limpiar</a>
</form>

<div class="card">
  <?php if ($orders): ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Código</th><th>Cliente / mesa</th><th>Modo</th><th>Estado</th><th>Fecha</th><th class="num">Total</th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><a class="cell-title link-line" href="<?= e(mg_url('/panel/pedidos/' . (int)$o['id'])) ?>"><?= e($o['code']) ?></a></td>
              <td>
                <?= e($o['table_name'] ? $o['table_name'] : ($o['customer_name'] !== '' ? $o['customer_name'] : 'Sin nombre')) ?>
                <?php if ($o['customer_phone'] !== ''): ?><span class="muted" style="display:block;font-size:11.5px"><?= e($o['customer_phone']) ?></span><?php endif; ?>
              </td>
              <td class="muted"><?= e(Order::modeLabel($o['mode'])) ?></td>
              <td><span class="chip <?= $o['status'] === 'paid' ? 'chip-green' : ($o['status'] === 'cancelled' ? 'chip-ember' : '') ?>"><?= e(Order::$statusLabels[$o['status']]) ?></span></td>
              <td class="muted" style="font-size:12px"><?= e(mg_date($o['placed_at'])) ?></td>
              <td class="num tabular"><?= e(mg_money($o['total'], $cur)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><h3>No hay pedidos con esos filtros</h3><p>Prueba ampliando el rango de fechas.</p></div>
  <?php endif; ?>
</div>
<?php $view->stop() ?>
