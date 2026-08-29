<?php
page('withChart', true);
$inline = '<script nonce="' . e($nonce) . '">' . <<<JS
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') { return; }
  var d = window.CP.sup || {};
  Chart.defaults.font.family = "Inter, system-ui, sans-serif";
  Chart.defaults.color = '#8B939C';
  var el = document.getElementById('supChart');
  if (el) {
    new Chart(el, { type: 'bar',
      data: { labels: d.labels, datasets: [
        { label: 'Cotizaciones emitidas', data: d.quotes, backgroundColor: '#E8590C', borderRadius: 3 },
        { type: 'line', label: 'Empresas', data: d.companies, borderColor: '#1C1F22', backgroundColor: 'transparent', tension: .3, yAxisID: 'y2', pointRadius: 3 }
      ]},
      options: { responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true, pointStyle: 'rect' } } },
        scales: { x: { grid: { display: false } },
          y: { border: { display: false }, grid: { color: 'rgba(28,31,34,.07)' } },
          y2: { position: 'right', grid: { display: false }, border: { display: false }, ticks: { precision: 0 } } } } });
  }
});
JS . '</script>';
page('inlineScript', $inline);
page('jsConfig', ['sup' => [
    'labels' => array_column($months, 'label'),
    'quotes' => array_column($months, 'quotes'),
    'companies' => array_column($months, 'empresas'),
]]);
page('barActions', '<a class="btn btn--accent btn--sm" href="' . e(url('/super/empresas/nueva')) . '">Nueva empresa</a>');
?>
<dl class="kpis">
  <div class="kpi"><dt>Empresas</dt><dd><?= e($totals['empresas']) ?></dd><div class="kpi__delta"><?= e($totals['activas']) ?> activas</div></div>
  <div class="kpi"><dt>Usuarios</dt><dd><?= e(number_format($totals['usuarios'])) ?></dd></div>
  <div class="kpi"><dt>Productos</dt><dd><?= e(number_format($totals['productos'])) ?></dd></div>
  <div class="kpi"><dt>Cotizaciones</dt><dd><?= e(number_format($totals['quotes'])) ?></dd></div>
  <div class="kpi"><dt>Monto ganado (global)</dt><dd><?= e(money($totals['monto'], 'Q')) ?></dd></div>
</dl>

<div class="cols cols--main">
  <div class="stack">
    <div class="card">
      <div class="card__head"><span class="secnum">01/</span><h2>Actividad de la plataforma</h2></div>
      <div class="card__body"><div class="chartbox"><canvas id="supChart" role="img" aria-label="Cotizaciones y empresas por mes"></canvas></div></div>
    </div>
    <div class="card">
      <div class="card__head"><span class="secnum">02/</span><h2>Empresas</h2>
        <a class="btn btn--ghost btn--xs ml-auto" href="<?= e(url('/super/empresas')) ?>">Administrar</a></div>
      <div class="card__body card__body--flush tablescroll">
        <table class="datatable" style="border:0;border-radius:0">
          <caption class="sr-only">Empresas de la plataforma</caption>
          <thead><tr><th scope="col">Empresa</th><th scope="col">Plan</th><th scope="col">Estado</th><th scope="col">Vence</th>
            <th scope="col" class="num">Productos</th><th scope="col" class="num">Cotiz. mes</th><th scope="col"></th></tr></thead>
          <tbody>
            <?php foreach ($companies as $c): ?>
              <tr>
                <td><a href="<?= e(url('/super/empresas/' . $c['id'])) ?>"><strong><?= e($c['name']) ?></strong></a>
                  <br><span class="small muted">/e/<?= e($c['slug']) ?><?= $c['domain'] ? ' · ' . e($c['domain']) : '' ?></span></td>
                <td class="small"><?= e($c['plan_name'] ?: '—') ?></td>
                <td><span class="badge<?= $c['status'] === 'activa' ? ' badge--ok' : ($c['status'] === 'suspendida' ? ' badge--bad' : '') ?>"><?= e(ucfirst((string) $c['status'])) ?></span></td>
                <td class="small"><?= $c['expires_at'] ? e(fechaCorta((string) $c['expires_at'])) : '—' ?></td>
                <td class="num"><?= e((int) $c['n_products']) ?></td>
                <td class="num"><?= e((int) $c['quotes_month']) ?></td>
                <td class="nowrap">
                  <a class="btn btn--ghost btn--xs" href="<?= e(url('/e/' . $c['slug'])) ?>" target="_blank" rel="noopener">Sitio</a>
                  <a class="btn btn--ghost btn--xs" href="<?= e(url('/super/empresas/' . $c['id'])) ?>">Editar</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card__head"><span class="secnum">03/</span><h2>Vencimientos próximos</h2></div>
      <div class="card__body">
        <?php if (!$expiring): ?><p class="small muted" style="margin:0">Ninguna empresa vence en los próximos 30 días.</p><?php endif; ?>
        <?php foreach ($expiring as $x): ?>
          <div class="stat-line">
            <span style="flex:1"><strong><?= e($x['name']) ?></strong><br><span class="small muted"><?= e(ucfirst((string) $x['status'])) ?></span></span>
            <b class="small"><?= e(fechaCorta((string) $x['expires_at'])) ?></b>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="card__head"><span class="secnum">04/</span><h2>Tareas programadas</h2></div>
      <div class="card__body">
        <?php if ($lastCron): ?>
          <p class="small" style="margin:0 0 6px"><strong>Última ejecución:</strong> <?= e(fechaHora((string) $lastCron['created_at'])) ?></p>
          <p class="small muted" style="margin:0"><?= e(str_limit((string) $lastCron['result'], 160)) ?></p>
        <?php else: ?>
          <div class="alert alert--warn" style="margin:0"><span aria-hidden="true">△</span><span>El cron aún no se ha ejecutado. Configúrelo en cPanel (ver LEEME.md).</span></div>
        <?php endif; ?>
        <a class="btn btn--ghost btn--block" style="margin-top:14px" href="<?= e(url('/super/ajustes')) ?>">Ver el comando del cron</a>
      </div>
    </div>
  </div>
</div>
