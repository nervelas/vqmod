<div class="page-head"><div><p class="eyebrow">Administración</p><h1>Concursos <small class="muted">(<?= $tot ?>)</small></h1></div>
<div class="btn-row"><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/concursos/importar')) ?>">Importar CSV</a><a class="btn btn-gold btn-sm" href="<?= e(url('admin/concursos/nuevo')) ?>">Nuevo concurso</a></div></div>
<form class="filters" method="get" action="<?= e(url('admin/concursos')) ?>"><?php if (S('clean_urls', '1') === '0'): ?><input type="hidden" name="r" value="admin/concursos"><?php endif; ?>
  <input name="q" value="<?= e(get('q')) ?>" placeholder="Buscar título, entidad o NOG" aria-label="Buscar">
  <select name="st" aria-label="Estado"><?php foreach (['' => 'Todos', 'abierto' => 'Abiertos', 'adjudicado' => 'Adjudicados', 'cerrado' => 'Cerrados', 'desierto' => 'Desiertos'] as $k => $l): ?><option value="<?= $k ?>" <?= get('st') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
  <label class="check"><input type="checkbox" name="demo" value="1" <?= get('demo') ? 'checked' : '' ?>> <span>Solo DEMO</span></label>
  <button class="btn btn-ghost btn-sm">Filtrar</button></form>
<div class="tbl-wrap"><table class="tbl"><tr><th>Concurso</th><th>Monto</th><th>Vence</th><th>Estado</th><th></th></tr>
<?php foreach ($list as $t): ?><tr><td data-l="Concurso"><strong><?= e(mb_substr($t['title'], 0, 90)) ?></strong> <?= $t['is_demo'] ? '<span class="badge b-demo">DEMO</span>' : '' ?><br><small class="muted"><?= e($t['entity']) ?> · <?= e($t['ext_id']) ?></small></td><td class="mono" data-l="Monto"><?= e(money($t['status'] === 'adjudicado' ? $t['awarded_amount'] : $t['amount'])) ?></td><td class="mono" data-l="Vence"><?= e(fdate($t['deadline'])) ?></td><td data-l="Estado"><?= e(ucfirst($t['status'])) ?></td><td><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/concursos/' . $t['id'])) ?>">Editar</a></td></tr>
<?php endforeach; if (!$list) echo '<tr><td colspan="5" class="muted">No hay concursos con ese filtro.</td></tr>'; ?></table></div>
<?= pager($page, $per, $tot, 'admin/concursos') ?>
<form method="post" action="<?= e(url('admin/concursos/borrar-demo')) ?>" data-confirm="¿Borrar TODOS los concursos DEMO? Hazlo cuando ya tengas datos reales."><?= csrf_field() ?><button class="btn btn-danger btn-sm">Borrar todos los concursos DEMO</button></form>
