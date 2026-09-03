<?php $roles = ['admin' => 'Administrador', 'vendedor' => 'Vendedor', 'visor' => 'Visor (solo reportes)']; ?>
<div class="cols cols--sidebar">
  <form class="card" method="post" action="<?= e(url('/panel/usuarios/' . $row['id'])) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="card__head"><span class="secnum">01/</span><h2>Datos del usuario</h2>
      <button class="btn btn--accent btn--sm ml-auto" type="submit">Guardar</button></div>
    <div class="card__body">
      <div class="row-2">
        <div class="field"><label for="name">Nombre *</label><input class="input" id="name" name="name" maxlength="120" required value="<?= e($row['name']) ?>"></div>
        <div class="field"><label for="email">Correo *</label><input class="input" id="email" name="email" type="email" maxlength="150" required value="<?= e($row['email']) ?>"></div>
      </div>
      <div class="row-2">
        <div class="field"><label for="username">Usuario</label><input class="input" id="username" name="username" maxlength="60" value="<?= e($row['username']) ?>"></div>
        <div class="field"><label for="role">Rol</label>
          <select class="select" id="role" name="role">
            <?php foreach ($roles as $k => $lbl): ?><option value="<?= e($k) ?>"<?= $row['role'] === $k ? ' selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
          </select></div>
      </div>
      <div class="row-3">
        <div class="field"><label for="position">Puesto</label><input class="input" id="position" name="position" maxlength="90" value="<?= e($row['position']) ?>"></div>
        <div class="field"><label for="phone">Teléfono</label><input class="input" id="phone" name="phone" maxlength="40" value="<?= e($row['phone']) ?>"></div>
        <div class="field"><label for="whatsapp">WhatsApp</label><input class="input" id="whatsapp" name="whatsapp" maxlength="30" value="<?= e($row['whatsapp']) ?>"></div>
      </div>
      <div class="field"><label for="password">Nueva contraseña (dejar vacío para no cambiar)</label>
        <input class="input" id="password" name="password" type="password" minlength="8" autocomplete="new-password"></div>
      <div class="field"><label for="avatar">Foto</label><input class="input" id="avatar" name="avatar" type="file" accept="image/*"></div>
      <label class="check"><input type="checkbox" name="active" value="1"<?= $row['status'] === 'activo' ? ' checked' : '' ?>><span>Usuario activo</span></label>
      <label class="check"><input type="checkbox" name="receives_leads" value="1"<?= $row['receives_leads'] ? ' checked' : '' ?>><span>Recibe solicitudes entrantes</span></label>
      <label class="check"><input type="checkbox" name="twofa" value="1"<?= $row['twofa_enabled'] ? ' checked' : '' ?>><span>Verificación en dos pasos por correo</span></label>
    </div>
  </form>
  <div class="card">
    <div class="card__head"><h2>Actividad</h2></div>
    <div class="card__body">
      <table class="spectable" style="border-top:0"><tbody>
        <tr><th scope="row">Último acceso</th><td><?= $row['last_login_at'] ? e(fechaHora((string) $row['last_login_at'])) : 'nunca' ?></td></tr>
        <tr><th scope="row">Desde IP</th><td><?= e($row['last_login_ip'] ?: '—') ?></td></tr>
        <tr><th scope="row">Creado</th><td><?= e(fechaCorta((string) $row['created_at'])) ?></td></tr>
      </tbody></table>
      <a class="btn btn--ghost btn--block" style="margin-top:16px" href="<?= e(url('/panel/usuarios')) ?>">Volver al equipo</a>
    </div>
  </div>
</div>
