<div class="page-head"><div><p class="eyebrow">Administración</p><h1>Clientes <small class="muted">(<?= $tot ?>)</small></h1></div><a class="btn btn-gold btn-sm" href="<?= e(url('admin/clientes/nuevo')) ?>">Nuevo cliente</a></div>
<form class="filters" method="get" action="<?= e(url('admin/clientes')) ?>"><?php if (!cfg('clean_urls', true) || S('clean_urls', '1') === '0'): ?><input type="hidden" name="r" value="admin/clientes"><?php endif; ?>
  <input name="q" value="<?= e(get('q')) ?>" placeholder="Buscar nombre, correo o teléfono" aria-label="Buscar">
  <select name="f" aria-label="Filtro"><?php foreach (['' => 'Todos', 'activos' => 'Con acceso', 'prueba' => 'En prueba', 'vencidos' => 'Vencidos', 'suspendidos' => 'Suspendidos'] as $k => $l): ?><option value="<?= $k ?>" <?= get('f') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
  <button class="btn btn-ghost btn-sm">Filtrar</button></form>
<div class="tbl-wrap"><table class="tbl"><tr><th>Cliente</th><th>Plan</th><th>Vence</th><th>Coincid.</th><th>Estado</th><th></th></tr>
<?php foreach ($list as $c): $ok = $c['sub_ends'] && strtotime($c['sub_ends']) >= time(); ?>
<tr><td data-l="Cliente"><strong><?= e($c['name']) ?></strong><br><small class="muted"><?= e($c['email']) ?> · <?= e($c['phone']) ?></small></td><td data-l="Plan"><?= e($c['plan_name'] ?: 'Prueba') ?></td><td class="mono" data-l="Vence"><?= e(fdate($c['sub_ends'])) ?></td><td class="mono" data-l="Coincid."><?= (int) $c['nmatch'] ?></td>
<td data-l="Estado"><span class="pill <?= $c['status'] === 'suspendido' ? 'p-urgent' : ($ok ? 'p-new' : 'p-soon') ?>"><?= $c['status'] === 'suspendido' ? 'Suspendido' : ($ok ? 'Activo' : 'Vencido') ?></span></td><td><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/clientes/' . $c['id'])) ?>">Abrir</a></td></tr>
<?php endforeach; if (!$list): ?><tr><td colspan="6" class="muted">No hay clientes con ese filtro.</td></tr><?php endif; ?></table></div>
<?= pager($page, $per, $tot, 'admin/clientes') ?>
