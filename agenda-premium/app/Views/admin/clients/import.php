<?php /** @var ?array $preview @var string $error */
$labels = ['nuevo' => ['Nuevo', 'badge-ok'], 'actualizar' => ['Se actualiza', 'badge-gold'], 'error' => ['Con error', 'badge-err'], 'duplicado' => ['Repetido', 'badge-muted']];
?>
<div class="page">
  <div class="page-head"><div>
    <p class="crumbs muted"><a href="<?= e(url('/admin/clientes')) ?>">Clientes</a> / Importar</p>
    <h1 class="page-title serif">Importar clientes</h1>
    <p class="page-sub">Sube un archivo CSV (desde Excel: Guardar como → CSV). Verás una vista previa antes de guardar nada.</p>
  </div></div>
  <?php if ($error !== '') : ?><div class="alert alert-err" role="alert"><?= e($error) ?></div><?php endif; ?>

  <?php if ($preview === null) : ?>
    <form class="card" method="post" action="<?= e(url('/admin/clientes/importar')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="card-body stack">
        <div class="field"><label for="archivo">Archivo CSV</label><input class="input" id="archivo" type="file" name="archivo" accept=".csv,.txt,text/csv" required>
          <p class="hint">Máximo 2 MB y 2,000 filas. La primera fila debe tener encabezados: <strong>Nombre</strong> (obligatorio), Correo, Teléfono, NIT, Etiquetas, Origen.</p></div>
        <p class="muted small">Si una persona ya existe (mismo correo o teléfono) solo se completan sus datos vacíos y se suman las etiquetas; nunca se borra nada.</p>
      </div>
      <div class="card-foot form-actions"><a class="btn btn-ghost" href="<?= e(url('/admin/clientes')) ?>">Cancelar</a><button class="btn btn-gold" type="submit"><?= icon('upload') ?>Ver vista previa</button></div>
    </form>
  <?php else : $pc = $preview['counts']; ?>
    <div class="kpis">
      <?php foreach ($labels as $k => [$lbl, $cls]) : ?><div class="stat"><span class="stat-label"><?= e($lbl) ?></span><span class="stat-value serif"><?= (int) $pc[$k] ?></span></div><?php endforeach; ?>
    </div>
    <div class="card">
      <div class="card-head"><h2 class="serif">Vista previa</h2><p class="muted small">Mostrando <?= count($preview['rows']) ?> de <?= (int) $preview['total'] ?> filas.</p></div>
      <div class="table-wrap"><table class="table table-sm">
        <caption class="sr-only">Vista previa de la importación</caption>
        <thead><tr><th scope="col">Fila</th><th scope="col">Nombre</th><th scope="col" class="hide-sm">Correo</th><th scope="col" class="hide-sm">Teléfono</th><th scope="col">Resultado</th></tr></thead>
        <tbody><?php foreach ($preview['rows'] as $r) : [$lbl, $cls] = $labels[$r['status']]; ?>
          <tr><td class="mono"><?= (int) $r['line'] ?></td><td><?= e($r['name']) ?></td><td class="hide-sm"><?= e($r['email']) ?></td><td class="hide-sm mono"><?= e($r['phone']) ?></td><td><span class="badge <?= e($cls) ?>"><?= e($lbl) ?></span> <span class="muted small"><?= e($r['msg']) ?></span></td></tr>
        <?php endforeach; ?></tbody></table></div>
      <form class="card-foot form-actions" method="post" action="<?= e(url('/admin/clientes/importar/confirmar')) ?>">
        <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($preview['token']) ?>">
        <a class="btn btn-ghost" href="<?= e(url('/admin/clientes/importar')) ?>">Elegir otro archivo</a>
        <?php if ($pc['nuevo'] + $pc['actualizar'] > 0) : ?><button class="btn btn-gold" type="submit"><?= icon('check') ?>Importar <?= (int) ($pc['nuevo'] + $pc['actualizar']) ?> filas válidas</button>
        <?php else : ?><span class="muted">No hay filas válidas para importar.</span><?php endif; ?>
      </form>
    </div>
  <?php endif; ?>
</div>
