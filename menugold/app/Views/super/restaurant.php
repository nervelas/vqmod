<?php
/** Alta y edición de restaurante. */
use MenuGold\Core\Csrf;
use MenuGold\Core\Url;
$view->extend('layouts/panel');
$isNew = !$row;
$r = $row;
$view->set('title', $isNew ? 'Nuevo restaurante' : $r['name']);
?>
<?php $view->start('content') ?>
<form method="post" action="<?= e(mg_url('/super/restaurante/' . ($isNew ? 'nuevo' : (int)$r['id']))) ?>">
  <?= Csrf::field() ?>
  <div class="grid grid-side">
    <div class="card">
      <div class="card-head"><h2><?= $isNew ? 'Datos del restaurante' : 'Datos' ?></h2></div>
      <div class="grid grid-2">
        <div class="field"><label for="name">Nombre *</label><input type="text" class="input" id="name" name="name" required maxlength="120" value="<?= e($r ? $r['name'] : '') ?>"></div>
        <div class="field"><label for="slug">Dirección web</label>
          <input type="text" class="input" id="slug" name="slug" maxlength="64" value="<?= e($r ? $r['slug'] : '') ?>" placeholder="brasa-negra">
          <p class="field-hint"><?= e(Url::abs('/r/')) ?><span id="slug-echo"><?= e($r ? $r['slug'] : 'nombre') ?></span></p></div>
      </div>
      <div class="field"><label for="tagline">Frase corta</label><input type="text" class="input" id="tagline" name="tagline" maxlength="180" value="<?= e($r ? $r['tagline'] : '') ?>"></div>
      <div class="grid grid-2">
        <div class="field"><label for="email">Correo</label><input class="input" id="email" name="email" type="email" value="<?= e($r ? $r['email'] : '') ?>"></div>
        <div class="field"><label for="phone">Teléfono</label><input type="text" class="input" id="phone" name="phone" value="<?= e($r ? $r['phone'] : '') ?>"></div>
        <div class="field"><label for="whatsapp">WhatsApp</label><input type="text" class="input" id="whatsapp" name="whatsapp" value="<?= e($r ? $r['whatsapp'] : '') ?>"></div>
        <div class="field"><label for="city">Ciudad</label><input type="text" class="input" id="city" name="city" value="<?= e($r ? $r['city'] : '') ?>"></div>
      </div>
      <div class="field"><label for="address">Dirección</label><input type="text" class="input" id="address" name="address" value="<?= e($r ? $r['address'] : '') ?>"></div>
      <div class="field"><label for="notes">Notas internas</label><textarea class="textarea" id="notes" name="notes" rows="2"><?= e($r ? $r['notes'] : '') ?></textarea></div>

      <?php if ($isNew): ?>
        <div class="card-head mt-3"><h3>Usuario del dueño</h3><p>Con estas credenciales entrará a su panel.</p></div>
        <div class="grid grid-2">
          <div class="field"><label for="owner_name">Nombre</label><input type="text" class="input" id="owner_name" name="owner_name" value="Dueño"></div>
          <div class="field"><label for="owner_username">Usuario *</label><input type="text" class="input" id="owner_username" name="owner_username" required autocomplete="off"></div>
        </div>
        <div class="field"><label for="owner_password">Contraseña *</label>
          <input class="input" id="owner_password" name="owner_password" type="password" minlength="8" required autocomplete="new-password"></div>
      <?php endif; ?>
    </div>

    <div class="stack">
      <div class="card">
        <div class="card-head"><h3>Plan y estado</h3></div>
        <div class="field"><label for="plan_id">Plan</label>
          <select class="select" id="plan_id" name="plan_id">
            <option value="0">Sin plan</option>
            <?php foreach ($plans as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= $r && (int)$r['plan_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field"><label for="plan_expires_at">Vence el</label>
          <input class="input" id="plan_expires_at" name="plan_expires_at" type="date" value="<?= e($r ? $r['plan_expires_at'] : '') ?>">
          <p class="field-hint">Al vencer, el menú público deja de mostrarse.</p></div>
        <div class="field"><label for="status">Estado</label>
          <select class="select" id="status" name="status">
            <option value="trial" <?= $r && $r['status'] === 'trial' ? 'selected' : '' ?>>Prueba</option>
            <option value="active" <?= $r && $r['status'] === 'active' ? 'selected' : '' ?>>Activo</option>
            <option value="suspended" <?= $r && $r['status'] === 'suspended' ? 'selected' : '' ?>>Suspendido</option>
          </select></div>
        <div class="field"><label for="currency">Moneda</label><input type="text" class="input" id="currency" name="currency" maxlength="6" value="<?= e($r ? $r['currency'] : 'Q') ?>"></div>
      </div>

      <?php if (!$isNew): ?>
        <div class="card">
          <div class="card-head"><h3>Uso actual</h3></div>
          <ul class="stack" style="gap:.55rem;font-size:var(--step--1)">
            <li class="row-between"><span class="muted">Platillos</span><b class="tabular"><?= (int)$usage['products'] ?></b></li>
            <li class="row-between"><span class="muted">Mesas</span><b class="tabular"><?= (int)$usage['tables'] ?></b></li>
            <li class="row-between"><span class="muted">Usuarios</span><b class="tabular"><?= (int)$usage['users'] ?></b></li>
            <li class="row-between"><span class="muted">Pedidos del mes</span><b class="tabular"><?= (int)$usage['orders'] ?></b></li>
          </ul>
          <div class="stack mt-2" style="gap:.6rem">
            <a class="btn btn-ghost btn-block" href="<?= e(mg_url('/super/entrar/' . (int)$r['id'])) ?>">Entrar a su panel</a>
            <a class="btn btn-ghost btn-block" href="<?= e(mg_url('/r/' . $r['slug'])) ?>" target="_blank" rel="noopener">Ver su menú</a>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><h3>Usuarios</h3></div>
          <ul class="stack" style="gap:.55rem;font-size:var(--step--1)">
            <?php foreach ($owners as $u): ?>
              <li class="row-between"><span><?= e($u['name']) ?> <span class="faint">· <?= e($u['username']) ?></span></span>
                <span class="chip chip-dim"><?= e($u['role']) ?></span></li>
            <?php endforeach; ?>
            <?php if (!$owners): ?><li class="faint">Sin usuarios.</li><?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="row mt-2">
    <button class="btn" type="submit"><?= $isNew ? 'Crear restaurante' : 'Guardar cambios' ?></button>
    <a class="btn btn-ghost" href="<?= e(mg_url('/super/restaurantes')) ?>">Volver</a>
  </div>
</form>
<?php $view->stop() ?>

<?php $view->start('scripts') ?>
<script>
(function () {
  var name = document.getElementById('name');
  var slug = document.getElementById('slug');
  var echo = document.getElementById('slug-echo');
  function slugify(v) {
    return v.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  }
  if (name && slug) {
    name.addEventListener('input', function () {
      if (!slug.dataset.touched) { slug.value = slugify(name.value); if (echo) { echo.textContent = slug.value || 'nombre'; } }
    });
    slug.addEventListener('input', function () {
      slug.dataset.touched = '1';
      if (echo) { echo.textContent = slugify(slug.value) || 'nombre'; }
    });
  }
})();
</script>
<?php $view->stop() ?>
