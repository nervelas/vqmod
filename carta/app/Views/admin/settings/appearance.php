<?php
/** Apariencia del menú: temas de color, tipografía y marca. */
use MenuGold\Core\Csrf;
use MenuGold\Core\Theme;
$view->extend('layouts/panel');
$view->set('title', 'Apariencia');
$r = $cfg;
$temaActual = Theme::normaliza($r['theme']);

/** Una tarjeta de tema: mini-maqueta del menú con los colores reales. */
$muestra = function ($key, $t, $activo) {
    ob_start(); ?>
    <label class="tema<?= $activo ? ' is-on' : '' ?>" data-tema="<?= e($key) ?>"
           data-primario="<?= e($t['gold']) ?>" data-acento="<?= e($t['ember']) ?>">
      <input type="radio" name="theme" value="<?= e($key) ?>" <?= $activo ? 'checked' : '' ?>>
      <span class="tema-lienzo" style="background:<?= e($t['ink']) ?>" aria-hidden="true">
        <span class="tema-barra" style="background:<?= e($t['carbon']) ?>">
          <i style="background:<?= e($t['gold']) ?>"></i>
          <b style="background:<?= e($t['cream']) ?>"></b>
        </span>
        <span class="tema-ficha" style="background:<?= e($t['carbon']) ?>;border-color:<?= e($t['gold']) ?>33">
          <i style="background:<?= e($t['carbon2']) ?>"></i>
          <u style="background:<?= e($t['cream']) ?>"></u>
          <u style="background:<?= e($t['cream']) ?>;width:44%;opacity:.45"></u>
          <s style="background:<?= e($t['gold']) ?>"></s>
        </span>
        <span class="tema-boton" style="background:<?= e($t['ember']) ?>"></span>
      </span>
      <span class="tema-pie">
        <span class="tema-nombre"><?= e($t['label']) ?></span>
        <span class="tema-nota"><?= e($t['nota']) ?></span>
      </span>
      <span class="tema-check" aria-hidden="true">
        <svg width="14" height="14" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2.4"
             stroke-linecap="round" stroke-linejoin="round"><path d="m3.5 9.5 3.5 3.5 7.5-8"/></svg>
      </span>
    </label>
    <?php return ob_get_clean();
};
?>
<?php $view->start('content') ?>
<?php $view->partial('admin/settings/_tabs'); ?>

<form method="post" enctype="multipart/form-data" action="<?= e(mg_url('/panel/ajustes/apariencia')) ?>">
  <?= Csrf::field() ?>
  <div class="grid grid-side">
    <div class="stack">

      <div class="card">
        <div class="card-head">
          <h2>Tema de color</h2>
          <p>Diez paletas afinadas a mano: cuatro oscuras de noche y seis claras de día.
             Se aplican al menú y a este panel al guardar.</p>
        </div>

        <h3 class="tema-grupo">Oscuros <span>Elegantes, para cenas y bares</span></h3>
        <div class="temas">
          <?php foreach ($oscuros as $key => $t) { echo $muestra($key, $t, $temaActual === $key); } ?>
        </div>

        <h3 class="tema-grupo">Claros <span>Luminosos, para desayunos y cafés</span></h3>
        <div class="temas">
          <?php foreach ($claros as $key => $t) { echo $muestra($key, $t, $temaActual === $key); } ?>
        </div>
      </div>

      <div class="card">
        <div class="card-head">
          <h3>Colores de tu marca</h3>
          <p>Opcional. Sustituye el dorado y el acento del tema elegido, conservando el resto.</p>
        </div>
        <label class="opt">
          <input type="checkbox" name="color_custom" value="1" id="color-custom"
                 <?= $r['primary_color'] !== '' && $r['primary_color'] !== Theme::uno($temaActual)['gold'] ? 'checked' : '' ?>>
          <span class="opt-mark" aria-hidden="true"></span>
          <span class="opt-name">Usar mis propios colores</span>
        </label>
        <div class="grid grid-2 mt-2" data-depends-on="color-custom" data-depends-value="1">
          <div class="field"><label for="primary_color">Color principal</label>
            <input class="input" id="primary_color" name="primary_color" type="color"
                   value="<?= e($r['primary_color'] !== '' ? $r['primary_color'] : '#D8B26E') ?>" style="height:48px;padding:4px"></div>
          <div class="field"><label for="accent_color">Color de acento</label>
            <input class="input" id="accent_color" name="accent_color" type="color"
                   value="<?= e($r['accent_color'] !== '' ? $r['accent_color'] : '#C4502B') ?>" style="height:48px;padding:4px"></div>
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
        <a class="btn btn-ghost btn-block" href="<?= e(mg_url('/')) ?>" target="_blank" rel="noopener">Abrir mi menú</a>
      </div>
    </div>
  </div>

  <div class="row mt-2"><button class="btn" type="submit">Guardar apariencia</button></div>
</form>

<script>
// Marca visualmente el tema elegido y, si no se usan colores propios,
// mantiene los selectores de color alineados con el tema.
(function () {
  var form = document.currentScript.closest('form') || document.querySelector('form');
  if (!form) { return; }
  var propio = form.querySelector('#color-custom');
  form.addEventListener('change', function (ev) {
    var t = ev.target;
    if (t.name !== 'theme') { return; }
    var cards = form.querySelectorAll('.tema');
    for (var i = 0; i < cards.length; i++) { cards[i].classList.remove('is-on'); }
    var card = t.closest('.tema');
    if (card) { card.classList.add('is-on'); }
    if (propio && !propio.checked && card) {
      var p = form.querySelector('#primary_color'), a = form.querySelector('#accent_color');
      if (p) { p.value = card.getAttribute('data-primario'); }
      if (a) { a.value = card.getAttribute('data-acento'); }
    }
  });
})();
</script>
<?php $view->stop() ?>
