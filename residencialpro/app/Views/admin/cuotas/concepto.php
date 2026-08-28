<?php $esNuevo = $concepto === null; ?>
<div class="contenedor-sm" style="margin-left:0">
  <a class="btn btn-claro btn-sm mb-3" href="<?= e(url('/admin/cuotas')) ?>"><?= ico('flechaIzq', 16) ?> Volver a cuotas</a>
  <form method="post">
    <?= csrf() ?>
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3><?= $esNuevo ? 'Nuevo concepto de cobro' : 'Editar concepto' ?></h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campos">
          <div class="campo campo-ancho">
            <label for="nombre">Nombre del concepto *</label>
            <input type="text" id="nombre" name="nombre" required maxlength="120"
                   value="<?= e($concepto['nombre'] ?? '') ?>" placeholder="Cuota de mantenimiento">
          </div>
          <div class="campo campo-ancho">
            <label for="descripcion">Descripción</label>
            <input type="text" id="descripcion" name="descripcion" maxlength="255"
                   value="<?= e($concepto['descripcion'] ?? '') ?>"
                   placeholder="Cuota ordinaria mensual: seguridad, jardinería y limpieza.">
          </div>
          <div class="campo">
            <label for="calculo">Forma de cálculo *</label>
            <select id="calculo" name="calculo" required>
              <option value="fijo"        <?= ($concepto['calculo'] ?? 'fijo') === 'fijo' ? 'selected' : '' ?>>Monto fijo por vivienda</option>
              <option value="coeficiente" <?= ($concepto['calculo'] ?? '') === 'coeficiente' ? 'selected' : '' ?>>Prorrateo por coeficiente</option>
              <option value="metros"      <?= ($concepto['calculo'] ?? '') === 'metros' ? 'selected' : '' ?>>Por metro de construcción</option>
            </select>
            <span class="ayuda">
              Fijo: todas pagan lo mismo · Coeficiente: el monto se reparte según el % de cada vivienda
              (<?= (int) $totalCasas ?> viviendas) · Metros: monto × m² (<?= e(number_format($totalMetros, 0)) ?> m² en total).
            </span>
          </div>
          <div class="campo">
            <label for="monto">Monto base *</label>
            <input type="number" id="monto" name="monto" step="0.01" min="0" required value="<?= e($concepto['monto'] ?? '0') ?>">
          </div>
          <div class="campo">
            <label for="periodicidad">Periodicidad *</label>
            <select id="periodicidad" name="periodicidad" required>
              <?php foreach (['mensual' => 'Mensual', 'bimestral' => 'Bimestral (meses impares)', 'trimestral' => 'Trimestral', 'anual' => 'Anual (enero)', 'unico' => 'Cobro único'] as $k => $et): ?>
                <option value="<?= e($k) ?>" <?= ($concepto['periodicidad'] ?? 'mensual') === $k ? 'selected' : '' ?>><?= e($et) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="dia_vence">Día de vencimiento *</label>
            <input type="number" id="dia_vence" name="dia_vence" min="1" max="28" required value="<?= e($concepto['dia_vence'] ?? '10') ?>">
          </div>
          <div class="campo">
            <label for="mora_tipo">Recargo por mora</label>
            <select id="mora_tipo" name="mora_tipo">
              <option value="porcentaje" <?= ($concepto['mora_tipo'] ?? 'porcentaje') === 'porcentaje' ? 'selected' : '' ?>>Porcentaje mensual sobre el saldo</option>
              <option value="fijo"       <?= ($concepto['mora_tipo'] ?? '') === 'fijo' ? 'selected' : '' ?>>Monto fijo por mes de atraso</option>
              <option value="ninguna"    <?= ($concepto['mora_tipo'] ?? '') === 'ninguna' ? 'selected' : '' ?>>Sin recargo</option>
            </select>
          </div>
          <div class="campo">
            <label for="mora_valor">Valor de la mora</label>
            <input type="number" id="mora_valor" name="mora_valor" step="0.01" min="0" value="<?= e($concepto['mora_valor'] ?? '2') ?>">
            <span class="ayuda">Si es porcentaje, escriba 2 para 2% mensual.</span>
          </div>
          <div class="campo">
            <label for="pronto_pago">Descuento por pronto pago</label>
            <input type="number" id="pronto_pago" name="pronto_pago" step="0.01" min="0" value="<?= e($concepto['pronto_pago'] ?? '0') ?>">
          </div>
          <div class="campo">
            <label for="pronto_dias">Días antes del vencimiento</label>
            <input type="number" id="pronto_dias" name="pronto_dias" min="0" max="28" value="<?= e($concepto['pronto_dias'] ?? '0') ?>">
          </div>
          <div class="campo">
            <label for="orden">Orden de presentación</label>
            <input type="number" id="orden" name="orden" min="0" value="<?= e($concepto['orden'] ?? '0') ?>">
          </div>
        </div>
        <label class="marca-check">
          <input type="checkbox" name="automatico" value="1" <?= (int) ($concepto['automatico'] ?? 1) === 1 ? 'checked' : '' ?>>
          <span>Incluir automáticamente al generar los cargos del período</span>
        </label>
        <label class="marca-check">
          <input type="checkbox" name="activo" value="1" <?= (int) ($concepto['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
          <span>Concepto activo</span>
        </label>
      </div>
      <div class="tarjeta-pie fila-fin">
        <a class="btn btn-claro" href="<?= e(url('/admin/cuotas')) ?>">Cancelar</a>
        <button class="btn btn-oro" type="submit"><?= ico('guardar', 17) ?> Guardar concepto</button>
      </div>
    </div>
  </form>
</div>
