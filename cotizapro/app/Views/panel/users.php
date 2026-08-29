<?php
$lim = (int) ($limits['users'] ?? 0);
$use = (int) ($usage['users'] ?? 0);
$roles = ['admin' => 'Administrador', 'vendedor' => 'Vendedor', 'visor' => 'Visor (solo reportes)'];
?>
<div class="cols cols--sidebar">
  <div class="card">
    <div class="card__head"><span class="secnum">01/</span><h2>Equipo</h2>
      <span class="badge ml-auto"><?= e($use) ?><?= $lim > 0 ? ' / ' . e($lim) : '' ?> usuarios</span></div>
    <div class="card__body card__body--flush tablescroll">
      <table class="datatable" style="border:0;border-radius:0">
        <caption class="sr-only">Usuarios de la empresa</caption>
        <thead><tr><th scope="col">Usuario</th><th scope="col">Rol</th><th scope="col">Recibe solicitudes</th><th scope="col">Último acceso</th><th scope="col">Estado</th><th scope="col"></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><strong><?= e($r['name']) ?></strong><br><span class="small muted"><?= e($r['email']) ?><?= $r['username'] ? ' · ' . e($r['username']) : '' ?></span></td>
              <td><span class="badge<?= $r['role'] === 'admin' ? ' badge--dark' : '' ?>"><?= e($roles[$r['role']] ?? $r['role']) ?></span></td>
              <td class="small"><?= $r['receives_leads'] ? 'Sí' : 'No' ?></td>
              <td class="small muted"><?= $r['last_login_at'] ? e(fechaCorta((string) $r['last_login_at'])) : 'nunca' ?></td>
              <td><span class="badge<?= $r['status'] === 'activo' ? ' badge--ok' : '' ?>"><?= e(ucfirst((string) $r['status'])) ?></span></td>
              <td class="nowrap">
                <a class="btn btn--ghost btn--xs" href="<?= e(url('/panel/usuarios/' . $r['id'])) ?>">Editar</a>
                <?php if ((int) $r['id'] !== (int) $auth['id']): ?>
                  <button class="btn btn--ghost btn--xs" type="submit" form="delu<?= e($r['id']) ?>">Eliminar</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card__body" style="border-top:1px solid var(--line)">
      <p class="small muted" style="margin:0"><strong>Administrador:</strong> ve y edita todo. <strong>Vendedor:</strong> solo sus cotizaciones y clientes.
      <strong>Visor:</strong> únicamente reportes y consultas. Los permisos se verifican en el servidor en cada petición.</p>
    </div>
  </div>

  <form class="card" method="post" action="<?= e(url('/panel/usuarios')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="card__head"><span class="secnum">02/</span><h2>Nuevo usuario</h2></div>
    <div class="card__body">
      <?php if ($lim > 0 && $use >= $lim): ?>
        <div class="alert alert--warn"><span aria-hidden="true">△</span><span>Alcanzó el límite de <?= e($lim) ?> usuarios de su plan.</span></div>
      <?php endif; ?>
      <div class="field"><label for="name">Nombre *</label><input class="input" id="name" name="name" maxlength="120" required></div>
      <div class="field"><label for="email">Correo *</label><input class="input" id="email" name="email" type="email" maxlength="150" required></div>
      <div class="field"><label for="username">Usuario (opcional)</label><input class="input" id="username" name="username" maxlength="60" placeholder="vendedor1"></div>
      <div class="field"><label for="password">Contraseña *</label><input class="input" id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
        <p class="hint">Mínimo 8 caracteres con mayúsculas, minúsculas y números.</p></div>
      <div class="field"><label for="role">Rol</label>
        <select class="select" id="role" name="role">
          <?php foreach ($roles as $k => $lbl): ?><option value="<?= e($k) ?>"<?= $k === 'vendedor' ? ' selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
        </select></div>
      <div class="row-2">
        <div class="field"><label for="position">Puesto</label><input class="input" id="position" name="position" maxlength="90" placeholder="Asesor técnico"></div>
        <div class="field"><label for="phone">Teléfono</label><input class="input" id="phone" name="phone" maxlength="40"></div>
      </div>
      <div class="field"><label for="whatsapp">WhatsApp (solo números)</label><input class="input" id="whatsapp" name="whatsapp" maxlength="30" placeholder="50255551234"></div>
      <div class="field"><label for="avatar">Foto</label><input class="input" id="avatar" name="avatar" type="file" accept="image/*"></div>
      <label class="check"><input type="checkbox" name="active" value="1" checked><span>Usuario activo</span></label>
      <label class="check"><input type="checkbox" name="receives_leads" value="1" checked><span>Recibe solicitudes entrantes (asignación rotativa)</span></label>
      <label class="check"><input type="checkbox" name="twofa" value="1"><span>Verificación en dos pasos por correo</span></label>
      <button class="btn btn--accent btn--block" type="submit">Crear usuario</button>
    </div>
  </form>
</div>
<?php foreach ($rows as $r): if ((int) $r['id'] === (int) $auth['id']) { continue; } ?>
  <form id="delu<?= e($r['id']) ?>" method="post" action="<?= e(url('/panel/usuarios/' . $r['id'] . '/eliminar')) ?>" class="hide" data-confirm="¿Eliminar a <?= e($r['name']) ?>?"><?= csrf_field() ?></form>
<?php endforeach; ?>
