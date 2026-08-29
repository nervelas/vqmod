<?php
/** @var array $r, $resumen, $serie, $top, $modos, $recientes, $abiertos, $apertura, $uso, $limites, $faltantes; ?int $diasPlan */
use MenuGold\Core\Security;
use MenuGold\Core\View;
use MenuGold\Models\Order;

View::set('titulo', 'Escritorio');
View::set('subtitulo', 'Cómo va tu restaurante hoy · ' . dt(date('Y-m-d H:i:s'), 'd/m/Y'));
$s = (string)($r['simbolo'] ?? 'Q');
$var = (int)$resumen['variacion'];
?>

<?php if ($diasPlan !== null && $diasPlan <= 10): ?>
  <div class="aviso aviso--<?= $diasPlan < 0 ? 'error' : 'aviso' ?>">
    <?= icon('alert') ?>
    <span>
      <?php if ($diasPlan < 0): ?>
        Tu suscripción venció hace <?= abs($diasPlan) ?> día(s). Renueva para seguir recibiendo pedidos.
      <?php else: ?>
        Tu suscripción vence en <strong><?= $diasPlan ?> día(s)</strong> (<?= e(dt((string)$r['vence_el'], 'd/m/Y')) ?>).
      <?php endif; ?>
    </span>
  </div>
<?php endif; ?>

<?php if ($faltantes): ?>
  <div class="tarjeta-p" style="border-color:var(--p-oro)">
    <div class="tarjeta-p__cab">
      <h2 class="tarjeta-p__titulo"><?= icon('sparkles') ?> Termina de preparar tu menú</h2>
      <span class="insignia insignia--oro"><?= count($faltantes) ?> pendiente(s)</span>
    </div>
    <div class="rejilla rejilla--3">
      <?php foreach (array_slice($faltantes, 0, 3) as $f): ?>
        <a class="bt bt--linea" href="<?= e(url($f[1])) ?>" style="justify-content:flex-start;text-align:left;min-height:52px">
          <?= icon($f[2]) ?><span class="crece truncar"><?= e($f[0]) ?></span><?= icon('chevron-right', 'ico-sm') ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<!-- ============ Indicadores ============ -->
<div class="rejilla rejilla--4" style="margin-bottom:18px">
  <div class="kpi <?= $var > 0 ? 'kpi--sube' : ($var < 0 ? 'kpi--baja' : '') ?>">
    <div class="kpi__icono"><?= icon('money') ?></div>
    <div class="kpi__etiqueta">Ventas de hoy</div>
    <div class="kpi__valor" id="kpiVentas"><?= e(money($resumen['ventas_hoy'], $s)) ?></div>
    <div class="kpi__pie">
      <?= icon($var >= 0 ? 'trending' : 'chevron-down') ?>
      <?= $var === 0 ? 'Igual que ayer' : abs($var) . '% ' . ($var > 0 ? 'más' : 'menos') . ' que ayer' ?>
    </div>
  </div>
  <div class="kpi">
    <div class="kpi__icono"><?= icon('receipt') ?></div>
    <div class="kpi__etiqueta">Pedidos de hoy</div>
    <div class="kpi__valor" id="kpiPedidos"><?= (int)$resumen['pedidos_hoy'] ?></div>
    <div class="kpi__pie"><?= icon('clock') ?> <?= (int)$resumen['abiertos'] ?> en curso</div>
  </div>
  <div class="kpi">
    <div class="kpi__icono"><?= icon('wallet') ?></div>
    <div class="kpi__etiqueta">Ticket promedio del mes</div>
    <div class="kpi__valor"><?= e(money($resumen['ticket_prom'], $s)) ?></div>
    <div class="kpi__pie"><?= icon('chart') ?> <?= (int)$resumen['pedidos_mes'] ?> pedidos este mes</div>
  </div>
  <div class="kpi <?= (int)$resumen['llamadas'] > 0 ? 'kpi--alerta' : 'kpi--ok' ?>">
    <div class="kpi__icono"><?= icon((int)$resumen['llamadas'] > 0 ? 'bell' : 'check-circle') ?></div>
    <div class="kpi__etiqueta">Llamadas de mesa</div>
    <div class="kpi__valor" id="kpiLlamadas"><?= (int)$resumen['llamadas'] ?></div>
    <div class="kpi__pie"><?= icon('clock') ?> <?= e((string)$resumen['prep_promedio']) ?> min de preparación</div>
  </div>
</div>

<div class="rejilla rejilla--2">
  <!-- ============ Gráfica de ventas ============ -->
  <div class="tarjeta-p">
    <div class="tarjeta-p__cab">
      <h2 class="tarjeta-p__titulo"><?= icon('chart') ?> Ventas de los últimos 14 días</h2>
      <a class="bt bt--sm bt--suave" href="<?= e(url('panel/reportes')) ?>">Ver reportes</a>
    </div>
    <div class="grafica-caja"><canvas id="grafVentas" height="240" aria-label="Ventas de los últimos 14 días"></canvas></div>
  </div>

  <!-- ============ Pedidos en curso ============ -->
  <div class="tarjeta-p">
    <div class="tarjeta-p__cab">
      <h2 class="tarjeta-p__titulo"><?= icon('chef') ?> Pedidos en curso</h2>
      <a class="bt bt--sm bt--oro" href="<?= e(url('panel/cocina')) ?>"><?= icon('maximize') ?> Pantalla de cocina</a>
    </div>
    <?php if (!$abiertos): ?>
      <div class="vacio-p" style="padding:30px 16px">
        <?= icon('check-circle', 'ico-lg') ?>
        <p style="margin:0">Todo al día. No hay pedidos pendientes.</p>
      </div>
    <?php else: ?>
      <?php foreach ($abiertos as $o): ?>
        <?php $min = (int)floor((time() - strtotime((string)$o['creado'])) / 60); ?>
        <a href="<?= e(url('panel/pedidos/' . $o['id'])) ?>" class="entre" style="padding:11px 0;border-bottom:1px solid var(--p-borde)">
          <span class="crece truncar">
            <strong><?= e($o['mesa_nombre'] ?: Order::etiquetaModo((string)$o['modo'])) ?></strong>
            <span style="color:var(--p-tenue);font-size:12.5px"> · <?= e((string)$o['codigo']) ?></span>
          </span>
          <span class="insignia insignia--<?= $o['estado'] === 'nuevo' ? 'aviso' : ($o['estado'] === 'preparando' ? 'info' : 'exito') ?>">
            <?= e(Order::ETIQUETA_ESTADO[$o['estado']] ?? '') ?>
          </span>
          <span class="mono" style="min-width:64px;text-align:right"><?= e(money($o['total'], $s)) ?></span>
          <span class="insignia <?= $min > 25 ? 'insignia--peligro' : '' ?>"><?= $min ?> min</span>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="rejilla rejilla--2">
  <!-- ============ Más vendidos ============ -->
  <div class="tarjeta-p">
    <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('fire') ?> Los más pedidos del mes</h2></div>
    <?php if (!$top): ?>
      <p style="color:var(--p-tenue);margin:0">Aún no hay ventas registradas este mes.</p>
    <?php else: ?>
      <?php $maxU = max(array_map(static fn($t) => (int)$t['unidades'], $top)) ?: 1; ?>
      <?php foreach ($top as $i => $t): ?>
        <div style="padding:9px 0">
          <div class="entre" style="margin-bottom:5px">
            <span class="truncar"><strong style="color:var(--p-oro);margin-right:6px"><?= $i + 1 ?>.</strong><?= e((string)$t['nombre']) ?></span>
            <span class="mono" style="flex:0 0 auto"><?= (int)$t['unidades'] ?> u · <?= e(money($t['total'], $s)) ?></span>
          </div>
          <div style="height:6px;border-radius:4px;background:var(--p-superficie-3);overflow:hidden">
            <div style="height:100%;width:<?= (int)round(((int)$t['unidades'] / $maxU) * 100) ?>%;background:var(--p-oro);border-radius:4px"></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ============ Distribución por modo ============ -->
  <div class="tarjeta-p">
    <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('layers') ?> Cómo piden tus clientes</h2></div>
    <div class="grafica-caja"><canvas id="grafModos" height="220" aria-label="Ventas por modo de pedido"></canvas></div>
  </div>
</div>

<!-- ============ Últimos pedidos ============ -->
<div class="tarjeta-p tarjeta-p--plana">
  <div class="tarjeta-p__cab">
    <h2 class="tarjeta-p__titulo"><?= icon('history') ?> Últimos pedidos</h2>
    <a class="bt bt--sm bt--suave" href="<?= e(url('panel/pedidos')) ?>">Ver todos</a>
  </div>
  <div class="tabla-caja">
    <table class="tabla">
      <thead><tr>
        <th>Código</th><th>Origen</th><th>Estado</th><th>Fecha</th><th class="num">Total</th><th></th>
      </tr></thead>
      <tbody>
        <?php if (!$recientes): ?>
          <tr><td colspan="6" style="text-align:center;padding:28px;color:var(--p-tenue)">Aún no hay pedidos.</td></tr>
        <?php endif; ?>
        <?php foreach ($recientes as $o): ?>
          <tr>
            <td class="mono"><?= e((string)$o['codigo']) ?></td>
            <td><?= e($o['mesa_nombre'] ?: Order::etiquetaModo((string)$o['modo'])) ?></td>
            <td>
              <span class="insignia insignia--<?= in_array($o['estado'], ['pagado','entregado'], true) ? 'exito' : ($o['estado'] === 'anulado' ? 'peligro' : 'aviso') ?>">
                <?= e(Order::ETIQUETA_ESTADO[$o['estado']] ?? '') ?>
              </span>
            </td>
            <td style="color:var(--p-tenue);font-size:13px"><?= e(dt((string)$o['creado'])) ?></td>
            <td class="num"><?= e(money($o['total'], $s)) ?></td>
            <td class="tabla__acciones">
              <a class="bt bt--sm bt--suave" href="<?= e(url('panel/pedidos/' . $o['id'])) ?>"><?= icon('eye', 'ico-sm') ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php View::start('scripts'); ?>
<script src="<?= e(guion('chart')) ?>" nonce="<?= e(Security::nonce()) ?>"></script>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
  var etiquetas = <?= json_encode(array_map(static fn($d) => date('d/m', strtotime($d)), array_keys($serie))) ?>;
  var datos = <?= json_encode(array_values($serie)) ?>;
  var cv = document.getElementById('grafVentas');
  if (cv && window.Chart) {
    new Chart(cv, {
      type: 'line',
      data: { labels: etiquetas, datasets: [{ label: 'Ventas', data: datos, borderColor: '#D4AF37', fill: true }] },
      options: { moneda: true, leyenda: false, alto: 240 }
    });
  }
  var modos = <?= json_encode(array_map(static fn($m) => [
      'etiqueta' => Order::etiquetaModo((string)$m['modo']), 'total' => round((float)$m['total'], 2)
  ], $modos), JSON_UNESCAPED_UNICODE) ?>;
  var cd = document.getElementById('grafModos');
  if (cd && window.Chart) {
    new Chart(cd, {
      type: 'doughnut',
      data: {
        labels: modos.map(function (m) { return m.etiqueta; }),
        datasets: [{ data: modos.map(function (m) { return m.total; }),
                     backgroundColor: ['#D4AF37', '#3E6B5A', '#7A2E3B', '#2C4A6E'] }]
      },
      options: { moneda: true, alto: 220, totalTexto: 'Ventas' }
    });
  }

  // Refresco discreto de los indicadores vivos
  setInterval(function () {
    if (document.hidden) return;
    window.MGPanel.pedir('panel/resumen.json').then(function (res) {
      if (!res.ok) return;
      var v = document.getElementById('kpiVentas');
      if (v) v.textContent = window.MGPanel.money(res.resumen.ventas_hoy);
      var p = document.getElementById('kpiPedidos');
      if (p) p.textContent = res.resumen.pedidos_hoy;
      var l = document.getElementById('kpiLlamadas');
      if (l) l.textContent = res.resumen.llamadas;
    });
  }, 45000);
})();
</script>
<?php View::stop(); ?>
