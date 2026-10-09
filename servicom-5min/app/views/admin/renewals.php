<?php /** Vars: $rows */ use S5\Services\Orders; ?>
<h1>Renovaciones</h1>
<table class="resp"><thead><tr><th>Cliente</th><th>Vence</th><th>Estado</th><th>Contacto</th></tr></thead><tbody>
<?php foreach ($rows as $r): $days = (int) floor((strtotime($r['renewal_at'] . ' UTC') - time()) / 86400); ?>
<tr><td data-l="Cliente"><a href="/admin/pedido/<?= (int) $r['id'] ?>"><?= e($r['business_name'] ?: $r['fqdn']) ?></a></td>
<td data-l="Vence"><b class="<?= $days < 0 ? 'err' : ($days <= 30 ? 'warn' : '') ?>"><?= e($r['renewal_at']) ?></b> <span class="mut">(<?= $days < 0 ? 'vencida hace ' . abs($days) : 'en ' . $days ?> días)</span></td>
<td data-l="Estado"><?= e(Orders::LABELS[$r['status']] ?? $r['status']) ?></td><td data-l="Contacto"><?= e($r['client_email']) ?> <?= e($r['client_phone']) ?></td></tr>
<?php endforeach; ?></tbody></table>
