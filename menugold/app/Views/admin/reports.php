<?php
/** Reportes. */
use MenuGold\Models\Order;
$view->extend('layouts/panel');
$view->set('title', 'Reportes');
$cur = $restaurant['currency'];
$qs = '?desde=' . rawurlencode($from) . '&hasta=' . rawurlencode($to);
?>
<?php $view->start('actions') ?>
  <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/panel/reportes/exportar.xlsx') . $qs) ?>">Excel</a>
  <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/panel/reportes/exportar.pdf') . $qs) ?>" target="_blank" rel="noopener">PDF</a>
<?php $view->stop() ?>

<?php $view->start('content') ?>
<form class="filters" method="get" action="<?= e(mg_url('/panel/reportes')) ?>">
  <div class="field"><label for="f-from">Desde</label><input class="input" id="f-from" name="desde" type="date" value="<?= e($from) ?>"></div>
  <div class="field"><label for="f-to">Hasta</label><input class="input" id="f-to" name="hasta" type="date" value="<?= e($to) ?>"></div>
  <button class="btn btn-sm" type="submit">Ver</button>
  <button class="btn btn-sm btn-ghost" type="button" data-range="6">7 días</button>
  <button class="btn btn-sm btn-ghost" type="button" data-range="29">30 días</button>
  <button class="btn btn-sm btn-ghost" type="button" data-range="0">Este mes</button>
</form>

<div class="grid grid-4">
  <div class="card kpi"><p class="label">Ventas</p><b><?= e(mg_money($summary['revenue'], $cur)) ?></b>
    <?php if ($summary['growth'] !== null): ?>
      <span class="kpi-delta <?= $summary['growth'] >= 0 ? 'is-up' : 'is-down' ?>"><?= $summary['growth'] >= 0 ? '▲' : '▼' ?> <?= e(abs($summary['growth'])) ?>%</span>
    <?php endif; ?></div>
  <div class="card kpi"><p class="label">Pedidos</p><b><?= (int)$summary['orders'] ?></b><span class="muted" style="font-size:12px"><?= (int)$summary['cancelled'] ?> anulados</span></div>
  <div class="card kpi"><p class="label">Ticket promedio</p><b><?= e(mg_money($summary['ticket'], $cur)) ?></b></div>
  <div class="card kpi"><p class="label">Propinas</p><b><?= e(mg_money($summary['tips'], $cur)) ?></b><span class="muted" style="font-size:12px">Descuentos: <?= e(mg_money($summary['discounts'], $cur)) ?></span></div>
</div>

<div class="grid grid-side mt-2">
  <div class="card">
    <div class="card-head"><h2>Ventas por día</h2></div>
    <div style="height:250px">
      <canvas role="img" data-chart='<?= $view->json(array(
        'type' => 'line', 'money' => true, 'currency' => $cur,
        'labels' => array_map(function ($r) { return date('d/m', strtotime($r['d'])); }, $byDay),
        'datasets' => array(array('label' => 'Ventas', 'data' => array_map(function ($r) { return (float)$r['revenue']; }, $byDay))),
      )) ?>' aria-label="Ventas por día"></canvas>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>Por categoría</h3></div>
    <div style="height:220px">
      <canvas role="img" data-chart='<?= $view->json(array(
        'type' => 'doughnut', 'multicolor' => true, 'legend' => true,
        'labels' => array_map(function ($r) { return $r['name']; }, $byCat),
        'datasets' => array(array('data' => array_map(function ($r) { return (float)$r['revenue']; }, $byCat))),
      )) ?>' aria-label="Ventas por categoría"></canvas>
    </div>
  </div>
</div>

<div class="grid grid-side mt-2">
  <div class="card">
    <div class="card-head"><h2>Horas pico</h2><p>Pedidos por hora del día.</p></div>
    <div style="height:220px">
      <canvas role="img" data-chart='<?= $view->json(array(
        'type' => 'bar',
        'labels' => array_map(function ($r) { return sprintf('%02d', $r['h']); }, $byHour),
        'datasets' => array(array('label' => 'Pedidos', 'data' => array_map(function ($r) { return (int)$r['orders']; }, $byHour))),
      )) ?>' aria-label="Pedidos por hora"></canvas>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>Tiempos</h3></div>
    <ul class="stack" style="gap:.9rem">
      <?php foreach (array(
        array('Del pedido a aceptarlo', $timings['to_accept']),
        array('De aceptar a listo', $timings['to_ready']),
        array('Del pedido a la entrega', $timings['to_deliver']),
      ) as $t): ?>
        <li class="row-between">
          <span class="muted" style="font-size:var(--step--1)"><?= e($t[0]) ?></span>
          <span class="tabular gold"><?= $t[1] !== null ? e($t[1]) . ' min' : '—' ?></span>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="card-head mt-3"><h3>Por modo</h3></div>
    <ul class="stack" style="gap:.6rem">
      <?php foreach ($byMode as $m): ?>
        <li class="row-between" style="font-size:var(--step--1)">
          <span class="muted"><?= e(Order::modeLabel($m['mode'])) ?></span>
          <span class="tabular"><?= e(mg_money($m['revenue'], $cur)) ?></span>
        </li>
      <?php endforeach; ?>
      <?php if (!$byMode): ?><li class="faint" style="font-size:var(--step--1)">Sin datos.</li><?php endif; ?>
    </ul>
  </div>
</div>

<div class="grid grid-2 mt-2">
  <div class="card">
    <div class="card-head"><h3>Los más vendidos</h3></div>
    <?php $view->partial('admin/partials/top-table', array('rows' => $topUp, 'cur' => $cur)); ?>
  </div>
  <div class="card">
    <div class="card-head"><h3>Los que menos salen</h3><p>Candidatos a salir de la carta.</p></div>
    <?php $view->partial('admin/partials/top-table', array('rows' => $topDown, 'cur' => $cur)); ?>
  </div>
</div>

<?php if ($byWaiter): ?>
  <div class="card mt-2">
    <div class="card-head"><h3>Por mesero</h3></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Mesero</th><th class="num">Pedidos</th><th class="num">Ventas</th></tr></thead>
        <tbody>
          <?php foreach ($byWaiter as $w): ?>
            <tr><td><?= e($w['name']) ?></td><td class="num tabular"><?= (int)$w['orders'] ?></td><td class="num tabular"><?= e(mg_money($w['revenue'], $cur)) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
<?php $view->stop() ?>

<?php $view->start('scripts') ?>
<script src="<?= e(mg_asset('assets/vendor/chart.umd.js')) ?>"></script>
<?php $view->stop() ?>
