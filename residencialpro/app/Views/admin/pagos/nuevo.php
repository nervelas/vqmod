<?php use App\Models\Cuota; ?>
<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,360px)">
  <form method="post" enctype="multipart/form-data" id="f-pago">
    <?= csrf() ?>
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3>Datos del pago</h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campos">
          <div class="campo campo-ancho">
            <label for="casa_id">Vivienda *</label>
            <select id="casa_id" name="casa_id" required data-recargar-casa>
              <option value="">Seleccione la vivienda…</option>
              <?php foreach ($casas as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $casaId === (int) $c['id'] ? 'selected' : '' ?>>
                  <?= e($c['codigo']) ?> · <?= e($c['fase'] ?? '') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="fecha">Fecha del pago *</label>
            <input type="date" id="fecha" name="fecha" required value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>">
          </div>
          <div class="campo">
            <label for="monto">Monto recibido *</label>
            <input type="number" id="monto" name="monto" step="0.01" min="0.01" required inputmode="decimal">
          </div>
          <div class="campo">
            <label for="metodo">Forma de pago *</label>
            <select id="metodo" name="metodo" required>
              <?php foreach (['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'deposito' => 'Depósito', 'tarjeta' => 'Tarjeta', 'linea' => 'Pago en línea', 'otro' => 'Otro'] as $k => $et): ?>
                <option value="<?= e($k) ?>"><?= e($et) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="cuenta_id">Cuenta de destino</label>
            <select id="cuenta_id" name="cuenta_id">
              <option value="">Sin especificar</option>
              <?php foreach ($cuentas as $cu): ?>
                <option value="<?= (int) $cu['id'] ?>"><?= e($cu['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="banco">Banco</label>
            <input type="text" id="banco" name="banco" maxlength="90">
          </div>
          <div class="campo">
            <label for="referencia">Número de boleta o referencia</label>
            <input type="text" id="referencia" name="referencia" maxlength="90">
          </div>
          <div class="campo campo-ancho">
            <label for="comprobante">Comprobante (imagen o PDF)</label>
            <input type="file" id="comprobante" name="comprobante" accept="image/*,application/pdf">
          </div>
          <div class="campo campo-ancho">
            <label for="notas">Notas</label>
            <textarea id="notas" name="notas" rows="2" maxlength="500"></textarea>
          </div>
        </div>
        <label class="marca-check">
          <input type="checkbox" name="enviar_recibo" value="1" checked>
          <span>Enviar el recibo en PDF por correo al residente</span>
        </label>
      </div>

      <?php if ($casaId > 0): ?>
        <div class="tarjeta-cab" style="border-top:1px solid var(--linea)">
          <h3>Aplicación a los cargos pendientes</h3>
          <button class="btn btn-sm btn-claro" type="button" data-aplicar-saldo><?= ico('rayo', 15) ?> Aplicar automáticamente</button>
        </div>
        <div class="tarjeta-cuerpo compacto">
          <?php if ($cargos === []): ?>
            <p class="texto-3 centrado" style="padding:18px 0;margin:0">La vivienda no tiene cargos pendientes. El pago quedará como saldo a favor.</p>
          <?php else: ?>
            <div class="aviso-caja info mb-2"><?= ico('info', 18) ?>
              <div>Si deja los campos vacíos, el sistema aplicará el pago a los cargos <strong>más antiguos primero</strong>.</div>
            </div>
            <div class="tabla-caja">
              <table class="tabla">
                <thead><tr><th>Concepto</th><th class="c">Vence</th><th class="d">Saldo</th><th class="d" style="width:150px">Aplicar</th></tr></thead>
                <tbody>
                  <?php foreach ($cargos as $g): $s = Cuota::saldoCargo($g); ?>
                    <tr>
                      <td><?= e($g['descripcion']) ?></td>
                      <td class="c texto-3"><?= e(fecha((string) $g['fecha_vence'])) ?></td>
                      <td class="d num" data-saldo="<?= $s ?>"><?= e(q($s)) ?></td>
                      <td class="d">
                        <input type="number" name="cargo[<?= (int) $g['id'] ?>]" step="0.01" min="0" max="<?= $s ?>"
                               data-cargo style="text-align:right" aria-label="Monto a aplicar">
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="tarjeta-pie fila-fin">
        <a class="btn btn-claro" href="<?= e(url('/admin/pagos')) ?>">Cancelar</a>
        <button class="btn btn-oro btn-lg" type="submit"><?= ico('guardar', 18) ?> Registrar pago</button>
      </div>
    </div>
  </form>

  <div class="columna">
    <?php if ($casa !== null): ?>
      <article class="tarjeta">
        <div class="tarjeta-cuerpo">
          <div class="mayus">Vivienda seleccionada</div>
          <h3 style="margin:6px 0 2px"><?= e($casa['codigo']) ?></h3>
          <p class="texto-3" style="font-size:.86rem;margin-bottom:14px"><?= e($casa['fase']) ?><?= !empty($casa['calle']) ? ' · ' . e($casa['calle']) : '' ?></p>
          <div class="mayus">Saldo pendiente</div>
          <div class="kpi-valor" style="margin-top:4px"><?= e(q($saldo)) ?></div>
          <a class="btn btn-claro btn-sm mt-2" href="<?= e(url('/admin/casas/' . (int) $casa['id'])) ?>"><?= ico('ojo', 15) ?> Ver la vivienda</a>
        </div>
      </article>
    <?php else: ?>
      <article class="tarjeta">
        <div class="tarjeta-cuerpo">
          <div class="aviso-caja info"><?= ico('info', 19) ?>
            <div>Seleccione una vivienda para ver sus cargos pendientes y aplicar el pago concepto por concepto.</div>
          </div>
        </div>
      </article>
    <?php endif; ?>
  </div>
</div>

<script<?= nonce() ?>>
(function () {
  var sel = document.querySelector('[data-recargar-casa]');
  if (sel) {
    sel.addEventListener('change', function () {
      if (sel.value) location.href = RP.ruta('/admin/pagos/nuevo') + '?casa=' + encodeURIComponent(sel.value);
    });
  }
  var btn = document.querySelector('[data-aplicar-saldo]');
  if (btn) {
    btn.addEventListener('click', function () {
      var restante = parseFloat(document.getElementById('monto').value || '0');
      document.querySelectorAll('[data-cargo]').forEach(function (inp) {
        var fila = inp.closest('tr');
        var saldo = parseFloat(fila.querySelector('[data-saldo]').dataset.saldo || '0');
        var aplicar = Math.min(saldo, Math.max(0, restante));
        inp.value = aplicar > 0 ? aplicar.toFixed(2) : '';
        restante -= aplicar;
      });
      if (restante > 0.009) RP.aviso('Quedan ' + RP.moneda(restante) + ' que se registrarán como saldo a favor.', 'info');
    });
  }
})();
</script>
