<?php $esNuevo = empty($alumno['id']); ?>
<div class="pagina-cab">
  <div>
    <h1><?= e($titulo) ?></h1>
    <p class="pagina-cab__sub"><?= $esNuevo ? 'Complete la ficha del nuevo alumno' : 'Actualice los datos del alumno' ?></p>
  </div>
  <div class="acciones"><a href="<?= e(url('alumnos')) ?>" class="btn btn--linea"><?= icono('atras', 17) ?> Volver</a></div>
</div>

<form method="post" enctype="multipart/form-data"
      action="<?= e(url($esNuevo ? 'alumnos' : 'alumnos/' . (int)$alumno['id'])) ?>">
  <?= csrf_field() ?>
  <div class="split">
    <div class="col">
      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Datos personales</h2></div>
        <div class="fila">
          <div class="campo">
            <label for="nombres">Nombres <span class="oro">*</span></label>
            <input type="text" id="nombres" name="nombres" required maxlength="120" value="<?= e($alumno['nombres'] ?? '') ?>">
          </div>
          <div class="campo">
            <label for="apellidos">Apellidos <span class="oro">*</span></label>
            <input type="text" id="apellidos" name="apellidos" required maxlength="120" value="<?= e($alumno['apellidos'] ?? '') ?>">
          </div>
        </div>
        <div class="fila fila--3">
          <div class="campo">
            <label for="codigo">Código <span class="oro">*</span></label>
            <input type="text" id="codigo" name="codigo" required maxlength="30" value="<?= e($alumno['codigo'] ?? '') ?>">
          </div>
          <div class="campo">
            <label for="fecha_nacimiento">Fecha de nacimiento</label>
            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" max="<?= e(date('Y-m-d')) ?>"
                   value="<?= e($alumno['fecha_nacimiento'] ?? '') ?>">
          </div>
          <div class="campo">
            <label for="genero">Género</label>
            <select id="genero" name="genero">
              <option value="">Sin especificar</option>
              <?php foreach (['M' => 'Masculino', 'F' => 'Femenino', 'O' => 'Otro'] as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= ($alumno['genero'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="fila">
          <div class="campo">
            <label for="dpi">DPI / CUI</label>
            <input type="text" id="dpi" name="dpi" maxlength="30" value="<?= e($alumno['dpi'] ?? '') ?>">
          </div>
          <div class="campo">
            <label for="partida">Partida de nacimiento</label>
            <input type="text" id="partida" name="partida" maxlength="60" value="<?= e($alumno['partida'] ?? '') ?>">
          </div>
        </div>
        <div class="campo">
          <label for="direccion">Dirección</label>
          <input type="text" id="direccion" name="direccion" maxlength="255" value="<?= e($alumno['direccion'] ?? '') ?>">
        </div>
      </div>

      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Salud y emergencias</h2></div>
        <div class="campo">
          <label for="alergias">Alergias y condiciones médicas</label>
          <textarea id="alergias" name="alergias" maxlength="1000"><?= e($alumno['alergias'] ?? '') ?></textarea>
        </div>
        <div class="fila">
          <div class="campo">
            <label for="emergencia_nombre">Contacto de emergencia</label>
            <input type="text" id="emergencia_nombre" name="emergencia_nombre" maxlength="120" value="<?= e($alumno['emergencia_nombre'] ?? '') ?>">
          </div>
          <div class="campo">
            <label for="emergencia_tel">Teléfono de emergencia</label>
            <input type="tel" id="emergencia_tel" name="emergencia_tel" maxlength="40" value="<?= e($alumno['emergencia_tel'] ?? '') ?>">
          </div>
        </div>
        <div class="campo">
          <label for="observaciones">Observaciones</label>
          <textarea id="observaciones" name="observaciones" maxlength="1000"><?= e($alumno['observaciones'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Fotografía</h2></div>
        <div class="cen mb-3">
          <img id="vista-foto" class="avatar avatar--xl" style="margin:0 auto"
               src="<?= e(!empty($alumno['foto']) ? archivo_url($alumno['foto']) : asset('img/alumno.svg')) ?>" alt="">
        </div>
        <div class="campo">
          <label for="foto">Cargar fotografía</label>
          <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" data-previsualizar="#vista-foto">
          <p class="ayuda">JPG, PNG o WEBP. Se ajusta automáticamente.</p>
        </div>
      </div>

      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Inscripción</h2></div>
        <div class="campo">
          <label for="seccion_id">Grado y sección</label>
          <select id="seccion_id" name="seccion_id">
            <option value="">Sin asignar</option>
            <?php foreach ($secciones as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= (int)($alumno['seccion_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>>
                <?= e($s['etiqueta']) ?> (<?= (int)$s['inscritos'] ?>/<?= (int)$s['capacidad'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="beca_pct">Beca (%)</label>
          <input type="number" id="beca_pct" name="beca_pct" min="0" max="100" step="0.01"
                 value="<?= e((string)($alumno['beca_pct'] ?? '0')) ?>">
          <p class="ayuda">Se descuenta automáticamente de los cargos que apliquen beca.</p>
        </div>
        <div class="campo">
          <label for="estado">Estado <span class="oro">*</span></label>
          <select id="estado" name="estado" required>
            <?php foreach (['activo' => 'Activo', 'retirado' => 'Retirado', 'graduado' => 'Graduado'] as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= ($alumno['estado'] ?? 'activo') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <button type="submit" class="btn btn--bloque"><?= icono('check', 17) ?> Guardar alumno</button>
    </div>
  </div>
</form>
