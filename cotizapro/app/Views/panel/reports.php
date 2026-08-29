<?php
page('withChart', true);
$sym = (string) $company['currency_symbol'];
$qs = http_build_query(array_filter(['desde' => $from, 'hasta' => $to, 'vendedor' => $userId ?: '']));
page('barActions',
    '<a class="btn btn--ghost btn--sm" href="' . e(url('/panel/reportes/excel') . '?' . $qs) . '">Excel</a>'
  . '<a class="btn btn--ghost btn--sm" href="' . e(url('/panel/reportes/pdf') . '?' . $qs) . '" target="_blank" rel="noopener">PDF</a>');
$inline = '<script nonce="' . e($nonce) . '">' . <<<JS
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') { return; }
  var d = window.CP.rep || {};
  Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
  Chart.defaults.font.size = 11;
  Chart.defaults.color = '#8B939C';
  var accent = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#E8590C';
  var money = function (v) { return d.sym + Number(v).toLocaleString('es-GT'); };

  var m = document.getElementById('repMonthly');
  if (m) {
    new Chart(m, {
      type: 'line',
      data: {
        labels: d.months,
        datasets: [
          { label: 'Cotizado', data: d.quoted, borderColor: '#5A6470', backgroundColor: 'rgba(90,100,112,.10)', fill: true, tension: 0.3, pointRadius: 3 },
          { label: 'Ganado', data: d.won, borderColor: accent, backgroundColor: 'rgba(232,89,12,.12)', fill: true, tension: 0.3, pointRadius: 3 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true, pointStyle: 'rect' } } },
        scales: {
          x: { grid: { display: false } },
          y: { border: { display: false }, grid: { color: 'rgba(28,31,34,.07)' }, ticks: { callback: money } }
        }
      }
    });
  }

  var s = document.getElementById('repSellers');
  if (s) {
    new Chart(s, {
      type: 'bar',
      data: { labels: d.sellerNames, datasets: [{ label: 'Ganado', data: d.sellerWon, backgroundColor: accent, borderRadius: 3 }] },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { border: { display: false }, grid: { color: 'rgba(28,31,34,.07)' }, ticks: { callback: money } },
          y: { grid: { display: false } }
        }
      }
    });
  }
});
JS . '</script>';
page('inlineScript', $inline);
page('jsConfig', ['rep' => [
    'months' => array_map(static fn ($m) => $m['mes'], $r['monthly']),
    'quoted' => array_map(static fn ($m) => round((float) $m['cotizado'], 2), $r['monthly']),
    'won'    => array_map(static fn ($m) => round((float) $m['ganado'], 2), $r['monthly']),
    'sellerNames' => array_map(static fn ($s) => $s['name'], $r['sellers']),
    'sellerWon'   => array_map(static fn ($s) => round((float) $s['ganado'], 2), $r['sellers']),
    'sym' => $sym,
]]);
?>
<form class="filterbar" method="get" action="<?= e(url('/panel/reportes')) ?>">
  <div class="field"><label for="rd">Desde</label><input class="input" id="rd" type="date" name="desde" value="<?= e($from) ?>"></div>
  <div class="field"><label for="rh">Hasta</label><input class="input" id="rh" type="date" name="hasta" value="<?= e($to) ?>"></div>
  <?php if (!\App\Core\Auth::isSeller() && $sellersList): ?>
    <div class="field"><label for="rv">Vendedor</label>
      <select class="select" id="rv" name="vendedor">
        <option value="">Todos</option>
        <?php foreach ($sellersList as $s): ?>
          <option value="<?= e($s['id']) ?>"<?= (int) $userId === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
  <?php endif; ?>
  <button class="btn btn--ghost btn--sm" type="submit">Aplicar</button>
</form>

<dl class="kpis">
  <div class="kpi"><dt>Cotizaciones</dt><dd><?= e(number_format($r['totalN'])) ?></dd></div>
  <div class="kpi"><dt>Monto cotizado</dt><dd><?= e(money($r['quoted'], $sym)) ?></dd></div>
  <div class="kpi"><dt>Monto ganado</dt><dd><?= e(money($r['won'], $sym)) ?></dd></div>
  <div class="kpi"><dt>Conversión</dt><dd><?= e(qty($r['conv'])) ?> %</dd></div>
  <div class="kpi"><dt>Ticket promedio</dt><dd><?= e(money($r['avgTicket'], $sym)) ?></dd></div>
  <div class="kpi"><dt>Respuesta</dt><dd><?= e($r['avgResponse'] > 0 ? qty(round((float) $r['avgResponse'], 1)) . ' h' : '—') ?></dd></div>
</dl>

<div class="cols cols--2">
  <div class="card">
    <div class="card__head"><span class="secnum">01/</span><h2>Evolución mensual</h2></div>
    <div class="card__body"><div class="chartbox"><canvas id="repMonthly" role="img" aria-label="Evolución mensual de cotizado y ganado"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card__head"><span class="secnum">02/</span><h2>Ranking de vendedores</h2></div>
    <div class="card__body"><div class="chartbox"><canvas id="repSellers" role="img" aria-label="Monto ganado por vendedor"></canvas></div></div>
  </div>
</div>

<div class="cols cols--2" style="margin-top:20px">
  <div class="card">
    <div class="card__head"><span class="secnum">03/</span><h2>Por estado</h2></div>
    <div class="card__body card__body--flush tablescroll">
      <table class="datatable" style="border:0;border-radius:0">
        <caption class="sr-only">Cotizaciones por estado</caption>
        <thead><tr><th scope="col">Estado</th><th scope="col" class="num">Cantidad</th><th scope="col" class="num">Monto</th></tr></thead>
        <tbody>
          <?php foreach ($statuses as $k => $m): ?>
            <tr><td><?= e($m['label']) ?></td>
              <td class="num"><?= e((int) ($r['byStatus'][$k]['n'] ?? 0)) ?></td>
              <td class="num nowrap"><?= e(money((float) ($r['byStatus'][$k]['monto'] ?? 0), $sym)) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card__head"><span class="secnum">04/</span><h2>Motivos de pérdida</h2></div>
    <div class="card__body card__body--flush tablescroll">
      <?php if (!$r['lost']): ?><p class="muted" style="padding:26px;text-align:center;margin:0">Sin cotizaciones perdidas en el periodo.</p><?php else: ?>
        <table class="datatable" style="border:0;border-radius:0">
          <caption class="sr-only">Motivos de pérdida</caption>
          <thead><tr><th scope="col">Motivo</th><th scope="col" class="num">Cantidad</th><th scope="col" class="num">Monto</th></tr></thead>
          <tbody>
            <?php foreach ($r['lost'] as $l): ?>
              <tr><td><?= e(ucfirst((string) $l['motivo'])) ?></td><td class="num"><?= e((int) $l['n']) ?></td><td class="num nowrap"><?= e(money((float) $l['monto'], $sym)) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="cols cols--2" style="margin-top:20px">
  <div class="card">
    <div class="card__head"><span class="secnum">05/</span><h2>Productos más cotizados</h2></div>
    <div class="card__body card__body--flush tablescroll">
      <table class="datatable" style="border:0;border-radius:0">
        <caption class="sr-only">Productos más cotizados</caption>
        <thead><tr><th scope="col">Código</th><th scope="col">Producto</th><th scope="col" class="num">Veces</th><th scope="col" class="num">Monto</th></tr></thead>
        <tbody>
          <?php foreach ($r['products'] as $p): ?>
            <tr><td class="nowrap"><span class="code-chip"><?= e($p['code']) ?></span></td>
              <td><?= e(str_limit((string) $p['name'], 46)) ?></td>
              <td class="num"><?= e((int) $p['veces']) ?></td>
              <td class="num nowrap"><?= e(money((float) $p['monto'], $sym)) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$r['products']): ?><tr><td colspan="4" style="text-align:center;padding:26px;color:var(--steel)">Sin datos</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card__head"><span class="secnum">06/</span><h2>Clientes con más movimiento</h2></div>
    <div class="card__body card__body--flush tablescroll">
      <table class="datatable" style="border:0;border-radius:0">
        <caption class="sr-only">Clientes con más movimiento</caption>
        <thead><tr><th scope="col">Cliente</th><th scope="col" class="num">Cotiz.</th><th scope="col" class="num">Cotizado</th><th scope="col" class="num">Ganado</th></tr></thead>
        <tbody>
          <?php foreach ($r['customers'] as $c): ?>
            <tr><td><?= e(str_limit((string) $c['cliente'], 38)) ?></td>
              <td class="num"><?= e((int) $c['n']) ?></td>
              <td class="num nowrap"><?= e(money((float) $c['cotizado'], $sym)) ?></td>
              <td class="num nowrap"><?= e(money((float) $c['ganado'], $sym)) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$r['customers']): ?><tr><td colspan="4" style="text-align:center;padding:26px;color:var(--steel)">Sin datos</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
