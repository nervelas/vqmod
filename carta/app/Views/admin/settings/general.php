<?php
/** Ajustes generales. */
use MenuGold\Core\Csrf;
use MenuGold\Models\Restaurant;
$view->extend('layouts/panel');
$view->set('title', 'Ajustes');
$r = $cfg;
$modes = MenuGold\Models\Settings::modes();
$langs = MenuGold\Models\Settings::list('langs');
?>
<?php $view->start('content') ?>
<?php $view->partial('admin/settings/_tabs'); ?>

<form method="post" action="<?= e(mg_url('/panel/ajustes')) ?>">
  <?= Csrf::field() ?>
  <div class="grid grid-side">
    <div class="stack">
      <div class="card">
        <div class="card-head"><h2>Identidad</h2></div>
        <div class="grid grid-2">
          <div class="field"><label for="name">Nombre del restaurante *</label>
            <input type="text" class="input" id="name" name="name" required maxlength="120" value="<?= e($r['name']) ?>"></div>
          <div class="field"><label for="tagline">Frase corta</label>
            <input type="text" class="input" id="tagline" name="tagline" maxlength="180" value="<?= e($r['tagline']) ?>" placeholder="Parrilla de leña y cortes madurados"></div>
        </div>
        <div class="field"><label for="description">Descripción</label>
          <textarea class="textarea" id="description" name="description" rows="3"><?= e($r['description']) ?></textarea></div>
        <div class="grid grid-2">
          <div class="field"><label for="phone">Teléfono</label><input type="text" class="input" id="phone" name="phone" maxlength="40" value="<?= e($r['phone']) ?>"></div>
          <div class="field"><label for="whatsapp">WhatsApp</label><input type="text" class="input" id="whatsapp" name="whatsapp" maxlength="40" value="<?= e($r['whatsapp']) ?>" placeholder="502 5555 5555"></div>
          <div class="field"><label for="email">Correo</label><input class="input" id="email" name="email" type="email" maxlength="160" value="<?= e($r['email']) ?>"></div>
          <div class="field"><label for="city">Ciudad</label><input type="text" class="input" id="city" name="city" maxlength="80" value="<?= e($r['city']) ?>"></div>
        </div>
        <div class="field"><label for="address">Dirección</label><input type="text" class="input" id="address" name="address" maxlength="220" value="<?= e($r['address']) ?>"></div>
        <div class="grid grid-2">
          <div class="field"><label for="map_url">Enlace de Google Maps</label><input type="text" class="input" id="map_url" name="map_url" value="<?= e($r['map_url']) ?>" placeholder="https://maps.app.goo.gl/…"></div>
          <div class="field"><label for="review_url">Enlace para reseñas</label><input type="text" class="input" id="review_url" name="review_url" value="<?= e($r['review_url']) ?>" placeholder="https://g.page/r/…/review">
            <p class="field-hint">Se invita al comensal después de recibir su pedido.</p></div>
        </div>
      </div>
    </div>

    <div class="stack">
      <div class="card">
        <div class="card-head"><h3>Cómo se pide</h3></div>
        <div class="field"><label for="order_mode">Modo del menú</label>
          <select class="select" id="order_mode" name="order_mode">
            <option value="order"    <?= $r['order_mode'] === 'order' ? 'selected' : '' ?>>Menú con pedidos</option>
            <option value="whatsapp" <?= $r['order_mode'] === 'whatsapp' ? 'selected' : '' ?>>Pedidos + envío a WhatsApp</option>
            <option value="catalog"  <?= $r['order_mode'] === 'catalog' ? 'selected' : '' ?>>Solo consulta (sin pedidos)</option>
          </select></div>

        <p class="label">Modos de servicio</p>
        <div class="stack" style="gap:.6rem">
          <?php foreach (array('dine_in' => 'En mesa (QR por mesa)', 'takeaway' => 'Para llevar', 'delivery' => 'A domicilio') as $k => $label): ?>
            <label class="switch">
              <input type="checkbox" name="service_modes[]" value="<?= e($k) ?>" <?= in_array($k, $modes, true) ? 'checked' : '' ?>>
              <span class="switch-track" aria-hidden="true"></span><span><?= e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h3>Moneda e impuesto</h3></div>
        <div class="grid grid-2">
          <div class="field"><label for="currency">Símbolo</label><input type="text" class="input" id="currency" name="currency" maxlength="6" value="<?= e($r['currency']) ?>"></div>
          <div class="field"><label for="tax_rate">Impuesto (%)</label><input class="input" id="tax_rate" name="tax_rate" type="number" step="0.01" min="0" max="100" value="<?= e($r['tax_rate']) ?>"></div>
        </div>
        <label class="switch"><input type="checkbox" name="tax_included" value="1" <?= (int)$r['tax_included'] === 1 ? 'checked' : '' ?>>
          <span class="switch-track" aria-hidden="true"></span><span>El impuesto ya está incluido en los precios</span></label>
      </div>

      <div class="card">
        <div class="card-head"><h3>Idiomas y zona horaria</h3></div>
        <div class="stack" style="gap:.6rem">
          <?php foreach (array('es' => 'Español', 'en' => 'Inglés') as $k => $label): ?>
            <label class="switch">
              <input type="checkbox" name="langs[]" value="<?= e($k) ?>" <?= in_array($k, $langs, true) ? 'checked' : '' ?>>
              <span class="switch-track" aria-hidden="true"></span><span><?= e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="field mt-2"><label for="lang_default">Idioma principal</label>
          <select class="select" id="lang_default" name="lang_default">
            <option value="es" <?= $r['lang_default'] === 'es' ? 'selected' : '' ?>>Español</option>
            <option value="en" <?= $r['lang_default'] === 'en' ? 'selected' : '' ?>>Inglés</option>
          </select></div>
        <div class="field"><label for="timezone">Zona horaria</label>
          <select class="select" id="timezone" name="timezone">
            <?php foreach ($timezones as $tz): ?>
              <option value="<?= e($tz) ?>" <?= $r['timezone'] === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>
    </div>
  </div>

  <div class="row mt-2"><button class="btn" type="submit">Guardar ajustes</button></div>
</form>
<?php $view->stop() ?>
