<div class="pagina-cab">
  <div><h1>Asistencia</h1>
    <p class="pagina-cab__sub"><?= e(App\Models\Alumno::nombre($alumno)) ?> · <?= e(mes_nombre($mes)) ?> <?= e((string)$anio) ?></p></div>
</div>

<form method="get" class="filtros">
  <div class="campo campo--corto"><label for="a-mes">Mes</label>
    <select id="a-mes" name="mes" data-auto-envio>
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= $m === (int)$mes ? 'selected' : '' ?>><?= e(mes_nombre($m)) ?></option>
      <?php endfor; ?>
    </select></div>
  <div class="campo campo--corto"><label for="a-anio">Año</label>
    <input type="number" id="a-anio" name="anio" value="<?= e((string)$anio) ?>" min="2000" max="2100"></div>
  <button type="submit" class="btn btn--linea"><?= icono('filtro', 17) ?> Consultar</button>
</form>

<div class="rejilla rejilla--4 mb-5">
  <div class="kpi"><div class="kpi__etq">Presentes</div><div class="kpi__valor"><?= (int)$resumen['presente'] ?></div></div>
  <div class="kpi"><div class="kpi__etq">Ausencias</div>
    <div class="kpi__valor" style="color:<?= (int)$resumen['ausente'] > 0 ? 'var(--bad)' : 'inherit' ?>"><?= (int)$resumen['ausente'] ?></div></div>
  <div class="kpi"><div class="kpi__etq">Tardanzas</div><div class="kpi__valor"><?= (int)$resumen['tarde'] ?></div></div>
  <div class="kpi"><div class="kpi__etq">Justificadas</div><div class="kpi__valor"><?= (int)$resumen['justificado'] ?></div></div>
</div>

<div class="tabla-env" tabindex="0">
  <table class="tabla">
    <thead><tr><th>Fecha</th><th>Día</th><th class="cen">Estado</th><th>Observación</th></tr></thead>
    <tbody>
    <?php foreach ($detalle as $d): ?>
      <tr>
        <td class="sm"><?= e(fecha((string)$d['fecha'])) ?></td>
        <td class="sm txt-2"><?= e(dia_nombre((string)$d['fecha'])) ?></td>
        <td class="cen"><span class="badge badge--<?= e(estado_badge((string)$d['estado'])) ?>"><?= e(ucfirst((string)$d['estado'])) ?></span></td>
        <td class="sm txt-2"><?= e($d['nota'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($detalle === []): ?>
      <tr><td colspan="4" class="tabla__vacio"><?= icono('asistencia', 40) ?><p>No hay registros de asistencia en este mes.</p></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
