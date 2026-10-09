<?php /** Vars: $rows $page */ ?>
<h1>Bitácora</h1>
<table class="resp"><thead><tr><th>Fecha (UTC)</th><th>Quién</th><th>Acción</th><th>Detalle</th><th>Pedido</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td data-l="Fecha"><?= e($r['created_at']) ?></td><td data-l="Quién"><?= e($r['actor']) ?></td><td data-l="Acción"><?= e($r['action']) ?></td><td data-l="Detalle"><?= e($r['detail']) ?></td><td data-l="Pedido"><?php if ($r['order_id']): ?><a href="/admin/pedido/<?= (int) $r['order_id'] ?>">#<?= (int) $r['order_id'] ?></a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table>
<p class="actions"><?php if ($page > 1): ?><a class="btn sec" href="?p=<?= $page - 1 ?>">← Anterior</a><?php endif; ?><?php if (count($rows) === 100): ?><a class="btn sec" href="?p=<?= $page + 1 ?>">Siguiente →</a><?php endif; ?></p>
