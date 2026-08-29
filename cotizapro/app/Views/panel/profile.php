<div class="cols cols--sidebar">
  <form class="card" method="post" action="<?= e(url('/panel/perfil')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="card__head"><span class="secnum">01/</span><h2>Mi perfil</h2>
      <button class="btn btn--accent btn--sm ml-auto" type="submit">Guardar</button></div>
    <div class="card__body">
      <div class="row-2">
        <div class="field"><label for="name">Nombre</label><input class="input" id="name" name="name" maxlength="120" value="<?= e($row['name']) ?>"></div>
        <div class="field"><label for="position">Puesto (sale en el PDF)</label><input class="input" id="position" name="position" maxlength="90" value="<?= e($row['position']) ?>"></div>
      </div>
      <div class="row-2">
        <div class="field"><label for="phone">Teléfono</label><input class="input" id="phone" name="phone" maxlength="40" value="<?= e($row['phone']) ?>"></div>
        <div class="field"><label for="whatsapp">WhatsApp (solo números)</label><input class="input" id="whatsapp" name="whatsapp" maxlength="30" value="<?= e($row['whatsapp']) ?>"></div>
      </div>
      <div class="field"><label for="avatar">Foto</label><input class="input" id="avatar" name="avatar" type="file" accept="image/*"></div>
      <label class="check"><input type="checkbox" name="twofa" value="1"<?= $row['twofa_enabled'] ? ' checked' : '' ?>><span>Verificación en dos pasos por correo</span></label>
      <hr style="margin:18px 0">
      <div class="row-2">
        <div class="field"><label for="current">Contraseña actual</label><input class="input" id="current" name="current" type="password" autocomplete="current-password"></div>
        <div class="field"><label for="password">Nueva contraseña</label><input class="input" id="password" name="password" type="password" minlength="8" autocomplete="new-password"></div>
      </div>
      <p class="hint">Deje ambas vacías si no desea cambiar la contraseña.</p>
    </div>
  </form>
  <div class="card">
    <div class="card__head"><h2>Sesión</h2></div>
    <div class="card__body">
      <table class="spectable" style="border-top:0"><tbody>
        <tr><th scope="row">Correo</th><td><?= e($row['email']) ?></td></tr>
        <tr><th scope="row">Rol</th><td><?= e(ucfirst((string) $row['role'])) ?></td></tr>
        <tr><th scope="row">Empresa</th><td><?= e($company['name']) ?></td></tr>
        <tr><th scope="row">Último acceso</th><td><?= $row['last_login_at'] ? e(fechaHora((string) $row['last_login_at'])) : '—' ?></td></tr>
      </tbody></table>
      <a class="btn btn--ghost btn--block" style="margin-top:16px" href="<?= e(url('/salir')) ?>">Cerrar sesión</a>
    </div>
  </div>
</div>
