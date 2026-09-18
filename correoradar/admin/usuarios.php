<?php
/** CorreoRadar - Gestión de usuarios. */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';
require_once __DIR__ . '/partials/layout.php';

Auth::exigirAdmin();

$yo = Auth::id();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Seguridad::exigirCsrf();
    $accion = (string) cr_post('accion');
    $id     = (int) cr_post('id', 0);

    switch ($accion) {
        case 'crear':
            $res = Auth::crear(
                (string) cr_post('usuario'),
                (string) cr_post('email'),
                (string) cr_post('clave'),
                (string) cr_post('rol') === 'admin' ? 'admin' : 'usuario',
                (string) cr_post('nombre')
            );
            cr_flash($res['ok'] ? 'exito' : 'error', $res['ok'] ? 'Usuario creado correctamente.' : ($res['error'] ?? 'Error.'));
            break;

        case 'estado':
            if ($id === $yo) {
                cr_flash('error', 'No puedes desactivar tu propia cuenta.');
                break;
            }
            $activo = (int) cr_post('activo', 0) === 1 ? 1 : 0;
            BD::actualizar('cr_usuarios', ['activo' => $activo], '`id` = ?', [$id]);
            cr_flash('exito', $activo ? 'Cuenta activada.' : 'Cuenta desactivada.');
            break;

        case 'rol':
            if ($id === $yo) {
                cr_flash('error', 'No puedes cambiar tu propio rol.');
                break;
            }
            $rol = (string) cr_post('rol') === 'admin' ? 'admin' : 'usuario';
            BD::actualizar('cr_usuarios', ['rol' => $rol], '`id` = ?', [$id]);
            cr_flash('exito', 'Rol actualizado.');
            break;

        case 'clave':
            $nueva = (string) cr_post('clave');
            if (strlen($nueva) < 8) {
                cr_flash('error', 'La contraseña debe tener al menos 8 caracteres.');
                break;
            }
            BD::actualizar('cr_usuarios', ['clave_hash' => password_hash($nueva, PASSWORD_DEFAULT)], '`id` = ?', [$id]);
            cr_flash('exito', 'Contraseña cambiada.');
            break;

        case 'borrar':
            if ($id === $yo) {
                cr_flash('error', 'No puedes borrar tu propia cuenta.');
                break;
            }
            $admins = (int) BD::valor('SELECT COUNT(*) FROM `cr_usuarios` WHERE `rol` = \'admin\' AND `activo` = 1', [], 0);
            $esAdmin = (string) BD::valor('SELECT `rol` FROM `cr_usuarios` WHERE `id` = ?', [$id], '') === 'admin';
            if ($esAdmin && $admins <= 1) {
                cr_flash('error', 'Debe quedar al menos un administrador activo.');
                break;
            }
            BD::ejecutar('UPDATE `cr_escaneos` SET `usuario_id` = NULL WHERE `usuario_id` = ?', [$id]);
            BD::ejecutar('DELETE FROM `cr_usuarios` WHERE `id` = ?', [$id]);
            cr_flash('exito', 'Usuario eliminado.');
            break;
    }
    cr_redirigir('admin/usuarios.php');
}

$usuarios = BD::todos(
    'SELECT u.*, (SELECT COUNT(*) FROM `cr_escaneos` e WHERE e.`usuario_id` = u.`id`) AS extracciones
     FROM `cr_usuarios` u ORDER BY u.`rol` = \'admin\' DESC, u.`creado` DESC'
);

admin_cabecera(['titulo' => 'Usuarios', 'activo' => 'usuarios.php']);
?>

<div class="rejilla rejilla-2" style="align-items:start">

  <!-- ===================== LISTA ===================== -->
  <div class="tarjeta" style="grid-column:1 / -1">
    <h3>Cuentas registradas (<?= cr_numero(count($usuarios)) ?>)</h3>

    <div class="tabla-scroll">
      <table class="tabla panel-tabla">
        <thead>
          <tr><th>Usuario</th><th>Correo</th><th>Rol</th><th>Extracciones</th><th>Último acceso</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
          <tr>
            <td>
              <b><?= e((string) $u['usuario']) ?></b>
              <?php if ((int) $u['id'] === $yo): ?><span class="chip chip-neon" style="margin-left:6px">tú</span><?php endif; ?>
              <?php if (!empty($u['nombre'])): ?><div class="pequeno suave"><?= e((string) $u['nombre']) ?></div><?php endif; ?>
            </td>
            <td class="celda-url"><?= e((string) $u['email']) ?></td>
            <td>
              <form method="post" style="display:inline">
                <?= Seguridad::campoCsrf() ?>
                <input type="hidden" name="accion" value="rol">
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <select name="rol" class="campo" style="padding:6px 10px;font-size:.82rem;width:auto"
                        onchange="this.form.submit()" <?= (int) $u['id'] === $yo ? 'disabled' : '' ?>>
                  <option value="usuario" <?= $u['rol'] === 'usuario' ? 'selected' : '' ?>>Usuario</option>
                  <option value="admin" <?= $u['rol'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                </select>
              </form>
            </td>
            <td><?= cr_numero((int) $u['extracciones']) ?></td>
            <td class="pequeno suave"><?= e(cr_fecha($u['ultimo_acceso'])) ?></td>
            <td>
              <span class="chip <?= (int) $u['activo'] === 1 ? 'chip-neon' : 'chip-rojo' ?>">
                <?= (int) $u['activo'] === 1 ? 'Activa' : 'Inactiva' ?>
              </span>
            </td>
            <td>
              <div class="acciones-fila">
                <?php if ((int) $u['id'] !== $yo): ?>
                  <form method="post">
                    <?= Seguridad::campoCsrf() ?>
                    <input type="hidden" name="accion" value="estado">
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <input type="hidden" name="activo" value="<?= (int) $u['activo'] === 1 ? 0 : 1 ?>">
                    <button class="btn btn-fantasma btn-peq"><?= (int) $u['activo'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                  </form>
                  <form method="post">
                    <?= Seguridad::campoCsrf() ?>
                    <input type="hidden" name="accion" value="borrar">
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <button class="btn btn-fantasma btn-peq" style="color:var(--error)"
                            data-confirmar="¿Seguro que quieres borrar al usuario <?= e((string) $u['usuario']) ?>?">Borrar</button>
                  </form>
                <?php endif; ?>
                <details>
                  <summary class="btn btn-fantasma btn-peq" style="list-style:none">Clave</summary>
                  <form method="post" style="margin-top:8px;display:flex;gap:6px">
                    <?= Seguridad::campoCsrf() ?>
                    <input type="hidden" name="accion" value="clave">
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <input type="password" name="clave" class="campo" style="width:150px;padding:7px 10px" placeholder="Nueva clave" minlength="8" required>
                    <button class="btn btn-peq">Cambiar</button>
                  </form>
                </details>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ===================== ALTA ===================== -->
  <div class="tarjeta">
    <h3>Crear una cuenta</h3>
    <form method="post">
      <?= Seguridad::campoCsrf() ?>
      <input type="hidden" name="accion" value="crear">

      <div class="campo-grupo">
        <label class="etiqueta" for="n-usuario">Usuario</label>
        <input type="text" id="n-usuario" name="usuario" class="campo" required minlength="3" maxlength="64" pattern="[a-zA-Z0-9._\-]+">
      </div>
      <div class="campo-grupo">
        <label class="etiqueta" for="n-nombre">Nombre (opcional)</label>
        <input type="text" id="n-nombre" name="nombre" class="campo" maxlength="120">
      </div>
      <div class="campo-grupo">
        <label class="etiqueta" for="n-email">Correo electrónico</label>
        <input type="email" id="n-email" name="email" class="campo" required>
      </div>
      <div class="campo-grupo">
        <label class="etiqueta" for="n-clave">Contraseña (mínimo 8 caracteres)</label>
        <input type="password" id="n-clave" name="clave" class="campo" required minlength="8">
      </div>
      <div class="campo-grupo">
        <label class="etiqueta" for="n-rol">Rol</label>
        <select id="n-rol" name="rol" class="campo">
          <option value="usuario">Usuario</option>
          <option value="admin">Administrador</option>
        </select>
      </div>
      <button type="submit" class="btn btn-bloque">Crear usuario</button>
    </form>
  </div>

  <div class="tarjeta">
    <h3>Cómo funcionan los permisos</h3>
    <div class="estado-linea">
      <span><b>Administrador</b><em>Entra al panel, cambia los ajustes, gestiona usuarios y ve todo el historial.</em></span>
    </div>
    <div class="estado-linea">
      <span><b>Usuario</b><em>Extrae correos y consulta su propio historial en «Mis extracciones».</em></span>
    </div>
    <div class="estado-linea">
      <span><b>Visitante sin cuenta</b><em>Puede extraer solo si el acceso libre está activado en Ajustes → Acceso y límites.</em></span>
    </div>
    <p class="pequeno suave" style="margin-top:14px">
      Las contraseñas se guardan cifradas con <span class="mono">password_hash()</span>; ni siquiera el administrador puede verlas.
    </p>
  </div>
</div>

<?php admin_pie(); ?>
