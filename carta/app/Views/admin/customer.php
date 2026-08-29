<?php
/** Ficha de un cliente. */
use MenuGold\Models\Order;
$view->extend('layouts/panel');
$view->set('title', $customer['name'] !== '' ? $customer['name'] : 'Cliente');
$cur = $cfg['currency'];
?>
<?php $view->start('actions') ?>
  <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/panel/clientes')) ?>">Volver</a>
<?php $view->stop() ?>

<?php $view->start('content') ?>
<div class="grid grid-side">
  <div class="card">
    <div class="card-head"><h2>Historial de pedidos</h2><p><?= count($orders) ?> pedidos registrados.</p></div>
    <?php if ($orders): ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Código</th><th>Fecha</th><th>Modo</th><th>Estado</th><th class="num">Total</th></tr></thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
              <tr>
                <td><a class="cell-title link-line" href="<?= e(mg_url('/panel/pedido/' . (int)$o['id'])) ?>"><?= e($o['code']) ?></a></td>
                <td class="muted"><?= e(mg_date($o['placed_at'])) ?></td>
                <td class="muted"><?= e(Order::modeLabel($o['mode'])) ?></td>
                <td><span class="chip <?= $o['status'] === 'closed' ? 'chip-green' : ($o['status'] === 'cancelled' ? 'chip-ember' : '') ?>"><?= e(Order::$statusLabels[$o['status']]) ?></span></td>
                <td class="num tabular"><?= e(mg_money($o['total'], $cur)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty"><h3>Sin pedidos todavía</h3><p>Aparecerán aquí en cuanto pida por primera vez.</p></div>
    <?php endif; ?>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h3>Datos</h3></div>
      <p><b><?= e($customer['name'] !== '' ? $customer['name'] : 'Sin nombre') ?></b></p>
      <?php if ($customer['phone'] !== ''): ?>
        <p class="mt-1"><a class="link-line" href="tel:<?= e($customer['phone']) ?>"><?= e($customer['phone']) ?></a></p>
        <p class="mt-1"><a class="link-line gold" href="<?= e(mg_wa($customer['phone'], 'Hola, le escribimos de ' . $cfg['name'] . '.')) ?>" target="_blank" rel="noopener">Escribir por WhatsApp</a></p>
      <?php endif; ?>
      <?php if ($customer['email'] !== ''): ?><p class="muted mt-1" style="font-size:var(--step--1)"><?= e($customer['email']) ?></p><?php endif; ?>
      <?php if ($customer['address'] !== ''): ?><p class="muted mt-1" style="font-size:var(--step--1)"><?= e($customer['address']) ?></p><?php endif; ?>
    </div>

    <div class="card">
      <div class="card-head"><h3>Resumen</h3></div>
      <ul class="stack" style="gap:.6rem;font-size:var(--step--1)">
        <li class="row-between"><span>Pedidos</span><b class="tabular"><?= (int)$customer['orders_count'] ?></b></li>
        <li class="row-between"><span>Consumo total</span><b class="tabular"><?= e(mg_money($customer['total_spent'], $cur)) ?></b></li>
        <li class="row-between"><span>Puntos</span><b class="tabular"><?= (int)$customer['points'] ?></b></li>
        <?php if (!empty($customer['last_order_at'])): ?>
          <li class="row-between"><span>Último pedido</span><span class="faint"><?= e(mg_date($customer['last_order_at'], 'd/m/Y')) ?></span></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</div>
<?php $view->stop() ?>
