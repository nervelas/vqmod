<?php
/** Hoja de códigos QR en pantalla (imprimible desde el navegador). */
$view->extend('layouts/panel');
$view->set('title', 'Códigos QR');
?>
<?php $view->start('actions') ?>
  <button class="btn btn-sm btn-ghost no-print" type="button" onclick="window.print()">Imprimir</button>
  <a class="btn btn-sm" href="<?= e(mg_url('/panel/mesas/qr.pdf?formato=tent')) ?>" target="_blank" rel="noopener">PDF de lujo</a>
<?php $view->stop() ?>

<?php $view->start('content') ?>
<div class="card no-print">
  <div class="card-head"><h2>Código general</h2><p>Sin mesa: sirve para llevar, domicilio o el rótulo de la entrada.</p></div>
  <div class="qr-tile" style="max-width:230px">
    <img src="<?= e($generalPng) ?>" alt="Código QR general del restaurante">
    <b><?= e($restaurant['name']) ?></b>
    <p class="faint" style="font-size:11px;word-break:break-all;margin-top:.5rem"><?= e($generalUrl) ?></p>
  </div>
</div>

<div class="card mt-2">
  <div class="card-head"><h2>Un código por mesa</h2></div>
  <?php if ($codes): ?>
    <div class="qr-sheet">
      <?php foreach ($codes as $c): ?>
        <div class="qr-tile">
          <img src="<?= e($c['png']) ?>" alt="Código QR de <?= e($c['table']['name']) ?>">
          <b><?= e($c['table']['name']) ?></b>
          <p class="faint" style="font-size:10.5px;margin-top:.3rem"><?= e($restaurant['name']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty"><h3>Sin mesas</h3><p><a class="link-line gold" href="<?= e(mg_url('/panel/mesas')) ?>">Crea las primeras</a>.</p></div>
  <?php endif; ?>
</div>
<?php $view->stop() ?>
