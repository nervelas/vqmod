<?php $diasSel = array_filter(array_map('trim', explode(',', (string) $area['dias'])), static fn($d) => $d !== ''); ?>
<div class="contenedor-sm" style="margin-left:0">
  <a class="btn btn-claro btn-sm mb-3" href="<?= e(url('/admin/areas')) ?>"><?= ico('flechaIzq', 16) ?> Volver a áreas</a>
  <form method="post" enctype="multipart/form-data">
    <?= csrf() ?>
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3>Editar área común</h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campos">
          <div class="campo campo-ancho"><label for="nombre">Nombre *</label>
            <input type="text" id="nombre" name="nombre" required maxlength="120" value="<?= e($area['nombre']) ?>"></div>
          <div class="campo campo-ancho"><label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="2" maxlength="600"><?= e($area['descripcion'] ?? '') ?></textarea></div>
          <div class="campo"><label for="hora_desde">Desde</label>
            <input type="time" id="hora_desde" name="hora_desde" value="<?= e(substr((string) $area['hora_desde'], 0, 5)) ?>"></div>
          <div class="campo"><label for="hora_hasta">Hasta</label>
            <input type="time" id="hora_hasta" name="hora_hasta" value="<?= e(substr((string) $area['hora_hasta'], 0, 5)) ?>"></div>
          <div class="campo"><label for="capacidad">Capacidad</label>
            <input type="number" id="capacidad" name="capacidad" min="0" value="<?= (int) $area['capacidad'] ?>"></div>
          <div class="campo"><label for="duracion_min">Duración máxima (min)</label>
            <input type="number" id="duracion_min" name="duracion_min" min="30" step="30" value="<?= (int) $area['duracion_min'] ?>"></div>
          <div class="campo"><label for="costo">Costo</label>
            <input type="number" id="costo" name="costo" step="0.01" min="0" value="<?= e($area['costo']) ?>"></div>
          <div class="campo"><label for="deposito">Depósito</label>
            <input type="number" id="deposito" name="deposito" step="0.01" min="0" value="<?= e($area['deposito']) ?>"></div>
          <div class="campo">
            <label for="aprobacion">Aprobación</label>
            <select id="aprobacion" name="aprobacion">
              <option value="manual" <?= $area['aprobacion'] === 'manual' ? 'selected' : '' ?>>La administración confirma</option>
              <option value="automatica" <?= $area['aprobacion'] === 'automatica' ? 'selected' : '' ?>>Inmediata</option>
            </select>
          </div>
          <div class="campo">
            <label for="foto">Fotografía</label>
            <input type="file" id="foto" name="foto" accept="image/*" data-previa="#previa-area">
            <?php if (!empty($area['foto'])): ?>
              <img id="previa-area" src="<?= e(subida($area['foto'], 'areas')) ?>" alt="Fotografía del área"
                   style="margin-top:10px;border-radius:var(--r-sm);max-height:130px">
            <?php else: ?>
              <img id="previa-area" src="<?= e(url('/assets/img/vacio.svg')) ?>" alt="" hidden
                   style="margin-top:10px;border-radius:var(--r-sm);max-height:130px">
            <?php endif; ?>
          </div>
          <div class="campo campo-ancho">
            <span class="etiqueta">Días disponibles</span>
            <div class="fila envolver" style="gap:10px">
              <?php foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 0 => 'Dom'] as $n => $et): ?>
                <label class="marca-check" style="margin:0">
                  <input type="checkbox" name="dias[]" value="<?= $n ?>" <?= in_array((string) $n, $diasSel, true) ? 'checked' : '' ?>>
                  <span><?= e($et) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="campo campo-ancho"><label for="reglas">Reglas de uso</label>
            <textarea id="reglas" name="reglas" rows="4" maxlength="1200"><?= e($area['reglas'] ?? '') ?></textarea></div>
        </div>
        <label class="marca-check"><input type="checkbox" name="bloquea_mora" value="1" <?= (int) $area['bloquea_mora'] === 1 ? 'checked' : '' ?>>
          <span>No permitir reservar si la vivienda tiene saldo pendiente</span></label>
        <label class="marca-check"><input type="checkbox" name="activo" value="1" <?= (int) $area['activo'] === 1 ? 'checked' : '' ?>>
          <span>Área activa</span></label>
      </div>
      <div class="tarjeta-pie fila-fin">
        <a class="btn btn-claro" href="<?= e(url('/admin/areas')) ?>">Cancelar</a>
        <button class="btn btn-oro" type="submit"><?= ico('guardar', 17) ?> Guardar cambios</button>
      </div>
    </div>
  </form>
</div>
