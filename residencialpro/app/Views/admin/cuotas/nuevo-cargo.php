<div class="contenedor-sm" style="margin-left:0">
  <a class="btn btn-claro btn-sm mb-3" href="<?= e(url('/admin/cargos')) ?>"><?= ico('flechaIzq', 16) ?> Volver</a>
  <form method="post">
    <?= csrf() ?>
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3>Cargo manual</h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campos">
          <div class="campo campo-ancho">
            <label for="descripcion">Descripción del cargo *</label>
            <input type="text" id="descripcion" name="descripcion" required maxlength="190"
                   placeholder="Multa por ruido después de las 22:00 horas">
          </div>
          <div class="campo">
            <label for="monto">Monto por vivienda *</label>
            <input type="number" id="monto" name="monto" step="0.01" min="0.01" required>
          </div>
          <div class="campo">
            <label for="vence">Fecha de vencimiento *</label>
            <input type="date" id="vence" name="vence" required value="<?= e(date('Y-m-d', strtotime('+15 days'))) ?>">
          </div>
          <div class="campo">
            <label for="concepto_id">Asociar a un concepto</label>
            <select id="concepto_id" name="concepto_id">
              <option value="">Sin concepto</option>
              <?php foreach ($conceptos as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= e($c['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="destino">Aplicar a *</label>
            <select id="destino" name="destino" required>
              <option value="seleccion">Viviendas seleccionadas</option>
              <option value="fase">Toda una fase</option>
              <option value="todas">Todas las viviendas</option>
            </select>
          </div>
          <div class="campo">
            <label for="fase_id">Fase (si aplica)</label>
            <select id="fase_id" name="fase_id">
              <?php foreach ($fases as $f): ?><option value="<?= (int) $f['id'] ?>"><?= e($f['nombre']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="campo campo-ancho">
            <label for="casas">Viviendas</label>
            <select id="casas" name="casas[]" multiple size="10">
              <?php foreach ($casas as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $casaPre === (int) $c['id'] ? 'selected' : '' ?>>
                  <?= e($c['codigo']) ?> · <?= e($c['fase'] ?? '') ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="ayuda">Mantenga presionada Ctrl (o Cmd) para seleccionar varias.</span>
          </div>
        </div>
      </div>
      <div class="tarjeta-pie fila-fin">
        <a class="btn btn-claro" href="<?= e(url('/admin/cargos')) ?>">Cancelar</a>
        <button class="btn btn-oro" type="submit"><?= ico('mas', 17) ?> Crear cargos</button>
      </div>
    </div>
  </form>
</div>
