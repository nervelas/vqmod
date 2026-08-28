<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,380px)">
  <form method="post">
    <?= csrf() ?>
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3>Emitir los cargos del período</h3></div>
      <div class="tarjeta-cuerpo">
        <div class="aviso-caja info mb-3">
          <?= ico('info', 20) ?>
          <div>Se creará un cargo por cada concepto automático y cada una de las
            <strong><?= (int) $totalCasas ?> viviendas</strong>. Si un cargo ya existe para ese período,
            <strong>no se duplica</strong>: puede ejecutarlo con confianza las veces que necesite.</div>
        </div>
        <div class="campos">
          <div class="campo">
            <label for="periodo">Período a generar *</label>
            <input type="month" id="periodo" name="periodo" required value="<?= e($periodo) ?>">
          </div>
          <div class="campo">
            <label for="concepto_id">Concepto</label>
            <select id="concepto_id" name="concepto_id">
              <option value="">Todos los conceptos automáticos</option>
              <?php foreach ($conceptos as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= e($c['nombre']) ?> — <?= e(q((float) $c['monto'])) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="ayuda">Si elige un concepto se emite aunque no toque por periodicidad.</span>
          </div>
        </div>
      </div>
      <div class="tarjeta-pie fila-fin">
        <button class="btn btn-oro btn-lg" type="submit"><?= ico('refrescar', 18) ?> Generar cargos</button>
      </div>
    </div>
  </form>

  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Períodos ya emitidos</h3></div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($existentes === []): ?>
        <p class="texto-3 centrado" style="padding:20px 0;margin:0">Aún no se ha emitido ningún período.</p>
      <?php else: ?>
        <table class="tabla">
          <thead><tr><th>Período</th><th class="c">Cargos</th><th class="d">Emitido</th></tr></thead>
          <tbody>
            <?php foreach ($existentes as $x): ?>
              <tr>
                <td><a href="<?= e(url('/admin/cargos', ['periodo' => $x['periodo']])) ?>" class="fuerte"><?= e(periodoNombre((string) $x['periodo'])) ?></a></td>
                <td class="c num"><?= (int) $x['n'] ?></td>
                <td class="d num"><?= e(q((float) $x['total'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </article>
</div>
