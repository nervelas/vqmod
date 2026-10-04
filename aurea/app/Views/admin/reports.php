<?php $dn = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']; $max = 1; foreach ($peak as $wd => $hs) { foreach ($hs as $n) { $max = max($max, $n); } } $hmin = 24; $hmax = 0; foreach ($peak as $hs) { foreach ($hs as $h => $n) { $hmin = min($hmin, $h); $hmax = max($hmax, $h); } } if ($hmin > $hmax) { $hmin = 8; $hmax = 17; }
$tot = max(1, $nr['new'] + $nr['returning']); $maxC = 1; foreach (array_merge($byPro, $bySvc) as $r) { $maxC = max($maxC, (int)$r['citas']); } ?>
<form method="get" class="toolbar no-print"><div class="field"><label for="d1"><?= e(__('Desde')) ?></label><input id="d1" type="date" name="desde" value="<?= e($from) ?>"></div><div class="field"><label for="d2"><?= e(__('Hasta')) ?></label><input id="d2" type="date" name="hasta" value="<?= e($to) ?>"></div>
  <button class="btn btn-ink" type="submit"><?= e(__('Ver')) ?></button>
  <a class="btn btn-line" href="<?= e(url('/admin/reportes/csv?' . http_build_query(['desde' => $from, 'hasta' => $to]))) ?>"><?= e(__('Exportar citas (CSV)')) ?></a>
  <a class="btn btn-line" href="<?= e(url('/admin/reportes/csv?' . http_build_query(['desde' => $from, 'hasta' => $to, 'tipo' => 'servicios']))) ?>"><?= e(__('CSV por servicio')) ?></a>
  <a class="btn btn-line" href="<?= e(url('/admin/reportes/csv?' . http_build_query(['desde' => $from, 'hasta' => $to, 'tipo' => 'profesionales']))) ?>"><?= e(__('CSV por profesional')) ?></a></form>
<div class="kpis">
  <div class="kpi"><div class="l"><?= e(term('appts')) ?></div><div class="v num"><?= (int)$sum['total'] ?></div><div class="s"><?= (int)$sum['completed'] ?> <?= e(__('completadas')) ?> · <?= (int)$sum['cancelled'] ?> <?= e(__('canceladas')) ?></div></div>
  <div class="kpi"><div class="l"><?= e(__('Ingresos estimados')) ?></div><div class="v num"><?= e(money($sum['revenue_est'])) ?></div><div class="s"><?= e(__('Cobrado')) ?>: <?= e(money($sum['revenue_paid'])) ?></div></div>
  <div class="kpi"><div class="l"><?= e(__('Asistencia')) ?></div><div class="v num"><?= $sum['attendance'] === null ? '—' : e((string)$sum['attendance']) . '%' ?></div></div>
  <div class="kpi"><div class="l"><?= e(__('No asistieron')) ?></div><div class="v num"><?= (int)$sum['no_show'] ?></div></div>
</div>
<div class="grid grid-2">
  <div class="card"><h3><?= e(__('Por %s', mb_strtolower(term('professional')))) ?></h3><?php foreach ($byPro as $r): ?><div style="margin-bottom:12px"><div style="display:flex;justify-content:space-between"><b><?= e($r['name']) ?></b><span><?= (int)$r['citas'] ?> · <?= e(money($r['ingresos'])) ?></span></div><div class="bar"><i style="width:<?= round((int)$r['citas'] / $maxC * 100) ?>%"></i></div></div><?php endforeach; if (!$byPro): ?><p class="hint"><?= e(__('Sin datos.')) ?></p><?php endif; ?></div>
  <div class="card"><h3><?= e(__('Por servicio')) ?></h3><?php foreach ($bySvc as $r): ?><div style="margin-bottom:12px"><div style="display:flex;justify-content:space-between"><b><?= e($r['name']) ?></b><span><?= (int)$r['citas'] ?> · <?= e(money($r['ingresos'])) ?></span></div><div class="bar"><i style="width:<?= round((int)$r['citas'] / $maxC * 100) ?>%"></i></div></div><?php endforeach; if (!$bySvc): ?><p class="hint"><?= e(__('Sin datos.')) ?></p><?php endif; ?></div>
</div>
<div class="grid grid-2">
  <div class="card"><h3><?= e(__('Horas pico')) ?></h3><div class="heat" style="--cols:<?= $hmax - $hmin + 1 ?>"><span></span><?php for ($h = $hmin; $h <= $hmax; $h++): ?><span class="hint" style="text-align:center"><?= $h ?></span><?php endfor; ?>
    <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $wd): ?><span class="hint"><?= e($dn[$wd]) ?></span><?php for ($h = $hmin; $h <= $hmax; $h++): $n = $peak[$wd][$h] ?? 0; ?><span class="c" style="--a:<?= round($n / $max, 2) ?>" title="<?= (int)$n ?>"><?= $n ?: '' ?></span><?php endfor; endforeach; ?></div></div>
  <div class="card"><h3><?= e(__('%s nuevos vs recurrentes', term('clients'))) ?></h3>
    <p><b class="num" style="font-size:2rem"><?= (int)$nr['new'] ?></b> <?= e(__('nuevos')) ?> &nbsp; <b class="num" style="font-size:2rem"><?= (int)$nr['returning'] ?></b> <?= e(__('recurrentes')) ?></p>
    <div class="bar" style="height:14px"><i style="width:<?= round($nr['new'] / $tot * 100) ?>%"></i></div></div>
</div>
