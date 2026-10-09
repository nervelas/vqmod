<?php /** Vars: $rows $total $page $per $status $q $plan */
use S5\Services\Orders;
$qs = fn(array $o) => '?' . http_build_query(array_filter(array_merge(['estado' => $status, 'q' => $q, 'plan' => $plan, 'p' => $page], $o), fn($v) => $v !== '' && $v !== null)); ?>
<h1>Pedidos <span class="mut" style="font-size:1rem">(<?= (int) $total ?>)</span></h1>
<form class="card row" method="get">
<div><label for="q">Buscar</label><input id="q" name="q" value="<?= e($q) ?>" placeholder="Nombre, dominio, correo, teléfono"></div>
<div><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Activos</option><option value="todos"<?= $status === 'todos' ? ' selected' : '' ?>>Todos (incluye eliminados)</option>
<?php foreach (Orders::LABELS as $k => $l): ?><option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
<div><label for="plan">Plan</label><select id="plan" name="plan"><option value="">Todos</option><option value="info"<?= $plan === 'info' ? ' selected' : '' ?>>Informativa</option><option value="tienda"<?= $plan === 'tienda' ? ' selected' : '' ?>>Tienda</option></select></div>
<div style="flex:0 0 auto"><button class="btn">Filtrar</button></div></form>
<table class="resp"><thead><tr><th>#</th><th>Negocio</th><th>Estado</th><th>Plan</th><th>Actualizado</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
<td data-l="#"><a href="/admin/pedido/<?= (int) $r['id'] ?>"><?= (int) $r['id'] ?></a></td>
<td data-l="Negocio"><a href="/admin/pedido/<?= (int) $r['id'] ?>"><?= e($r['business_name'] ?: '(sin nombre)') ?></a><?= $r['is_demo'] ? ' <span class="tag">demo</span>' : '' ?><br><span class="mut"><?= e($r['fqdn']) ?></span></td>
<td data-l="Estado"><span class="tag <?= e($r['status']) ?>"><?= e(Orders::LABELS[$r['status']] ?? $r['status']) ?></span></td>
<td data-l="Plan"><?= $r['plan'] === 'tienda' ? 'Tienda' : 'Informativa' ?></td>
<td data-l="Actualizado"><?= e(substr((string) $r['updated_at'], 0, 16)) ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="mut">No hay pedidos con esos filtros.</td></tr><?php endif; ?></tbody></table>
<p class="actions"><?php if ($page > 1): ?><a class="btn sec" href="<?= e($qs(['p' => $page - 1])) ?>">← Anterior</a><?php endif; ?><?php if ($page * $per < $total): ?><a class="btn sec" href="<?= e($qs(['p' => $page + 1])) ?>">Siguiente →</a><?php endif; ?></p>
