<?php /** Una excepción por fecha. @var string|int $x @var array $o */
$n = 'ex[' . $x . ']';
$uid = 'ex' . $x;
?>
<div class="p2-ex" data-ex-row>
  <div class="p2-ex-head">
    <div class="field">
      <label for="<?= e($uid) ?>-date">Fecha</label>
      <input class="input" type="date" id="<?= e($uid) ?>-date" name="<?= e($n) ?>[date]" value="<?= e($o['date']) ?>" data-ex-date>
    </div>
    <div class="field">
      <label for="<?= e($uid) ?>-open">Ese día</label>
      <select class="select" id="<?= e($uid) ?>-open" name="<?= e($n) ?>[open]" data-ex-open>
        <option value="0"<?= sel($o['open'], 0) ?>>Cerrado</option>
        <option value="1"<?= sel($o['open'], 1) ?>>Abierto con horas propias</option>
      </select>
    </div>
    <div class="field p2-ex-note">
      <label for="<?= e($uid) ?>-note">Nota (opcional)</label>
      <input class="input" type="text" id="<?= e($uid) ?>-note" name="<?= e($n) ?>[note]" maxlength="190" value="<?= e($o['note']) ?>" placeholder="Ej. Jornada de capacitación">
    </div>
    <button class="btn btn-ghost btn-icon btn-sm text-err" type="button" data-ex-remove aria-label="Quitar excepción"><?= icon('trash') ?></button>
  </div>
  <div class="p2-ex-blocks" data-ex-blocks<?= (int) $o['open'] === 1 ? '' : ' hidden' ?>>
    <div data-blocks>
      <?php foreach ($o['blocks'] as $j => $b) : ?>
        <?= \App\Core\View::partial('admin/schedules/block', ['prefix' => $n . '[blocks][' . $j . ']', 'b' => $b]) ?>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-outline btn-sm" type="button" data-ex-addblock data-next="<?= count($o['blocks']) ?>"><?= icon('plus') ?> Agregar bloque</button>
  </div>
</div>
