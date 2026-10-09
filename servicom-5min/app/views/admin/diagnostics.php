<?php /** Vars: $d $test */ $csrf = '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; ?>
<h1>Diagnóstico</h1>
<?php if ($test): ?><div class="flash <?= $test['ok'] ? 'ok' : 'err' ?>"><?= e($test['msg']) ?></div><?php endif; ?>
<div class="actions"><form method="post" action="/admin/diagnostico"><?= $csrf ?><input type="hidden" name="accion" value="ia"><button class="btn sec">Probar la API de IA</button></form>
<form method="post" action="/admin/diagnostico"><?= $csrf ?><input type="hidden" name="accion" value="cron"><button class="btn sec">Ejecutar el cron ahora</button></form></div>
<?php foreach ($d as $group => $items): ?><h2><?= e($group) ?></h2><div class="card"><?php foreach ($items as $i): ?>
<p style="margin:.2rem 0"><span class="<?= $i['ok'] === true ? 'pill-ok' : ($i['ok'] === false ? 'pill-no' : 'pill-na') ?>"></span><?= e($i['label']) ?><?php if ($i['detail'] !== ''): ?> <span class="mut">— <?= e($i['detail']) ?></span><?php endif; ?></p>
<?php endforeach; ?></div><?php endforeach; ?>
