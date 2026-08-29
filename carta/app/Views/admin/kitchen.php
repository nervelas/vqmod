<?php
/** Pantalla de cocina (KDS). */
$view->extend('layouts/panel');
$view->set('title', 'Cocina');
?>
<?php $view->start('actions') ?>
  <button class="btn btn-sm btn-ghost" type="button" id="kds-sound-toggle" aria-pressed="true">Sonido activado</button>
<?php $view->stop() ?>

<?php $view->start('content') ?>
<div class="kds" id="kds-board" data-hash="<?= e($pulse['hash']) ?>" style="padding:0">
  <?php $view->partial('admin/partials/kds-columns', array('board' => $board)); ?>
</div>
<p class="field-hint mt-2">Los pedidos entran solos. Después de 10 minutos la ficha se pone ámbar; a los 18, roja.</p>
<?php $view->stop() ?>
