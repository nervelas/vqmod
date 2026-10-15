<div class="page-head"><div><p class="eyebrow">Administración</p><h1>Pagos</h1></div></div>
<div class="tabs"><?php foreach (['pendiente' => 'Por revisar', 'aprobado' => 'Aprobados', 'rechazado' => 'Rechazados', 'todos' => 'Todos'] as $k => $l): ?><a class="<?= $f === $k ? 'on' : '' ?>" href="<?= e(url('admin/pagos?f=' . $k)) ?>"><?= $l ?></a><?php endforeach; ?></div>
<div class="tbl-wrap"><table class="tbl"><tr><th>Fecha</th><th>Cliente</th><th>Plan</th><th>Monto</th><th>Referencia</th><th>Estado</th><th></th></tr>
<?php foreach ($list as $p): ?><tr><td data-l="Fecha"><?= e(fdate($p['created_at'])) ?></td><td data-l="Cliente"><?= e($p['name']) ?><br><small class="muted"><?= e($p['email']) ?></small></td><td data-l="Plan"><?= e($p['plan_name']) ?></td><td class="mono" data-l="Monto"><?= e(money($p['amount'])) ?></td>
<td data-l="Ref."><?= e($p['method']) ?> <?= e($p['reference']) ?><?php if ($p['receipt']): ?> · <a href="<?= e(url('archivo/comprobante/' . $p['id'])) ?>" target="_blank" rel="noopener">comprobante</a><?php endif; ?></td>
<td data-l="Estado"><span class="pill <?= $p['status'] === 'aprobado' ? 'p-new' : ($p['status'] === 'rechazado' ? 'p-urgent' : 'p-soon') ?>"><?= e(ucfirst($p['status'])) ?></span></td>
<td class="btn-row"><?php if ($p['status'] === 'pendiente'): ?><form method="post" action="<?= e(url('admin/pagos/' . $p['id'] . '/aprobar')) ?>"><?= csrf_field() ?><button class="btn btn-gold btn-sm">Aprobar</button></form><form method="post" action="<?= e(url('admin/pagos/' . $p['id'] . '/rechazar')) ?>"><?= csrf_field() ?><button class="btn btn-danger btn-sm">Rechazar</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$list) echo '<tr><td colspan="7" class="muted">No hay pagos en esta vista.</td></tr>'; ?></table></div>
<form method="post" action="<?= e(url('admin/pagos/registrar')) ?>" enctype="multipart/form-data" class="card form"><?= csrf_field() ?>
  <h2>Registrar pago manual (transferencia / depósito)</h2><p class="muted small">Al guardar, se activa el plan y se calcula el vencimiento automáticamente.</p>
  <div class="two-col">
  <label>Cliente<select name="user_id" required><option value="">Elige…</option><?php foreach ($clients as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name'] . ' · ' . $c['email']) ?></option><?php endforeach; ?></select></label>
  <label>Plan<select name="plan_id" required><?php foreach ($plans as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name'] . ' · ' . money($p['price'])) ?></option><?php endforeach; ?></select></label>
  <?= f_input('amount', 'Monto recibido (Q, vacío = precio del plan)', '', 'text', 'inputmode="decimal"') ?><?= f_input('period_days', 'Días a activar (vacío = los del plan)', '', 'number') ?>
  <?= f_input('method', 'Método', 'Transferencia') ?><?= f_input('reference', 'Boleta / referencia', '') ?></div>
  <label>Comprobante (opcional)<input type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>
  <?= f_input('notes', 'Notas', '') ?>
  <button class="btn btn-gold">Registrar y activar</button>
</form>
