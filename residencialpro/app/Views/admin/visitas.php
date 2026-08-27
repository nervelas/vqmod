<?php use App\Core\Vista;
$graf = ['type' => 'bar', 'data' => [
  'labels' => array_column($serie, 'etiqueta'),
  'datasets' => [['label' => 'Ingresos', 'backgroundColor' => '#0F2E24', 'data' => array_column($serie, 'n')]],
]];
?>
<section class="rejilla mb-3" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr)) minmax(0,2fr)">
  <article class="kpi">
    <div class="kpi-et"><?= ico('puerta', 15) ?> Ingresos hoy</div>
    <div class="kpi-valor"><?= (int) $hoy ?></div>
  </article>
  <article class="kpi">
    <div class="kpi-et"><?= ico('usuarios', 15) ?> Dentro ahora</div>
    <div class="kpi-valor"><?= (int) $adentro ?></div>
  </article>
  <article class="tarjeta">
    <div class="tarjeta-cuerpo">
      <canvas role="img" height="140" data-grafica="<?= e(json_encode($graf, JSON_UNESCAPED_UNICODE)) ?>" aria-label="Visitas por día"></canvas>
    </div>
  </article>
</section>

<form method="get" class="fila envolver mb-3" style="gap:10px">
  <div class="entrada-icono" style="max-width:230px">
    <?= ico('buscar', 18) ?>
    <input type="search" name="buscar" aria-label="Buscar por visitante, DPI o placa" value="<?= e($filtros['buscar']) ?>" placeholder="Visitante, DPI o placa">
  </div>
  <select aria-label="Filtrar por vivienda" name="casa" data-auto-enviar style="max-width:180px">
    <option value="">Todas las casas</option>
    <?php foreach ($casas as $c): ?>
      <option value="<?= (int) $c['id'] ?>" <?= $filtros['casa'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['codigo']) ?></option>
    <?php endforeach; ?>
  </select>
  <select aria-label="Filtrar por tipo" name="tipo" data-auto-enviar style="max-width:170px">
    <option value="">Todos los tipos</option>
    <?php foreach (['visita' => 'Visita', 'proveedor' => 'Proveedor', 'delivery' => 'Delivery', 'servicio' => 'Servicio', 'empleado' => 'Empleado', 'mudanza' => 'Mudanza'] as $k => $et): ?>
      <option value="<?= e($k) ?>" <?= $filtros['tipo'] === $k ? 'selected' : '' ?>><?= e($et) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="desde" value="<?= e($filtros['desde']) ?>" style="max-width:160px" aria-label="Desde">
  <input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>" style="max-width:160px" aria-label="Hasta">
  <button class="btn btn-claro btn-sm" type="submit"><?= ico('filtro', 16) ?> Filtrar</button>
  <a class="btn btn-claro btn-sm" href="<?= e(url('/excel/visitas', $filtros)) ?>"><?= ico('descargar', 15) ?> Excel</a>
</form>

<div class="tarjeta">
  <?php if ($visitas === []): ?>
    <?= Vista::parcial('partials/vacio', ['icono' => 'puerta', 'titulo' => 'No hay visitas con esos filtros']) ?>
  <?php else: ?>
    <div class="tabla-caja">
      <table class="tabla apilar">
        <thead><tr><th>Visitante</th><th>Casa</th><th>Tipo</th><th>Placa</th><th class="c">Entrada</th><th class="c">Salida</th><th>Guardia</th></tr></thead>
        <tbody>
          <?php foreach ($visitas as $v): ?>
            <tr>
              <td data-et="Visitante">
                <b><?= e(recortar((string) $v['visitante'], 30)) ?></b>
                <?php if (!empty($v['dpi'])): ?><div class="meta texto-3">DPI <?= e($v['dpi']) ?></div><?php endif; ?>
              </td>
              <td data-et="Casa"><?= !empty($v['casa_id']) ? '<a href="' . e(url('/admin/casas/' . (int) $v['casa_id'])) . '">' . e($v['casa']) . '</a>' : '—' ?></td>
              <td data-et="Tipo"><span class="chip neutro"><?= e(ucfirst((string) $v['tipo'])) ?></span></td>
              <td data-et="Placa" class="texto-2"><?= e($v['placa'] ?? '—') ?></td>
              <td data-et="Entrada" class="c texto-3"><?= e(fechahora((string) $v['entrada'])) ?></td>
              <td data-et="Salida" class="c"><?= $v['salida'] ? e(fechahora((string) $v['salida'])) : '<span class="chip info">Adentro</span>' ?></td>
              <td data-et="Guardia" class="texto-3" style="font-size:.85rem"><?= e(recortar((string) $v['guardia'], 20)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?= Vista::parcial('partials/paginacion', ['pagina' => $pagina, 'total' => $total, 'porPagina' => $porPagina]) ?>
