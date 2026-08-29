<?php
/** Puesta en marcha en cuatro pasos. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Puesta en marcha');
$r = $restaurant;
?>
<?php $view->start('content') ?>
<div class="shell-narrow" style="width:min(100%,720px);margin-inline:auto">
  <div class="steps-bar" aria-hidden="true">
    <?php for ($i = 1; $i <= 4; $i++): ?><i class="<?= $i <= $step ? 'is-done' : '' ?>"></i><?php endfor; ?>
  </div>
  <p class="eyebrow" style="margin-bottom:1rem">Paso <?= (int)$step ?> de 4</p>

  <?php if ($step === 1): ?>
    <form class="card" method="post" enctype="multipart/form-data" action="<?= e(mg_url('/panel/inicio-guiado')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="step" value="1">
      <div class="card-head"><h2 class="display" style="font-size:var(--step-2)">Empecemos por tu marca</h2></div>
      <div class="field"><label for="name">Nombre del restaurante *</label>
        <input type="text" class="input" id="name" name="name" required maxlength="120" value="<?= e($r['name']) ?>"></div>
      <div class="field"><label for="tagline">Frase corta</label>
        <input type="text" class="input" id="tagline" name="tagline" maxlength="180" value="<?= e($r['tagline']) ?>" placeholder="Parrilla de leña y cortes madurados"></div>
      <div class="grid grid-2">
        <div class="field"><label for="phone">Teléfono</label><input type="text" class="input" id="phone" name="phone" value="<?= e($r['phone']) ?>"></div>
        <div class="field"><label for="whatsapp">WhatsApp</label><input type="text" class="input" id="whatsapp" name="whatsapp" value="<?= e($r['whatsapp']) ?>"></div>
      </div>
      <div class="field"><label for="address">Dirección</label><input type="text" class="input" id="address" name="address" value="<?= e($r['address']) ?>"></div>
      <div class="field"><label for="logo">Logo</label>
        <input class="input" id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" data-preview="logo-preview">
        <div id="logo-preview" style="max-width:150px;margin-top:.8rem"><?= mg_img($r['logo'], array('alt' => '', 'sizes' => '150px', 'ratio' => '1/1')) ?></div></div>
      <button class="btn btn-block mt-2" type="submit">Siguiente</button>
    </form>

  <?php elseif ($step === 2): ?>
    <form class="card" method="post" action="<?= e(mg_url('/panel/inicio-guiado')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="step" value="2">
      <div class="card-head"><h2 class="display" style="font-size:var(--step-2)">Las secciones de tu carta</h2></div>
      <p class="page-intro">Escribe las que uses. Puedes cambiarlas después.</p>
      <?php
      $suggested = array('Entradas', 'Fuertes', 'Del mar', 'Postres', 'Bebidas', 'Barra');
      $existing = array();
      foreach ($categories as $c) { $existing[] = $c['name']; }
      for ($i = 0; $i < 6; $i++): ?>
        <div class="field">
          <input type="text" class="input" name="categories[]" maxlength="120"
                 value="<?= e(isset($existing[$i]) ? $existing[$i] : '') ?>"
                 placeholder="<?= e($suggested[$i]) ?>">
        </div>
      <?php endfor; ?>
      <div class="row mt-2">
        <button class="btn" type="submit">Siguiente</button>
        <a class="btn btn-ghost" href="<?= e(mg_url('/panel/inicio-guiado?paso=3')) ?>">Saltar</a>
      </div>
    </form>

  <?php elseif ($step === 3): ?>
    <form class="card" method="post" action="<?= e(mg_url('/panel/inicio-guiado')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="step" value="3">
      <div class="card-head"><h2 class="display" style="font-size:var(--step-2)">Tus mesas</h2></div>
      <p class="page-intro">Cada mesa recibe su propio código QR firmado. Ya tienes <?= (int)$tables ?>.</p>
      <div class="grid grid-2">
        <div class="field"><label for="tables">¿Cuántas mesas?</label><input class="input" id="tables" name="tables" type="number" min="0" max="80" value="<?= $tables > 0 ? 0 : 10 ?>"></div>
        <div class="field"><label for="prefix">Prefijo</label><input type="text" class="input" id="prefix" name="prefix" maxlength="30" value="Mesa"></div>
      </div>
      <div class="row mt-2">
        <button class="btn" type="submit">Siguiente</button>
        <a class="btn btn-ghost" href="<?= e(mg_url('/panel/inicio-guiado?paso=4')) ?>">Saltar</a>
      </div>
    </form>

  <?php else: ?>
    <div class="card">
      <div class="card-head"><h2 class="display" style="font-size:var(--step-2)">Ya casi</h2></div>
      <p class="page-intro">Tu menú vive en <a class="link-line gold" href="<?= e(mg_url('/r/' . $r['slug'])) ?>" target="_blank" rel="noopener"><?= e(\MenuGold\Core\Url::abs('/r/' . $r['slug'])) ?></a></p>
      <ul class="stack" style="gap:.8rem;font-size:var(--step--1)">
        <li class="row-between"><span class="muted">Platillos en la carta</span><b class="tabular gold"><?= (int)$products ?></b></li>
        <li class="row-between"><span class="muted">Mesas con QR</span><b class="tabular gold"><?= (int)$tables ?></b></li>
      </ul>
      <div class="row mt-3">
        <a class="btn btn-ghost" href="<?= e(mg_url('/panel/menu/producto/nuevo')) ?>">Agregar un platillo</a>
        <a class="btn btn-ghost" href="<?= e(mg_url('/panel/mesas/qr.pdf?formato=tent')) ?>" target="_blank" rel="noopener">Imprimir los QR</a>
      </div>
      <form class="mt-3" method="post" action="<?= e(mg_url('/panel/inicio-guiado')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="step" value="4">
        <button class="btn btn-block" type="submit">Terminar y ver mi panel</button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php $view->stop() ?>
