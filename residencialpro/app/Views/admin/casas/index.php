<?php use App\Core\Vista; ?>
<div class="fila-entre mb-3">
  <form class="fila envolver crecer" method="get" style="gap:10px">
    <div class="entrada-icono" style="max-width:260px">
      <?= ico('buscar', 18) ?>
      <input type="search" name="buscar" aria-label="Buscar casa o residente" value="<?= e($filtros['buscar']) ?>" placeholder="Buscar casa o residente">
    </div>
    <select aria-label="Filtrar por fase" name="fase" data-auto-enviar style="max-width:190px">
      <option value="">Todas las fases</option>
      <?php foreach ($fases as $f): ?>
        <option value="<?= (int) $f['id'] ?>" <?= $filtros['fase'] === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
    <select aria-label="Filtrar por estado" name="estado" data-auto-enviar style="max-width:170px">
      <option value="">Todos los estados</option>
      <?php foreach (['habitada' => 'Habitada', 'desocupada' => 'Desocupada', 'venta' => 'En venta', 'alquiler' => 'En alquiler'] as $k => $et): ?>
        <option value="<?= e($k) ?>" <?= $filtros['estado'] === $k ? 'selected' : '' ?>><?= e($et) ?></option>
      <?php endforeach; ?>
    </select>
    <label class="marca-check" style="white-space:nowrap">
      <input type="checkbox" name="morosas" value="1" data-auto-enviar <?= $filtros['morosas'] ? 'checked' : '' ?>>
      <span>Solo con saldo</span>
    </label>
    <button class="btn btn-claro btn-sm" type="submit"><?= ico('filtro', 16) ?> Filtrar</button>
  </form>
  <div class="fila" style="gap:8px">
    <a class="btn btn-claro" href="<?= e(url('/excel/casas')) ?>"><?= ico('descargar', 17) ?> Excel</a>
    <?php if (esRol('admin')): ?>
      <a class="btn btn-claro" href="<?= e(url('/admin/casas/importar')) ?>"><?= ico('subir', 17) ?> Importar</a>
      <a class="btn btn-oro" href="<?= e(url('/admin/casas/nueva')) ?>"><?= ico('mas', 17) ?> Nueva vivienda</a>
    <?php endif; ?>
  </div>
</div>

<div class="tarjeta">
  <?php if ($casas === []): ?>
    <?= Vista::parcial('partials/vacio', [
        'icono' => 'casa', 'titulo' => 'No hay viviendas que coincidan',
        'texto' => 'Ajuste los filtros o registre la primera vivienda del residencial.',
        'accion' => esRol('admin') ? '/admin/casas/nueva' : null, 'accionTexto' => 'Registrar vivienda']) ?>
  <?php else: ?>
    <div class="tabla-caja">
      <table class="tabla apilar">
        <thead>
          <tr>
            <th>Casa</th><th>Ubicación</th><th>Residente</th><th class="c">Estado</th>
            <th class="c">m²</th><th class="c">Coef.</th><th class="d">Saldo</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($casas as $c):
            $saldo = (float) $c['saldo'];
            $dias  = $c['vence_mas_antiguo'] ? max(0, (int) floor((time() - strtotime((string) $c['vence_mas_antiguo'])) / 86400)) : 0;
          ?>
            <tr>
              <td data-et="Casa" class="fuerte">
                <a href="<?= e(url('/admin/casas/' . (int) $c['id'])) ?>"><?= e($c['codigo']) ?></a>
                <?php if ((int) $c['restringida'] === 1): ?>
                  <span class="chip grave" title="Restricción de servicios por morosidad">Restringida</span>
                <?php endif; ?>
              </td>
              <td data-et="Ubicación" class="texto-2"><?= e($c['fase']) ?><?= !empty($c['calle']) ? ' · ' . e($c['calle']) : '' ?></td>
              <td data-et="Residente"><?= e(recortar((string) $c['residente'], 28) ?: '—') ?></td>
              <td data-et="Estado" class="c"><span class="chip <?= e(estadoBadge((string) $c['estado'])) ?>"><?= e(ucfirst((string) $c['estado'])) ?></span></td>
              <td data-et="Metros" class="c num"><?= e(number_format((float) $c['metros'], 0)) ?></td>
              <td data-et="Coeficiente" class="c num"><?= e(number_format((float) $c['coeficiente'], 3)) ?>%</td>
              <td data-et="Saldo" class="d">
                <?php if ($saldo > 0.009): ?>
                  <span class="chip <?= e(semaforoMora($dias)) ?>"><?= e(q($saldo)) ?></span>
                <?php else: ?>
                  <span class="chip ok">Solvente</span>
                <?php endif; ?>
              </td>
              <td data-et="" class="d nowrap">
                <a class="btn btn-sm btn-fantasma" href="<?= e(url('/admin/casas/' . (int) $c['id'])) ?>" aria-label="Ver detalle"><?= ico('ojo', 15) ?></a>
                <a class="btn btn-sm btn-fantasma" href="<?= e(url('/doc/estado-cuenta/' . (int) $c['id'])) ?>" target="_blank" rel="noopener" aria-label="Estado de cuenta en PDF"><?= ico('archivo', 15) ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?= Vista::parcial('partials/paginacion', ['pagina' => $pagina, 'total' => $total, 'porPagina' => $porPagina]) ?>
