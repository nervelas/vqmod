<?php
/** Alta y edición de una persona del equipo. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$nuevo = empty($u);
$view->set('title', $nuevo ? 'Nuevo usuario' : $u['name']);
$roles = array('owner' => 'Dueño · todo el panel', 'manager' => 'Gerente · sin ajustes ni usuarios',
               'kitchen' => 'Cocina · solo la pantalla de cocina', 'waiter' => 'Mesero · salón y cobro');
?>
<?php $view->start('content') ?>
<?php $view->partial('admin/settings/_tabs'); ?>

<form method="post" action="<?= e(mg_url('/panel/usuario/' . ($nuevo ? 'nuevo' : (int)$u['id']))) ?>" autocomplete="off">
  <?= Csrf::field() ?>
  <div class="grid grid-2">
    <div class="card">
      <div class="card-head"><h2>Datos</h2></div>
      <div class="field"><label for="name">Nombre *</label>
        <input class="input" id="name" name="name" type="text" required maxlength="120" value="<?= e($nuevo ? '' : $u['name']) ?>"></div>
      <div class="field"><label for="username">Usuario para entrar *</label>
        <input class="input" id="username" name="username" type="text" required maxlength="120" value="<?= e($nuevo ? '' : $u['username']) ?>">
        <p class="field-hint">Letras, números, punto, guion y arroba.</p></div>
      <div class="field"><label for="email">Correo</label>
        <input class="input" id="email" name="email" type="email" maxlength="190" value="<?= e($nuevo ? '' : $u['email']) ?>"></div>
      <label class="switch"><input type="checkbox" name="is_active" value="1" <?= $nuevo || (int)$u['is_active'] === 1 ? 'checked' : '' ?>>
        <span class="switch-track" aria-hidden="true"></span><span>Puede entrar al panel</span></label>
    </div>

    <div class="card">
      <div class="card-head"><h2>Permisos y acceso</h2></div>
      <div class="field"><label for="role">Rol</label>
        <select class="select" id="role" name="role">
          <?php foreach ($roles as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= !$nuevo && $u['role'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="field"><label for="password">Contraseña <?= $nuevo ? '*' : '' ?></label>
        <input class="input" id="password" name="password" type="password" <?= $nuevo ? 'required' : '' ?> minlength="8" autocomplete="new-password">
        <p class="field-hint"><?= $nuevo ? 'Mínimo 8 caracteres.' : 'Déjala vacía para no cambiarla.' ?></p></div>
      <div class="field"><label for="pin">PIN para la tablet del salón</label>
        <input class="input" id="pin" name="pin" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="8" autocomplete="off">
        <p class="field-hint">4 a 8 dígitos. Sirve para entrar rápido en <code>/panel/pin</code>.</p></div>
    </div>
  </div>

  <div class="row mt-3">
    <button class="btn" type="submit">Guardar</button>
    <a class="btn btn-ghost" href="<?= e(mg_url('/panel/usuarios')) ?>">Cancelar</a>
  </div>
</form>
<?php $view->stop() ?>
