<div class="pagina-cab">
  <div><h1>Usuarios y accesos</h1><p class="pagina-cab__sub"><?= number_format((float)$total) ?> usuarios registrados</p></div>
  <div class="acciones">
    <button type="button" class="btn" data-modal="modal-usuario"
      data-valores='{"id":"","nombre":"","email":"","rol":"docente","telefono":""}'><?= icono('mas', 17) ?> Nuevo usuario</button>
  </div>
</div>

<form method="get" class="filtros">
  <div class="campo"><label for="u-q">Buscar</label>
    <input type="search" id="u-q" name="q" value="<?= e($filtros['q']) ?>" placeholder="Nombre o correo" data-buscar></div>
  <div class="campo campo--corto"><label for="u-rol">Rol</label>
    <select id="u-rol" name="rol" data-auto-envio>
      <option value="">Todos</option>
      <?php foreach (['superadmin', 'secretaria', 'docente', 'padre'] as $r): ?>
        <option value="<?= e($r) ?>" <?= $filtros['rol'] === $r ? 'selected' : '' ?>><?= e(rol_nombre($r)) ?></option>
      <?php endforeach; ?>
    </select></div>
  <button type="submit" class="btn btn--linea"><?= icono('buscar', 17) ?> Filtrar</button>
</form>

<div class="tabla-env" tabindex="0">
  <table class="tabla">
    <thead><tr><th>Usuario</th><th>Rol</th><th>Teléfono</th><th>Último acceso</th><th class="cen">Estado</th><th class="cen">Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($usuarios as $u): ?>
      <tr>
        <td><strong><?= e($u['nombre']) ?></strong><div class="xs txt-3"><?= e($u['email']) ?></div></td>
        <td class="sm txt-2"><?= e(rol_nombre((string)$u['rol'])) ?></td>
        <td class="sm txt-2"><?= e($u['telefono'] ?? '—') ?></td>
        <td class="sm"><?= e($u['ultimo_acceso'] ? fecha_hora((string)$u['ultimo_acceso']) : 'Nunca') ?></td>
        <td class="cen"><span class="badge badge--<?= (int)$u['activo'] === 1 ? 'ok' : 'mute' ?>"><?= (int)$u['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
        <td class="cen">
          <div class="flex" style="justify-content:center;gap:4px">
            <button type="button" class="btn btn--fantasma btn--sm" aria-label="Editar" data-modal="modal-usuario"
              data-valores='<?= e(json_encode([
                'id' => (string)$u['id'], 'nombre' => $u['nombre'], 'email' => $u['email'],
                'rol' => $u['rol'], 'telefono' => (string)($u['telefono'] ?? ''),
              ], JSON_UNESCAPED_UNICODE)) ?>' title="Editar"><?= icono('editar', 16) ?></button>
            <form method="post" action="<?= e(url('configuracion/usuarios/' . (int)$u['id'] . '/restablecer')) ?>"
                  data-confirmar="¿Restablecer la contraseña de este usuario?" style="display:inline">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn--fantasma btn--sm" title="Restablecer contraseña"><?= icono('escudo', 16) ?></button>
            </form>
            <form method="post" action="<?= e(url('configuracion/usuarios/' . (int)$u['id'] . '/estado')) ?>" style="display:inline">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn--fantasma btn--sm" title="Activar o desactivar"><?= icono((int)$u['activo'] === 1 ? 'x' : 'check', 16) ?></button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($usuarios === []): ?><tr><td colspan="6" class="tabla__vacio">No se encontraron usuarios.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?= App\Core\View::partial('partials/paginacion', ['total' => $total, 'pagina' => $pagina, 'porPagina' => $porPagina]) ?>

<div class="modal" id="modal-usuario" aria-hidden="true" role="dialog" aria-label="Usuario">
  <div class="modal__fondo" data-cerrar></div>
  <div class="modal__caja">
    <form method="post" action="<?= e(url('configuracion/usuarios')) ?>">
      <?= csrf_field() ?>
      <div class="modal__cab"><h3>Usuario</h3>
        <button type="button" class="btn btn--fantasma btn--sm" data-cerrar>Cerrar</button></div>
      <div class="modal__cuerpo">
        <input type="hidden" name="id" value="">
        <div class="campo">
          <label for="us-nombre">Nombre completo <span class="oro">*</span></label>
          <input type="text" id="us-nombre" name="nombre" required maxlength="120">
        </div>
        <div class="campo">
          <label for="us-email">Correo electrónico <span class="oro">*</span></label>
          <input type="email" id="us-email" name="email" required maxlength="160">
        </div>
        <div class="fila">
          <div class="campo">
            <label for="us-rol">Rol <span class="oro">*</span></label>
            <select id="us-rol" name="rol" required>
              <?php foreach (['superadmin', 'secretaria', 'docente', 'padre'] as $r): ?>
                <option value="<?= e($r) ?>"><?= e(rol_nombre($r)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="us-tel">Teléfono</label>
            <input type="tel" id="us-tel" name="telefono" maxlength="40">
          </div>
        </div>
        <p class="ayuda">Al crear un usuario nuevo se genera una contraseña temporal y se envía por correo.</p>
      </div>
      <div class="modal__pie">
        <button type="button" class="btn btn--linea" data-cerrar>Cancelar</button>
        <button type="submit" class="btn">Guardar usuario</button>
      </div>
    </form>
  </div>
</div>
