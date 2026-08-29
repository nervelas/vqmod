<?php
/** Planes mostrados en el sitio de venta. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Planes del sitio');
?>
<?php $view->start('content') ?>
<div class="grid grid-side">
  <div class="stack">
    <?php foreach ($rows as $p): ?>
      <form class="card" method="post" action="<?= e(mg_url('/super/landing/planes')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <div class="card-head">
          <h3><?= e($p['name']) ?></h3>
          <span class="chip <?= (int)$p['is_featured'] === 1 ? '' : 'chip-dim' ?>"><?= (int)$p['is_featured'] === 1 ? 'Destacado' : 'Normal' ?></span>
        </div>
        <div class="grid grid-2">
          <div class="field"><label>Nombre</label><input type="text" class="input" name="name" value="<?= e($p['name']) ?>"></div>
          <div class="field"><label>Precio</label><input type="text" class="input" name="price" value="<?= e($p['price']) ?>" placeholder="Q349"></div>
          <div class="field"><label>Periodo</label><input type="text" class="input" name="period" value="<?= e($p['period']) ?>"></div>
          <div class="field"><label>Orden</label><input class="input" name="sort" type="number" value="<?= (int)$p['sort'] ?>"></div>
        </div>
        <div class="field"><label>Frase</label><input type="text" class="input" name="pitch" value="<?= e($p['pitch']) ?>"></div>
        <div class="field"><label>Características (una por línea)</label><textarea class="textarea" name="features" rows="5"><?= e($p['features']) ?></textarea></div>
        <div class="grid grid-2">
          <div class="field"><label>Texto del botón</label><input type="text" class="input" name="cta_text" value="<?= e($p['cta_text']) ?>"></div>
          <div class="field"><label>Mensaje de WhatsApp</label><input type="text" class="input" name="wa_message" value="<?= e($p['wa_message']) ?>"></div>
        </div>
        <div class="row">
          <label class="switch"><input type="checkbox" name="is_featured" value="1" <?= (int)$p['is_featured'] === 1 ? 'checked' : '' ?>><span class="switch-track" aria-hidden="true"></span><span>Destacado</span></label>
          <label class="switch"><input type="checkbox" name="is_active" value="1" <?= (int)$p['is_active'] === 1 ? 'checked' : '' ?>><span class="switch-track" aria-hidden="true"></span><span>Visible</span></label>
        </div>
        <div class="row mt-2">
          <button class="btn btn-sm" type="submit">Guardar</button>
          <button class="btn btn-ghost btn-sm" type="submit" name="action" value="delete"
                  onclick="return confirm('¿Eliminar este plan del sitio?')">Eliminar</button>
        </div>
      </form>
    <?php endforeach; ?>
  </div>

  <form class="card" method="post" action="<?= e(mg_url('/super/landing/planes')) ?>">
    <?= Csrf::field() ?>
    <div class="card-head"><h3>Nuevo plan</h3></div>
    <div class="field"><label for="np-name">Nombre *</label><input type="text" class="input" id="np-name" name="name" required></div>
    <div class="field"><label for="np-price">Precio</label><input type="text" class="input" id="np-price" name="price" placeholder="Q349"></div>
    <div class="field"><label for="np-period">Periodo</label><input type="text" class="input" id="np-period" name="period" value="al mes"></div>
    <div class="field"><label for="np-pitch">Frase</label><input type="text" class="input" id="np-pitch" name="pitch"></div>
    <div class="field"><label for="np-features">Características</label><textarea class="textarea" id="np-features" name="features" rows="5"></textarea></div>
    <div class="field"><label for="np-cta">Botón</label><input type="text" class="input" id="np-cta" name="cta_text" value="Quiero este plan"></div>
    <div class="row">
      <label class="switch"><input type="checkbox" name="is_featured" value="1"><span class="switch-track" aria-hidden="true"></span><span>Destacado</span></label>
      <label class="switch"><input type="checkbox" name="is_active" value="1" checked><span class="switch-track" aria-hidden="true"></span><span>Visible</span></label>
    </div>
    <button class="btn btn-block mt-2" type="submit">Crear</button>
  </form>
</div>
<?php $view->stop() ?>
