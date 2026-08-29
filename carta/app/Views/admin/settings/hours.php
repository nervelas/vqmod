<?php
/** Horarios de atención. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Horarios');
$days = array('Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado');
?>
<?php $view->start('content') ?>
<?php $view->partial('admin/settings/_tabs'); ?>

<form method="post" action="<?= e(mg_url('/panel/ajustes/horario')) ?>">
  <?= Csrf::field() ?>
  <div class="card">
    <div class="card-head"><h2>Horario de atención</h2><p>Define cuándo el menú muestra «Abierto ahora». Si cierras después de medianoche, pon por ejemplo 18:00 → 01:00.</p></div>
    <?php for ($d = 0; $d <= 6; $d++):
      $row = isset($hours[$d]) ? $hours[$d] : null;
      $closed = $row ? (int)$row['is_closed'] === 1 : false;
    ?>
      <div class="row-between" style="padding:.9rem 0;border-bottom:1px solid var(--line-soft);gap:1rem">
        <b style="min-width:110px"><?= e($days[$d]) ?></b>
        <div class="row" style="flex:1;justify-content:flex-end">
          <input class="input" style="max-width:130px" type="time" name="opens_at[<?= $d ?>]"
                 value="<?= e($row && $row['opens_at'] ? substr($row['opens_at'], 0, 5) : '11:00') ?>" aria-label="Abre <?= e($days[$d]) ?>">
          <span class="faint">→</span>
          <input class="input" style="max-width:130px" type="time" name="closes_at[<?= $d ?>]"
                 value="<?= e($row && $row['closes_at'] ? substr($row['closes_at'], 0, 5) : '22:00') ?>" aria-label="Cierra <?= e($days[$d]) ?>">
          <label class="switch" style="margin-left:.8rem">
            <input type="checkbox" name="closed[]" value="<?= $d ?>" <?= $closed ? 'checked' : '' ?>>
            <span class="switch-track" aria-hidden="true"></span><span class="muted" style="font-size:12px">Cerrado</span>
          </label>
        </div>
      </div>
    <?php endfor; ?>
    <div class="row mt-2"><button class="btn" type="submit">Guardar horarios</button></div>
  </div>
</form>
<?php $view->stop() ?>
