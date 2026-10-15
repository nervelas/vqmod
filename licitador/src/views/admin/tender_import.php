<p><a class="back" href="<?= e(url('admin/concursos')) ?>">← Concursos</a></p>
<div class="page-head"><div><p class="eyebrow">Importación manual</p><h1>Importar concursos desde CSV</h1></div></div>
<?php if ($step === 1): ?>
<form method="post" enctype="multipart/form-data" class="card form narrow-form"><?= csrf_field() ?>
  <p class="muted">Sube un CSV (UTF-8 o Latin-1; separador coma, punto y coma o tab) con encabezados. En el siguiente paso mapeas las columnas. Si el identificador ya existe, el concurso se actualiza.</p>
  <label>Archivo CSV (máx. 20 MB)<input type="file" name="csv" accept=".csv,.txt" required></label>
  <button class="btn btn-gold">Continuar</button>
</form>
<?php else: ?>
<form method="post" action="<?= e(url('admin/concursos/importar/aplicar')) ?>" class="card form"><?= csrf_field() ?><input type="hidden" name="file" value="<?= e($file) ?>">
  <h2>Mapeo de columnas (<?= (int) $nrows ?> filas)</h2>
  <div class="two-col"><?php foreach (csv_fields() as $k => $lbl): ?>
    <label><?= e($lbl) ?><?= $k === 'title' ? ' *' : '' ?><select name="map[<?= e($k) ?>]"><option value="">— no usar —</option><?php foreach ($header as $h): ?><option value="<?= e($h) ?>" <?= ($auto[$k] ?? '') === $h ? 'selected' : '' ?>><?= e($h) ?></option><?php endforeach; ?></select></label>
  <?php endforeach; ?></div>
  <h3>Vista previa</h3><div class="tbl-wrap"><table class="tbl"><tr><?php foreach ($header as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr><?php foreach ($sample as $r): ?><tr><?php foreach ($r as $c): ?><td><?= e(mb_substr((string) $c, 0, 40)) ?></td><?php endforeach; ?></tr><?php endforeach; ?></table></div>
  <?= f_check('guardar_mapa', 'Guardar este mapeo como predeterminado (para la fuente CSV por URL)', false) ?>
  <button class="btn btn-gold">Importar ahora</button>
</form>
<?php endif; ?>
