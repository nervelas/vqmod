<div class="page-head"><div><p class="eyebrow">Ajustes</p><h1><?= e($def['title']) ?></h1></div></div>
<form method="post" enctype="multipart/form-data" class="card form"><?= csrf_field() ?>
<?php foreach ($def['fields'] as $k => $f): [$lbl, $type] = $f; $help = $f[2] ?? ''; $v = S($k, '');
  if ($type === 'text' || $type === 'number') echo f_input($k, $lbl, $v, $type === 'number' ? 'number' : 'text');
  elseif ($type === 'textarea') echo f_area($k, $lbl, $v, 3);
  elseif ($type === 'color') echo f_input($k, $lbl, $v ?: '#C9A24B', 'color');
  elseif ($type === 'secret') { echo f_input($k, $lbl . ($v !== '' ? ' (ya guardada: déjala vacía para conservarla)' : ''), '', 'password', 'autocomplete="new-password"'); if ($v !== '') echo f_check($k . '_clear', 'Borrar la clave guardada', false); }
  elseif (str_starts_with($type, 'select:')) { $o = []; foreach (explode(',', substr($type, 7)) as $p) { [$a, $b] = explode('=', $p, 2); $o[$a] = $b; } echo f_select($k, $lbl, $o, $v !== '' ? $v : array_key_first($o)); }
  if ($help) echo '<p class="fine">' . e($help) . '</p>';
endforeach; ?>
<?php if ($sec === 'sitio'): ?><label>Logo (PNG/JPG/WebP, máx. 1 MB)<input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp"></label><?php if (S('logo')): ?><p class="fine">Logo actual: <img src="<?= e(url('archivo/logo')) ?>" alt="Logo" height="28" style="vertical-align:middle"></p><?php endif; endif; ?>
<button class="btn btn-gold">Guardar cambios</button>
</form>
