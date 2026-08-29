<?php
page('withChart', true);
$sym = (string) $company['currency_symbol'];
$delta = $wonPrev > 0 ? round(($won - $wonPrev) / $wonPrev * 100) : ($won > 0 ? 100 : 0);
$statuses = \App\Models\Quote::STATUSES;
$maxProd = max(1, (int) ($topProducts[0]['veces'] ?? 1));
$inlineScript = '<script nonce="' . e($nonce) . '">' . <<<JS
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') { return; }
  var css = getComputedStyle(document.documentElement);
  var accent = css.getPropertyValue('--accent').trim() || '#E8590C';
  var ink = css.getPropertyValue('--ink').trim() || '#1C1F22';
  var steel = '#8B939C';
  Chart.defaults.font.family = "Inter, system-ui, sans-serif";
  Chart.defaults.font.size = 11;
  Chart.defaults.color = steel;
  var d = window.CP.charts || {};
  var line = document.getElementById('chartTrend');
  if (line) {
    new Chart(line, {
      type: 'bar',
      data: { labels: d.labels, datasets: [
        { label: 'Cotizado', data: d.quoted, backgroundColor: 'rgba(139,147,156,.30)', borderColor: steel, borderWidth: 1, borderRadius: 3, barPercentage: .78 },
        { label: 'Ganado', data: d.won, backgroundColor: accent, borderRadius: 3, barPercentage: .78 }
      ]},
      options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'rect' } } },
        scales: { x: { grid: { display: false } }, y: { border: { display: false }, grid: { color: 'rgba(28,31,34,.07)' },
          ticks: { callback: function (v) { return d.sym + Number(v).toLocaleString('es-GT'); } } } } }
    });
  }
  var don = document.getElementById('chartStatus');
  if (don) {
    new Chart(don, {
      type: 'doughnut',
      data: { labels: d.stLabels, datasets: [{ data: d.stValues, backgroundColor: d.stColors, borderColor: '#fff', borderWidth: 2 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '62%',
        plugins: { legend: { position: 'right', labels: { boxWidth: 9, boxHeight: 9, usePointStyle: true, pointStyle: 'rect' } } } }
    });
  }
});
JS . '</script>';
page('inlineScript', $inlineScript);
$jsCfg = ['charts' => [
    'labels' => array_column($series, 'label'),
    'quoted' => array_map(static fn ($r) => round($r['cotizado'], 2), $series),
    'won'    => array_map(static fn ($r) => round($r['ganado'], 2), $series),
    'sym'    => $sym,
    'stLabels' => array_map(static fn ($k) => $statuses[$k]['short'], array_keys($statuses)),
    'stValues' => array_map(static fn ($k) => (int) ($byStatus[$k]['n'] ?? 0), array_keys($statuses)),
    'stColors' => ['#8B939C', '#5A6470', '#1C1F22', '#B7791F', '#2E7D4F', '#B4342A'],
]];
page('jsConfig', $jsCfg);
page('barActions', '<a class="btn btn--accent btn--sm" href="' . e(url('/panel/cotizaciones/nueva')) . '">Nueva cotización</a>');
?>
<dl class="kpis">
  <div class="kpi"><dt>Cotizado este mes</dt><dd><?= e(money($quoted, $sym)) ?></dd>
    <div class="kpi__delta"><?= e(array_sum(array_column($byStatus, 'n'))) ?> cotizaciones vivas</div></div>
  <div class="kpi"><dt>Ganado este mes</dt><dd><?= e(money($won, $sym)) ?></dd>
    <div class="kpi__delta <?= $delta > 0 ? 'up' : ($delta < 0 ? 'down' : '') ?>">
      <span aria-hidden="true"><?= $delta > 0 ? '▲' : ($delta < 0 ? '▼' : '—') ?></span>
      <?= e(abs($delta)) ?>% vs. mes anterior</div></div>
  <div class="kpi"><dt>Tasa de conversión</dt><dd><?= e(qty($conv)) ?> %</dd>
    <div class="kpi__delta"><?= e((int) ($byStatus['aprobada']['n'] ?? 0)) ?> ganadas · <?= e((int) ($byStatus['perdida']['n'] ?? 0)) ?> perdidas</div></div>
  <div class="kpi"><dt>Respuesta promedio</dt><dd><?= e($avgResponse > 0 ? qty(round($avgResponse, 1)) . ' h' : '—') ?></dd>
    <div class="kpi__delta">Desde la solicitud hasta el envío</div></div>
</dl>

<div class="cols cols--main">
  <div class="stack">
    <div class="card">
      <div class="card__head">
        <span class="secnum">01/</span>
        <h2>Cotizado vs. ganado — 6 meses</h2>
        <a class="btn btn--ghost btn--xs ml-auto" href="<?= e(url('/panel/reportes')) ?>">Ver reportes</a>
      </div>
      <div class="card__body"><div class="chartbox"><canvas id="chartTrend" aria-label="Gráfica de cotizado contra ganado por mes" role="img"></canvas></div></div>
    </div>

    <div class="card">
      <div class="card__head">
        <span class="secnum">02/</span>
        <h2>Necesitan seguimiento</h2>
        <a class="btn btn--ghost btn--xs ml-auto" href="<?= e(url('/panel/tablero')) ?>">Abrir el tablero</a>
      </div>
      <div class="card__body card__body--flush tablescroll">
        <?php if (!$stale): ?>
          <p class="muted" style="padding:26px;text-align:center;margin:0">Todo al día. No hay cotizaciones abiertas sin contacto reciente.</p>
        <?php else: ?>
          <table class="datatable" style="border:0;border-radius:0">
            <caption class="sr-only">Cotizaciones con más días sin contacto</caption>
            <thead><tr><th scope="col">Número</th><th scope="col">Cliente</th><th scope="col">Estado</th><th scope="col">Último contacto</th><th scope="col" class="num">Monto</th></tr></thead>
            <tbody>
              <?php foreach ($stale as $r): $light = \App\Models\Quote::trafficLight($r); ?>
                <tr>
                  <td class="nowrap"><a href="<?= e(url('/panel/cotizaciones/' . $r['id'])) ?>"><strong><?= e($r['number']) ?></strong></a></td>
                  <td><?= e(str_limit((string) ($r['contact_company'] ?: $r['contact_name']), 34)) ?></td>
                  <td><span class="badge"><?= e($statuses[$r['status']]['short']) ?></span></td>
                  <td class="nowrap"><span class="qcard__dot dot-<?= e($light) ?>" style="display:inline-block;margin-right:6px"></span><?= e(humanDays((string) ($r['last_contact_at'] ?: $r['created_at']))) ?></td>
                  <td class="num nowrap"><?= e(money((float) $r['total'], $sym)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card__head"><span class="secnum">03/</span><h2>Por estado</h2></div>
      <div class="card__body"><div class="chartbox chartbox--sm"><canvas id="chartStatus" aria-label="Distribución de cotizaciones por estado" role="img"></canvas></div></div>
    </div>

    <div class="card">
      <div class="card__head"><span class="secnum">04/</span><h2>Más cotizados</h2></div>
      <div class="card__body">
        <?php if (!$topProducts): ?><p class="muted small" style="margin:0">Aún sin datos.</p><?php endif; ?>
        <?php foreach ($topProducts as $tp): ?>
          <div class="stat-line">
            <span class="code-chip"><?= e($tp['code']) ?></span>
            <span class="small" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($tp['name']) ?></span>
            <span class="bar"><i style="width:<?= e(round((int) $tp['veces'] / $maxProd * 100)) ?>%"></i></span>
            <b><?= e((int) $tp['veces']) ?></b>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><span class="secnum">05/</span><h2>Ranking de vendedores</h2></div>
      <div class="card__body">
        <?php foreach ($ranking as $i => $r): ?>
          <div class="stat-line">
            <span class="secnum"><?= e(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) ?>/</span>
            <span class="small"><?= e($r['name']) ?></span>
            <b><?= e(money((float) $r['monto'], $sym)) ?></b>
          </div>
        <?php endforeach; ?>
        <?php if (!$ranking): ?><p class="muted small" style="margin:0">Sin vendedores registrados.</p><?php endif; ?>
      </div>
    </div>

    <?php if ($lostReasons): ?>
    <div class="card">
      <div class="card__head"><span class="secnum">06/</span><h2>Motivos de pérdida</h2></div>
      <div class="card__body">
        <?php $maxL = max(array_map(static fn ($l) => (int) $l['n'], $lostReasons)); ?>
        <?php foreach ($lostReasons as $l): ?>
          <div class="stat-line">
            <span class="small" style="flex:1"><?= e(ucfirst((string) $l['lost_reason'])) ?></span>
            <span class="bar"><i style="width:<?= e(round((int) $l['n'] / max(1, $maxL) * 100)) ?>%"></i></span>
            <b><?= e((int) $l['n']) ?></b>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
