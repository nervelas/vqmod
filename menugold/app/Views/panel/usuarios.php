<?php
/** @var array $usuarios, $roles, $limites */
use MenuGold\Core\Auth;
use MenuGold\Core\Security;
use MenuGold\Core\View;
View::set('titulo', 'Usuarios');
View::set('subtitulo', count($usuarios) . ' usuario(s)'
    . ((int)$limites['max_usuarios'] > 0 ? ' · tu plan permite ' . (int)$limites['max_usuarios'] : ''));

View::start('acciones');
?>
<button class="bt bt--oro" type="button" data-modal="modalUsuario" data-limpiar="1" data-titulo="Nuevo usuario">
  <?= icon('plus') ?><span>Nuevo</span>
</button>
<?php View::stop(); ?>

<div class="tarjeta-p tarjeta-p--plana">
  <div class="tabla-caja">
    <table class="tabla">
      <thead><tr><th>Usuario</th><th>Rol</th><th>Acceso</th><th>Último ingreso</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($usuarios as $u): ?>
          <tr>
            <td>
              <div class="celda-producto">
                <span class="mini-foto" style="display:grid;place-items:center;background:var(--p-oro-suave);color:var(--p-oro);font-weight:700">
                  <?= e(mb_strtoupper(mb_substr((string)$u['nombre'], 0, 1))) ?>
                </span>
                <div>
                  <strong><?= e((string)$u['nombre']) ?><?= (int)$u['id'] === Auth::id() ? ' (tú)' : '' ?></strong>
                  <small><?= e((string)($u['telefono'] ?: '')) ?></small>
                </div>
              </div>
            </td>
            <td><span class="insignia insignia--<?= $u['rol'] === 'dueno' ? 'oro' : 'info' ?>"><?= e($roles[$u['rol']] ?? $u['rol']) ?></span></td>
            <td style="font-size:13px;color:var(--p-suave)">
              <?= e((string)($u['email'] ?: '')) ?><br>
              <span class="mono" style="color:var(--p-tenue)"><?= e((string)$u['usuario']) ?></span>
            </td>
            <td style="color:var(--p-tenue);font-size:13px"><?= e($u['ultimo_acceso'] ? dt((string)$u['ultimo_acceso']) : 'Nunca') ?></td>
            <td><span class="insignia insignia--<?= (int)$u['activo'] === 1 ? 'exito' : 'peligro' ?>">
                <?= (int)$u['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
            <td class="tabla__acciones">
              <button class="bt bt--sm bt--suave" type="button" data-modal="modalUsuario" data-titulo="Editar usuario"
                      data-rellenar='<?= e(json_encode([
                          'id' => (int)$u['id'], 'nombre' => $u['nombre'], 'email' => $u['email'],
                          'usuario' => $u['usuario'], 'rol' => $u['rol'], 'telefono' => $u['telefono'],
                          'activo' => (int)$u['activo'],
                      ], JSON_UNESCAPED_UNICODE)) ?>'><?= icon('edit', 'ico-sm') ?></button>
              <?php if ((int)$u['id'] !== Auth::id()): ?>
                <button class="bt bt--sm bt--suave" type="button" data-borrar-usuario="<?= (int)$u['id'] ?>"
                        data-nombre="<?= e((string)$u['nombre']) ?>"><?= icon('trash', 'ico-sm') ?></button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="tarjeta-p">
  <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('shield') ?> Qué puede hacer cada rol</h2></div>
  <div class="rejilla rejilla--4">
    <?php foreach ([
      'Dueño' => 'Todo: menú, mesas, cobros, reportes, usuarios, configuración y respaldos.',
      'Administrador' => 'Todo menos los respaldos de la base de datos.',
      'Cocina' => 'Solo la pantalla de comandas: ver y avanzar pedidos.',
      'Mesero / Caja' => 'Mesas, cobros, pedidos y clientes. No cambia precios ni configuración.',
    ] as $rol => $desc): ?>
      <div style="padding:12px;border:1px solid var(--p-borde);border-radius:11px">
        <strong style="display:block;margin-bottom:5px"><?= e($rol) ?></strong>
        <small style="color:var(--p-tenue);line-height:1.5"><?= e($desc) ?></small>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="modal-p" id="modalUsuario" role="dialog" aria-modal="true">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja" style="width:min(480px,calc(100vw - 28px))">
    <form data-ajax action="<?= e(url('panel/usuarios/guardar')) ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="" data-limpiable>
      <div class="modal-p__cab">
        <h2 class="modal-p__titulo">Nuevo usuario</h2>
        <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
      </div>
      <div class="modal-p__cuerpo">
        <div class="campo-p"><label for="uNombre">Nombre completo *</label>
          <input type="text" id="uNombre" name="nombre" required maxlength="120"></div>
        <div class="fila-campos">
          <div class="campo-p"><label for="uEmail">Correo</label>
            <input type="email" id="uEmail" name="email" maxlength="190" autocomplete="off"></div>
          <div class="campo-p"><label for="uUsuario">Usuario</label>
            <input type="text" id="uUsuario" name="usuario" maxlength="60" autocomplete="off" placeholder="cocina1">
            <p class="ayuda-p">Para cocina y meseros basta el usuario.</p></div>
        </div>
        <div class="fila-campos">
          <div class="campo-p"><label for="uRol">Rol *</label>
            <select id="uRol" name="rol">
              <?php foreach ($roles as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
            </select></div>
          <div class="campo-p"><label for="uTel">Teléfono</label>
            <input type="tel" id="uTel" name="telefono" maxlength="30"></div>
        </div>
        <div class="campo-p"><label for="uClave">Contraseña</label>
          <input type="password" id="uClave" name="password" maxlength="200" autocomplete="new-password">
          <p class="ayuda-p">Al editar, déjala vacía para no cambiarla. Mínimo 8 caracteres con letras y números.</p></div>
        <label class="interruptor"><input type="checkbox" name="activo" value="1" checked>
          <span class="interruptor__pista"></span><span class="interruptor__texto">Usuario activo</span></label>
      </div>
      <div class="modal-p__pie">
        <button class="bt bt--linea" type="button" data-cerrar-modal>Cancelar</button>
        <button class="bt bt--oro" type="submit"><?= icon('save') ?> Guardar</button>
      </div>
    </form>
  </div>
</div>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
document.addEventListener('click', function (ev) {
  var b = ev.target.closest('[data-borrar-usuario]');
  if (!b) return;
  var M = window.MGPanel;
  M.confirmar('Se eliminará el usuario "' + b.dataset.nombre + '" y perderá el acceso.', 'Eliminar usuario', 'Sí, eliminar')
    .then(function (ok) {
      if (!ok) return;
      M.pedir('panel/usuarios/borrar', { id: Number(b.dataset.borrarUsuario) }).then(function (r) {
        if (r.ok) { b.closest('tr').remove(); M.avisar(r.mensaje, 'ok'); }
        else M.avisar(r.error, 'error');
      });
    });
});
</script>
<?php View::stop(); ?>
