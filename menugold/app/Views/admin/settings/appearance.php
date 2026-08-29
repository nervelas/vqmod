<?php
/** Apariencia del menú. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Apariencia');
$r = $restaurant;
?>
<?php $view->start('content') ?>
<?php $view->partial('admin/settings/_tabs'); ?>

<form method="post" enctype="multipart/form-data" action="<?= e(mg_url('/panel/ajustes/apariencia')) ?>">
  <?= Csrf::field() ?>
  <div class="grid grid-side">
    <div class="stack">
      <div class="card">
        <div class="card-head"><h2>Tema</h2><p>Ocho combinaciones de lujo, o los colores exactos de tu marca.</p></div>
        <div class="grid grid-2">
          <?php foreach ($themes as $key => $t): ?>
            <label class="opt" style="<?= $r['theme'] === $key ? 'border-color:var(--gold)' : '' ?>">
              <input type="radio" name="theme" value="<?= e($key) ?>" <?= $r['theme'] === $key ? 'checked' : '' ?>>
              <span class="opt-mark" aria-hidden="true"></span>
              <span class="opt-name"><?= e($t['label']) ?></span>
              <span style="display:flex;gap:4px">
                <i style="width:16px;height:16px;border-radius:5px;background:<?= e($t['primary']) ?>"></i>
                <i style="width:16px;height:16px;border-radius:5px;background:<?= e($t['accent']) ?>"></i>
              </span>
            </label>
          <?php endforeach; ?>
          <label class="opt">
            <input type="radio" name="theme" value="custom" id="theme-custom" <?= $r['theme'] === 'custom' ? 'checked' : '' ?>>
            <span class="opt-mark" aria-hidden="true"></span>
            <span class="opt-name">Personalizado</span>
          </label>
        </div>

        <div class="grid grid-2 mt-2" data-depends-on="theme-custom" data-depends-value="1">
          <div class="field"><label for="primary_color">Color principal</label>
            <input class="input" id="primary_color" name="primary_color" type="color" value="<?= e($r['primary_color']) ?>" style="height:48px;padding:4px"></div>
          <div class="field"><label for="accent_color">Color de acento</label>
            <input class="input" id="accent_color" name="accent_color" type="color" value="<?= e($r['accent_color']) ?>" style="height:48px;padding:4px"></div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h3>Tipografía</h3></div>
        <div class="stack" style="gap:.5rem">
          <?php foreach ($combos as $key => $c): ?>
            <label class="opt">
              <input type="radio" name="font_combo" value="<?= e($key) ?>" <?= $r['font_combo'] === $key ? 'checked' : '' ?>>
              <span class="opt-mark" aria-hidden="true"></span>
              <span class="opt-name"><?= e($c['label']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="stack">
      <div class="card">
        <div class="card-head"><h3>Logo</h3><p>Cuadrado. También genera los iconos de la app.</p></div>
        <div id="logo-preview" style="max-width:180px">
          <?= mg_img($r['logo'], array('alt' => '', 'sizes' => '180px', 'ratio' => '1/1')) ?>
        </div>
        <div class="field mt-2"><label for="logo">Cambiar logo</label>
          <input class="input" id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" data-preview="logo-preview"></div>
      </div>

      <div class="card">
        <div class="card-head"><h3>Portada</h3><p>La foto que abre el menú a pantalla completa.</p></div>
        <div id="cover-preview"><?= mg_img($r['cover'], array('alt' => '', 'sizes' => '340px', 'ratio' => '16/10')) ?></div>
        <div class="field mt-2"><label for="cover">Cambiar portada</label>
          <input class="input" id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" data-preview="cover-preview"></div>
      </div>

      <div class="card">
        <div class="card-head"><h3>Vista previa</h3></div>
        <a class="btn btn-ghost btn-block" href="<?= e(mg_url('/r/' . $r['slug'])) ?>" target="_blank" rel="noopener">Abrir mi menú</a>
      </div>
    </div>
  </div>

  <div class="row mt-2"><button class="btn" type="submit">Guardar apariencia</button></div>
</form>
<?php $view->stop() ?>
