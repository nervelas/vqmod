<?php use App\Core\Auth; ?>
<div class="pagina-cab">
  <div><h1>Pagos registrados</h1>
    <p class="pagina-cab__sub"><?= number_format((float)$total) ?> movimientos · aprobado: <strong><?= e(moneda($suma)) ?></strong></p></div>
  <div class="acciones"><a href="<?= e(url('cobranza')) ?>" class="btn btn--linea"><?= icono('atras', 17) ?> Volver</a></div>
</div>

<form method="get" class="filtros">
  <div class="campo"><label for="f-desde">Desde</label>
    <input type="date" id="f-desde" name="desde" value="<?= e($filtros['desde']) ?>"></div>
  <div class="campo"><label for="f-hasta">Hasta</label>
    <input type="date" id="f-hasta" name="hasta" value="<?= e($filtros['hasta']) ?>"></div>
  <div class="campo campo--corto"><label for="f-estado">Estado</label>
    <select id="f-estado" name="estado" data-auto-envio>
      <option value="">Todos</option>
      <?php foreach (['aprobado' => 'Aprobado', 'revision' => 'En revisión', 'rechazado' => 'Rechazado', 'anulado' => 'Anulado'] as $k => $v): ?>
        <option value="<?= e($k) ?>" <?= $filtros['estado'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select></div>
  <button type="submit" class="btn btn--linea"><?= icono('filtro', 17) ?> Filtrar</button>
</form>

<div class="tabla-env" tabindex="0">
  <table class="tabla">
    <thead><tr><th>Recibo</th><th>Alumno</th><th>Fecha</th><th>Método</th><th class="num">Monto</th><th class="cen">Estado</th><th class="cen"></th></tr></thead>
    <tbody>
    <?php foreach ($pagos as $p): ?>
      <tr>
        <td class="sm"><?= e($p['recibo_no'] ?? '—') ?></td>
        <td><a href="<?= e(url('cobranza/estado/' . (int)$p['alumno_id'])) ?>"><?= e(trim($p['nombres'] . ' ' . $p['apellidos'])) ?></a>
          <div class="xs txt-3"><?= e($p['codigo']) ?></div></td>
        <td class="sm"><?= e(fecha((string)$p['fecha'])) ?></td>
        <td class="sm txt-2"><?= e(ucfirst((string)$p['metodo'])) ?></td>
        <td class="num negrita"><?= e(moneda((float)$p['monto'])) ?></td>
        <td class="cen"><span class="badge badge--<?= e(estado_badge((string)$p['estado'])) ?>"><?= e(ucfirst((string)$p['estado'])) ?></span></td>
        <td class="cen">
          <?php if ($p['estado'] === 'aprobado'): ?>
            <a class="btn btn--fantasma btn--sm" target="_blank" rel="noopener" href="<?= e(url('recibo/' . (int)$p['id'])) ?>" aria-label="Descargar recibo"><?= icono('descargar', 15) ?></a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($pagos === []): ?><tr><td colspan="7" class="tabla__vacio">No hay pagos en el rango seleccionado.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?= App\Core\View::partial('partials/paginacion', ['total' => $total, 'pagina' => $pagina, 'porPagina' => $porPagina]) ?>
