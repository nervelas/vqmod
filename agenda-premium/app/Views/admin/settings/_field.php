<?php
/**
 * Campo de formulario reutilizable. Variables: $name, $label, $value, $error, $type, $hint, $max, $attrs (array), $options (select), $rows (textarea), $prefix
 */
$type = $type ?? 'text';
$hint = $hint ?? '';
$error = $error ?? '';
$attrs = $attrs ?? [];
$id = 'f-' . $name;
$desc = trim(($hint !== '' ? $id . '-hint ' : '') . ($error !== '' ? $id . '-err' : ''));
$attrHtml = '';
foreach ($attrs as $ak => $av) {
    if ($av === true) {
        $attrHtml .= ' ' . e($ak);
    } elseif ($av !== false && $av !== null) {
        $attrHtml .= ' ' . e($ak) . '="' . e($av) . '"';
    }
}
$common = ' id="' . e($id) . '" name="' . e($name) . '"' . ($desc !== '' ? ' aria-describedby="' . e($desc) . '"' : '') . ($error !== '' ? ' aria-invalid="true"' : '') . $attrHtml;
?>
<div class="field<?= !empty($wide) ? ' p4-wide' : '' ?>">
  <label for="<?= e($id) ?>"><?= e($label) ?></label>
  <?php if ($type === 'textarea') : ?>
    <textarea class="textarea"<?= $common ?> rows="<?= (int) ($rows ?? 4) ?>"<?= isset($max) ? ' maxlength="' . (int) $max . '"' : '' ?>><?= e($value) ?></textarea>
  <?php elseif ($type === 'select') : ?>
    <select class="select"<?= $common ?>>
      <?php foreach ($options as $ov => $ol) : ?><option value="<?= e($ov) ?>"<?= sel($value, $ov) ?>><?= e($ol) ?></option><?php endforeach; ?>
    </select>
  <?php elseif ($type === 'password') : ?>
    <input class="input" type="password" autocomplete="new-password"<?= $common ?> value="" placeholder="<?= e($placeholder ?? '') ?>">
  <?php else : ?>
    <input class="input" type="<?= e($type) ?>"<?= $common ?> value="<?= e($value) ?>"<?= isset($max) ? ' maxlength="' . (int) $max . '"' : '' ?><?= isset($placeholder) ? ' placeholder="' . e($placeholder) . '"' : '' ?>>
  <?php endif; ?>
  <?php if ($hint !== '') : ?><p class="hint" id="<?= e($id) ?>-hint"><?= e($hint) ?></p><?php endif; ?>
  <?php if ($error !== '') : ?><p class="error" id="<?= e($id) ?>-err" role="alert"><?= e($error) ?></p><?php endif; ?>
</div>
