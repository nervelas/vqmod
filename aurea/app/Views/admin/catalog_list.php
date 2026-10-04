<?php
$labels = ['modality' => ['presencial' => 'Presencial', 'virtual' => 'Virtual', 'domicilio' => 'A domicilio'], 'kind' => ['full' => 'Día completo', 'half' => 'Medio día'], 'scope' => ['national' => 'Nacional', 'city' => 'Ciudad de Guatemala'],
  'ckind' => ['percent' => 'Porcentaje', 'fixed' => 'Monto', 'gift' => 'Regalo'], 'role' => \Aurea\Core\Auth::ROLES, 'ftype' => \Aurea\Services\FormService::TYPES];
?>
<div class="card-head no-print">
  <?php if ($mod === 'feriados'): ?>
    <form method="get" class="toolbar" style="margin:0"><div class="field"><label for="anio"><?= e(__('Año')) ?></label><select id="anio" name="anio" data-autosubmit><?php for ($y = (int)date('Y') - 1; $y <= (int)date('Y') + 3; $y++): ?><option<?= sel($y, $year) ?>><?= $y ?></option><?php endfor; ?></select></div></form>
    <form method="post" action="<?= e(url('/admin/feriados/generar')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="anio" value="<?= (int)$year ?>"><button class="btn btn-line" type="submit"><?= e(__('Generar feriados de Guatemala %d', $year)) ?></button></form>
  <?php elseif ($mod === 'plantillas'): ?>
    <p class="hint" style="margin:0"><?= e(__('Variables: {nombre} {servicio} {fecha} {hora} {profesional} {direccion} {enlace} {negocio} {telefono} {precio}')) ?></p>
    <form method="post" action="<?= e(url('/admin/plantillas/restaurar')) ?>" class="inline-form" data-confirm="<?= e(__('Se perderán tus ediciones de todas las plantillas. ¿Continuar?')) ?>"><?= csrf_field() ?><button class="btn btn-line" type="submit"><?= e(__('Restaurar textos originales')) ?></button></form>
  <?php else: ?><span></span><?php endif; ?>
  <?php if (empty($cfg['no_create'])): ?><a class="btn btn-gold" href="<?= e(url('/admin/' . $mod . '/nuevo')) ?>">+ <?= e(__('Nuevo')) ?> <?= e($cfg['singular']) ?></a><?php endif; ?>
</div>
<?php if (!$rows): ?><div class="card empty-state"><div class="big"><?= e(__('Todavía no hay registros')) ?></div><p><?= e(__('Crea el primero con el botón de arriba.')) ?></p></div><?php else: ?>
<div class="tbl-wrap"><table class="tbl"><thead><tr><?php foreach ($cfg['columns'] as $c): ?><th><?= e(__($c[1])) ?></th><?php endforeach; ?><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
  <?php foreach ($cfg['columns'] as $i => $col): $k = $col[0]; $t = $col[2] ?? 'text'; $v = $r[$k] ?? ''; ?>
    <td class="<?= in_array($t, ['money', 'num', 'int'], true) ? 'num' : '' ?>"><?php
      if ($i === 0) { echo '<a class="strong" href="' . e(url('/admin/' . $mod . '/' . $r['id'])) . '">' . e($v) . '</a>'; }
      elseif ($t === 'money') { echo e(money($v)); }
      elseif ($t === 'active') { echo '<span class="badge ' . ($v ? 'badge-ok' : '') . '">' . e($v ? __('Activo') : __('Inactivo')) . '</span>'; }
      elseif ($t === 'bool') { echo e($v ? __('Sí') : __('No')); }
      elseif ($t === 'date') { echo e(fdate((string)$v)); }
      elseif ($t === 'datetime') { echo e(fdatetime((string)$v)); }
      elseif ($t === 'proname') { echo e($v ?: __('Todos')); }
      elseif ($t === 'svc') { echo e($v ?: __('Todos')); }
      elseif (isset($labels[$t])) { echo e($labels[$t][$v] ?? $v); }
      else { echo e(mb_strimwidth((string)$v, 0, 80, '…')); } ?></td>
  <?php endforeach; ?>
  <td class="acts"><a class="btn btn-line btn-sm" href="<?= e(url('/admin/' . $mod . '/' . $r['id'])) ?>"><?= e(__('Editar')) ?></a>
    <?php if (empty($cfg['no_delete'])): ?><form class="inline-form" method="post" action="<?= e(url('/admin/' . $mod . '/' . $r['id'] . '/eliminar')) ?>" data-confirm="<?= e(__('¿Eliminar este registro? Esta acción no se puede deshacer.')) ?>"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit"><?= e(__('Eliminar')) ?></button></form><?php endif; ?></td>
</tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>
