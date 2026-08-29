<?php
/** Clientes de domicilio y para llevar. */
$view->extend('layouts/panel');
$view->set('title', 'Clientes');
$cur = $restaurant['currency'];
?>
<?php $view->start('content') ?>
<div class="grid grid-3">
  <div class="card kpi"><p class="label">Clientes registrados</p><b><?= (int)$totals['n'] ?></b></div>
  <div class="card kpi"><p class="label">Consumo acumulado</p><b><?= e(mg_money($totals['spent'], $cur)) ?></b></div>
  <div class="card kpi"><p class="label">Pedidos por cliente</p><b><?= e(number_format((float)$totals['avg_orders'], 1)) ?></b></div>
</div>

<div class="card mt-2">
  <div class="card-head">
    <h2>Directorio</h2>
    <form class="filters" method="get" action="<?= e(mg_url('/panel/clientes')) ?>" style="margin-bottom:0">
      <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Nombre o teléfono">
      <button class="btn btn-sm" type="submit">Buscar</button>
    </form>
  </div>

  <?php if ($customers): ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Cliente</th><th>Teléfono</th><th class="num">Pedidos</th><th class="num">Consumo</th><th>Último</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($customers as $c): ?>
            <tr>
              <td><span class="cell-title"><?= e($c['name'] !== '' ? $c['name'] : 'Sin nombre') ?></span>
                <?php if ($c['address'] !== ''): ?><span class="muted" style="display:block;font-size:11.5px"><?= e(\MenuGold\Core\Str::limit($c['address'], 46)) ?></span><?php endif; ?></td>
              <td class="tabular"><?= e($c['phone']) ?></td>
              <td class="num tabular"><?= (int)$c['orders_count'] ?></td>
              <td class="num tabular"><?= e(mg_money($c['total_spent'], $cur)) ?></td>
              <td class="muted" style="font-size:12px"><?= e($c['last_order_at'] ? mg_ago($c['last_order_at']) : '—') ?></td>
              <td class="num"><a class="btn btn-ghost btn-sm" href="<?= e(mg_wa($c['phone'], 'Hola, le escribimos de ' . $restaurant['name'] . '.')) ?>" target="_blank" rel="noopener">WhatsApp</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><h3>Sin clientes registrados</h3><p>Se registran solos cuando alguien pide para llevar o a domicilio.</p></div>
  <?php endif; ?>
</div>
<?php $view->stop() ?>
