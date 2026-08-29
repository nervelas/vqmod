<?php
/** Testimonios del sitio de venta. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Testimonios');
?>
<?php $view->start('content') ?>
<div class="grid grid-side">
  <div class="stack">
    <?php foreach ($rows as $t): ?>
      <form class="card" method="post" action="<?= e(mg_url('/super/landing/testimonios')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
        <div class="grid grid-2">
          <div class="field"><label>Nombre</label><input type="text" class="input" name="name" value="<?= e($t['name']) ?>"></div>
          <div class="field"><label>Cargo</label><input type="text" class="input" name="role" value="<?= e($t['role']) ?>"></div>
          <div class="field"><label>Restaurante</label><input type="text" class="input" name="place" value="<?= e($t['place']) ?>"></div>
          <div class="field"><label>Orden</label><input class="input" name="sort" type="number" value="<?= (int)$t['sort'] ?>"></div>
        </div>
        <div class="field"><label>Testimonio</label><textarea class="textarea" name="quote" rows="3"><?= e($t['quote']) ?></textarea></div>
        <input type="hidden" name="rating" value="<?= (int)$t['rating'] ?>">
        <div class="row">
          <label class="switch"><input type="checkbox" name="is_active" value="1" <?= (int)$t['is_active'] === 1 ? 'checked' : '' ?>><span class="switch-track" aria-hidden="true"></span><span>Visible</span></label>
          <button class="btn btn-sm" type="submit">Guardar</button>
          <button class="btn btn-ghost btn-sm" type="submit" name="action" value="delete" onclick="return confirm('¿Eliminar este testimonio?')">Eliminar</button>
        </div>
      </form>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="empty"><h3>Sin testimonios</h3><p>Agrega el primero a la derecha.</p></div><?php endif; ?>
  </div>

  <form class="card" method="post" action="<?= e(mg_url('/super/landing/testimonios')) ?>">
    <?= Csrf::field() ?>
    <div class="card-head"><h3>Nuevo testimonio</h3></div>
    <div class="field"><label for="nt-name">Nombre *</label><input type="text" class="input" id="nt-name" name="name" required></div>
    <div class="field"><label for="nt-role">Cargo</label><input type="text" class="input" id="nt-role" name="role" placeholder="Propietario"></div>
    <div class="field"><label for="nt-place">Restaurante</label><input type="text" class="input" id="nt-place" name="place"></div>
    <div class="field"><label for="nt-quote">Testimonio *</label><textarea class="textarea" id="nt-quote" name="quote" rows="3" required></textarea></div>
    <input type="hidden" name="rating" value="5">
    <label class="switch"><input type="checkbox" name="is_active" value="1" checked><span class="switch-track" aria-hidden="true"></span><span>Visible</span></label>
    <button class="btn btn-block mt-2" type="submit">Crear</button>
  </form>
</div>
<?php $view->stop() ?>
