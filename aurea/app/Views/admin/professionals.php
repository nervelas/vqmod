<div class="card-head"><p class="hint" style="margin:0"><?= e(__('Cada %s tiene su horario, servicios y color en la agenda.', mb_strtolower(term('professional')))) ?></p><a class="btn btn-gold" href="<?= e(url('/admin/profesionales/nuevo')) ?>">+ <?= e(__('Nuevo %s', mb_strtolower(term('professional')))) ?></a></div>
<?php if (!$rows): ?><div class="card empty-state"><div class="big"><?= e(__('Aún no hay profesionales')) ?></div><p><?= e(__('Agrega el primero para empezar a recibir reservas.')) ?></p></div><?php else: ?>
<div class="tbl-wrap"><table class="tbl"><thead><tr><th><?= e(__('Nombre')) ?></th><th><?= e(__('Título')) ?></th><th><?= e(__('Próximas')) ?></th><th><?= e(__('Estado')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
  <td><span class="dot" style="background:<?= e($r['color']) ?>"></span><a class="strong" href="<?= e(url('/admin/profesionales/' . $r['id'])) ?>"><?= e($r['name']) ?></a></td>
  <td><?= e($r['title']) ?></td><td class="num"><?= (int)$r['upcoming'] ?></td>
  <td><span class="badge <?= $r['active'] ? 'badge-ok' : '' ?>"><?= e($r['active'] ? __('Activo') : __('Inactivo')) ?></span></td>
  <td class="acts"><a class="btn btn-line btn-sm" href="<?= e(url('/admin/profesionales/' . $r['id'])) ?>"><?= e(__('Editar')) ?></a>
    <form class="inline-form" method="post" action="<?= e(url('/admin/profesionales/' . $r['id'] . '/eliminar')) ?>" data-confirm="<?= e(__('¿Eliminar este perfil? Esta acción no se puede deshacer.')) ?>"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit"><?= e(__('Eliminar')) ?></button></form></td>
</tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>
