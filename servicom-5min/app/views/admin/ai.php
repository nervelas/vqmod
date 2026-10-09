<?php /** Vars: $sum $recent $capDay $capTotal */
$row = fn($a) => number_format((float) ($a ?? 0), 4); ?>
<h1>Consumo de IA</h1>
<div class="grid">
<div class="stat"><b>$<?= $row($sum['dia']['total'] ?? 0) ?></b><span>Hoy (tope $<?= number_format($capDay, 2) ?>)</span></div>
<div class="stat"><b>$<?= $row($sum['total']['total'] ?? 0) ?></b><span>Total (tope $<?= number_format($capTotal, 2) ?>)</span></div>
<div class="stat"><b>$<?= $row($sum['total']['redaccion'] ?? 0) ?></b><span>Redacción de textos</span></div>
<div class="stat"><b>$<?= $row($sum['total']['analisis'] ?? 0) ?></b><span>Análisis de presentaciones</span></div></div>
<p class="mut">Estimado según tokens y precios configurados en Ajustes. Al llegar a un tope, el sistema usa textos base automáticamente.</p>
<table class="resp"><thead><tr><th>Fecha</th><th>Tipo</th><th>Modelo</th><th>Tokens</th><th>Costo</th><th>OK</th></tr></thead><tbody>
<?php foreach ($recent as $r): ?><tr><td data-l="Fecha"><?= e($r['created_at']) ?></td><td data-l="Tipo"><?= e($r['kind']) ?></td><td data-l="Modelo"><?= e($r['model']) ?></td><td data-l="Tokens"><?= (int) $r['tokens_in'] ?> / <?= (int) $r['tokens_out'] ?></td><td data-l="Costo">$<?= e($r['cost_usd']) ?></td><td data-l="OK"><?= $r['ok'] ? 'sí' : 'no' ?></td></tr><?php endforeach; ?></tbody></table>
