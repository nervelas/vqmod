<?php use App\Core\Vista; ?>
<div class="fila-entre mb-3">
  <div class="aviso-caja info crecer" style="max-width:640px">
    <?= ico('info', 20) ?>
    <div>Los conceptos definen <strong>qué</strong> se cobra y <strong>cómo</strong> se calcula.
      Los cargos se emiten para todas las viviendas desde “Generar cargos”.
      <?php if ($ultimoPeriodo !== ''): ?>
        Último período emitido: <strong><?= e(periodoNombre($ultimoPeriodo)) ?></strong>.
      <?php endif; ?>
    </div>
  </div>
  <div class="fila" style="gap:8px">
    <?php if (esRol('admin')): ?>
      <a class="btn btn-claro" href="<?= e(url('/admin/cuotas/generar')) ?>"><?= ico('refrescar', 17) ?> Generar cargos</a>
      <a class="btn btn-oro" href="<?= e(url('/admin/cuotas/concepto')) ?>"><?= ico('mas', 17) ?> Nuevo concepto</a>
    <?php endif; ?>
  </div>
</div>

<div class="rejilla rejilla-2">
  <?php if ($conceptos === []): ?>
    <div class="tarjeta" style="grid-column:1/-1">
      <?= Vista::parcial('partials/vacio', ['icono' => 'billetera', 'titulo' => 'Todavía no hay conceptos de cobro',
          'texto' => 'Cree la cuota de mantenimiento para empezar a emitir cargos.',
          'accion' => '/admin/cuotas/concepto', 'accionTexto' => 'Crear el primer concepto']) ?>
    </div>
  <?php endif; ?>

  <?php foreach ($conceptos as $c):
    $calculo = ['fijo' => 'Monto fijo por vivienda', 'coeficiente' => 'Prorrateo por coeficiente', 'metros' => 'Por metro de construcción'];
    $per = ['mensual' => 'Mensual', 'bimestral' => 'Bimestral', 'trimestral' => 'Trimestral', 'anual' => 'Anual', 'unico' => 'Cobro único'];
    $estimado = match ($c['calculo']) {
        'fijo'        => (float) $c['monto'] * $totalCasas,
        'coeficiente' => (float) $c['monto'],
        default       => 0.0,
    };
  ?>
    <article class="tarjeta tarjeta-flota">
      <div class="tarjeta-cab">
        <div>
          <h3 style="margin:0"><?= e($c['nombre']) ?></h3>
          <div class="texto-3" style="font-size:.8rem"><?= e($per[$c['periodicidad']] ?? '') ?> · vence el día <?= (int) $c['dia_vence'] ?></div>
        </div>
        <div class="fila" style="gap:6px">
          <?php if ((int) $c['automatico'] === 1): ?><span class="chip oro">Automático</span><?php endif; ?>
          <span class="chip <?= (int) $c['activo'] === 1 ? 'ok' : 'neutro' ?>"><?= (int) $c['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span>
        </div>
      </div>
      <div class="tarjeta-cuerpo">
        <?php if (!empty($c['descripcion'])): ?>
          <p class="texto-2" style="font-size:.9rem"><?= e($c['descripcion']) ?></p>
        <?php endif; ?>
        <div class="fila envolver" style="gap:22px">
          <div>
            <div class="mayus">Monto</div>
            <div style="font-family:var(--f-titulo);font-size:1.7rem;color:var(--marca)"><?= e(q((float) $c['monto'])) ?></div>
            <div class="texto-3" style="font-size:.78rem"><?= e($calculo[$c['calculo']] ?? '') ?></div>
          </div>
          <?php if ($estimado > 0): ?>
            <div>
              <div class="mayus">Emisión estimada</div>
              <div style="font-family:var(--f-titulo);font-size:1.7rem;color:var(--acento-3)"><?= e(q($estimado)) ?></div>
              <div class="texto-3" style="font-size:.78rem">por período</div>
            </div>
          <?php endif; ?>
        </div>
        <hr>
        <div class="fila envolver" style="gap:18px;font-size:.85rem">
          <span class="texto-2">Mora:
            <b><?= $c['mora_tipo'] === 'ninguna' ? 'sin recargo'
                : ($c['mora_tipo'] === 'fijo' ? q((float) $c['mora_valor']) . ' por mes' : (float) $c['mora_valor'] . '% mensual') ?></b>
          </span>
          <?php if ((float) $c['pronto_pago'] > 0): ?>
            <span class="texto-2">Pronto pago: <b><?= e(q((float) $c['pronto_pago'])) ?></b> si paga <?= (int) $c['pronto_dias'] ?> días antes</span>
          <?php endif; ?>
        </div>
        <div class="fila envolver mt-2" style="gap:18px;font-size:.85rem">
          <span class="texto-2">Emitido histórico: <b class="num"><?= e(q((float) $c['emitido'])) ?></b></span>
          <span class="texto-2">Pendiente: <b class="num <?= (float) $c['pendiente'] > 0 ? 'texto-grave' : 'texto-ok' ?>"><?= e(q((float) $c['pendiente'])) ?></b></span>
        </div>
      </div>
      <?php if (esRol('admin')): ?>
        <div class="tarjeta-pie fila-fin">
          <a class="btn btn-sm btn-claro" href="<?= e(url('/admin/cargos', ['concepto' => (int) $c['id']])) ?>"><?= ico('lista', 15) ?> Ver cargos</a>
          <a class="btn btn-sm btn-oro" href="<?= e(url('/admin/cuotas/concepto/' . (int) $c['id'])) ?>"><?= ico('editar', 15) ?> Editar</a>
        </div>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>
