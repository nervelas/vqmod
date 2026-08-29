<?php
/** Consola del superadministrador. */
$view->extend('layouts/panel');
$view->set('title', 'Consola de la plataforma');
$series = array();
for ($i = 29; $i >= 0; $i--) { $series[date('Y-m-d', strtotime('-' . $i . ' days'))] = 0; }
foreach ($orders as $o) { if (isset($series[$o['d']])) { $series[$o['d']] = (int)$o['n']; } }
?>
<?php $view->start('actions') ?>
  <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/super/landing')) ?>">Sitio de venta</a>
  <a class="btn btn-sm" href="<?= e(mg_url('/super/restaurante/nuevo')) ?>">Nuevo restaurante</a>
<?php $view->stop() ?>

<?php $view->start('content') ?>
<div class="grid grid-4">
  <div class="card kpi"><p class="label">Restaurantes</p><b><?= (int)$stats['restaurants'] ?></b>
    <span class="muted" style="font-size:12px"><?= (int)$stats['active'] ?> activos · <?= (int)$stats['suspended'] ?> suspendidos</span></div>
  <div class="card kpi"><p class="label">Pedidos del mes</p><b><?= number_format((int)$stats['orders_month']) ?></b></div>
  <div class="card kpi"><p class="label">Volumen del mes</p><b>Q<?= number_format((float)$stats['revenue_month'], 2) ?></b>
    <span class="muted" style="font-size:12px">suma de todos los locales</span></div>
  <div class="card kpi"><p class="label">Platillos publicados</p><b><?= number_format((int)$stats['products']) ?></b></div>
</div>

<div class="grid grid-side mt-2">
  <div class="card">
    <div class="card-head"><h2>Pedidos de los últimos 30 días</h2></div>
    <div style="height:240px">
      <canvas role="img" data-chart='<?= $view->json(array(
        'type' => 'bar',
        'labels' => array_map(function ($d) { return date('d/m', strtotime($d)); }, array_keys($series)),
        'datasets' => array(array('label' => 'Pedidos', 'data' => array_values($series))),
      )) ?>' aria-label="Pedidos por día en la plataforma"></canvas>
    </div>
  </div>

  <div class="stack">
    <?php if ($stats['expiring']): ?>
      <div class="card" style="border-color:rgba(196,80,43,.4)">
        <div class="card-head"><h3>Planes por vencer</h3></div>
        <ul class="stack" style="gap:.6rem;font-size:var(--step--1)">
          <?php foreach ($stats['expiring'] as $x): ?>
            <li class="row-between">
              <a class="link-line" href="<?= e(mg_url('/super/restaurante/' . (int)$x['id'])) ?>"><?= e($x['name']) ?></a>
              <span class="tabular <?= $x['plan_expires_at'] < date('Y-m-d') ? 'gold' : 'muted' ?>"><?= e(mg_date($x['plan_expires_at'], 'd/m/Y')) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><h3>Últimos restaurantes</h3></div>
      <ul class="stack" style="gap:.7rem;font-size:var(--step--1)">
        <?php foreach ($recent as $r): ?>
          <li class="row-between">
            <span>
              <a class="link-line" href="<?= e(mg_url('/super/restaurante/' . (int)$r['id'])) ?>"><?= e($r['name']) ?></a>
              <span class="faint" style="display:block;font-size:11px">/r/<?= e($r['slug']) ?> · <?= e($r['plan_name'] ? $r['plan_name'] : 'sin plan') ?></span>
            </span>
            <span class="chip <?= $r['status'] === 'active' ? 'chip-green' : ($r['status'] === 'suspended' ? 'chip-ember' : 'chip-dim') ?>"><?= e($r['status']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card">
      <div class="card-head"><h3>Herramientas</h3></div>
      <div class="stack" style="gap:.6rem">
        <a class="btn btn-ghost btn-block" href="<?= e(mg_url('/super/planes')) ?>">Planes y límites</a>
        <a class="btn btn-ghost btn-block" href="<?= e(mg_url('/super/respaldo')) ?>">Respaldos</a>
        <a class="btn btn-ghost btn-block" href="<?= e(mg_url('/super/bitacora')) ?>">Bitácora general</a>
      </div>
    </div>
  </div>
</div>
<?php $view->stop() ?>

<?php $view->start('scripts') ?>
<script src="<?= e(mg_asset('assets/vendor/chart.umd.js')) ?>"></script>
<?php $view->stop() ?>
