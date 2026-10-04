<form method="get" class="toolbar">
  <div class="field grow"><label for="q"><?= e(__('Buscar')) ?></label><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('Nombre, teléfono, correo, NIT o etiqueta')) ?>"></div>
  <div class="field"><label for="f"><?= e(__('Filtro')) ?></label><select id="f" name="f" data-autosubmit><option value=""><?= e(__('Todos')) ?></option><option value="noshow"<?= sel('noshow', $filter) ?>><?= e(__('Con inasistencias')) ?></option><option value="blocked"<?= sel('blocked', $filter) ?>><?= e(__('Bloqueados')) ?></option></select></div>
  <button class="btn btn-ink" type="submit"><?= e(__('Buscar')) ?></button>
  <a class="btn btn-gold" href="<?= e(url('/admin/clientes/nuevo')) ?>">+ <?= e(__('Nuevo')) ?></a>
  <a class="btn btn-line" href="<?= e(url('/admin/clientes/exportar')) ?>"><?= e(__('Exportar CSV')) ?></a>
</form>
<?php if (\Aurea\Core\Auth::role() === 'admin'): ?>
<details class="card"><summary style="cursor:pointer;font-weight:600"><?= e(__('Importar desde CSV')) ?></summary>
  <form method="post" action="<?= e(url('/admin/clientes/importar')) ?>" enctype="multipart/form-data" style="margin-top:14px"><?= csrf_field() ?>
    <p class="hint"><?= e(__('Columnas: nombre, teléfono, correo, NIT, etiquetas (la primera fila puede ser el encabezado). Los teléfonos repetidos se omiten.')) ?></p>
    <div class="field"><input type="file" name="csv" accept=".csv,text/csv" required aria-label="CSV"></div><button class="btn btn-ink btn-sm" type="submit"><?= e(__('Importar')) ?></button></form></details>
<?php endif; ?>
<?php if (!$rows): ?><div class="card empty-state"><div class="big"><?= e(__('Aún no hay %s', mb_strtolower(term('clients')))) ?></div><p><?= e(__('Aparecerán aquí cuando reserven o los agregues.')) ?></p></div><?php else: ?>
<div class="tbl-wrap"><table class="tbl"><thead><tr><th><?= e(__('Nombre')) ?></th><th><?= e(__('Teléfono')) ?></th><th><?= e(__('Correo')) ?></th><th><?= e(__('Visitas')) ?></th><th><?= e(__('Última cita')) ?></th><th><?= e(__('Etiquetas')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $c): ?><tr>
  <td><a class="strong" href="<?= e(url('/admin/clientes/' . $c['id'])) ?>"><?= e($c['name']) ?></a><?php if ($c['blocked']): ?> <span class="badge badge-bad"><?= e(__('Bloqueado')) ?></span><?php endif; ?><?php if ($c['noshow_count'] > 0): ?> <span class="badge badge-warn"><?= (int)$c['noshow_count'] ?> no asistió</span><?php endif; ?></td>
  <td>+<?= e($c['phone_cc']) ?> <?= e($c['phone']) ?></td><td><?= e($c['email']) ?></td><td class="num"><?= (int)$c['visits'] ?></td><td><?= e(fdate($c['last_at'])) ?></td><td><?= e($c['tags']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php include __DIR__ . '/../partials/pager.php'; endif; ?>
