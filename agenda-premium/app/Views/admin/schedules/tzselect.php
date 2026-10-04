<?php
/** Selector de zona horaria. @var string $name @var string $value @var string $id @var string $label */
$common = ['America/Guatemala', 'America/Mexico_City', 'America/El_Salvador', 'America/Tegucigalpa', 'America/Costa_Rica', 'America/Panama', 'America/Bogota', 'America/New_York', 'America/Chicago', 'America/Los_Angeles', 'Europe/Madrid'];
$all = \App\Core\Tz::list();
$rest = array_values(array_diff($all, $common));
?>
<select class="select" id="<?= e($id) ?>" name="<?= e($name) ?>" aria-label="<?= e($label ?? 'Zona horaria') ?>">
  <optgroup label="Frecuentes">
    <?php foreach ($common as $z) : ?><option value="<?= e($z) ?>"<?= sel($value, $z) ?>><?= e($z) ?></option><?php endforeach; ?>
  </optgroup>
  <optgroup label="Todas las zonas">
    <?php foreach ($rest as $z) : ?><option value="<?= e($z) ?>"<?= sel($value, $z) ?>><?= e($z) ?></option><?php endforeach; ?>
  </optgroup>
</select>
