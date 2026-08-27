<?php $q = http_build_query(array_diff_key($_GET, ['p' => 1])); ?>
<div class="pagina-cab">
  <div><h1><?= e($titulo) ?></h1><p class="pagina-cab__sub"><?= e($subtitulo) ?> · <?= count($filas) ?> filas</p></div>
  <div class="acciones">
    <a class="btn btn--linea" target="_blank" rel="noopener"
       href="<?= e(url('reportes/' . $tipo . '/pdf' . ($q ? '?' . $q : ''))) ?>"><?= icono('descargar', 17) ?> PDF</a>
    <a class="btn btn--linea" href="<?= e(url('reportes/' . $tipo . '/excel' . ($q ? '?' . $q : ''))) ?>"><?= icono('descargar', 17) ?> Excel</a>
    <a href="<?= e(url('reportes')) ?>" class="btn btn--linea"><?= icono('atras', 17) ?> Volver</a>
  </div>
</div>

<form method="get" class="filtros">
  <div class="campo campo--corto"><label for="r-anio">Año</label>
    <input type="number" id="r-anio" name="anio" value="<?= e((string)($_GET['anio'] ?? date('Y'))) ?>" min="2000" max="2100"></div>
  <?php if ($tipo === 'asistencia'): ?>
    <div class="campo campo--corto"><label for="r-mes">Mes</label>
      <select id="r-mes" name="mes" data-auto-envio>
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?= $m ?>" <?= $m === (int)($_GET['mes'] ?? date('n')) ? 'selected' : '' ?>><?= e(mes_nombre($m)) ?></option>
        <?php endfor; ?>
      </select></div>
  <?php endif; ?>
  <button type="submit" class="btn btn--linea"><?= icono('filtro', 17) ?> Actualizar</button>
</form>

<div class="tabla-env tabla-env--alta" tabindex="0">
  <table class="tabla">
    <thead><tr>
      <?php foreach ($columnas as $c): ?>
        <th class="<?= ($c['alinear'] ?? 'L') === 'R' ? 'num' : (($c['alinear'] ?? 'L') === 'C' ? 'cen' : '') ?>"><?= e($c['titulo']) ?></th>
      <?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($filas as $fila): ?>
      <tr>
        <?php foreach (array_values($fila) as $i => $valor): ?>
          <td class="<?= ($columnas[$i]['alinear'] ?? 'L') === 'R' ? 'num' : (($columnas[$i]['alinear'] ?? 'L') === 'C' ? 'cen' : '') ?>">
            <?= e(is_array($valor) ? (string)($valor['valor'] ?? '') : (string)$valor) ?>
          </td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    <?php if ($filas === []): ?>
      <tr><td colspan="<?= count($columnas) ?>" class="tabla__vacio">No hay datos para los filtros seleccionados.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="pestanas mt-5">
  <?php foreach ($tipos as $clave => $nombre): ?>
    <a href="<?= e(url('reportes/' . $clave)) ?>" class="<?= $clave === $tipo ? 'activo' : '' ?>"><?= e($nombre) ?></a>
  <?php endforeach; ?>
</div>
