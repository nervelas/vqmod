<?php use App\Core\Sesion; $esNuevo = $residente === null; $casaSel = (int) ($residente['casa_id'] ?? $casaPre); ?>
<div class="contenedor-sm" style="margin-left:0">
  <a class="btn btn-claro btn-sm mb-3" href="<?= e(url($casaSel > 0 ? '/admin/casas/' . $casaSel : '/admin/residentes')) ?>">
    <?= ico('flechaIzq', 16) ?> Volver
  </a>
  <form method="post">
    <?= csrf() ?>
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3><?= $esNuevo ? 'Datos del nuevo residente' : 'Editar residente' ?></h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campos">
          <div class="campo campo-ancho">
            <label for="nombre">Nombre completo *</label>
            <input type="text" id="nombre" name="nombre" required maxlength="140"
                   value="<?= e($residente['nombre'] ?? Sesion::viejo('nombre')) ?>">
          </div>
          <div class="campo">
            <label for="casa_id">Vivienda *</label>
            <select id="casa_id" name="casa_id" required>
              <option value="">Seleccione…</option>
              <?php foreach ($casas as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $casaSel === (int) $c['id'] ? 'selected' : '' ?>>
                  <?= e($c['codigo']) ?> · <?= e($c['fase'] ?? '') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="tipo">Tipo de residente *</label>
            <select id="tipo" name="tipo" required>
              <?php foreach (['propietario' => 'Propietario', 'inquilino' => 'Inquilino', 'familiar' => 'Familiar'] as $k => $et): ?>
                <option value="<?= e($k) ?>" <?= ($residente['tipo'] ?? 'propietario') === $k ? 'selected' : '' ?>><?= e($et) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="dpi">DPI</label>
            <input type="text" id="dpi" name="dpi" maxlength="30" value="<?= e($residente['dpi'] ?? '') ?>">
          </div>
          <div class="campo">
            <label for="nit">NIT</label>
            <input type="text" id="nit" name="nit" maxlength="30" value="<?= e($residente['nit'] ?? '') ?>">
          </div>
          <div class="campo">
            <label for="telefono">Teléfono</label>
            <input type="tel" id="telefono" name="telefono" maxlength="40" value="<?= e($residente['telefono'] ?? '') ?>">
          </div>
          <div class="campo">
            <label for="whatsapp">WhatsApp</label>
            <input type="tel" id="whatsapp" name="whatsapp" maxlength="40" value="<?= e($residente['whatsapp'] ?? '') ?>">
            <span class="ayuda">Si lo deja vacío se usará el teléfono.</span>
          </div>
          <div class="campo campo-ancho">
            <label for="correo">Correo electrónico</label>
            <input type="email" id="correo" name="correo" maxlength="160" value="<?= e($residente['correo'] ?? '') ?>">
            <span class="ayuda">Necesario para enviarle recibos y crear su acceso al portal.</span>
          </div>
          <div class="campo">
            <label for="fecha_inicio">Fecha de ingreso</label>
            <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= e($residente['fecha_inicio'] ?? date('Y-m-d')) ?>">
          </div>
          <div class="campo">
            <label for="fecha_fin">Fecha de salida</label>
            <input type="date" id="fecha_fin" name="fecha_fin" value="<?= e($residente['fecha_fin'] ?? '') ?>">
          </div>
          <div class="campo campo-ancho">
            <label for="notas">Notas</label>
            <textarea id="notas" name="notas" rows="3" maxlength="1000"><?= e($residente['notas'] ?? '') ?></textarea>
          </div>
        </div>
        <label class="marca-check">
          <input type="checkbox" name="activo" value="1" <?= (int) ($residente['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
          <span>Residente activo</span>
        </label>
        <?php if ($esNuevo): ?>
          <label class="marca-check">
            <input type="checkbox" name="crear_acceso" value="1" checked>
            <span>Crear su acceso al portal y enviarle la contraseña por correo</span>
          </label>
        <?php endif; ?>
      </div>
      <div class="tarjeta-pie fila-fin">
        <?php if (!$esNuevo): ?>
          <button type="button" class="btn btn-fantasma" data-enviar="#f-baja"><?= ico('salir', 16) ?> Dar de baja</button>
        <?php endif; ?>
        <button class="btn btn-oro" type="submit"><?= ico('guardar', 17) ?> Guardar residente</button>
      </div>
    </div>
  </form>

  <?php if (!$esNuevo): ?>
    <form id="f-baja" method="post" action="<?= e(url('/admin/residentes/' . (int) $residente['id'] . '/baja')) ?>"
          data-confirmar="El residente quedará inactivo y su acceso al portal se desactivará. El historial se conserva."
          data-confirmar-titulo="¿Dar de baja a este residente?" data-confirmar-boton="Sí, dar de baja" hidden>
      <?= csrf() ?>
      <input type="hidden" name="motivo" value="Baja registrada desde el panel.">
      <button type="submit" class="solo-lectores">Confirmar la baja</button>
    </form>
  <?php endif; ?>
</div>
