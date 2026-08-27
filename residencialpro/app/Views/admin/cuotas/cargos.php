<?php use App\Core\Vista; use App\Models\Cuota; ?>
<section class="rejilla rejilla-4 mb-3">
  <?php foreach ([
    ['Emitido', (float) ($resumen['emitido'] ?? 0), 'recibo', ''],
    ['Mora acumulada', (float) ($resumen['mora'] ?? 0), 'alerta', ''],
    ['Cobrado', (float) ($resumen['pagado'] ?? 0), 'checkCirculo', 'ok'],
    ['Saldo pendiente', (float) ($resumen['saldo'] ?? 0), 'billetera', 'grave'],
  ] as [$et, $valor, $icono, $clase]): ?>
    <article class="kpi">
      <div class="kpi-et"><?= ico($icono, 15) ?> <?= e($et) ?></div>
      <div class="kpi-valor"><?= e(q($valor)) ?></div>
      <?php if ($clase !== ''): ?><div class="kpi-nota <?= e($clase) ?>"><?= $clase === 'ok' ? 'Ingresos aplicados' : 'Por cobrar' ?></div><?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>

<div class="fila-entre mb-3">
  <form class="fila envolver crecer" method="get" style="gap:10px">
    <div class="entrada-icono" style="max-width:240px">
      <?= ico('buscar', 18) ?>
      <input type="search" name="buscar" aria-label="Buscar por casa o concepto" value="<?= e($filtros['buscar']) ?>" placeholder="Casa o concepto">
    </div>
    <select aria-label="Filtrar por período" name="periodo" data-auto-enviar style="max-width:180px">
      <option value="">Todos los períodos</option>
      <?php foreach ($periodos as $p): ?>
        <option value="<?= e($p['periodo']) ?>" <?= $filtros['periodo'] === $p['periodo'] ? 'selected' : '' ?>><?= e(periodoNombre((string) $p['periodo'])) ?></option>
      <?php endforeach; ?>
    </select>
    <select aria-label="Filtrar por concepto" name="concepto" data-auto-enviar style="max-width:200px">
      <option value="">Todos los conceptos</option>
      <?php foreach ($conceptos as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= $filtros['concepto'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
    <select aria-label="Filtrar por estado" name="estado" data-auto-enviar style="max-width:150px">
      <option value="">Todos</option>
      <?php foreach (['pendiente' => 'Pendientes', 'parcial' => 'Parciales', 'pagado' => 'Pagados', 'anulado' => 'Anulados'] as $k => $et): ?>
        <option value="<?= e($k) ?>" <?= $filtros['estado'] === $k ? 'selected' : '' ?>><?= e($et) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-claro btn-sm" type="submit"><?= ico('filtro', 16) ?> Filtrar</button>
  </form>
  <?php if (esRol('admin')): ?>
    <a class="btn btn-oro" href="<?= e(url('/admin/cargos/nuevo')) ?>"><?= ico('mas', 17) ?> Cargo manual</a>
  <?php endif; ?>
</div>

<div class="tarjeta">
  <?php if ($cargos === []): ?>
    <?= Vista::parcial('partials/vacio', ['icono' => 'recibo', 'titulo' => 'No hay cargos con esos filtros',
        'texto' => 'Genere los cargos del período o ajuste la búsqueda.',
        'accion' => esRol('admin') ? '/admin/cuotas/generar' : null, 'accionTexto' => 'Generar cargos']) ?>
  <?php else: ?>
    <div class="tabla-caja">
      <table class="tabla apilar">
        <thead><tr><th>Casa</th><th>Concepto</th><th class="c">Vence</th><th class="d">Cargo</th><th class="d">Mora</th><th class="d">Pagado</th><th class="d">Saldo</th><th class="c">Estado</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($cargos as $g):
            $saldo = Cuota::saldoCargo($g);
            $dias  = $g['estado'] !== 'pagado' ? (int) floor((time() - strtotime((string) $g['fecha_vence'])) / 86400) : 0;
          ?>
            <tr>
              <td data-et="Casa" class="fuerte"><a href="<?= e(url('/admin/casas/' . (int) $g['casa_id'])) ?>"><?= e($g['casa']) ?></a></td>
              <td data-et="Concepto"><?= e($g['descripcion']) ?></td>
              <td data-et="Vence" class="c">
                <?= e(fecha((string) $g['fecha_vence'])) ?>
                <?php if ($dias > 0 && $g['estado'] !== 'anulado'): ?>
                  <div><span class="chip <?= e(semaforoMora($dias)) ?>"><?= $dias ?> d</span></div>
                <?php endif; ?>
              </td>
              <td data-et="Cargo" class="d num"><?= e(q((float) $g['monto'])) ?></td>
              <td data-et="Mora" class="d num"><?= (float) $g['mora'] > 0 ? e(q((float) $g['mora'])) : '—' ?></td>
              <td data-et="Pagado" class="d num"><?= (float) $g['pagado'] > 0 ? e(q((float) $g['pagado'])) : '—' ?></td>
              <td data-et="Saldo" class="d num fuerte"><?= e(q($saldo)) ?></td>
              <td data-et="Estado" class="c"><span class="chip <?= e(estadoBadge((string) $g['estado'])) ?>"><?= e(ucfirst((string) $g['estado'])) ?></span></td>
              <td data-et="" class="d">
                <?php if (esRol('admin') && $g['estado'] === 'pendiente' && (float) $g['pagado'] <= 0): ?>
                  <form method="post" action="<?= e(url('/admin/cargos/' . (int) $g['id'] . '/anular')) ?>" style="display:inline"
                        data-confirmar="El cargo dejará de contar en el saldo de la vivienda."
                        data-confirmar-titulo="¿Anular este cargo?" data-confirmar-boton="Sí, anular">
                    <?= csrf() ?>
                    <button class="btn btn-sm btn-fantasma" type="submit" aria-label="Anular cargo"><?= ico('equis', 15) ?></button>
                  </form>
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
