<form method="post" action="<?= e(url('/admin/' . $mod . '/guardar')) ?>" class="card form-wide">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php foreach ($cfg['fields'] as [$k, $label, $type, $o]): $v = $row[$k] ?? ($o['default'] ?? ''); $fid = 'f_' . $k; $help = $o['help'] ?? ''; ?>
    <?php if ($type === 'bool'): ?>
      <div class="field"><label class="check"><input type="checkbox" name="<?= e($k) ?>" value="1" <?= chk($v) ?>><span><?= e(__($label)) ?></span></label><?php if ($help): ?><div class="help"><?= e(__($help)) ?></div><?php endif; ?></div>
    <?php else: ?>
      <div class="field"><label for="<?= e($fid) ?>" class="<?= !empty($o['required']) ? 'req' : '' ?>"><?= e(__($label)) ?></label>
      <?php if ($type === 'textarea'): ?><textarea id="<?= e($fid) ?>" name="<?= e($k) ?>" style="min-height:<?= (int)($o['rows'] ?? 5) * 26 ?>px"><?= e($v) ?></textarea>
      <?php elseif ($type === 'select'): ?><select id="<?= e($fid) ?>" name="<?= e($k) ?>"><?php if (isset($o['blank'])): ?><option value=""><?= e(__($o['blank'])) ?></option><?php endif; ?><?php foreach ($opts[$k] as $ov => $ol): ?><option value="<?= e($ov) ?>"<?= sel($ov, $v) ?>><?= e($ol) ?></option><?php endforeach; ?></select>
      <?php elseif ($type === 'password'): ?><input id="<?= e($fid) ?>" name="<?= e($k) ?>" type="password" autocomplete="new-password">
      <?php else: $it = ['int' => 'number', 'decimal' => 'number', 'email' => 'email', 'url' => 'url', 'date' => 'date', 'time' => 'time', 'datetime' => 'datetime-local', 'color' => 'color'][$type] ?? 'text'; if ($type === 'time' && $v) { $v = substr((string)$v, 0, 5); } ?>
        <input id="<?= e($fid) ?>" name="<?= e($k) ?>" type="<?= e($it) ?>" value="<?= e($v) ?>" <?= $type === 'decimal' ? 'step="0.01"' : '' ?> <?= isset($o['min']) ? 'min="' . e($o['min']) . '"' : '' ?> <?= isset($o['max']) && in_array($type, ['int', 'decimal'], true) ? 'max="' . e($o['max']) . '"' : '' ?> <?= isset($o['max']) && $type === 'text' ? 'maxlength="' . (int)$o['max'] . '"' : '' ?>>
      <?php endif; ?>
      <?php if ($help): ?><div class="help"><?= e(__($help)) ?></div><?php endif; ?></div>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if ($mod === 'servicios'): ?>
    <div class="fieldset"><legend><?= e(__('%s que lo ofrecen', term('professionals'))) ?></legend><div class="checklist">
      <?php foreach ($profs as $p): ?><label class="check"><input type="checkbox" name="profs[<?= (int)$p['id'] ?>]" value="1" <?= chk($p['linked'] !== null || !$id) ?>><span><?= e($p['name']) ?></span></label><?php endforeach; ?>
      <?php if (!$profs): ?><span class="hint"><?= e(__('Aún no hay profesionales.')) ?></span><?php endif; ?></div></div>
  <?php endif; ?>
  <button class="btn btn-gold" type="submit"><?= e(__('Guardar')) ?></button> <a class="btn btn-ghost" href="<?= e(url('/admin/' . $mod)) ?>"><?= e(__('Cancelar')) ?></a>
</form>
