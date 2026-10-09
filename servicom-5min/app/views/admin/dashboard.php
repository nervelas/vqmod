<?php /** Vars: $counts $pay $alerts $soon $prep $ai */
use S5\Services\Orders;
$lab = Orders::LABELS; ?>
<h1>Resumen</h1>
<?php if ($alerts): ?><div class="card"><h2 style="margin-top:0">Alertas</h2>
<?php foreach ($alerts as $a): ?><form method="post" action="/admin/alerta/<?= (int) $a['id'] ?>" class="row" style="align-items:center;border-bottom:1px solid var(--line);padding:.4rem 0">
<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<span class="<?= $a['level'] === 'error' ? 'err' : 'warn' ?>" style="flex:3 1 260px"><?= e($a['message']) ?> <?php if ($a['order_id']): ?><a href="/admin/pedido/<?= (int) $a['order_id'] ?>">ver pedido</a><?php endif; ?></span>
<button class="btn sec" style="flex:0 0 auto">Listo</button></form><?php endforeach; ?></div><?php endif; ?>
<?php if ($pay): ?><div class="card"><h2 style="margin-top:0">Pagos por revisar</h2>
<?php foreach ($pay as $p): ?><p><a class="btn" href="/admin/pedido/<?= (int) $p['id'] ?>">Revisar</a> <?= e($p['business_name'] ?: '#' . $p['id']) ?> <span class="mut">· <?= e($p['plan'] === 'tienda' ? 'Tienda' : 'Informativa') ?> · <?= e($p['fqdn']) ?></span></p><?php endforeach; ?></div><?php endif; ?>
<?php if ($prep): ?><div class="card"><h2 style="margin-top:0">Vistas previas que requieren atención</h2>
<?php foreach ($prep as $p): ?><p><a href="/admin/pedido/<?= (int) $p['id'] ?>"><?= e($p['business_name'] ?: '#' . $p['id']) ?></a> <span class="mut"><?= e($p['fqdn']) ?></span></p><?php endforeach; ?></div><?php endif; ?>
<div class="grid">
<?php foreach ($lab as $k => $l): if ($k === 'eliminada') { continue; } ?><a class="stat" href="/admin/pedidos?estado=<?= e($k) ?>"><b><?= (int) ($counts[$k] ?? 0) ?></b><span><?= e($l) ?></span></a><?php endforeach; ?>
</div>
<?php if ($soon): ?><h2>Renovaciones próximas (30 días)</h2><div class="card"><?php foreach ($soon as $s): ?><p><a href="/admin/pedido/<?= (int) $s['id'] ?>"><?= e($s['business_name'] ?: $s['fqdn']) ?></a> — vence <b><?= e($s['renewal_at']) ?></b></p><?php endforeach; ?></div><?php endif; ?>
<h2>Gasto de IA (estimado)</h2>
<div class="card"><dl class="kv">
<dt>Hoy</dt><dd>$<?= number_format((float) ($ai['dia']['total'] ?? 0), 4) ?></dd>
<dt>Total</dt><dd>$<?= number_format((float) ($ai['total']['total'] ?? 0), 4) ?></dd>
</dl><p><a class="btn sec" href="/admin/ia">Ver detalle</a></p></div>
