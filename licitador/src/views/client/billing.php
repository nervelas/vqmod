<?php $days = $u['sub_ends'] ? days_left($u['sub_ends']) : null; $active = client_has_access($u); ?>
<div class="page-head"><div><p class="eyebrow">Suscripción</p><h1>Tu plan</h1></div></div>
<section class="card sub-status <?= $active ? '' : 'urgent' ?>">
  <div><small>Plan actual</small><strong><?= e($plan['name']) ?></strong></div>
  <div><small>Estado</small><strong><?= $active ? 'Activo' : 'Vencido' ?></strong></div>
  <div><small>Vence</small><strong class="mono"><?= e(fdate($u['sub_ends'])) ?><?= $active && $days !== null ? ' (' . $days . ' d)' : '' ?></strong></div>
</section>
<h2>Elige o renueva tu plan</h2>
<div class="plans compact">
<?php foreach ($plans as $p): ?>
  <article class="plan card <?= $p['highlight'] ? 'plan-hl' : '' ?>"><h3><?= e($p['name']) ?></h3><p class="price"><span class="mono"><?= e(money($p['price'])) ?></span><small>/ <?= (int) $p['period_days'] ?> días</small></p>
  <ul class="ticks"><?php foreach (array_filter(array_map('trim', explode("\n", (string) $p['features']))) as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul>
  <?php if ($p['pay_link']): ?><a class="btn btn-ghost btn-sm" href="<?= e($p['pay_link']) ?>" target="_blank" rel="noopener noreferrer">Pagar en línea ↗</a><?php endif; ?></article>
<?php endforeach; ?>
</div>
<section class="card">
  <h2>Pagar por depósito o transferencia</h2>
  <pre class="bank"><?= e(S('bank_info')) ?></pre>
  <form method="post" enctype="multipart/form-data" class="form narrow-form"><?= csrf_field() ?>
    <label>Plan que pagaste<select name="plan_id" required><?php foreach ($plans as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $u['plan_id'] == $p['id'] ? 'selected' : '' ?>><?= e($p['name'] . ' · ' . money($p['price'])) ?></option><?php endforeach; ?></select></label>
    <?= f_input('method', 'Método', 'Transferencia', 'text', 'maxlength="40"') ?>
    <?= f_input('reference', 'Número de boleta o referencia', '') ?>
    <label>Comprobante (foto o PDF, máx. 3 MB)<input type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>
    <button class="btn btn-gold">Enviar comprobante</button>
  </form>
</section>
<?php if ($pays): ?><h2>Mis pagos</h2><div class="tbl-wrap"><table class="tbl"><tr><th>Fecha</th><th>Plan</th><th>Monto</th><th>Estado</th></tr>
<?php foreach ($pays as $p): ?><tr><td data-l="Fecha"><?= e(fdate($p['created_at'])) ?></td><td data-l="Plan"><?= e($p['plan_name']) ?></td><td class="mono" data-l="Monto"><?= e(money($p['amount'])) ?></td><td data-l="Estado"><span class="pill <?= $p['status'] === 'aprobado' ? 'p-new' : ($p['status'] === 'rechazado' ? 'p-urgent' : 'p-soon') ?>"><?= e(ucfirst($p['status'])) ?></span></td></tr><?php endforeach; ?></table></div><?php endif; ?>
