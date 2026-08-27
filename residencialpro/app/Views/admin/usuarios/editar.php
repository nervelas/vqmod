<?php $esNuevo = $usuario === null; ?>
<div class="contenedor-sm" style="margin-left:0">
  <a class="btn btn-claro btn-sm mb-3" href="<?= e(url('/admin/usuarios')) ?>"><?= ico('flechaIzq', 16) ?> Volver a usuarios</a>
  <form method="post">
    <?= csrf() ?>
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3><?= $esNuevo ? 'Nuevo usuario' : 'Editar usuario' ?></h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campos">
          <div class="campo campo-ancho">
            <label for="nombre">Nombre completo *</label>
            <input type="text" id="nombre" name="nombre" required maxlength="140" value="<?= e($usuario['nombre'] ?? '') ?>">
          </div>
          <div class="campo">
            <label for="rol">Perfil *</label>
            <select id="rol" name="rol" required data-rol>
              <?php foreach (['admin', 'junta', 'contabilidad', 'garita', 'residente'] as $r): ?>
                <option value="<?= e($r) ?>" <?= ($usuario['rol'] ?? 'residente') === $r ? 'selected' : '' ?>><?= e(rolNombre($r)) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="ayuda">
              Administración: todo · Junta: reportes y avisos · Contabilidad: finanzas ·
              Garita: solo accesos · Residente: su vivienda.
            </span>
          </div>
          <div class="campo">
            <label for="usuario">Usuario para ingresar *</label>
            <input type="text" id="usuario" name="usuario" required pattern="[a-z0-9._\-]{4,60}"
                   value="<?= e($usuario['usuario'] ?? '') ?>" style="text-transform:lowercase">
          </div>
          <div class="campo">
            <label for="correo">Correo electrónico</label>
            <input type="email" id="correo" name="correo" maxlength="160" value="<?= e($usuario['correo'] ?? '') ?>">
          </div>
          <div class="campo">
            <label for="telefono">Teléfono</label>
            <input type="tel" id="telefono" name="telefono" maxlength="40" value="<?= e($usuario['telefono'] ?? '') ?>">
          </div>
          <div class="campo campo-ancho">
            <label for="clave"><?= $esNuevo ? 'Contraseña *' : 'Nueva contraseña (opcional)' ?></label>
            <div class="fila" style="gap:8px">
              <input type="text" id="clave" name="clave" class="crecer" minlength="10" maxlength="80"
                     <?= $esNuevo ? 'required value="' . e($claveSugerida) . '"' : 'placeholder="Dejar vacío para no cambiarla"' ?>>
              <button type="button" class="btn btn-claro btn-sm" data-generar-clave>Generar</button>
            </div>
            <span class="ayuda">Mínimo 10 caracteres, con letras y números.</span>
          </div>
          <div class="campo campo-ancho" id="campo-casas" <?= ($usuario['rol'] ?? 'residente') !== 'residente' ? 'hidden' : '' ?>>
            <label for="casas">Viviendas asociadas</label>
            <select id="casas" name="casas[]" multiple size="8">
              <?php foreach ($casas as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $casasUsuario, true) ? 'selected' : '' ?>>
                  <?= e($c['codigo']) ?> · <?= e($c['fase'] ?? '') ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="ayuda">El residente solo verá la información de estas viviendas.</span>
          </div>
        </div>
        <label class="marca-check">
          <input type="checkbox" name="activo" value="1" <?= (int) ($usuario['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
          <span>Usuario activo (puede iniciar sesión)</span>
        </label>
        <?php if ($esNuevo): ?>
          <label class="marca-check">
            <input type="checkbox" name="enviar_datos" value="1" checked>
            <span>Enviar los datos de acceso por correo</span>
          </label>
        <?php endif; ?>
      </div>
      <div class="tarjeta-pie fila-fin">
        <a class="btn btn-claro" href="<?= e(url('/admin/usuarios')) ?>">Cancelar</a>
        <button class="btn btn-oro" type="submit"><?= ico('guardar', 17) ?> Guardar usuario</button>
      </div>
    </div>
  </form>
</div>
<script<?= nonce() ?>>
(function () {
  var rol = document.querySelector('[data-rol]');
  var casas = document.getElementById('campo-casas');
  rol.addEventListener('change', function () { casas.hidden = rol.value !== 'residente'; });
  document.querySelectorAll('[data-generar-clave]').forEach(function (b) {
    b.addEventListener('click', function () {
      var s = ['ci','pre','sol','lu','ver','na','ro','mi','ta','be'], p = '';
      for (var i = 0; i < 3; i++) p += s[Math.floor(Math.random() * s.length)];
      var v = p.charAt(0).toUpperCase() + p.slice(1) + Math.floor(1000 + Math.random() * 9000);
      document.getElementById('clave').value = v;
      RP.aviso('Contraseña generada: ' + v, 'info', 9);
    });
  });
})();
</script>
