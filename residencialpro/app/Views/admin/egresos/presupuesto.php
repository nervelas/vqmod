<?php
$totalPres = array_sum(array_map(static fn($f) => (float) $f['presupuesto'], $filas));
$totalEjec = array_sum(array_map(static fn($f) => (float) $f['ejecutado'], $filas));
$graf = ['type' => 'bar', 'data' => [
  'labels' => array_column($flujo, 'etiqueta'),
  'datasets' => [
    ['label' => 'Ingresos', 'backgroundColor' => '#2F6B4F', 'data' => array_map(static fn($f) => round((float) $f['ingresos'], 2), $flujo)],
    ['label' => 'Egresos', 'backgroundColor' => '#B4620F', 'data' => array_map(static fn($f) => round((float) $f['egresos'], 2), $flujo)],
  ],
], 'options' => ['formato' => 'moneda']];
?>
<section class="rejilla rejilla-3 mb-3">
  <article class="kpi">
    <div class="kpi-et"><?= ico('barras', 15) ?> Presupuestado</div>
    <div class="kpi-valor"><?= e(q($totalPres)) ?></div>
    <div class="kpi-nota">Año <?= (int) $anio ?></div>
  </article>
  <article class="kpi">
    <div class="kpi-et"><?= ico('moneda', 15) ?> Ejecutado</div>
    <div class="kpi-valor"><?= e(q($totalEjec)) ?></div>
    <div class="kpi-nota"><?= $totalPres > 0 ? round($totalEjec * 100 / $totalPres, 1) : 0 ?>% del presupuesto</div>
  </article>
  <article class="kpi">
    <div class="kpi-et"><?= ico($totalPres - $totalEjec >= 0 ? 'subeTendencia' : 'bajaTendencia', 15) ?> Disponible</div>
    <div class="kpi-valor"><?= e(q($totalPres - $totalEjec)) ?></div>
    <div class="kpi-nota <?= $totalPres - $totalEjec >= 0 ? 'ok' : 'grave' ?>">
      <?= $totalPres - $totalEjec >= 0 ? 'Dentro del presupuesto' : 'Presupuesto excedido' ?>
    </div>
  </article>
</section>

<form method="get" class="fila mb-3" style="gap:10px">
  <label class="etiqueta" for="anio" style="margin:0;align-self:center">Año</label>
  <input type="number" id="anio" name="anio" value="<?= (int) $anio ?>" min="2020" max="2100" style="width:120px" data-auto-enviar>
  <button class="btn btn-claro btn-sm" type="submit"><?= ico('filtro', 16) ?> Ver</button>
</form>

<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,400px)">
  <form method="post">
    <?= csrf() ?>
    <div class="tarjeta">
      <div class="tarjeta-cab">
        <h3>Presupuesto por categoría</h3>
      </div>
      <div class="tabla-caja">
        <table class="tabla">
          <thead><tr><th>Categoría</th><th class="d" style="width:160px">Presupuesto</th><th class="d">Ejecutado</th><th class="d">Diferencia</th><th style="width:150px">Avance</th></tr></thead>
          <tbody>
            <?php foreach ($filas as $f):
              $pres = (float) $f['presupuesto']; $ejec = (float) $f['ejecutado'];
              $pct = $pres > 0 ? min(150, round($ejec * 100 / $pres, 1)) : 0; ?>
              <tr>
                <td class="fuerte">
                  <span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:<?= e($f['color']) ?>"></span>
                  <?= e($f['categoria']) ?>
                </td>
                <td class="d">
                  <input type="number" name="monto[<?= (int) $f['id'] ?>]" step="0.01" min="0" value="<?= e(number_format($pres, 2, '.', '')) ?>"
                         style="text-align:right" aria-label="Presupuesto de <?= e($f['categoria']) ?>">
                </td>
                <td class="d num"><?= e(q($ejec)) ?></td>
                <td class="d num <?= $pres - $ejec >= 0 ? 'texto-ok' : 'texto-grave' ?>"><?= e(q($pres - $ejec)) ?></td>
                <td>
                  <div class="progreso <?= $pct > 100 ? 'grave' : ($pct > 85 ? '' : 'ok') ?>"><span style="width:<?= min(100, $pct) ?>%"></span></div>
                  <small class="texto-3"><?= $pct ?>%</small>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (esRol('admin', 'contabilidad')): ?>
        <div class="tarjeta-pie fila-fin">
          <button class="btn btn-oro" type="submit"><?= ico('guardar', 17) ?> Guardar presupuesto</button>
        </div>
      <?php endif; ?>
    </div>
  </form>

  <article class="tarjeta" style="align-self:start">
    <div class="tarjeta-cab"><h3>Ingresos y egresos del año</h3></div>
    <div class="tarjeta-cuerpo">
      <canvas role="img" height="250" data-grafica="<?= e(json_encode($graf, JSON_UNESCAPED_UNICODE)) ?>" aria-label="Flujo mensual"></canvas>
    </div>
  </article>
</div>
