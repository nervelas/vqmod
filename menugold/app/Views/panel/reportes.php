<?php
/** @var array $total, $porDia, $porHora, $topMas, $topMenos, $categorias, $modos, $meseros, $prep, $anulados; string $desde, $hasta */
use MenuGold\Core\Security;
use MenuGold\Core\View;
use MenuGold\Models\Order;
View::set('titulo', 'Reportes');
View::set('subtitulo', dt($desde, 'd/m/Y') . ' al ' . dt($hasta, 'd/m/Y'));
$s = (string)($r['simbolo'] ?? 'Q');
$q = ['desde' => $desde, 'hasta' => $hasta];

View::start('acciones');
?>
<a class="bt bt--linea" href="<?= e(url('panel/reportes/excel', $q)) ?>"><?= icon('excel') ?><span class="oculto-movil">Excel</span></a>
<a class="bt bt--oro" href="<?= e(url('panel/reportes/pdf', $q)) ?>" target="_blank"><?= icon('pdf') ?><span class="oculto-movil">PDF</span></a>
<?php View::stop(); ?>

<form class="filtros-barra" method="get" action="<?= e(url('panel/reportes')) ?>">
  <div class="campo-p"><label for="desde">Desde</label><input type="date" id="desde" name="desde" value="<?= e($desde) ?>"></div>
  <div class="campo-p"><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="<?= e($hasta) ?>"></div>
  <button class="bt bt--linea" type="submit"><?= icon('filter') ?> Aplicar</button>
  <?php foreach ([
    'Hoy' => [date('Y-m-d'), date('Y-m-d')],
    'Ayer' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
    'Esta semana' => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
    'Este mes' => [date('Y-m-01'), date('Y-m-d')],
    'Mes pasado' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
  ] as $etq => $rg): ?>
    <a class="bt bt--sm bt--suave" href="<?= e(url('panel/reportes', ['desde' => $rg[0], 'hasta' => $rg[1]])) ?>"><?= e($etq) ?></a>
  <?php endforeach; ?>
</form>

<div class="rejilla rejilla--4" style="margin-bottom:18px">
  <?php
  $kpis = [
    ['Ventas del periodo', money($total['total'], $s), 'money', 'chart'],
    ['Pedidos', (string)(int)$total['pedidos'], 'receipt', 'clock'],
    ['Ticket promedio', money($total['ticket'], $s), 'wallet', 'trending'],
    ['Propinas', money($total['propinas'], $s), 'gift', 'star'],
  ];
  foreach ($kpis as $k): ?>
    <div class="kpi">
      <div class="kpi__icono"><?= icon($k[2]) ?></div>
      <div class="kpi__etiqueta"><?= e($k[0]) ?></div>
      <div class="kpi__valor"><?= e($k[1]) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="rejilla rejilla--2">
  <div class="tarjeta-p">
    <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('chart') ?> Ventas por día</h2></div>
    <div class="grafica-caja"><canvas id="gDias" height="250"></canvas></div>
  </div>
  <div class="tarjeta-p">
    <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('clock') ?> Horas pico</h2></div>
    <div class="grafica-caja"><canvas id="gHoras" height="250"></canvas></div>
  </div>
</div>

<div class="rejilla rejilla--2">
  <div class="tarjeta-p">
    <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('layers') ?> Ventas por categoría</h2></div>
    <div class="grafica-caja"><canvas id="gCats" height="240"></canvas></div>
  </div>
  <div class="tarjeta-p">
    <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('box') ?> Ventas por modo</h2></div>
    <div class="grafica-caja"><canvas id="gModos" height="240"></canvas></div>
  </div>
</div>

<div class="rejilla rejilla--2">
  <?php
  $tablas = [
    ['Platillos más vendidos', 'fire', $topMas, ['Platillo', 'Unid.', 'Ventas']],
    ['Platillos menos vendidos', 'chevron-down', $topMenos, ['Platillo', 'Unid.', 'Ventas']],
  ];
  foreach ($tablas as $t): ?>
    <div class="tarjeta-p tarjeta-p--plana">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon($t[1]) ?> <?= e($t[0]) ?></h2></div>
      <?php if (!$t[2]): ?>
        <p style="padding:20px;color:var(--p-tenue);margin:0">Sin datos en este periodo.</p>
      <?php else: ?>
        <div class="tabla-caja">
          <table class="tabla" style="min-width:auto">
            <thead><tr><th><?= e($t[3][0]) ?></th><th class="num"><?= e($t[3][1]) ?></th><th class="num"><?= e($t[3][2]) ?></th></tr></thead>
            <tbody>
              <?php foreach ($t[2] as $f): ?>
                <tr><td><?= e((string)$f['nombre']) ?></td>
                    <td class="num"><?= (int)$f['unidades'] ?></td>
                    <td class="num"><?= e(money($f['total'], $s)) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="rejilla rejilla--2">
  <div class="tarjeta-p tarjeta-p--plana">
    <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('users') ?> Ventas por mesero</h2></div>
    <?php if (!$meseros): ?>
      <p style="padding:20px;color:var(--p-tenue);margin:0">Sin datos en este periodo.</p>
    <?php else: ?>
      <div class="tabla-caja">
        <table class="tabla" style="min-width:auto">
          <thead><tr><th>Mesero</th><th class="num">Pedidos</th><th class="num">Ventas</th><th class="num">Ticket</th></tr></thead>
          <tbody>
            <?php foreach ($meseros as $m): ?>
              <tr><td><?= e((string)$m['mesero']) ?></td>
                  <td class="num"><?= (int)$m['pedidos'] ?></td>
                  <td class="num"><?= e(money($m['total'], $s)) ?></td>
                  <td class="num"><?= e(money($m['ticket'], $s)) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="tarjeta-p">
    <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('clock') ?> Tiempos y anulaciones</h2></div>
    <div class="entre" style="padding:9px 0;border-bottom:1px solid var(--p-borde)">
      <span>Tiempo promedio de preparación</span><strong><?= e((string)round((float)$prep['promedio'], 1)) ?> min</strong>
    </div>
    <div class="entre" style="padding:9px 0;border-bottom:1px solid var(--p-borde)">
      <span>Más rápido / más lento</span><strong><?= (int)$prep['minimo'] ?> / <?= (int)$prep['maximo'] ?> min</strong>
    </div>
    <div class="entre" style="padding:9px 0;border-bottom:1px solid var(--p-borde)">
      <span>Descuentos otorgados</span><strong><?= e(money($total['descuentos'], $s)) ?></strong>
    </div>
    <div class="entre" style="padding:9px 0;border-bottom:1px solid var(--p-borde)">
      <span>Impuestos</span><strong><?= e(money($total['impuestos'], $s)) ?></strong>
    </div>
    <div class="entre" style="padding:9px 0">
      <span>Pedidos anulados</span>
      <strong class="<?= count($anulados) > 0 ? '' : '' ?>"><?= count($anulados) ?></strong>
    </div>
    <?php if ($anulados): ?>
      <div style="max-height:170px;overflow-y:auto;margin-top:8px">
        <?php foreach (array_slice($anulados, 0, 20) as $a): ?>
          <div style="padding:7px 0;border-top:1px solid var(--p-borde);font-size:13px">
            <strong class="mono"><?= e((string)$a['codigo']) ?></strong> · <?= e(money($a['total'], $s)) ?>
            <div style="color:var(--p-tenue)"><?= e((string)$a['motivo_anulacion']) ?> — <?= e((string)($a['usuario'] ?? '')) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php View::start('scripts'); ?>
<script src="<?= e(asset('vendor/chart.min.js')) ?>" nonce="<?= e(Security::nonce()) ?>"></script>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
  if (!window.Chart) return;
  var ORO = '#D4AF37', PAL = ['#D4AF37','#3E6B5A','#7A2E3B','#2C4A6E','#B4643C','#8C6B2F','#5A5A5A','#C9A227'];

  new Chart(document.getElementById('gDias'), {
    type: 'line',
    data: {
      labels: <?= json_encode(array_map(static fn($f) => date('d/m', strtotime((string)$f['dia'])), $porDia)) ?>,
      datasets: [{ label: 'Ventas', data: <?= json_encode(array_map(static fn($f) => round((float)$f['total'], 2), $porDia)) ?>, borderColor: ORO }]
    },
    options: { moneda: true, leyenda: false, alto: 250, vacio: 'Sin ventas en este periodo' }
  });

  new Chart(document.getElementById('gHoras'), {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_map(static fn($h) => str_pad((string)$h, 2, '0', STR_PAD_LEFT) . 'h', array_keys($porHora))) ?>,
      datasets: [{ label: 'Pedidos', data: <?= json_encode(array_values($porHora)) ?>, backgroundColor: ORO }]
    },
    options: { leyenda: false, alto: 250, sufijo: ' pedidos', vacio: 'Sin datos' }
  });

  new Chart(document.getElementById('gCats'), {
    type: 'doughnut',
    data: {
      labels: <?= json_encode(array_map(static fn($f) => (string)$f['categoria'], $categorias), JSON_UNESCAPED_UNICODE) ?>,
      datasets: [{ data: <?= json_encode(array_map(static fn($f) => round((float)$f['total'], 2), $categorias)) ?>, backgroundColor: PAL }]
    },
    options: { moneda: true, alto: 240, totalTexto: 'Ventas', vacio: 'Sin datos' }
  });

  new Chart(document.getElementById('gModos'), {
    type: 'doughnut',
    data: {
      labels: <?= json_encode(array_map(static fn($f) => Order::etiquetaModo((string)$f['modo']), $modos), JSON_UNESCAPED_UNICODE) ?>,
      datasets: [{ data: <?= json_encode(array_map(static fn($f) => round((float)$f['total'], 2), $modos)) ?>, backgroundColor: PAL }]
    },
    options: { moneda: true, alto: 240, totalTexto: 'Ventas', vacio: 'Sin datos' }
  });
})();
</script>
<?php View::stop(); ?>
