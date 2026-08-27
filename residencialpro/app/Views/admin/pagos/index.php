<?php use App\Core\Vista; ?>
<?php if ($enRevision > 0): ?>
  <div class="aviso-caja alerta mb-3">
    <?= ico('archivo', 20) ?>
    <div class="crecer"><strong><?= (int) $enRevision ?> comprobante(s) esperan revisión</strong>
      Los residentes ya reportaron su pago y esperan la aprobación.</div>
    <a class="btn btn-sm btn-oro" href="<?= e(url('/admin/comprobantes')) ?>">Revisar</a>
  </div>
<?php endif; ?>

<section class="rejilla rejilla-4 mb-3">
  <article class="kpi">
    <div class="kpi-et"><?= ico('billetera', 15) ?> Recaudado en el rango</div>
    <div class="kpi-valor"><?= e(q($recaudado)) ?></div>
    <div class="kpi-nota"><?= $filtros['desde'] !== '' ? e(fecha($filtros['desde'])) . ' a ' . e(fecha($filtros['hasta'] ?: date('Y-m-d'))) : 'Mes en curso' ?></div>
  </article>
  <?php foreach (array_slice($porMetodo, 0, 3) as $m): ?>
    <article class="kpi">
      <div class="kpi-et"><?= ico('tarjeta', 15) ?> <?= e(ucfirst((string) $m['metodo'])) ?></div>
      <div class="kpi-valor"><?= e(q((float) $m['total'])) ?></div>
      <div class="kpi-nota"><?= (int) $m['n'] ?> operación(es)</div>
    </article>
  <?php endforeach; ?>
</section>

<div class="fila-entre mb-3">
  <form class="fila envolver crecer" method="get" style="gap:10px">
    <div class="entrada-icono" style="max-width:220px">
      <?= ico('buscar', 18) ?>
      <input type="search" name="buscar" aria-label="Buscar por recibo, referencia o casa" value="<?= e($filtros['buscar']) ?>" placeholder="Recibo, referencia o casa">
    </div>
    <input type="date" name="desde" value="<?= e($filtros['desde']) ?>" style="max-width:160px" aria-label="Desde">
    <input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>" style="max-width:160px" aria-label="Hasta">
    <select aria-label="Filtrar por estado" name="estado" data-auto-enviar style="max-width:150px">
      <option value="">Todos</option>
      <?php foreach (['aprobado' => 'Aprobados', 'revision' => 'En revisión', 'rechazado' => 'Rechazados', 'anulado' => 'Anulados'] as $k => $et): ?>
        <option value="<?= e($k) ?>" <?= $filtros['estado'] === $k ? 'selected' : '' ?>><?= e($et) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-claro btn-sm" type="submit"><?= ico('filtro', 16) ?> Filtrar</button>
  </form>
  <div class="fila" style="gap:8px">
    <a class="btn btn-claro" href="<?= e(url('/excel/pagos', $filtros)) ?>"><?= ico('descargar', 17) ?> Excel</a>
    <?php if (esRol('admin', 'contabilidad')): ?>
      <a class="btn btn-oro" href="<?= e(url('/admin/pagos/nuevo')) ?>"><?= ico('mas', 17) ?> Registrar pago</a>
    <?php endif; ?>
  </div>
</div>

<div class="tarjeta">
  <?php if ($pagos === []): ?>
    <?= Vista::parcial('partials/vacio', ['icono' => 'tarjeta', 'titulo' => 'No hay pagos con esos filtros',
        'accion' => esRol('admin', 'contabilidad') ? '/admin/pagos/nuevo' : null, 'accionTexto' => 'Registrar pago']) ?>
  <?php else: ?>
    <div class="tabla-caja">
      <table class="tabla apilar">
        <thead><tr><th>Recibo</th><th>Casa</th><th>Residente</th><th class="c">Fecha</th><th>Forma</th><th>Referencia</th><th class="c">Estado</th><th class="d">Monto</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($pagos as $p): ?>
            <tr>
              <td data-et="Recibo" class="fuerte"><a href="<?= e(url('/admin/pagos/' . (int) $p['id'])) ?>"><?= e($p['recibo'] ?? '—') ?></a></td>
              <td data-et="Casa"><a href="<?= e(url('/admin/casas/' . (int) $p['casa_id'])) ?>"><?= e($p['casa']) ?></a></td>
              <td data-et="Residente"><?= e(recortar((string) $p['residente'], 24) ?: '—') ?></td>
              <td data-et="Fecha" class="c texto-3"><?= e(fecha((string) $p['fecha'])) ?></td>
              <td data-et="Forma"><?= e(ucfirst((string) $p['metodo'])) ?></td>
              <td data-et="Referencia" class="texto-3"><?= e($p['referencia'] ?? '—') ?></td>
              <td data-et="Estado" class="c"><span class="chip <?= e(estadoBadge((string) $p['estado'])) ?>"><?= e(ucfirst((string) $p['estado'])) ?></span></td>
              <td data-et="Monto" class="d num fuerte"><?= e(q((float) $p['monto'])) ?></td>
              <td data-et="" class="d">
                <?php if ($p['estado'] === 'aprobado'): ?>
                  <a class="btn btn-sm btn-fantasma" href="<?= e(url('/doc/recibo/' . (int) $p['id'])) ?>" target="_blank" rel="noopener" aria-label="Descargar recibo"><?= ico('archivo', 15) ?></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?= Vista::parcial('partials/paginacion', ['pagina' => $pagina, 'total' => $total, 'porPagina' => $porPagina]) ?>
