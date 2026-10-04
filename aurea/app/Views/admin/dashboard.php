<?php use Aurea\Services\BookingService; ?>
<?php foreach ($alerts as [$lvl, $msg, $link]): ?>
  <div class="flash <?= $lvl === 'err' ? 'err' : ($lvl === 'info' ? '' : '') ?>" style="<?= $lvl === 'warn' ? 'border-color:var(--warn)' : '' ?>"><?= e($msg) ?><?php if ($link): ?> <a href="<?= e(url($link)) ?>"><?= e(__('Ver')) ?> →</a><?php endif; ?></div>
<?php endforeach; ?>
<div class="kpis">
  <div class="kpi"><div class="l"><?= e(term('appts')) ?> <?= e(__('del mes')) ?></div><div class="v num"><?= (int)$kpi['total'] - (int)$kpi['cancelled'] ?></div><div class="s"><?= (int)$kpi['cancelled'] ?> <?= e(__('canceladas')) ?></div></div>
  <div class="kpi"><div class="l"><?= e(__('Ingresos estimados')) ?></div><div class="v num"><?= e(money($kpi['revenue_est'])) ?></div><div class="s"><?= e(__('Cobrado')) ?>: <?= e(money($kpi['revenue_paid'])) ?></div></div>
  <div class="kpi"><div class="l"><?= e(__('Tasa de asistencia')) ?></div><div class="v num"><?= $kpi['attendance'] === null ? '—' : e((string)$kpi['attendance']) . '%' ?></div><div class="s"><?= (int)$kpi['completed'] ?> <?= e(__('completadas')) ?></div></div>
  <div class="kpi"><div class="l"><?= e(__('No asistieron')) ?></div><div class="v num"><?= (int)$kpi['no_show'] ?></div><div class="s"><?= (int)$kpi['pending'] ?> <?= e(__('pendientes por confirmar')) ?></div></div>
</div>
<div class="grid grid-21">
  <div class="card">
    <div class="card-head"><h2><?= e(__('Hoy')) ?> · <?= e(\Aurea\Core\Util::dateLong(date('Y-m-d'))) ?></h2><a class="btn btn-gold btn-sm" href="<?= e(url('/admin/citas/nueva')) ?>">+ <?= e(__('Nueva %s', mb_strtolower(term('appt')))) ?></a></div>
    <?php if (!$today): ?><div class="empty-state"><div class="big"><?= e(__('Agenda libre hoy')) ?></div><p><?= e(__('No hay %s programadas para hoy.', mb_strtolower(term('appts')))) ?></p></div><?php else: ?>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th><?= e(__('Hora')) ?></th><th><?= e(term('client')) ?></th><th><?= e(__('Servicio')) ?></th><th><?= e(term('professional')) ?></th><th><?= e(__('Estado')) ?></th></tr></thead><tbody>
      <?php foreach ($today as $a): ?><tr>
        <td class="num"><b><?= e(ftime($a['start_at'])) ?></b></td>
        <td><a class="strong" href="<?= e(url('/admin/citas/' . $a['id'])) ?>"><?= e($a['client_name']) ?></a></td>
        <td><?= e($a['service_name']) ?></td>
        <td><span class="dot" style="background:<?= e($a['prof_color']) ?>"></span><?= e($a['prof_name']) ?></td>
        <td><span class="st-<?= e($a['status']) ?>"><?= e(BookingService::STATUSES[$a['status']]) ?></span></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </div>
  <div>
    <div class="card">
      <h2><?= e(__('Pendientes por confirmar')) ?></h2>
      <?php if (!$pending): ?><p class="hint"><?= e(__('Nada pendiente. ¡Bien!')) ?></p><?php endif; ?>
      <?php foreach ($pending as $a): ?>
        <div style="display:flex;justify-content:space-between;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)">
          <div><a class="strong" style="font-weight:600;text-decoration:none;color:var(--fg)" href="<?= e(url('/admin/citas/' . $a['id'])) ?>"><?= e($a['client_name']) ?></a><br><span class="hint"><?= e(fdate($a['start_at'])) ?> <?= e(ftime($a['start_at'])) ?> · <?= e($a['service_name']) ?></span></div>
          <form method="post" action="<?= e(url('/admin/citas/' . $a['id'] . '/estado')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="status" value="confirmed"><button class="btn btn-gold btn-sm" type="submit"><?= e(__('Confirmar')) ?></button></form>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="card">
      <h2><?= e(__('Próximos huecos libres')) ?></h2>
      <?php if (!$slots): ?><p class="hint"><?= e(__('No hay horarios disponibles próximamente.')) ?></p><?php endif; ?>
      <?php foreach ($slots as $s): ?>
        <div style="display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)"><span><span class="dot" style="background:<?= e($s['prof']['color']) ?>"></span><?= e($s['prof']['name']) ?></span><b><?= e(\Aurea\Core\Util::dateShort($s['start'])) ?> · <?= e(ftime($s['start'])) ?></b></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
