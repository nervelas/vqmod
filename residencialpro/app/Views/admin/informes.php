<?php
$resultado = $ingresos - $egresos;
$colores = array_map(static fn($c) => (string) $c['color'], $porCategoria);
$gCat = ['type' => 'doughnut', 'data' => [
  'labels' => array_column($porCategoria, 'categoria'),
  'datasets' => [['data' => array_map(static fn($c) => round((float) $c['total'], 2), $porCategoria), 'backgroundColor' => $colores ?: ['#C9A961']]],
], 'options' => ['formato' => 'moneda', 'centro' => ['etiqueta' => 'Egresos', 'valor' => q($egresos)]]];
$gFlujo = ['type' => 'line', 'data' => [
  'labels' => array_column($flujo, 'etiqueta'),
  'datasets' => [
    ['label' => 'Ingresos', 'borderColor' => '#2F6B4F', 'data' => array_map(static fn($f) => round((float) $f['ingresos'], 2), $flujo)],
    ['label' => 'Egresos', 'borderColor' => '#B4620F', 'fill' => false, 'data' => array_map(static fn($f) => round((float) $f['egresos'], 2), $flujo)],
  ],
], 'options' => ['formato' => 'moneda']];
?>
<div class="fila-entre mb-3">
  <form method="get" class="fila" style="gap:10px">
    <label class="etiqueta" for="periodo" style="margin:0;align-self:center">Período</label>
    <input type="month" id="periodo" name="periodo" value="<?= e($periodo) ?>" data-auto-enviar style="max-width:190px">
    <button class="btn btn-claro btn-sm" type="submit"><?= ico('filtro', 16) ?> Ver</button>
  </form>
  <div class="fila" style="gap:8px">
    <a class="btn btn-oro" href="<?= e(url('/doc/informe/' . $periodo)) ?>" target="_blank" rel="noopener">
      <?= ico('archivo', 17) ?> Informe para la asamblea (PDF)
    </a>
  </div>
</div>

<section class="rejilla rejilla-4 mb-3">
  <article class="kpi">
    <div class="kpi-et"><?= ico('subeTendencia', 15) ?> Ingresos</div>
    <div class="kpi-valor"><?= e(q($ingresos)) ?></div>
    <div class="kpi-nota"><?= $esperado > 0 ? round($ingresos * 100 / $esperado, 1) . '% de lo emitido' : '' ?></div>
  </article>
  <article class="kpi">
    <div class="kpi-et"><?= ico('bajaTendencia', 15) ?> Egresos</div>
    <div class="kpi-valor"><?= e(q($egresos)) ?></div>
  </article>
  <article class="kpi">
    <div class="kpi-et"><?= ico('moneda', 15) ?> Resultado del mes</div>
    <div class="kpi-valor"><?= e(q($resultado)) ?></div>
    <div class="kpi-nota <?= $resultado >= 0 ? 'ok' : 'grave' ?>"><?= $resultado >= 0 ? 'Superávit' : 'Déficit' ?></div>
  </article>
  <article class="kpi">
    <div class="kpi-et"><?= ico('alerta', 15) ?> Cartera vencida</div>
    <div class="kpi-valor"><?= e(q((float) $morosidad['total'])) ?></div>
    <div class="kpi-nota grave"><?= (int) $morosidad['casas'] ?> viviendas</div>
  </article>
</section>

<section class="rejilla mb-3" style="grid-template-columns:minmax(0,1.5fr) minmax(0,1fr)">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Ingresos y egresos de los últimos 12 meses</h3></div>
    <div class="tarjeta-cuerpo">
      <canvas role="img" height="240" data-grafica="<?= e(json_encode($gFlujo, JSON_UNESCAPED_UNICODE)) ?>" aria-label="Flujo mensual"></canvas>
    </div>
  </article>
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Egresos del período</h3></div>
    <div class="tarjeta-cuerpo">
      <?php if ($porCategoria === []): ?>
        <p class="texto-3 centrado" style="padding:40px 0;margin:0">Sin egresos registrados.</p>
      <?php else: ?>
        <canvas role="img" height="240" data-grafica="<?= e(json_encode($gCat, JSON_UNESCAPED_UNICODE)) ?>" aria-label="Egresos por categoría"></canvas>
      <?php endif; ?>
    </div>
  </article>
</section>

<section class="rejilla rejilla-3">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Ingresos por forma de pago</h3></div>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Forma</th><th class="c">Ops.</th><th class="d">Monto</th></tr></thead>
        <tbody>
          <?php foreach ($porMetodo as $m): ?>
            <tr><td><?= e(ucfirst((string) $m['metodo'])) ?></td><td class="c num"><?= (int) $m['n'] ?></td><td class="d num"><?= e(q((float) $m['total'])) ?></td></tr>
          <?php endforeach; ?>
          <?php if ($porMetodo === []): ?><tr><td colspan="3" class="centrado texto-3" style="padding:20px">Sin pagos.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </article>

  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Saldos en caja y bancos</h3></div>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Cuenta</th><th class="d">Saldo</th></tr></thead>
        <tbody>
          <?php $tot = 0; foreach ($cuentas as $c): $tot += (float) $c['saldo']; ?>
            <tr><td><?= e($c['nombre']) ?></td><td class="d num fuerte"><?= e(q((float) $c['saldo'])) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td>Total</td><td class="d num"><?= e(q($tot)) ?></td></tr></tfoot>
      </table>
    </div>
  </article>

  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Presupuesto <?= (int) substr($periodo, 0, 4) ?></h3></div>
    <div class="tarjeta-cuerpo compacto">
      <?php foreach ($presupuesto as $p):
        $pres = (float) $p['presupuesto']; $ejec = (float) $p['ejecutado'];
        if ($pres <= 0 && $ejec <= 0) { continue; }
        $pct = $pres > 0 ? min(150, round($ejec * 100 / $pres, 1)) : 100; ?>
        <div class="mb-2">
          <div class="fila-entre" style="font-size:.85rem;margin-bottom:4px">
            <span><?= e($p['categoria']) ?></span>
            <b class="num"><?= e(q($ejec)) ?> / <?= e(q($pres)) ?></b>
          </div>
          <div class="progreso <?= $pct > 100 ? 'grave' : 'ok' ?>"><span style="width:<?= min(100, $pct) ?>%"></span></div>
        </div>
      <?php endforeach; ?>
    </div>
  </article>
</section>
