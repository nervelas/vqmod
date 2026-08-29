<?php
/** Resumen del restaurante. */
use MenuGold\Core\Csrf;
use MenuGold\Models\Order;
$view->extend('layouts/panel');
$view->set('title', 'Resumen');
$cur = $restaurant['currency'];
$pending = array_filter($setupDone, function ($v) { return !$v; });
?>
<?php $view->start('actions') ?>
  <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/panel/cocina')) ?>">Pantalla de cocina</a>
  <a class="btn btn-sm" href="<?= e(mg_url('/panel/menu/producto/nuevo')) ?>">Nuevo platillo</a>
<?php $view->stop() ?>

<?php $view->start('content') ?>

<?php if ($pending): ?>
  <div class="card" style="border-color:var(--line)">
    <div class="row-between">
      <div>
        <h2 class="display" style="font-size:var(--step-1)">Te faltan <?= count($pending) ?> pasos para terminar</h2>
        <p class="muted" style="font-size:var(--step--1);margin-top:.3rem">
          <?php
          $labels = array('identity' => 'logo y portada', 'menu' => 'platillos', 'tables' => 'mesas con QR', 'hours' => 'horarios');
          echo e('Pendiente: ' . implode(', ', array_map(function ($k) use ($labels) { return $labels[$k]; }, array_keys($pending))) . '.');
          ?>
        </p>
      </div>
      <a class="btn btn-sm" href="<?= e(mg_url('/panel/inicio-guiado')) ?>">Continuar</a>
    </div>
  </div>
<?php endif; ?>

<div class="grid grid-4 mt-2">
  <div class="card kpi">
    <p class="label">Ventas de hoy</p>
    <b><?= e(mg_money($summary['revenue'], $cur)) ?></b>
    <span class="muted" style="font-size:12px"><?= (int)$summary['orders'] ?> pedidos</span>
  </div>
  <div class="card kpi">
    <p class="label">Ventas del mes</p>
    <b><?= e(mg_money($month['revenue'], $cur)) ?></b>
    <?php if ($month['growth'] !== null): ?>
      <span class="kpi-delta <?= $month['growth'] >= 0 ? 'is-up' : 'is-down' ?>">
        <?= $month['growth'] >= 0 ? '▲' : '▼' ?> <?= e(abs($month['growth'])) ?>% vs. periodo anterior
      </span>
    <?php else: ?>
      <span class="muted" style="font-size:12px"><?= (int)$month['orders'] ?> pedidos</span>
    <?php endif; ?>
  </div>
  <div class="card kpi">
    <p class="label">Ticket promedio</p>
    <b><?= e(mg_money($month['ticket'], $cur)) ?></b>
    <span class="muted" style="font-size:12px">del mes en curso</span>
  </div>
  <div class="card kpi">
    <p class="label">Propinas del mes</p>
    <b><?= e(mg_money($month['tips'], $cur)) ?></b>
    <span class="muted" style="font-size:12px"><?= (int)$month['cancelled'] ?> pedidos anulados</span>
  </div>
</div>

<div class="grid grid-side mt-2">
  <div class="card">
    <div class="card-head">
      <div><h2>Últimos 14 días</h2><p>Ventas por día</p></div>
      <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/panel/reportes')) ?>">Ver reportes</a>
    </div>
    <div style="height:230px">
      <canvas id="dash-chart" role="img" data-chart='<?= $view->json(array(
        'type' => 'line',
        'money' => true,
        'currency' => $cur,
        'labels' => array_map(function ($d) { return date('d/m', strtotime($d)); }, array_keys($series)),
        'datasets' => array(array('label' => 'Ventas', 'data' => array_values($series))),
      )) ?>' aria-label="Gráfica de ventas de los últimos 14 días"></canvas>
    </div>
  </div>

  <div class="stack">
    <?php if ($calls): ?>
      <div class="card" style="border-color:rgba(196,80,43,.4)">
        <div class="card-head"><h3>Llamadas abiertas</h3></div>
        <?php foreach ($calls as $c): ?>
          <div class="row-between" style="padding:.6rem 0;border-bottom:1px solid var(--line-soft)">
            <div>
              <b><?= e($c['table_name'] ? $c['table_name'] : 'Mesa') ?></b>
              <span class="muted" style="font-size:12px"> · <?= e($c['type'] === 'bill' ? 'pide la cuenta' : 'llama al mesero') ?> · <?= e(mg_ago($c['created_at'])) ?></span>
            </div>
            <form method="post" action="<?= e(mg_url('/panel/llamadas/' . (int)$c['id'] . '/atender')) ?>">
              <?= Csrf::field() ?>
              <button class="btn btn-sm btn-ghost" type="submit">Atendida</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><h3>Los más vendidos del mes</h3></div>
      <?php if ($top): ?>
        <ul class="stack" style="gap:.7rem">
          <?php foreach ($top as $i => $t): ?>
            <li class="row-between" style="gap:.8rem">
              <span><span class="numeral" style="margin-right:.6rem"><?= sprintf('%02d', $i + 1) ?></span><?= e($t['name']) ?></span>
              <span class="tabular gold"><?= (int)$t['qty'] ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="muted" style="font-size:var(--step--1)">Todavía no hay ventas este mes.</p>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-head"><h3>Tu plan</h3></div>
      <?php if ($usage['plan']): ?>
        <p><b class="gold"><?= e($usage['plan']['name']) ?></b></p>
        <?php
        $limits = array(
            array('Platillos', $usage['products'], (int)$usage['plan']['max_products']),
            array('Mesas', $usage['tables'], (int)$usage['plan']['max_tables']),
            array('Pedidos del mes', $usage['orders'], (int)$usage['plan']['max_orders_month']),
        );
        foreach ($limits as $l):
          $pct = $l[2] > 0 ? min(100, round(($l[1] / $l[2]) * 100)) : 0;
        ?>
          <div style="margin-top:.9rem">
            <div class="row-between" style="font-size:12px">
              <span class="muted"><?= e($l[0]) ?></span>
              <span class="tabular"><?= (int)$l[1] ?><?= $l[2] > 0 ? ' / ' . (int)$l[2] : '' ?></span>
            </div>
            <div style="height:3px;background:var(--line-soft);border-radius:2px;margin-top:.4rem;overflow:hidden">
              <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct >= 90 ? 'var(--ember)' : 'var(--gold)' ?>"></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="muted" style="font-size:var(--step--1)">Sin plan asignado: no hay límites activos.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card mt-2">
  <div class="card-head">
    <h2>Pedidos recientes</h2>
    <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/panel/pedidos')) ?>">Ver todos</a>
  </div>
  <?php if ($active): ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Código</th><th>Mesa</th><th>Modo</th><th>Estado</th><th>Hora</th><th class="num">Total</th></tr></thead>
        <tbody>
          <?php foreach ($active as $o): ?>
            <tr>
              <td><a class="cell-title link-line" href="<?= e(mg_url('/panel/pedidos/' . (int)$o['id'])) ?>"><?= e($o['code']) ?></a></td>
              <td><?= e($o['table_name'] ? $o['table_name'] : '—') ?></td>
              <td class="muted"><?= e(Order::modeLabel($o['mode'])) ?></td>
              <td><span class="chip <?= $o['status'] === 'paid' ? 'chip-green' : ($o['status'] === 'cancelled' ? 'chip-ember' : '') ?>"><?= e(Order::$statusLabels[$o['status']]) ?></span></td>
              <td class="muted"><?= e(mg_date($o['placed_at'], 'H:i')) ?></td>
              <td class="num tabular"><?= e(mg_money($o['total'], $cur)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><h3>Aún no hay pedidos</h3><p>Cuando alguien escanee el QR de una mesa, aparecerá aquí.</p></div>
  <?php endif; ?>
</div>
<?php $view->stop() ?>

<?php $view->start('scripts') ?>
<script src="<?= e(mg_asset('assets/vendor/chart.umd.js')) ?>"></script>
<?php $view->stop() ?>
