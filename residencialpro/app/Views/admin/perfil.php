<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,340px)">
  <div class="columna">
    <form method="post">
      <?= csrf() ?>
      <input type="hidden" name="accion" value="datos">
      <div class="tarjeta">
        <div class="tarjeta-cab"><h3>Mis datos</h3></div>
        <div class="tarjeta-cuerpo">
          <div class="campos">
            <div class="campo campo-ancho"><label for="nombre">Nombre completo</label>
              <input type="text" id="nombre" name="nombre" required maxlength="140" value="<?= e($u['nombre']) ?>"></div>
            <div class="campo"><label for="correo">Correo electrónico</label>
              <input type="email" id="correo" name="correo" maxlength="160" value="<?= e($u['correo'] ?? '') ?>"></div>
            <div class="campo"><label for="telefono">Teléfono</label>
              <input type="tel" id="telefono" name="telefono" maxlength="40" value="<?= e($u['telefono'] ?? '') ?>"></div>
          </div>
          <?php if (in_array($u['rol'], ['admin', 'junta', 'contabilidad'], true)): ?>
            <label class="marca-check">
              <input type="checkbox" name="dos_factores" value="1" <?= (int) $u['dos_factores'] === 1 ? 'checked' : '' ?>>
              <span><strong>Verificación en dos pasos</strong> — al ingresar le enviaremos un código de 6 dígitos a su correo.</span>
            </label>
          <?php endif; ?>
        </div>
        <div class="tarjeta-pie fila-fin">
          <button class="btn btn-oro" type="submit"><?= ico('guardar', 17) ?> Guardar datos</button>
        </div>
      </div>
    </form>

    <form method="post">
      <?= csrf() ?>
      <input type="hidden" name="accion" value="clave">
      <div class="tarjeta">
        <div class="tarjeta-cab"><h3>Cambiar contraseña</h3></div>
        <div class="tarjeta-cuerpo">
          <div class="campos">
            <div class="campo campo-ancho"><label for="clave_actual">Contraseña actual *</label>
              <input type="password" id="clave_actual" name="clave_actual" required autocomplete="current-password"></div>
            <div class="campo"><label for="clave">Contraseña nueva *</label>
              <input type="password" id="clave" name="clave" required minlength="10" autocomplete="new-password"></div>
            <div class="campo"><label for="clave2">Repítala *</label>
              <input type="password" id="clave2" name="clave2" required minlength="10" autocomplete="new-password"></div>
          </div>
          <span class="ayuda">Mínimo 10 caracteres, combinando letras y números.</span>
        </div>
        <div class="tarjeta-pie fila-fin">
          <button class="btn btn-oro" type="submit"><?= ico('candado', 17) ?> Cambiar contraseña</button>
        </div>
      </div>
    </form>
  </div>

  <div class="columna">
    <article class="tarjeta">
      <div class="tarjeta-cuerpo centrado">
        <span class="avatar lg" style="margin:0 auto"><?= e(iniciales((string) $u['nombre'])) ?></span>
        <h3 class="mt-2" style="margin-bottom:2px"><?= e($u['nombre']) ?></h3>
        <span class="chip oro"><?= e(rolNombre((string) $u['rol'])) ?></span>
        <p class="texto-3 mt-2" style="font-size:.85rem;margin:8px 0 0">
          Usuario: <b><?= e($u['usuario']) ?></b><br>
          Último acceso: <?= $u['ultimo_acceso'] ? e(fechahora((string) $u['ultimo_acceso'])) : 'nunca' ?>
        </p>
      </div>
    </article>

    <?php if ($casas !== []): ?>
      <article class="tarjeta">
        <div class="tarjeta-cab"><h3>Mis viviendas</h3></div>
        <div class="tarjeta-cuerpo compacto">
          <ul class="lista-limpia">
            <?php foreach ($casas as $c): if ($c === null) { continue; } ?>
              <li class="item-lista">
                <span style="color:var(--arcilla)"><?= ico('casa', 19) ?></span>
                <div class="crecer"><b><?= e($c['codigo']) ?></b><div class="meta"><?= e($c['fase'] ?? '') ?></div></div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </article>
    <?php endif; ?>

    <article class="tarjeta">
      <div class="tarjeta-cab"><h3>Notificaciones</h3></div>
      <div class="tarjeta-cuerpo compacto">
        <p class="texto-2" style="font-size:.88rem">
          Active las notificaciones para enterarse al instante cuando llegue una visita,
          se apruebe su pago o se publique un aviso importante.
        </p>
        <button class="btn btn-oro btn-bloque" type="button" data-activar-push><?= ico('campana', 17) ?> Activar en este dispositivo</button>
        <?php if ($dispositivos !== []): ?>
          <p class="ayuda mt-2"><?= count($dispositivos) ?> dispositivo(s) ya reciben notificaciones.</p>
        <?php endif; ?>
      </div>
    </article>
  </div>
</div>
