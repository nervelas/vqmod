<div class="contenedor-sm" style="margin-left:0">
  <a class="btn btn-claro btn-sm mb-3" href="<?= e(url('/admin/egresos')) ?>"><?= ico('flechaIzq', 16) ?> Volver a egresos</a>
  <form method="post" enctype="multipart/form-data">
    <?= csrf() ?>
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3><?= $egreso === null ? 'Registrar un egreso' : 'Editar egreso' ?></h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campos">
          <div class="campo campo-ancho">
            <label for="descripcion">Descripción del gasto *</label>
            <input type="text" id="descripcion" name="descripcion" required maxlength="190"
                   value="<?= e($egreso['descripcion'] ?? '') ?>" placeholder="Servicio de seguridad — 4 guardias">
          </div>
          <div class="campo">
            <label for="fecha">Fecha *</label>
            <input type="date" id="fecha" name="fecha" required value="<?= e($egreso['fecha'] ?? date('Y-m-d')) ?>">
          </div>
          <div class="campo">
            <label for="monto">Monto *</label>
            <input type="number" id="monto" name="monto" step="0.01" min="0.01" required value="<?= e($egreso['monto'] ?? '') ?>">
          </div>
          <div class="campo">
            <label for="categoria_id">Categoría</label>
            <select id="categoria_id" name="categoria_id">
              <option value="">Sin categoría</option>
              <?php foreach ($categorias as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) ($egreso['categoria_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="proveedor_id">Proveedor</label>
            <select id="proveedor_id" name="proveedor_id">
              <option value="">Sin proveedor</option>
              <?php foreach ($proveedores as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= (int) ($egreso['proveedor_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="cuenta_id">Cuenta de origen</label>
            <select id="cuenta_id" name="cuenta_id">
              <option value="">Sin especificar</option>
              <?php foreach ($cuentas as $cu): ?>
                <option value="<?= (int) $cu['id'] ?>" <?= (int) ($egreso['cuenta_id'] ?? 0) === (int) $cu['id'] ? 'selected' : '' ?>><?= e($cu['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="metodo">Forma de pago</label>
            <select id="metodo" name="metodo">
              <?php foreach (['transferencia' => 'Transferencia', 'cheque' => 'Cheque', 'efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'otro' => 'Otro'] as $k => $et): ?>
                <option value="<?= e($k) ?>" <?= ($egreso['metodo'] ?? 'transferencia') === $k ? 'selected' : '' ?>><?= e($et) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="documento">Número de factura</label>
            <input type="text" id="documento" name="documento" maxlength="60" value="<?= e($egreso['documento'] ?? '') ?>">
          </div>
          <div class="campo campo-ancho">
            <label for="archivo">Adjuntar la factura</label>
            <input type="file" id="archivo" name="archivo" accept="image/*,application/pdf">
            <?php if (!empty($egreso['archivo'])): ?>
              <span class="ayuda">Ya hay un archivo adjunto.
                <a href="<?= e(url('/archivo/facturas/' . $egreso['archivo'])) ?>" target="_blank" rel="noopener">Verlo</a>.</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="tarjeta-pie fila-fin">
        <?php if ($egreso !== null && esRol('admin')): ?>
          <button type="button" class="btn btn-fantasma" data-enviar="#f-anular"><?= ico('basura', 16) ?> Anular</button>
        <?php endif; ?>
        <button class="btn btn-oro" type="submit"><?= ico('guardar', 17) ?> Guardar egreso</button>
      </div>
    </div>
  </form>
  <?php if ($egreso !== null && esRol('admin')): ?>
    <form id="f-anular" method="post" action="<?= e(url('/admin/egresos/' . (int) $egreso['id'] . '/anular')) ?>"
          data-confirmar="El egreso dejará de contar en los informes y saldos."
          data-confirmar-titulo="¿Anular este egreso?" data-confirmar-boton="Sí, anular" hidden>
      <?= csrf() ?>
      <button type="submit" class="solo-lectores">Confirmar la anulación</button>
    </form>
  <?php endif; ?>
</div>
