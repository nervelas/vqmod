<?php use App\Core\Auth; ?>
<div class="pagina-cab">
  <div><h1>Mi perfil</h1><p class="pagina-cab__sub"><?= e(rol_nombre((string)$usuario['rol'])) ?> · <?= e($usuario['email']) ?></p></div>
</div>

<div class="split">
  <div class="col">
    <div class="tarjeta">
      <div class="tarjeta__cab"><h2>Datos personales</h2></div>
      <form method="post" enctype="multipart/form-data" action="<?= e(url('perfil')) ?>">
        <?= csrf_field() ?>
        <div class="flex mb-4">
          <?php if (!empty($usuario['foto'])): ?>
            <img class="avatar avatar--lg" id="vista-foto" src="<?= e(archivo_url($usuario['foto'])) ?>" alt="">
          <?php else: ?>
            <span class="avatar avatar--lg iniciales" style="font-size:1.4rem"><?= e(mb_strtoupper(mb_substr((string)$usuario['nombre'], 0, 2))) ?></span>
          <?php endif; ?>
          <div style="flex:1">
            <div class="campo" style="margin:0">
              <label for="pf-foto">Cambiar fotografía</label>
              <input type="file" id="pf-foto" name="foto" accept="image/jpeg,image/png,image/webp" data-previsualizar="#vista-foto">
            </div>
          </div>
        </div>
        <div class="fila">
          <div class="campo">
            <label for="pf-nombre">Nombre completo <span class="oro">*</span></label>
            <input type="text" id="pf-nombre" name="nombre" required maxlength="120" value="<?= e($usuario['nombre']) ?>">
          </div>
          <div class="campo">
            <label for="pf-tel">Teléfono</label>
            <input type="tel" id="pf-tel" name="telefono" maxlength="40" value="<?= e($usuario['telefono'] ?? '') ?>">
          </div>
        </div>
        <button type="submit" class="btn"><?= icono('check', 17) ?> Guardar cambios</button>
      </form>
    </div>

    <div class="tarjeta">
      <div class="tarjeta__cab"><h2>Cambiar contraseña</h2></div>
      <form method="post" action="<?= e(url('perfil/password')) ?>">
        <?= csrf_field() ?>
        <div class="campo">
          <label for="pf-actual">Contraseña actual <span class="oro">*</span></label>
          <input type="password" id="pf-actual" name="password_actual" required autocomplete="current-password">
        </div>
        <div class="fila">
          <div class="campo">
            <label for="pf-nueva">Contraseña nueva <span class="oro">*</span></label>
            <input type="password" id="pf-nueva" name="password" required minlength="10" autocomplete="new-password">
          </div>
          <div class="campo">
            <label for="pf-conf">Confirmar contraseña <span class="oro">*</span></label>
            <input type="password" id="pf-conf" name="password_confirmacion" required minlength="10" autocomplete="new-password">
          </div>
        </div>
        <button type="submit" class="btn"><?= icono('escudo', 17) ?> Actualizar contraseña</button>
      </form>
    </div>
  </div>

  <div class="col">
    <div class="tarjeta">
      <div class="tarjeta__cab"><h2>Apariencia</h2></div>
      <form method="post" action="<?= e(url('perfil/apariencia')) ?>">
        <?= csrf_field() ?>
        <div class="campo">
          <label for="pf-tema">Tema</label>
          <select id="pf-tema" name="tema">
            <?php foreach (App\Controllers\Configuracion::TEMAS as $k => $t): ?>
              <option value="<?= e($k) ?>" <?= ($usuario['tema'] ?? 'default') === $k ? 'selected' : '' ?>><?= e($t['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <label class="check"><input type="checkbox" name="modo_oscuro" value="1"
          <?= (int)($usuario['modo_oscuro'] ?? 0) === 1 ? 'checked' : '' ?>> Modo oscuro</label>
        <button type="submit" class="btn btn--linea btn--sm"><?= icono('luna', 15) ?> Guardar apariencia</button>
      </form>
    </div>

    <div class="tarjeta">
      <div class="tarjeta__cab"><h2>Seguridad</h2></div>
      <?php if (Auth::is('superadmin')): ?>
        <form method="post" action="<?= e(url('perfil/2fa')) ?>" class="mb-3">
          <?= csrf_field() ?>
          <label class="check"><input type="checkbox" name="twofa" value="1"
            <?= (int)($usuario['twofa'] ?? 0) === 1 ? 'checked' : '' ?>>
            Verificación en dos pasos por correo</label>
          <button type="submit" class="btn btn--linea btn--sm"><?= icono('escudo', 15) ?> Guardar</button>
        </form>
      <?php endif; ?>
      <button type="button" class="btn btn--linea btn--sm mb-3" data-activar-push>
        <?= icono('campana', 15) ?> Activar notificaciones en este dispositivo</button>
      <form method="post" action="<?= e(url('perfil/sesiones')) ?>"
            data-confirmar="Se cerrará su sesión en todos los dispositivos. ¿Continuar?">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn--peligro btn--sm btn--bloque"><?= icono('salir', 15) ?> Cerrar sesión en todos los dispositivos</button>
      </form>
    </div>

    <div class="tarjeta">
      <div class="tarjeta__cab"><h3>Accesos recientes</h3></div>
      <?php if ($sesiones === []): ?>
        <p class="sm txt-3">Sin registros.</p>
      <?php else: ?>
        <div class="pila sm">
          <?php foreach ($sesiones as $s): ?>
            <div>
              <strong><?= e(fecha_hora((string)$s['creado_en'])) ?></strong>
              <div class="xs txt-3"><?= e($s['ip'] ?? '') ?> · <?= e(recorta($s['agente'] ?? '', 60)) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
