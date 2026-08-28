<?php use App\Core\Vista;
$colores = array_map(static fn($c) => (string) $c['color'], $porCategoria);
$graf = ['type' => 'doughnut', 'data' => [
  'labels' => array_column($porCategoria, 'categoria'),
  'datasets' => [['data' => array_map(static fn($c) => round((float) $c['total'], 2), $porCategoria), 'backgroundColor' => $colores ?: ['#B94E27']]],
], 'options' => ['formato' => 'moneda', 'centro' => ['etiqueta' => 'Total del período', 'valor' => q($total)]]];
?>
<section class="rejilla mb-3" style="grid-template-columns:minmax(0,1fr) minmax(0,330px)">
  <div class="rejilla rejilla-3" style="align-content:start">
    <article class="kpi">
      <div class="kpi-et"><?= ico('moneda', 15) ?> Egresos del período</div>
      <div class="kpi-valor"><?= e(q($total)) ?></div>
      <div class="kpi-nota"><?= e(fecha($filtros['desde'])) ?> a <?= e(fecha($filtros['hasta'])) ?></div>
    </article>
    <?php foreach (array_slice($cuentas, 0, 2) as $cu): ?>
      <article class="kpi">
        <div class="kpi-et"><?= ico($cu['tipo'] === 'caja' ? 'billetera' : 'edificio', 15) ?> <?= e(recortar((string) $cu['nombre'], 22)) ?></div>
        <div class="kpi-valor"><?= e(q((float) $cu['saldo'])) ?></div>
        <div class="kpi-nota">Saldo disponible</div>
      </article>
    <?php endforeach; ?>
  </div>
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Por categoría</h3></div>
    <div class="tarjeta-cuerpo">
      <?php if ($porCategoria === []): ?>
        <p class="texto-3 centrado" style="padding:30px 0;margin:0">Sin egresos en el período.</p>
      <?php else: ?>
        <canvas role="img" height="220" data-grafica="<?= e(json_encode($graf, JSON_UNESCAPED_UNICODE)) ?>" aria-label="Egresos por categoría"></canvas>
      <?php endif; ?>
    </div>
  </article>
</section>

<div class="fila-entre mb-3">
  <form class="fila envolver crecer" method="get" style="gap:10px">
    <input type="date" name="desde" value="<?= e($filtros['desde']) ?>" style="max-width:160px" aria-label="Desde">
    <input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>" style="max-width:160px" aria-label="Hasta">
    <select aria-label="Filtrar por categoría" name="categoria" data-auto-enviar style="max-width:200px">
      <option value="">Todas las categorías</option>
      <?php foreach ($categorias as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= $filtros['categoria'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="entrada-icono" style="max-width:210px">
      <?= ico('buscar', 18) ?>
      <input type="search" name="buscar" aria-label="Buscar por descripción o documento" value="<?= e($filtros['buscar']) ?>" placeholder="Descripción o documento">
    </div>
    <button class="btn btn-claro btn-sm" type="submit"><?= ico('filtro', 16) ?> Filtrar</button>
  </form>
  <div class="fila" style="gap:8px">
    <a class="btn btn-claro" href="<?= e(url('/excel/egresos', $filtros)) ?>"><?= ico('descargar', 17) ?> Excel</a>
    <?php if (esRol('admin', 'contabilidad')): ?>
      <a class="btn btn-oro" href="<?= e(url('/admin/egresos/nuevo')) ?>"><?= ico('mas', 17) ?> Registrar egreso</a>
    <?php endif; ?>
  </div>
</div>

<div class="tarjeta">
  <?php if ($egresos === []): ?>
    <?= Vista::parcial('partials/vacio', ['icono' => 'moneda', 'titulo' => 'No hay egresos en el período',
        'accion' => esRol('admin', 'contabilidad') ? '/admin/egresos/nuevo' : null, 'accionTexto' => 'Registrar egreso']) ?>
  <?php else: ?>
    <div class="tabla-caja">
      <table class="tabla apilar">
        <thead><tr><th class="c">Fecha</th><th>Descripción</th><th>Categoría</th><th>Proveedor</th><th>Documento</th><th>Cuenta</th><th class="d">Monto</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($egresos as $e): ?>
            <tr>
              <td data-et="Fecha" class="c texto-3"><?= e(fecha((string) $e['fecha'])) ?></td>
              <td data-et="Descripción"><?= e($e['descripcion']) ?></td>
              <td data-et="Categoría">
                <span class="chip neutro">
                  <i class="punto-cat" style="background:<?= e($e['color'] ?? '#6E6A61') ?>"></i>
                  <?= e($e['categoria'] ?? 'Sin categoría') ?>
                </span>
              </td>
              <td data-et="Proveedor" class="texto-2"><?= e(recortar((string) $e['proveedor'], 24) ?: '—') ?></td>
              <td data-et="Documento" class="texto-3"><?= e($e['documento'] ?? '—') ?></td>
              <td data-et="Cuenta" class="texto-3"><?= e(recortar((string) $e['cuenta'], 18) ?: '—') ?></td>
              <td data-et="Monto" class="d num fuerte"><?= e(q((float) $e['monto'])) ?></td>
              <td data-et="" class="d nowrap">
                <?php if (!empty($e['archivo'])): ?>
                  <a class="btn btn-sm btn-fantasma" href="<?= e(url('/archivo/facturas/' . $e['archivo'])) ?>" target="_blank" rel="noopener" aria-label="Ver factura"><?= ico('archivo', 15) ?></a>
                <?php endif; ?>
                <?php if (esRol('admin', 'contabilidad')): ?>
                  <a class="btn btn-sm btn-fantasma" href="<?= e(url('/admin/egresos/' . (int) $e['id'] . '/editar')) ?>" aria-label="Editar"><?= ico('editar', 15) ?></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="6">Total del período</td><td class="d num"><?= e(q($total)) ?></td><td></td></tr></tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>
