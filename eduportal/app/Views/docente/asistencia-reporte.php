<div class="pagina-cab">
  <div><h1>Reporte mensual de asistencia</h1>
    <p class="pagina-cab__sub"><?= e($seccion['etiqueta'] ?? '') ?> · <?= e(mes_nombre($mes)) ?> <?= e((string)$anio) ?></p></div>
  <div class="acciones">
    <a class="btn btn--linea" target="_blank" rel="noopener"
       href="<?= e(url('asistencia/' . (int)$seccion['id'] . '/reporte/pdf?' . http_build_query(['anio' => $anio, 'mes' => $mes]))) ?>"><?= icono('descargar', 17) ?> PDF</a>
    <a class="btn btn--linea"
       href="<?= e(url('asistencia/' . (int)$seccion['id'] . '/reporte/excel?' . http_build_query(['anio' => $anio, 'mes' => $mes]))) ?>"><?= icono('descargar', 17) ?> Excel</a>
    <a href="<?= e(url('asistencia')) ?>" class="btn btn--linea"><?= icono('atras', 17) ?> Volver</a>
  </div>
</div>

<form method="get" class="filtros">
  <div class="campo campo--corto"><label for="f-mes">Mes</label>
    <select id="f-mes" name="mes" data-auto-envio>
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= $m === (int)$mes ? 'selected' : '' ?>><?= e(mes_nombre($m)) ?></option>
      <?php endfor; ?>
    </select></div>
  <div class="campo campo--corto"><label for="f-anio">Año</label>
    <input type="number" id="f-anio" name="anio" value="<?= e((string)$anio) ?>" min="2000" max="2100"></div>
  <button type="submit" class="btn btn--linea"><?= icono('filtro', 17) ?> Consultar</button>
</form>

<div class="tabla-env" tabindex="0">
  <table class="tabla">
    <thead><tr><th>Código</th><th>Alumno</th><th class="cen">Presente</th><th class="cen">Ausente</th>
      <th class="cen">Tarde</th><th class="cen">Justificado</th><th class="cen">% asistencia</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
      <?php
      $p = (int)$f['presente']; $a = (int)$f['ausente']; $t = (int)$f['tarde']; $j = (int)$f['justificado'];
      $total = $p + $a + $t + $j;
      $pct = $total > 0 ? round(($p + $t + $j) / $total * 100, 1) : 0;
      ?>
      <tr>
        <td class="sm"><?= e($f['codigo']) ?></td>
        <td><?= e(trim($f['apellidos'] . ', ' . $f['nombres'])) ?></td>
        <td class="cen"><?= $p ?></td>
        <td class="cen <?= $a >= 3 ? 'nota-baja' : '' ?>"><?= $a ?></td>
        <td class="cen"><?= $t ?></td>
        <td class="cen"><?= $j ?></td>
        <td class="cen negrita <?= $pct < 80 ? 'nota-baja' : ($pct >= 95 ? 'nota-alta' : '') ?>"><?= e((string)$pct) ?>%</td>
      </tr>
    <?php endforeach; ?>
    <?php if ($filas === []): ?><tr><td colspan="7" class="tabla__vacio">Sin registros de asistencia en este mes.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
