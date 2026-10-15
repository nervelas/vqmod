<?php $e0 = $edit; ?>
<div class="page-head"><div><p class="eyebrow">Administración</p><h1>Planes</h1></div></div>
<div class="tbl-wrap"><table class="tbl"><tr><th>Plan</th><th>Precio</th><th>Días</th><th>Palabras</th><th>Alertas</th><th>Clientes</th><th>Estado</th><th></th></tr>
<?php foreach ($list as $p): ?><tr><td data-l="Plan"><strong><?= e($p['name']) ?></strong><?= $p['highlight'] ? ' ★' : '' ?></td><td class="mono" data-l="Precio"><?= e(money($p['price'])) ?></td><td class="mono" data-l="Días"><?= (int) $p['period_days'] ?></td><td class="mono" data-l="Palabras"><?= (int) $p['kw_limit'] ?></td><td class="mono" data-l="Alertas"><?= (int) $p['alert_limit'] ?></td><td class="mono" data-l="Clientes"><?= (int) $p['nusers'] ?></td><td data-l="Estado"><?= $p['active'] ? 'Activo' : 'Oculto' ?></td>
<td class="btn-row"><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/planes?e=' . $p['id'])) ?>">Editar</a><form method="post" action="<?= e(url('admin/planes/' . $p['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar el plan?"><?= csrf_field() ?><button class="btn btn-danger btn-sm">Eliminar</button></form></td></tr><?php endforeach; ?></table></div>
<form method="post" action="<?= e(url('admin/planes/guardar')) ?>" class="card form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($e0['id'] ?? 0) ?>">
  <h2><?= $e0 ? 'Editar plan' : 'Nuevo plan' ?></h2>
  <div class="two-col"><?= f_input('name', 'Nombre', $e0['name'] ?? '', 'text', 'required') ?><?= f_input('price', 'Precio (Q)', $e0['price'] ?? '', 'text', 'inputmode="decimal" required') ?>
  <?= f_input('period_days', 'Duración (días)', $e0['period_days'] ?? 30, 'number') ?><?= f_input('sort_order', 'Orden', $e0['sort_order'] ?? 0, 'number') ?>
  <?= f_input('kw_limit', 'Límite de palabras clave', $e0['kw_limit'] ?? 10, 'number') ?><?= f_input('alert_limit', 'Límite de alertas por mes', $e0['alert_limit'] ?? 100, 'number') ?></div>
  <?= f_area('features', 'Funciones (una por línea)', $e0['features'] ?? '', 5) ?>
  <?= f_input('pay_link', 'Enlace de pago externo (opcional)', $e0['pay_link'] ?? '', 'url') ?>
  <div class="btn-row"><?= f_check('highlight', 'Destacar como "más elegido"', (bool) ($e0['highlight'] ?? 0)) ?><?= f_check('active', 'Visible', (bool) ($e0['active'] ?? 1)) ?></div>
  <button class="btn btn-gold">Guardar plan</button>
</form>
