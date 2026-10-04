<?php
/** @var array $users @var ?array $invite @var array $roles */
?>
<div class="page p2-page">
  <header class="page-head">
    <div>
      <h1 class="page-title">Usuarios y roles</h1>
      <p class="page-sub">Quién entra al panel y qué puede hacer. Invita por enlace: la persona crea su propia contraseña.</p>
    </div>
    <div class="page-actions"><button class="btn btn-gold" type="button" data-p2-open="#invite-modal"><?= icon('plus') ?> Invitar persona</button></div>
  </header>

  <?php if (!empty($invite)) : ?>
    <section class="card card-gold p2-linkcard" aria-label="Enlace de invitación">
      <div class="card-body stack">
        <div>
          <h2 class="p2-h3">Invitación lista para <?= e($invite['name']) ?></h2>
          <p class="muted"><?= $invite['emailed'] ? 'También se lo enviamos a ' . e($invite['email']) . '. ' : 'No se envió por correo; compártelo tú. ' ?>El enlace vence en <?= (int) $invite['days'] ?> días y solo se muestra ahora.</p>
        </div>
        <div class="input-group">
          <input class="input mono" type="text" readonly id="invite-link" value="<?= e($invite['link']) ?>" aria-label="Enlace de invitación">
          <button class="btn btn-gold" type="button" data-copy="#invite-link"><?= icon('copy') ?> Copiar enlace</button>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <div class="card">
    <div class="table-wrap">
      <table class="table p2-users">
        <caption class="sr-only">Usuarios del panel</caption>
        <thead><tr><th scope="col">Persona</th><th scope="col">Rol</th><th scope="col">Estado</th><th scope="col" class="hide-sm">Último acceso</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u) : ?>
            <tr>
              <td>
                <strong><?= e($u['name']) ?></strong><?= $u['is_me'] ? ' <span class="badge badge-gold">Tú</span>' : '' ?><br>
                <span class="muted"><?= e($u['email']) ?></span><?php if (!empty($u['host_name'])) : ?><br><small class="muted">Anfitrión: <?= e($u['host_name']) ?></small><?php endif; ?>
              </td>
              <td>
                <form method="post" action="<?= e(url('/admin/usuarios/' . (int) $u['id'] . '/rol')) ?>" class="p2-rolefm">
                  <?= csrf_field() ?>
                  <label class="sr-only" for="role-<?= (int) $u['id'] ?>">Rol de <?= e($u['name']) ?></label>
                  <select class="select" id="role-<?= (int) $u['id'] ?>" name="role">
                    <?php foreach ($roles as $k => $label) : ?><option value="<?= e($k) ?>"<?= sel($u['role'], $k) ?>><?= e($label) ?></option><?php endforeach; ?>
                  </select>
                  <button class="btn btn-outline btn-sm" type="submit">Cambiar</button>
                </form>
              </td>
              <td>
                <?php if ((int) $u['pending'] === 1) : ?>
                  <?php if ($u['expired']) : ?><span class="badge badge-err">Invitación vencida</span><?php else : ?><span class="badge badge-warn">Invitación pendiente</span><?php endif; ?>
                <?php elseif ((int) $u['active'] === 1) : ?><span class="badge badge-ok">Activo</span>
                <?php else : ?><span class="badge badge-muted">Desactivado</span><?php endif; ?>
              </td>
              <td class="hide-sm muted"><?= e($u['last_login'] ?: 'Aún no entra') ?></td>
              <td class="right nowrap">
                <?php if (!$u['is_me']) : ?>
                  <form method="post" action="<?= e(url('/admin/usuarios/' . (int) $u['id'] . '/enlace')) ?>" class="inline" data-confirm="¿Generar un enlace nuevo para <?= e($u['name']) ?>? El anterior dejará de servir.">
                    <?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('refresh') ?> <span class="hide-sm">Restablecer enlace</span></button>
                  </form>
                  <?php if ((int) $u['pending'] !== 1) : ?>
                    <form method="post" action="<?= e(url('/admin/usuarios/' . (int) $u['id'] . '/activo')) ?>" class="inline"<?= (int) $u['active'] === 1 ? ' data-confirm="¿Desactivar a ' . e($u['name']) . '? Ya no podrá entrar al panel."' : '' ?>>
                      <?= csrf_field() ?><button class="btn btn-ghost btn-sm<?= (int) $u['active'] === 1 ? ' text-err' : '' ?>" type="submit"><?= (int) $u['active'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <dialog class="modal" id="invite-modal" aria-labelledby="invite-title">
    <form method="post" action="<?= e(url('/admin/usuarios/invitar')) ?>" class="stack">
      <?= csrf_field() ?>
      <div class="modal-head"><h2 class="p2-h3" id="invite-title">Invitar a una persona</h2></div>
      <div class="modal-body stack">
        <div class="field"><label for="inv-name">Nombre</label><input class="input" type="text" id="inv-name" name="name" maxlength="120" required></div>
        <div class="field"><label for="inv-email">Correo</label><input class="input" type="email" id="inv-email" name="email" maxlength="190" required></div>
        <div class="field">
          <label for="inv-role">Rol</label>
          <select class="select" id="inv-role" name="role">
            <?php foreach ($roles as $k => $label) : ?><option value="<?= e($k) ?>"<?= sel('host', $k) ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
          <p class="hint">Administración lo ve todo. Anfitrión solo su agenda. Recepción gestiona citas, clientes y cobros.</p>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-ghost" type="button" data-modal-close data-p2-close>Cancelar</button>
        <button class="btn btn-gold" type="submit">Crear invitación</button>
      </div>
    </form>
  </dialog>
</div>
