<?php $r = $d['run']; ?>
<div class="page-head"><div><p class="eyebrow">Hola, <?= e(explode(' ', $a['name'])[0]) ?></p><h1>Tablero de <em>mando</em></h1></div>
<form method="post" action="<?= e(url('admin/fuente/sincronizar')) ?>"><?= csrf_field() ?><button class="btn btn-gold btn-sm">Sincronizar ahora</button></form></div>
<div class="kpis">
  <div class="kpi card reveal"><div class="ki"><?= icon('users') ?></div><small>Clientes activos</small><strong class="mono" data-count="<?= $d['active'] ?>">0</strong><span class="muted"><?= $d['paying'] ?> con plan pagado</span></div>
  <div class="kpi card reveal" style="--d:.06s"><div class="ki"><?= icon('coins') ?></div><small>Ingresos del mes</small><strong class="mono" style="font-size:clamp(1.2rem,4.2vw,1.8rem)"><?= e(money($d['income'])) ?></strong><a href="<?= e(url('admin/pagos')) ?>"><?= $d['pending_pay'] ?> pago(s) por revisar</a></div>
  <div class="kpi card reveal" style="--d:.12s"><div class="ki"><?= icon('gavel') ?></div><small>Concursos importados hoy</small><strong class="mono" data-count="<?= $d['imported'] ?>">0</strong><span class="muted"><?= $d['open'] ?> abiertos · <?= $d['total'] ?> en total</span></div>
  <div class="kpi card reveal" style="--d:.18s"><div class="ki"><?= icon('bell') ?></div><small>Alertas enviadas hoy</small><strong class="mono" data-count="<?= $d['alerts'] ?>">0</strong><a href="<?= e(url('admin/notificaciones')) ?>">Ver cola</a></div>
</div>
<section class="card reveal"><h2><?= icon('chart') ?> Últimos 14 <em>días</em></h2>
  <?= svg_bars([$d['s_imp'], $d['s_alert']], $d['s_lbl']) ?>
  <div class="chart-legend"><span><i></i>Concursos importados</span><span><i class="g"></i>Alertas generadas</span></div></section>
<div class="two-col top">
  <section class="card"><h2>Última sincronización</h2>
    <?php if ($r): ?><p><span class="pill <?= $r['status'] === 'ok' ? 'p-new' : ($r['status'] === 'parcial' ? 'p-soon' : 'p-urgent') ?>"><?= e(strtoupper($r['status'])) ?></span> <span class="mono"><?= e(fdate($r['started_at'], true)) ?></span> · <?= e($r['source']) ?></p><p class="muted small"><?= e($r['message']) ?></p>
    <?php else: ?><p class="muted">Aún no se ha sincronizado. Pulsa "Sincronizar ahora" o configura el cron.</p><?php endif; ?></section>
  <section class="card"><h2>Suscripciones por vencer (7 días)</h2>
    <?php if (!$d['expiring']): ?><p class="muted">Ninguna por ahora.</p><?php else: ?><ul class="similar"><?php foreach ($d['expiring'] as $x): ?><li><a href="<?= e(url('admin/clientes/' . $x['id'])) ?>"><?= e($x['name']) ?></a><strong class="mono"><?= e(fdate($x['sub_ends'])) ?></strong></li><?php endforeach; ?></ul><?php endif; ?></section>
</div>
