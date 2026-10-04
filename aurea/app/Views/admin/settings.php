<div class="tabs" role="tablist"><?php foreach ($tabs as $k => $l): ?><a href="<?= e(url('/admin/ajustes?tab=' . $k)) ?>" <?= $tab === $k ? 'aria-current="page"' : '' ?>><?= e(__($l)) ?></a><?php endforeach; ?></div>
<form method="post" action="<?= e(url('/admin/ajustes')) ?>" enctype="multipart/form-data" class="card form-wide">
  <?= csrf_field() ?><input type="hidden" name="tab" value="<?= e($tab) ?>">
  <?php if ($tab === 'marca'): ?>
    <div class="fieldset"><legend><?= e(__('Imágenes')) ?></legend>
      <?php foreach (['logo' => 'Logo (PNG/WebP con fondo transparente recomendado)', 'favicon' => 'Favicon (cuadrado)', 'hero_image' => 'Fotografía de la portada (a la derecha del titular)'] as $f => $l): $cur = (string)setting($f, ''); ?>
        <div class="field"><label for="<?= e($f) ?>"><?= e(__($l)) ?></label><input id="<?= e($f) ?>" type="file" name="<?= e($f) ?>" accept="image/jpeg,image/png,image/webp"><?php if ($cur !== ''): ?><div class="help"><img src="<?= e(url('uploads/' . rawurlencode($cur))) ?>" alt="" height="48" style="display:inline-block;max-height:48px;width:auto;vertical-align:middle"> <label class="check" style="display:inline-flex;margin-left:12px"><input type="checkbox" name="<?= e($f) ?>_clear" value="1"><span><?= e(__('Quitar')) ?></span></label></div><?php endif; ?></div>
      <?php endforeach; ?><p class="hint"><?= e(__('Sin fotografía propia, la portada muestra una composición elegante con la inicial de tu negocio. Las imágenes se optimizan automáticamente.')) ?></p></div>
  <?php endif; ?>
  <?php foreach ($schema as $k => [$type, $o]): $v = $vals[$k]; $label = __($o['label'] ?? $k); ?>
    <?php if ($type === 'bool'): ?><div class="field"><label class="check"><input type="checkbox" name="<?= e($k) ?>" value="1" <?= chk($v === '1' || $v === 1) ?>><span><?= e($label) ?></span></label></div>
    <?php else: ?><div class="field"><label for="s_<?= e($k) ?>" class="<?= !empty($o['required']) ? 'req' : '' ?>"><?= e($label) ?></label>
      <?php if ($type === 'textarea'): ?><textarea id="s_<?= e($k) ?>" name="<?= e($k) ?>" style="min-height:<?= (int)($o['rows'] ?? 4) * 26 ?>px"><?= e($v) ?></textarea>
      <?php elseif ($type === 'select'): ?><select id="s_<?= e($k) ?>" name="<?= e($k) ?>"><?php foreach ($o['options'] as $ov => $ol): ?><option value="<?= e($ov) ?>"<?= sel($ov, $v) ?>><?= e($ol) ?></option><?php endforeach; ?></select>
      <?php elseif ($type === 'secret'): ?><input id="s_<?= e($k) ?>" name="<?= e($k) ?>" type="password" autocomplete="new-password" value="<?= e($v) ?>"><div class="help"><label class="check" style="margin:4px 0 0"><input type="checkbox" name="<?= e($k) ?>_clear" value="1"><span><?= e(__('Borrar valor guardado')) ?></span></label></div>
      <?php elseif ($type === 'color'): ?><div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap"><input id="s_<?= e($k) ?>" name="<?= e($k) ?>" type="color" value="<?= e(Aurea\Core\Util::isColor((string)$v) ? $v : ['color_gold' => '#B8924A', 'color_ink' => '#0B0A08', 'color_ivory' => '#F6F0E4'][$k] ?? '#B8924A') ?>" style="width:70px;padding:3px"><label class="check" style="margin:0"><input type="checkbox" name="<?= e($k) ?>_default" value="1" <?= chk($v === '') ?>><span><?= e(__('Usar el color predeterminado de Maison Aurea')) ?></span></label></div>
      <?php else: ?><input id="s_<?= e($k) ?>" name="<?= e($k) ?>" type="<?= ['int' => 'number', 'email' => 'email', 'url' => 'url'][$type] ?? 'text' ?>" value="<?= e($v) ?>" <?= $type === 'int' ? 'min="' . (int)($o['min'] ?? 0) . '" max="' . (int)($o['max'] ?? 999999) . '"' : '' ?>><?php endif; ?></div>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if ($tab === 'comunicacion'): ?><div class="alert"><?= e(__('WhatsApp Business Cloud API es opcional y requiere una cuenta de Meta del propio negocio, un número verificado y plantillas aprobadas por Meta (asigna el nombre aprobado a cada plantilla en Plantillas de mensajes). Sin él, usa el centro "Mensajes por enviar hoy" (wa.me, sin costo).')) ?></div><?php endif; ?>
  <button class="btn btn-gold" type="submit"><?= e(__('Guardar cambios')) ?></button>
</form>
<?php if ($tab === 'terminologia'): ?>
<form method="post" action="<?= e(url('/admin/ajustes/perfil-profesion')) ?>" class="card form-wide" data-confirm="<?= e(__('Se aplicará el perfil elegido. ¿Continuar?')) ?>">
  <?= csrf_field() ?><h3><?= e(__('Cargar un perfil de profesión')) ?></h3><p class="hint"><?= e(__('Cambia terminología, textos de portada y agrega servicios, formulario y plantillas sugeridos para esa profesión.')) ?></p>
  <div class="field"><label for="pf"><?= e(__('Profesión')) ?></label><select id="pf" name="preset"><?php foreach ($presets as $k => $l): ?><option value="<?= e($k) ?>"<?= sel($k, $current) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <label class="check"><input type="checkbox" name="replace" value="1"><span><?= e(__('Reemplazar servicios, categorías y formulario actuales que no tengan citas')) ?></span></label>
  <button class="btn btn-ink" type="submit"><?= e(__('Aplicar perfil')) ?></button>
</form>
<?php endif; ?>
