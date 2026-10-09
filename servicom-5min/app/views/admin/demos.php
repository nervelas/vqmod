<?php /** Vars: $rows */
use S5\Services\Brief; use S5\Services\Orders; ?>
<h1>Demos</h1>
<form class="card" method="post" action="/admin/demos"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<p class="mut">Crea una web de ejemplo (datos ficticios) que se muestra en la sección «Ejemplos» del portal.</p>
<div class="row"><div><label for="plan">Plan</label><select id="plan" name="plan"><option value="info">Página informativa</option><option value="tienda">Tienda virtual</option></select></div>
<div><label for="rubro">Rubro</label><select id="rubro" name="rubro"><?php foreach (Brief::RUBROS as $r): ?><option value="<?= e($r) ?>"><?= e(ucfirst($r)) ?></option><?php endforeach; ?></select></div>
<div><label for="estilo">Estilo</label><select id="estilo" name="estilo"><?php foreach ([1 => 'Oscuro elegante', 2 => 'Claro editorial', 3 => 'Oscuro moderno', 4 => 'Claro clásico', 5 => 'Claro audaz'] as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
</div>
<p><button class="btn">Crear demo</button></p></form>
<table class="resp"><thead><tr><th>Demo</th><th>Estado</th><th>Plan</th></tr></thead><tbody><?php foreach ($rows as $r): ?><tr><td data-l="Demo"><a href="/admin/pedido/<?= (int) $r['id'] ?>"><?= e($r['business_name']) ?></a><br><span class="mut"><?= e($r['fqdn']) ?></span></td><td data-l="Estado"><?= e(Orders::LABELS[$r['status']] ?? $r['status']) ?></td><td data-l="Plan"><?= e($r['plan']) ?></td></tr><?php endforeach; ?></tbody></table>
