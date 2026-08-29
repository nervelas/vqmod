<?php
/** Usuarios del restaurante. */
use MenuGold\Core\Auth;
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Usuarios');
$roles = array('owner' => 'Dueño', 'manager' => 'Encargado', 'kitchen' => 'Cocina', 'waiter' => 'Mesero');
?>
<?php $view->start('content') ?>
<div class="grid grid-side">
  <div class="card">
    <div class="card-head"><h2>Equipo</h2><p>Cocina y meseros pueden entrar con PIN desde la tablet del salón.</p></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Último acceso</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><span class="cell-title"><?= e($u['name']) ?></span>
                <?php if ((int)$u['is_active'] === 0): ?><span class="chip chip-dim">Inactivo</span><?php endif; ?></td>
              <td class="muted"><?= e($u['username']) ?></td>
              <td><span class="chip chip-dim"><?= e(isset($roles[$u['role']]) ? $roles[$u['role']] : $u['role']) ?></span></td>
              <td class="muted" style="font-size:12px"><?= e($u['last_login_at'] ? mg_ago($u['last_login_at']) : 'nunca') ?></td>
              <td class="num">
                <?php if ((int)$u['id'] !== Auth::id()): ?>
                  <form method="post" action="<?= e(mg_url('/panel/usuarios/' . (int)$u['id'] . '/eliminar')) ?>"
                        data-confirm="¿Eliminar a <?= e($u['name']) ?>?">
                    <?= Csrf::field() ?><button class="cart-remove" type="submit">Eliminar</button>
                  </form>
                <?php else: ?><span class="faint" style="font-size:11px">tú</span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <form class="card" method="post" action="<?= e(mg_url('/panel/usuarios')) ?>">
    <?= Csrf::field() ?>
    <div class="card-head"><h3>Nuevo usuario</h3></div>
    <div class="field"><label for="name">Nombre *</label><input type="text" class="input" id="name" name="name" required maxlength="120"></div>
    <div class="field"><label for="username">Usuario *</label><input type="text" class="input" id="username" name="username" required maxlength="80" autocomplete="off"></div>
    <div class="field"><label for="email">Correo</label><input class="input" id="email" name="email" type="email" maxlength="160"></div>
    <div class="field"><label for="role">Rol</label>
      <select class="select" id="role" name="role">
        <?php foreach ($roles as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
      </select></div>
    <div class="field"><label for="password">Contraseña *</label>
      <input class="input" id="password" name="password" type="password" minlength="8" autocomplete="new-password">
      <p class="field-hint">Mínimo 8 caracteres.</p></div>
    <div class="field"><label for="pin">PIN (cocina y meseros)</label>
      <input type="text" class="input" id="pin" name="pin" inputmode="numeric" maxlength="8" autocomplete="off">
      <p class="field-hint">4 a 8 dígitos, para entrar rápido en la tablet.</p></div>
    <label class="switch"><input type="checkbox" name="is_active" value="1" checked><span class="switch-track" aria-hidden="true"></span><span>Activo</span></label>
    <button class="btn btn-block mt-2" type="submit">Crear usuario</button>
  </form>
</div>
<?php $view->stop() ?>
