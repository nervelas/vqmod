<div class="page-head"><div><p class="eyebrow">Calendario</p><h1>Próximos <em>vencimientos</em></h1></div></div>
<?php if (!$items): ?>
<div class="empty card"><div class="eic"><?= icon('calendar') ?></div><h3>No tienes vencimientos próximos</h3><p class="muted">Cuando haya concursos activos que te sirvan, verás aquí sus fechas límite ordenadas.</p><a class="btn btn-gold" href="<?= e(url('app')) ?>">Ver mis oportunidades</a></div>
<?php else: $last = ''; foreach ($items as $t): $wk = fdate($t['deadline']); if ($wk !== $last): if ($last) echo '</ul>'; $last = $wk; $dl = days_left($t['deadline']); ?>
  <h3 class="day-h <?= $dl <= 3 ? 'urgent' : '' ?>"><span class="mono"><?= e($wk) ?></span> <small><?= e(days_label($t['deadline'])) ?></small></h3><ul class="day-list">
<?php endif; ?>
  <li class="card <?= urgency_class($t['deadline']) ?>"><a href="<?= e(url('app/oportunidad/' . $t['id'])) ?>"><strong><?= e($t['title']) ?></strong></a><small class="muted"><?= e($t['entity']) ?> · <span class="mono"><?= e(money($t['amount'])) ?></span> · <?= e(date('H:i', strtotime($t['deadline']))) ?> h</small></li>
<?php endforeach; echo '</ul>'; endif; ?>
