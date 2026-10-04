<?php
use App\Core\Fmt;

$dayN = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
$dayL = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];
$fmtDate = static fn (string $d): string => implode('/', array_reverse(explode('-', $d)));
$hour12 = static fn (int $h): string => ($h % 12 === 0 ? 12 : $h % 12) . ($h < 12 ? ' a. m.' : ' p. m.');
$kpiDefs = $report ? [
    ['views', 'Visitas a la página', 'int', true],
    ['conversion_pct', 'Conversión', 'pct', true],
    ['bookings', 'Citas', 'int', true],
    ['revenue', 'Ingresos', 'money', true],
    ['cancel_pct', 'Cancelaciones', 'pct', false],
    ['no_show_pct', 'No asistieron', 'pct', false],
] : [];
$fv = static function ($v, string $type): string {
    return $type === 'money' ? money($v) : ($type === 'pct' ? number_format((float) $v, 1) . ' %' : number_format((float) $v, 0, '.', ','));
};
?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title">Analítica</h1><p class="page-sub">Cómo llegan tus clientes, cuántos reservan y qué horarios funcionan mejor.</p></div>
  </div>

  <form class="card mb-3" method="get" action="<?= e(url('/admin/analitica')) ?>">
    <div class="card-body stack">
      <div class="chips" role="group" aria-label="Rangos rápidos">
        <?php foreach ($ranges as $l => $r) : $act = $r[0] === $from && $r[1] === $to; ?>
          <a class="chip<?= $act ? ' is-active' : '' ?>" href="<?= e(url('/admin/analitica', ['desde' => $r[0], 'hasta' => $r[1], 'evento' => $eventId ?: null, 'anfitrion' => $scoped ? null : ($hostId ?: null), 'agrupar' => $group])) ?>"<?= $act ? ' aria-current="true"' : '' ?>><?= e($l) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="form-row p3-filters">
        <div class="field"><label for="an-d">Desde</label><input class="input" type="date" id="an-d" name="desde" value="<?= e($from) ?>"></div>
        <div class="field"><label for="an-h">Hasta</label><input class="input" type="date" id="an-h" name="hasta" value="<?= e($to) ?>"></div>
        <div class="field"><label for="an-e">Servicio</label><select class="select" id="an-e" name="evento"><option value="0">Todos</option><?php foreach ($events as $ev) : ?><option value="<?= (int) $ev['id'] ?>"<?= sel($eventId, $ev['id']) ?>><?= e($ev['name']) ?></option><?php endforeach; ?></select></div>
        <?php if (!$scoped) : ?><div class="field"><label for="an-a">Anfitrión</label><select class="select" id="an-a" name="anfitrion"><option value="0">Todos</option><?php foreach ($hosts as $h) : ?><option value="<?= (int) $h['id'] ?>"<?= sel($hostId, $h['id']) ?>><?= e($h['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <div class="field"><label for="an-g">Agrupar por</label><select class="select" id="an-g" name="agrupar"><option value="day"<?= sel($group, 'day') ?>>Día</option><option value="week"<?= sel($group, 'week') ?>>Semana</option><option value="month"<?= sel($group, 'month') ?>>Mes</option></select></div>
        <div class="field p3-filter-btn"><button class="btn btn-gold" type="submit"><?= icon('filter') ?>Aplicar</button></div>
      </div>
    </div>
  </form>

  <?php if ($error) : ?>
    <div class="alert alert-err" role="alert"><?= e($error) ?></div>
  <?php elseif ($report) : $per = $report['period']; ?>
    <p class="muted mb-3">Del <?= e($fmtDate($per['from'])) ?> al <?= e($fmtDate($per['to'])) ?> (<?= (int) $per['days'] ?> días), comparado con el periodo anterior (<?= e($fmtDate($per['previous_from'])) ?> al <?= e($fmtDate($per['previous_to'])) ?>).</p>

    <div class="grid cols-3 mb-4 p3-kpis">
      <?php foreach ($kpiDefs as [$key, $label, $type, $upGood]) : $k = $report['kpis'][$key]; $d = $k['delta_pct'];
          $good = $d === null || $d == 0 ? null : (($d > 0) === $upGood); ?>
        <div class="stat">
          <div class="stat-label"><?= e($label) ?></div>
          <div class="stat-value serif"><?= e($fv($k['value'], $type)) ?></div>
          <div class="stat-delta <?= $good === null ? 'muted' : ($good ? 'text-ok' : 'text-err') ?>">
            <?php if ($d === null) : ?>Sin datos previos<?php elseif ($d == 0) : ?>Igual que antes<?php else : ?><span aria-hidden="true"><?= $d > 0 ? '▲' : '▼' ?></span> <?= e(number_format(abs((float) $d), 1)) ?> % <span class="muted">vs. <?= e($fv($k['previous'], $type)) ?></span><span class="sr-only"><?= $d > 0 ? ' más que' : ' menos que' ?> el periodo anterior</span><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <section class="card mb-4" aria-labelledby="h-per">
      <div class="card-head"><h2 id="h-per" class="serif">Citas por <?= $group === 'day' ? 'día' : ($group === 'week' ? 'semana' : 'mes') ?></h2>
        <div class="p3-legend"><span><i class="p3-sw p3-sw-main"></i>Citas atendidas o pendientes</span><span><i class="p3-sw p3-sw-lost"></i>Canceladas o no asistieron</span></div></div>
      <div class="card-body">
        <div class="p3-chart" data-chart="period" data-group="<?= e($group) ?>"><p class="muted p3-chart-fallback">Cargando gráfica…</p></div>
        <details class="p3-details mt-3"><summary>Ver los datos como tabla</summary>
          <div class="table-wrap"><table class="table table-sm">
            <caption class="sr-only">Citas por periodo</caption>
            <thead><tr><th scope="col"><?= $group === 'month' ? 'Mes' : ($group === 'week' ? 'Semana del' : 'Día') ?></th><th scope="col" class="right">Citas</th><th scope="col" class="right">Canceladas</th><th scope="col" class="right">No asistieron</th></tr></thead>
            <tbody><?php foreach ($report['by_period']['rows'] as $r) : ?><tr><th scope="row" class="mono"><?= e($group === 'month' ? $r['period'] : $fmtDate($r['period'])) ?></th><td class="right mono"><?= (int) $r['bookings'] ?></td><td class="right mono"><?= (int) $r['cancelled'] ?></td><td class="right mono"><?= (int) $r['no_show'] ?></td></tr><?php endforeach; ?></tbody>
          </table></div>
        </details>
      </div>
    </section>

    <div class="grid cols-2 mb-4">
      <section class="card" aria-labelledby="h-fun">
        <div class="card-head"><h2 id="h-fun" class="serif">Embudo de conversión</h2></div>
        <div class="card-body stack">
          <?php $top = max(1, (int) ($report['funnel'][0]['count'] ?? 0)); foreach ($report['funnel'] as $i => $s) : ?>
            <div class="p3-funnel-row">
              <div class="row row-between"><span><?= e($s['label']) ?></span><span class="mono"><strong><?= (int) $s['count'] ?></strong> <span class="muted">· <?= e(number_format((float) $s['pct'], 1)) ?> %</span></span></div>
              <div class="progress p3-funnel-bar" role="img" aria-label="<?= e($s['label']) ?>: <?= e(number_format((float) $s['pct'], 1)) ?> % de quienes vieron la página"><span <?= vars(['--p' => min(100, round($s['count'] * 100 / $top, 1))]) ?>></span></div>
            </div>
          <?php endforeach; ?>
          <?php if ((int) ($report['funnel'][0]['count'] ?? 0) === 0) : ?><p class="muted">Todavía no hay visitas registradas en este periodo. Cuando tus clientes abran tu página de reservas, verás el embudo aquí.</p><?php endif; ?>
        </div>
      </section>
      <section class="card" aria-labelledby="h-dev">
        <div class="card-head"><h2 id="h-dev" class="serif">Dispositivos</h2></div>
        <div class="card-body stack">
          <?php $dv = $report['devices']; $dt = max(1, $dv['mobile'] + $dv['desktop']); foreach (['mobile' => 'Celular', 'desktop' => 'Computadora'] as $k => $l) : ?>
            <div class="p3-funnel-row"><div class="row row-between"><span><?= e($l) ?></span><span class="mono"><strong><?= (int) $dv[$k] ?></strong> <span class="muted">· <?= e(number_format($dv[$k] * 100 / $dt, 1)) ?> %</span></span></div>
              <div class="progress" role="img" aria-label="<?= e($l) ?>: <?= e(number_format($dv[$k] * 100 / $dt, 1)) ?> %"><span <?= vars(['--p' => round($dv[$k] * 100 / $dt, 1)]) ?>></span></div></div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>

    <div class="stack mb-4">
      <section class="card" aria-labelledby="h-ev">
        <div class="card-head"><h2 id="h-ev" class="serif">Conversión por servicio</h2></div>
        <?php if (!$report['by_event']) : ?><div class="card-body"><p class="muted">Sin datos en este periodo.</p></div><?php else : ?>
        <div class="table-wrap"><table class="table table-sm">
          <caption class="sr-only">Conversión por servicio</caption>
          <thead><tr><th scope="col">Servicio</th><th scope="col" class="right">Visitas</th><th scope="col" class="right">Citas</th><th scope="col" class="right">Conv.</th></tr></thead>
          <tbody><?php foreach ($report['by_event'] as $r) : ?><tr><th scope="row"><?= e($r['name']) ?></th><td class="right mono"><?= (int) $r['views'] ?></td><td class="right mono"><?= (int) $r['bookings'] ?></td><td class="right mono"><?= e(number_format((float) $r['conversion_pct'], 1)) ?> %</td></tr><?php endforeach; ?></tbody>
        </table></div><?php endif; ?>
      </section>
      <section class="card" aria-labelledby="h-src">
        <div class="card-head"><h2 id="h-src" class="serif">Conversión por fuente</h2></div>
        <?php if (!$report['by_source']) : ?><div class="card-body"><p class="muted">Sin datos en este periodo.</p></div><?php else : ?>
        <div class="table-wrap"><table class="table table-sm">
          <caption class="sr-only">Conversión por fuente de visitas</caption>
          <thead><tr><th scope="col">Fuente</th><th scope="col" class="right">Visitas</th><th scope="col" class="right">Citas</th><th scope="col" class="right">Conv.</th></tr></thead>
          <tbody><?php foreach ($report['by_source'] as $r) : ?><tr><th scope="row"><?= e($r['source'] === 'directo' ? 'Directo' : $r['source']) ?></th><td class="right mono"><?= (int) $r['views'] ?></td><td class="right mono"><?= (int) $r['bookings'] ?></td><td class="right mono"><?= e(number_format((float) $r['conversion_pct'], 1)) ?> %</td></tr><?php endforeach; ?></tbody>
        </table></div><?php endif; ?>
      </section>
    </div>

    <section class="card mb-4" aria-labelledby="h-pk">
      <div class="card-head"><h2 id="h-pk" class="serif">Horas y días pico</h2>
        <p class="muted p3-peak-sum"><?php if ($report['peak']['peak_hour'] !== null) : ?>Tu hora más solicitada es las <strong><?= e($hour12((int) $report['peak']['peak_hour'])) ?></strong> y el día más fuerte es el <strong><?= e($dayL[(int) $report['peak']['peak_weekday']] ?? '') ?></strong>.<?php else : ?>Aún no hay citas en este periodo.<?php endif; ?></p></div>
      <div class="card-body stack">
        <?php $hrs = $report['peak']['by_hour']; $maxH = max(1, max($hrs)); ?>
        <div><p class="p3-heat-title">Por hora del día</p>
          <ol class="p3-heat p3-heat-24" aria-label="Citas por hora del día">
            <?php foreach ($hrs as $h => $n) : ?><li <?= vars(['--i' => round($n / $maxH, 2)]) ?> class="<?= $n === $maxH && $n > 0 ? 'is-peak' : '' ?>" title="<?= e($hour12((int) $h) . ': ' . $n . ' citas') ?>"><span class="p3-heat-n mono"><?= (int) $n ?></span><span class="p3-heat-l"><?= $h % 3 === 0 ? (int) $h : '' ?></span><span class="sr-only"><?= e($hour12((int) $h) . ', ' . $n . ' citas') ?></span></li><?php endforeach; ?>
          </ol></div>
        <?php $wd = $report['peak']['by_weekday']; $maxD = max(1, max($wd)); ?>
        <div><p class="p3-heat-title">Por día de la semana</p>
          <ol class="p3-heat p3-heat-7" aria-label="Citas por día de la semana">
            <?php foreach ($wd as $d => $n) : ?><li <?= vars(['--i' => round($n / $maxD, 2)]) ?> class="<?= $n === $maxD && $n > 0 ? 'is-peak' : '' ?>" title="<?= e(ucfirst($dayL[$d]) . ': ' . $n . ' citas') ?>"><span class="p3-heat-n mono"><?= (int) $n ?></span><span class="p3-heat-l"><?= e($dayN[$d]) ?></span><span class="sr-only"><?= e($dayL[$d] . ', ' . $n . ' citas') ?></span></li><?php endforeach; ?>
          </ol></div>
      </div>
    </section>

    <section class="card mb-4" aria-labelledby="h-oc">
      <div class="card-head"><h2 id="h-oc" class="serif">Ocupación por anfitrión</h2></div>
      <?php if (!$report['occupancy']) : ?><div class="card-body"><p class="muted">No hay anfitriones activos.</p></div><?php else : ?>
      <ul class="p3-list">
        <?php foreach ($report['occupancy'] as $o) : ?>
          <li class="p3-list-item"><div class="p3-occ-name"><strong><?= e($o['name']) ?></strong><div class="muted mono"><?= e(Fmt::duration((int) $o['booked_minutes'])) ?> de <?= e(Fmt::duration((int) $o['capacity_minutes'])) ?></div></div>
            <?php if ($o['pct'] === null) : ?><span class="muted">Sin horario definido</span><?php else : ?>
              <div class="p3-bar-row p3-occ"><div class="progress" role="img" aria-label="<?= e($o['name']) ?>: <?= e((string) $o['pct']) ?> % de ocupación"><span <?= vars(['--p' => min(100, (float) $o['pct'])]) ?>></span></div><span class="mono"><?= e(number_format((float) $o['pct'], 1)) ?> %</span></div><?php endif; ?></li>
        <?php endforeach; ?>
      </ul><?php endif; ?>
    </section>

    <?php if (!$scoped) : ?>
    <section class="card" aria-labelledby="h-ex">
      <div class="card-head"><h2 id="h-ex" class="serif">Exportar a CSV</h2></div>
      <div class="card-body row row-wrap gap-2">
        <?php foreach (['bookings' => 'Citas', 'payments' => 'Pagos', 'clients' => 'Clientes', 'analytics' => 'Resumen de analítica'] as $k => $l) : ?>
          <a class="btn btn-outline" href="<?= e(url('/admin/analitica/exportar', ['tipo' => $k, 'desde' => $from, 'hasta' => $to])) ?>"><?= icon('download') ?><?= e($l) ?></a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
    <script type="application/json" id="boot"><?= json_script(['period' => $report['by_period']['rows'], 'group' => $group]) ?></script>
  <?php endif; ?>
</div>
