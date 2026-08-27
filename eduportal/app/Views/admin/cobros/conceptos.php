<div class="pagina-cab">
  <div><h1>Conceptos de cobro</h1>
    <p class="pagina-cab__sub">Ciclo <?= e($ciclo['nombre'] ?? '') ?> · defina montos, vencimientos, mora y descuentos</p></div>
  <div class="acciones">
    <button type="button" class="btn" data-modal="modal-concepto"
      data-valores='{"id":"","nombre":"","tipo":"colegiatura","monto":"","dia_vencimiento":"5","mora_tipo":"fijo","mora_valor":"0","mora_gracia":"0"}'>
      <?= icono('mas', 17) ?> Nuevo concepto
    </button>
    <a href="<?= e(url('cobranza')) ?>" class="btn btn--linea"><?= icono('atras', 17) ?> Volver</a>
  </div>
</div>

<div class="tabla-env" tabindex="0">
  <table class="tabla">
    <thead><tr>
      <th>Concepto</th><th>Tipo</th><th class="num">Monto</th><th class="cen">Mensual</th>
      <th class="cen">Vence</th><th>Mora</th><th class="cen">Estado</th><th class="cen">Acciones</th>
    </tr></thead>
    <tbody>
      <?php foreach ($conceptos as $c): ?>
        <tr>
          <td><strong><?= e($c['nombre']) ?></strong>
            <?php if (!empty($c['nivel'])): ?><div class="xs txt-3">Solo <?= e($c['nivel']) ?></div><?php endif; ?></td>
          <td class="sm txt-2"><?= e(ucfirst((string)$c['tipo'])) ?></td>
          <td class="num negrita"><?= e(moneda((float)$c['monto'])) ?></td>
          <td class="cen"><?= (int)$c['recurrente'] === 1 ? '<span class="badge badge--info">Sí</span>' : '<span class="badge badge--mute">No</span>' ?></td>
          <td class="cen">Día <?= (int)$c['dia_vencimiento'] ?></td>
          <td class="sm txt-2">
            <?php if ((float)$c['mora_valor'] > 0): ?>
              <?= $c['mora_tipo'] === 'porcentaje' ? e(number_format((float)$c['mora_valor'], 2)) . '%' : e(moneda((float)$c['mora_valor'])) ?>
              <div class="xs txt-3"><?= (int)$c['mora_gracia'] ?> días de gracia</div>
            <?php else: ?>Sin mora<?php endif; ?>
          </td>
          <td class="cen"><span class="badge badge--<?= (int)$c['activo'] === 1 ? 'ok' : 'mute' ?>"><?= (int)$c['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
          <td class="cen">
            <div class="flex" style="justify-content:center;gap:4px">
              <button type="button" class="btn btn--fantasma btn--sm" aria-label="Editar" data-modal="modal-concepto"
                data-valores='<?= e(json_encode([
                  'id' => (string)$c['id'], 'nombre' => $c['nombre'], 'tipo' => $c['tipo'],
                  'monto' => (string)$c['monto'], 'dia_vencimiento' => (string)$c['dia_vencimiento'],
                  'mora_tipo' => $c['mora_tipo'], 'mora_valor' => (string)$c['mora_valor'],
                  'mora_gracia' => (string)$c['mora_gracia'], 'nivel_id' => (string)($c['nivel_id'] ?? ''),
                  'recurrente' => (string)$c['recurrente'], 'aplica_beca' => (string)$c['aplica_beca'],
                  'aplica_hermanos' => (string)$c['aplica_hermanos'], 'activo' => (string)$c['activo'],
                ], JSON_UNESCAPED_UNICODE)) ?>'><?= icono('editar', 16) ?></button>
              <form method="post" action="<?= e(url('cobranza/conceptos/' . (int)$c['id'] . '/eliminar')) ?>"
                    data-confirmar="¿Eliminar o desactivar este concepto?" style="display:inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--fantasma btn--sm" aria-label="Eliminar"><?= icono('borrar', 16) ?></button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($conceptos === []): ?>
        <tr><td colspan="8" class="tabla__vacio"><?= icono('dinero', 40) ?><p>Aún no hay conceptos de cobro configurados.</p></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal" id="modal-concepto" aria-hidden="true" role="dialog" aria-label="Concepto de cobro">
  <div class="modal__fondo" data-cerrar></div>
  <div class="modal__caja">
    <form method="post" action="<?= e(url('cobranza/conceptos')) ?>">
      <?= csrf_field() ?>
      <div class="modal__cab"><h3>Concepto de cobro</h3>
        <button type="button" class="btn btn--fantasma btn--sm" data-cerrar>Cerrar</button></div>
      <div class="modal__cuerpo">
        <input type="hidden" name="id" value="">
        <div class="campo">
          <label for="co-nombre">Nombre <span class="oro">*</span></label>
          <input type="text" id="co-nombre" name="nombre" required maxlength="120" placeholder="Colegiatura mensual">
        </div>
        <div class="fila fila--3">
          <div class="campo">
            <label for="co-tipo">Tipo <span class="oro">*</span></label>
            <select id="co-tipo" name="tipo" required>
              <?php foreach (['inscripcion' => 'Inscripción', 'colegiatura' => 'Colegiatura', 'transporte' => 'Transporte',
                              'uniforme' => 'Uniformes', 'actividad' => 'Actividades', 'otro' => 'Otro'] as $k => $v): ?>
                <option value="<?= e($k) ?>"><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="co-monto">Monto <span class="oro">*</span></label>
            <input type="number" id="co-monto" name="monto" required min="0" step="0.01">
          </div>
          <div class="campo">
            <label for="co-dia">Día de vencimiento <span class="oro">*</span></label>
            <input type="number" id="co-dia" name="dia_vencimiento" required min="1" max="28" value="5">
          </div>
        </div>
        <div class="fila fila--3">
          <div class="campo">
            <label for="co-mora-tipo">Tipo de mora</label>
            <select id="co-mora-tipo" name="mora_tipo">
              <option value="fijo">Monto fijo</option>
              <option value="porcentaje">Porcentaje</option>
            </select>
          </div>
          <div class="campo">
            <label for="co-mora-valor">Valor de la mora</label>
            <input type="number" id="co-mora-valor" name="mora_valor" min="0" step="0.01" value="0">
          </div>
          <div class="campo">
            <label for="co-gracia">Días de gracia</label>
            <input type="number" id="co-gracia" name="mora_gracia" min="0" max="60" value="0">
          </div>
        </div>
        <div class="campo">
          <label for="co-nivel">Aplicar solo al nivel</label>
          <select id="co-nivel" name="nivel_id">
            <option value="">Todos los niveles</option>
            <?php foreach ($niveles as $n): ?>
              <option value="<?= (int)$n['id'] ?>"><?= e($n['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <label class="check"><input type="checkbox" name="recurrente" value="1" checked> Se cobra todos los meses</label>
        <label class="check"><input type="checkbox" name="aplica_beca" value="1" checked> Aplica descuento por beca</label>
        <label class="check"><input type="checkbox" name="aplica_hermanos" value="1" checked> Aplica descuento por hermanos</label>
        <label class="check"><input type="checkbox" name="activo" value="1" checked> Concepto activo</label>
      </div>
      <div class="modal__pie">
        <button type="button" class="btn btn--linea" data-cerrar>Cancelar</button>
        <button type="submit" class="btn">Guardar concepto</button>
      </div>
    </form>
  </div>
</div>
