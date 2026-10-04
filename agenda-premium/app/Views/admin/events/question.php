<?php
/** Fila del editor de preguntas. @var array $q @var int|string $i @var array $types */
$n = 'q[' . $i . ']';
$sw = static function (string $name, string $label, bool $on, string $attrs = ''): string {
    $id = 'sw-' . preg_replace('/[^a-z0-9]+/i', '-', $name);
    return '<div class="switch-row"><label class="switch"><input type="checkbox" id="' . e($id) . '" name="' . e($name) . '" value="1"' . ($attrs !== '' ? ' ' . $attrs : '') . ($on ? ' checked' : '') . '><span></span></label><label for="' . e($id) . '">' . e($label) . '</label></div>';
};
$uid = 'q' . $i;
$hasOptions = in_array($q['type'], ['select', 'radio', 'checkbox'], true);
?>
<fieldset class="p2-q" data-q-row draggable="false">
  <input type="hidden" name="<?= e($n) ?>[id]" value="<?= e((string) $q['id']) ?>">
  <input type="hidden" name="<?= e($n) ?>[del]" value="0" data-q-del>
  <div class="p2-q-head">
    <span class="p2-handle" data-q-handle title="Arrastra para reordenar" aria-hidden="true"><?= icon('drag') ?></span>
    <div class="field p2-q-label">
      <label for="<?= e($uid) ?>-label">Pregunta</label>
      <input class="input" type="text" id="<?= e($uid) ?>-label" name="<?= e($n) ?>[label]" maxlength="190" value="<?= e($q['label']) ?>" placeholder="Ej. ¿Cuál es el motivo de tu consulta?">
    </div>
    <div class="field p2-q-type">
      <label for="<?= e($uid) ?>-type">Tipo</label>
      <select class="select" id="<?= e($uid) ?>-type" name="<?= e($n) ?>[type]" data-q-type>
        <?php foreach ($types as $k => $label) : ?><option value="<?= e($k) ?>"<?= sel($q['type'], $k) ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="p2-q-tools">
      <button class="btn btn-ghost btn-icon btn-sm" type="button" data-q-up aria-label="Subir pregunta"><?= icon('chevron-up') ?></button>
      <button class="btn btn-ghost btn-icon btn-sm" type="button" data-q-down aria-label="Bajar pregunta"><?= icon('chevron-down') ?></button>
      <button class="btn btn-ghost btn-icon btn-sm text-err" type="button" data-q-remove aria-label="Quitar pregunta"><?= icon('trash') ?></button>
    </div>
  </div>
  <div class="p2-q-body">
    <div class="field" data-q-options<?= $hasOptions ? '' : ' hidden' ?>>
      <label for="<?= e($uid) ?>-options">Opciones <small class="muted">(una por línea)</small></label>
      <textarea class="textarea" id="<?= e($uid) ?>-options" name="<?= e($n) ?>[options]" rows="3" maxlength="2000"><?= e($q['options']) ?></textarea>
    </div>
    <div class="p2-q-flags">
      <?= $sw($n . '[required]', 'Obligatoria', (int) $q['required'] === 1) ?>
      <?= $sw($n . '[active]', 'Visible', (int) $q['active'] === 1) ?>
      <div class="field p2-q-scope">
        <label for="<?= e($uid) ?>-scope">Se muestra en</label>
        <select class="select" id="<?= e($uid) ?>-scope" name="<?= e($n) ?>[scope]">
          <option value="event"<?= sel($q['scope'], 'event') ?>>Solo este evento</option>
          <option value="global"<?= sel($q['scope'], 'global') ?>>Todos los eventos</option>
        </select>
      </div>
    </div>
    <details class="p2-q-more"<?= ($q['condition_field'] !== '' || $q['help'] !== '') ? ' open' : '' ?>>
      <summary>Ayuda, clave y condición</summary>
      <div class="form-grid">
        <div class="field">
          <label for="<?= e($uid) ?>-help">Texto de ayuda</label>
          <input class="input" type="text" id="<?= e($uid) ?>-help" name="<?= e($n) ?>[help]" maxlength="255" value="<?= e($q['help']) ?>">
        </div>
        <div class="field">
          <label for="<?= e($uid) ?>-name">Clave interna</label>
          <input class="input mono" type="text" id="<?= e($uid) ?>-name" name="<?= e($n) ?>[name]" maxlength="60" pattern="[a-z][a-z0-9_]*" value="<?= e($q['name']) ?>" data-q-name placeholder="se-genera-sola">
        </div>
        <div class="field">
          <label for="<?= e($uid) ?>-cf">Mostrar solo si la pregunta…</label>
          <select class="select" id="<?= e($uid) ?>-cf" name="<?= e($n) ?>[condition_field]" data-q-cond data-value="<?= e($q['condition_field']) ?>">
            <option value="">Siempre se muestra</option>
            <?php if ($q['condition_field'] !== '') : ?><option value="<?= e($q['condition_field']) ?>" selected><?= e($q['condition_field']) ?></option><?php endif; ?>
          </select>
        </div>
        <div class="field">
          <label for="<?= e($uid) ?>-cv">…tiene como respuesta</label>
          <input class="input" type="text" id="<?= e($uid) ?>-cv" name="<?= e($n) ?>[condition_value]" maxlength="190" value="<?= e($q['condition_value']) ?>">
        </div>
      </div>
    </details>
  </div>
</fieldset>
