<?php $r = $d['run']; ?>
<div class="page-head"><div><p class="eyebrow">Hola, <?= e(explode(' ', $a['name'])[0]) ?></p><h1>Tablero</h1></div>
<form method="post" action="<?= e(url('admin/fuente/sincronizar')) ?>"><?= csrf_field() ?><button class="btn btn-gold btn-sm">Sincronizar ahora</button></form></div>
<div class="kpis">
  <div class="kpi card"><small>Clientes activos</small><strong class="mono"><?= $d['active'] ?></strong><span class="muted"><?= $d['paying'] ?> con plan pagado</span></div>
  <div class="kpi card"><small>Ingresos del mes</small><strong class="mono"><?= e(money($d['income'])) ?></strong><a href="<?= e(url('admin/pagos')) ?>"><?= $d['pending_pay'] ?> pago(s) por revisar</a></div>
  <div class="kpi card"><small>Concursos importados hoy</small><strong class="mono"><?= $d['imported'] ?></strong><span class="muted"><?= $d['open'] ?> abiertos · <?= $d['total'] ?> en total</span></div>
  <div class="kpi card"><small>Alertas enviadas hoy</small><strong class="mono"><?= $d['alerts'] ?></strong><a href="<?= e(url('admin/notificaciones')) ?>">Ver cola</a></div>
</div>
<div class="two-col top">
  <section class="card"><h2>Última sincronización</h2>
    <?php if ($r): ?><p><span class="pill <?= $r['status'] === 'ok' ? 'p-new' : ($r['status'] === 'parcial' ? 'p-soon' : 'p-urgent') ?>"><?= e(strtoupper($r['status'])) ?></span> <span class="mono"><?= e(fdate($r['started_at'], true)) ?></span> · <?= e($r['source']) ?></p><p class="muted small"><?= e($r['message']) ?></p>
    <?php else: ?><p class="muted">Aún no se ha sincronizado. Pulsa "Sincronizar ahora" o configura el cron.</p><?php endif; ?></section>
  <section class="card"><h2>Suscripciones por vencer (7 días)</h2>
    <?php if (!$d['expiring']): ?><p class="muted">Ninguna por ahora.</p><?php else: ?><ul class="similar"><?php foreach ($d['expiring'] as $x): ?><li><a href="<?= e(url('admin/clientes/' . $x['id'])) ?>"><?= e($x['name']) ?></a><strong class="mono"><?= e(fdate($x['sub_ends'])) ?></strong></li><?php endforeach; ?></ul><?php endif; ?></section>
</div>
