<?php /** Un bloque horario. @var string $prefix (nombre base del campo) @var array $b */ ?>
<div class="p2-block" data-block>
  <label class="sr-only" for="<?= e(preg_replace('/[^a-z0-9]+/i', '-', $prefix)) ?>-s">Hora de inicio</label>
  <input class="input mono" type="time" id="<?= e(preg_replace('/[^a-z0-9]+/i', '-', $prefix)) ?>-s" name="<?= e($prefix) ?>[start]" value="<?= e($b['start']) ?>" step="900">
  <span class="p2-to" aria-hidden="true">a</span>
  <label class="sr-only" for="<?= e(preg_replace('/[^a-z0-9]+/i', '-', $prefix)) ?>-e">Hora de fin</label>
  <input class="input mono" type="time" id="<?= e(preg_replace('/[^a-z0-9]+/i', '-', $prefix)) ?>-e" name="<?= e($prefix) ?>[end]" value="<?= e($b['end']) ?>" step="900">
  <button class="btn btn-ghost btn-icon btn-sm" type="button" data-block-remove aria-label="Quitar este bloque"><?= icon('x') ?></button>
</div>
