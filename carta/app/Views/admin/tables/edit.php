<?php
/** Alta y edición de mesa. */
use MenuGold\Core\Csrf;
use MenuGold\Core\Qr;
use MenuGold\Models\TableModel;
$view->extend('layouts/panel');
$isNew = !$table;
$t = $table;
$view->set('title', $isNew ? 'Nueva mesa' : $t['name']);
$url = $t ? TableModel::url($t) : '';
?>
<?php $view->start('content') ?>
<div class="grid grid-side">
  <form class="card" method="post" action="<?= e(mg_url('/panel/mesas/mesa/' . ($isNew ? 'nueva' : (int)$t['id']))) ?>">
    <?= Csrf::field() ?>
    <div class="card-head"><h2><?= $isNew ? 'Datos de la mesa' : 'Editar mesa' ?></h2></div>
    <div class="grid grid-2">
      <div class="field"><label for="name">Nombre *</label><input type="text" class="input" id="name" name="name" required maxlength="60" value="<?= e($t ? $t['name'] : '') ?>" placeholder="Mesa 7"></div>
      <div class="field"><label for="seats">Lugares</label><input class="input" id="seats" name="seats" type="number" min="1" max="60" value="<?= e($t ? $t['seats'] : 4) ?>"></div>
    </div>
    <div class="field"><label for="zone">Zona</label><input type="text" class="input" id="zone" name="zone" maxlength="60" value="<?= e($t ? $t['zone'] : '') ?>" placeholder="Terraza"></div>
    <label class="switch"><input type="checkbox" name="is_active" value="1" <?= (!$t || (int)$t['is_active'] === 1) ? 'checked' : '' ?>>
      <span class="switch-track" aria-hidden="true"></span><span>Mesa activa</span></label>
    <?php if (!$isNew): ?>
      <label class="switch mt-2"><input type="checkbox" name="regenerate" value="1">
        <span class="switch-track" aria-hidden="true"></span><span>Regenerar el código QR</span></label>
      <p class="field-hint">Solo si alguien fotografió el QR y quieres invalidarlo. Los códigos impresos dejarán de funcionar.</p>
    <?php endif; ?>
    <div class="row mt-2">
      <button class="btn" type="submit">Guardar mesa</button>
      <a class="btn btn-ghost" href="<?= e(mg_url('/panel/mesas')) ?>">Cancelar</a>
    </div>
  </form>

  <?php if (!$isNew): ?>
    <div class="stack">
      <div class="qr-tile">
        <img src="<?= e(Qr::dataUri($url, 6)) ?>" alt="Código QR de <?= e($t['name']) ?>">
        <b><?= e($t['name']) ?></b>
        <p class="faint" style="font-size:11px;word-break:break-all;margin-top:.5rem"><?= e($url) ?></p>
        <div class="row mt-2" style="justify-content:center">
          <a class="btn btn-ghost btn-sm" href="<?= e(mg_url('/panel/mesas/qr.pdf?formato=tent&mesa=' . (int)$t['id'])) ?>" target="_blank" rel="noopener">PDF</a>
          <a class="btn btn-ghost btn-sm" href="<?= e($url) ?>" target="_blank" rel="noopener">Probar</a>
        </div>
      </div>

      <form method="post" action="<?= e(mg_url('/panel/mesas/' . (int)$t['id'] . '/eliminar')) ?>"
            data-confirm="¿Eliminar «<?= e($t['name']) ?>»? Su código QR dejará de funcionar.">
        <?= Csrf::field() ?>
        <button class="btn btn-ghost btn-sm btn-block" type="submit" style="color:var(--ember);border-color:rgba(196,80,43,.4)">Eliminar mesa</button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php $view->stop() ?>
