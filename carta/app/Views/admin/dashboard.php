<?php
/** Resumen del restaurante. */
use MenuGold\Core\Csrf;
use MenuGold\Models\Order;
$view->extend('layouts/panel');
$view->set('title', 'Resumen');
$cur = $cfg['currency'];
$pending = array_filter($setup, function ($v) { return !$v; });
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
          $labels = array('identity' => 'nombre del restaurante', 'photos' => 'logo y portada', 'menu' => 'platillos', 'tables' => 'mesas con QR', 'hours' => 'horario');
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
      <div class="card-head"><h3>Accesos rápidos</h3></div>
      <div class="stack" style="gap:.5rem">
        <a class="btn btn-ghost btn-block" href="<?= e(mg_url('/panel/mesas')) ?>">Mesas y códigos QR</a>
        <a class="btn btn-ghost btn-block" href="<?= e(mg_url('/panel/menu')) ?>">Editar la carta</a>
        <a class="btn btn-ghost btn-block" href="<?= e(mg_url('/panel/reportes')) ?>">Ver reportes</a>
        <a class="btn btn-ghost btn-block" href="<?= e(mg_url('/')) ?>" target="_blank" rel="noopener">Abrir mi menú</a>
      </div>
    </div>
  </div>
</div>

<?php $view->stop() ?>

<?php $view->start('scripts') ?>
<script src="<?= e(mg_asset('assets/vendor/chart.umd.js')) ?>"></script>
<?php $view->stop() ?>
