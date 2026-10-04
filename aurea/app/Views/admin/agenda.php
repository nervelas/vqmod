<?php use Aurea\Services\BookingService; ?>
<div data-shortcuts="<?= e(json_encode($shortcuts)) ?>"></div>
<div class="toolbar no-print">
  <div class="btn-row" style="margin:0">
    <a class="btn btn-line btn-sm" href="<?= e(url($nav['prev'])) ?>" aria-label="<?= e(__('Anterior')) ?>">‹</a>
    <a class="btn btn-line btn-sm" href="<?= e(url($nav['today'])) ?>"><?= e(__('Hoy')) ?></a>
    <a class="btn btn-line btn-sm" href="<?= e(url($nav['next'])) ?>" aria-label="<?= e(__('Siguiente')) ?>">›</a>
  </div>
  <div class="grow"><h2 class="ag-title" style="margin:0;font-size:1.5rem;min-width:220px"><?= e($label) ?></h2></div>
  <form method="get" action="<?= e(url('/admin/agenda')) ?>" class="toolbar" style="margin:0">
    <input type="hidden" name="vista" value="<?= e($view) ?>"><input type="hidden" name="fecha" value="<?= e($date) ?>">
    <?php if (!$scope): ?><div class="field"><label class="sr-only" for="prof"><?= e(term('professional')) ?></label><select id="prof" name="prof" data-autosubmit><option value=""><?= e(__('Todos')) ?></option><?php foreach ($profs as $p): ?><option value="<?= (int)$p['id'] ?>"<?= sel($p['id'], $profSel) ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <label class="check" style="margin:0"><input type="checkbox" name="canceladas" value="1" <?= chk($showCancelled) ?> data-autosubmit><span><?= e(__('Ver canceladas')) ?></span></label>
  </form>
  <div class="btn-row" style="margin:0">
    <a class="btn btn-sm <?= $view === 'dia' ? 'btn-ink' : 'btn-line' ?>" href="<?= e(url($nav['day'])) ?>"><?= e(__('Día')) ?></a>
    <a class="btn btn-sm <?= $view === 'semana' ? 'btn-ink' : 'btn-line' ?>" href="<?= e(url($nav['week'])) ?>"><?= e(__('Semana')) ?></a>
    <a class="btn btn-sm <?= $view === 'mes' ? 'btn-ink' : 'btn-line' ?>" href="<?= e(url($nav['month'])) ?>"><?= e(__('Mes')) ?></a>
  </div>
  <a class="btn btn-gold btn-sm" href="<?= e(url('/admin/citas/nueva?fecha=' . $date . ($profSel ? '&prof=' . $profSel : ''))) ?>">+ <?= e(__('Nueva %s', mb_strtolower(term('appt')))) ?></a>
  <button class="btn btn-line btn-sm no-print" type="button" data-print><?= e(__('Imprimir')) ?></button>
</div>

<?php if ($mode === 'grid'): ?>
<div class="ag-wrap"><div class="ag" style="--cols:<?= count($cols) ?>;--h:<?= round($H) ?>px">
  <div class="ag-head"></div>
  <?php foreach ($cols as $c): ?>
    <div class="ag-head <?= $c['today'] ? 'today' : '' ?>"><?= e($c['label']) ?><?php if ($view === 'semana'): ?><small><?= e(fdate($c['date'])) ?></small><?php endif; ?><?php if ($c['holiday']): ?><small class="st-cancelled">🎌 <?= e($c['holiday']) ?></small><?php endif; ?></div>
  <?php endforeach; ?>
  <div class="ag-time"><?php foreach ($times as [$top, $lbl]): ?><span style="top:<?= round($top) ?>px"><?= e($lbl) ?></span><?php endforeach; ?></div>
  <?php foreach ($cols as $c): ?>
    <div class="ag-col">
      <?php foreach ($c['off'] as $o): ?><div class="ag-off" style="top:<?= round($o['top']) ?>px;height:<?= round($o['h']) ?>px"></div><?php endforeach; ?>
      <?php if (!$scope || true): foreach ($c['slots'] as [$top, $tm]): ?><a class="ag-slot" style="top:<?= round($top) ?>px" tabindex="-1" aria-label="<?= e(__('Nueva %s %s %s', mb_strtolower(term('appt')), fdate($c['date']), $tm)) ?>" href="<?= e(url('/admin/citas/nueva?' . http_build_query(['fecha' => $c['date'], 'hora' => $tm, 'prof' => $c['prof']['id'] ?? $profSel ?: '']))) ?>"></a><?php endforeach; endif; ?>
      <?php foreach ($c['blocks'] as $b): ?><div class="ag-ev off" style="top:<?= round($b['top']) ?>px;height:<?= round($b['h']) ?>px;left:2px;right:2px"><b><?= e($b['label']) ?></b></div><?php endforeach; ?>
      <?php foreach ($c['events'] as $ev): ?>
        <a class="ag-ev s-<?= e($ev['status']) ?>" href="<?= e(url('/admin/citas/' . $ev['id'])) ?>" style="--c:<?= e($ev['color']) ?>;top:<?= round($ev['top']) ?>px;height:<?= round($ev['h']) ?>px;left:calc(<?= round($ev['left'], 2) ?>% + 2px);width:calc(<?= round($ev['w'], 2) ?>% - 4px)" title="<?= e($ev['client_name'] . ' · ' . $ev['service_name']) ?>">
          <b><?= e(ftime($ev['start_at'])) ?> <?= e($ev['client_name']) ?><?= $ev['client_confirmed_at'] ? ' ✓' : '' ?></b><?= e($ev['service_name']) ?>
        </a>
      <?php endforeach; ?>
      <?php if ($now !== null && $c['today']): ?><div class="ag-now" style="top:<?= round($now) ?>px"></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div></div>
<?php else: ?>
<div class="month">
  <?php foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $d): ?><div class="dow"><?= e($d) ?></div><?php endforeach; ?>
  <?php foreach ($cells as $c): ?>
    <a class="day <?= $c['out'] ? 'out' : '' ?> <?= $c['today'] ? 'today' : '' ?> <?= $c['hol'] ? 'hol' : '' ?>" href="<?= e(url('/admin/agenda?' . http_build_query(['vista' => 'dia', 'fecha' => $c['date'], 'prof' => $profSel ?: '']))) ?>" <?= $c['hol'] ? 'title="' . e($c['hol']) . '"' : '' ?>>
      <span class="dn num"><?= (int)substr($c['date'], 8, 2) ?></span>
      <?php foreach (array_slice($c['events'], 0, 3) as $ev): ?><span class="ev" style="--c:<?= e($ev['color']) ?>"><?= e(ftime($ev['start_at'])) ?> <?= e($ev['client_name']) ?></span><?php endforeach; ?>
      <?php if (count($c['events']) > 3): ?><span class="more">+<?= count($c['events']) - 3 ?> <?= e(__('más')) ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<div class="legend no-print">
  <?php foreach ($profs as $p): ?><span><span class="dot" style="background:<?= e($p['color']) ?>"></span><?= e($p['name']) ?></span><?php endforeach; ?>
  <span><?= e(__('Borde punteado: pendiente')) ?></span><span><?= e(__('Zona rayada: fuera de horario, ausencia o feriado')) ?></span><span><?= e(__('Atajos: n nueva · t hoy · d/w/m vista · ← →')) ?></span>
</div>
<?php if (\Aurea\Core\Auth::can('schedule_own')): ?>
<details class="card no-print" style="margin-top:18px"><summary style="cursor:pointer;font-weight:600"><?= e(__('Bloquear horario (reunión, trámite, descanso)')) ?></summary>
  <form method="post" action="<?= e(url('/admin/bloqueos')) ?>" class="toolbar" style="margin-top:14px"><?= csrf_field() ?>
    <?php if (!$scope): ?><div class="field"><label for="bp"><?= e(term('professional')) ?></label><select id="bp" name="professional_id"><option value=""><?= e(__('Todos')) ?></option><?php foreach ($profs as $p): ?><option value="<?= (int)$p['id'] ?>"<?= sel($p['id'], $profSel) ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <div class="field"><label for="bs"><?= e(__('Desde')) ?></label><input id="bs" type="datetime-local" name="start_at" required value="<?= e($date) ?>T09:00"></div>
    <div class="field"><label for="be"><?= e(__('Hasta')) ?></label><input id="be" type="datetime-local" name="end_at" required value="<?= e($date) ?>T10:00"></div>
    <div class="field"><label for="br"><?= e(__('Motivo')) ?></label><input id="br" name="reason" maxlength="190"></div>
    <button class="btn btn-ink" type="submit"><?= e(__('Bloquear')) ?></button></form></details>
<?php endif; ?>
