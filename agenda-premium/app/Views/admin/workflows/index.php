<?php
use App\Controllers\Admin\WorkflowsController as W;
use App\Core\Fmt;

$offTxt = static function (array $w): string {
    if (!in_array($w['trigger_key'], ['booking.before_start', 'booking.after_end'], true)) {
        return '';
    }
    $m = (int) $w['offset_minutes'];
    $t = $m % 1440 === 0 && $m > 0 ? ($m / 1440) . ($m / 1440 === 1 ? ' día' : ' días') : ($m % 60 === 0 && $m > 0 ? ($m / 60) . ($m / 60 === 1 ? ' hora' : ' horas') : $m . ' min');
    return $t . ($w['trigger_key'] === 'booking.before_start' ? ' antes' : ' después');
};
?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title">Flujos y recordatorios</h1><p class="page-sub">Mensajes y acciones automáticas: confirmaciones, recordatorios, seguimiento y más. Tú decides el texto.</p></div>
    <div class="page-actions"><a class="btn btn-outline" href="<?= e(url('/admin/flujos/ejecuciones')) ?>"><?= icon('list') ?>Ver ejecuciones</a><a class="btn btn-gold" href="<?= e(url('/admin/flujos/nuevo')) ?>"><?= icon('plus') ?>Nuevo flujo</a></div>
  </div>
  <?php if (!$rows) : ?>
    <div class="empty"><?= icon('zap') ?><p class="empty-title">Aún no tienes flujos</p><p class="empty-text">Crea uno, por ejemplo un recordatorio 24 horas antes de la cita, para que nadie olvide su horario.</p><a class="btn btn-gold" href="<?= e(url('/admin/flujos/nuevo')) ?>">Crear el primero</a></div>
  <?php else : ?>
    <div class="card"><div class="table-wrap"><table class="table">
      <caption class="sr-only">Flujos configurados</caption>
      <thead><tr><th scope="col">Flujo</th><th scope="col">Cuándo</th><th scope="col" class="hide-sm">Acción</th><th scope="col" class="hide-sm">Resultados</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $w) : ?>
        <tr<?= (int) $w['active'] ? '' : ' class="muted"' ?>>
          <th scope="row"><a class="text-gold" href="<?= e(url('/admin/flujos/' . (int) $w['id'] . '/editar')) ?>"><?= e($w['name']) ?></a><div class="muted"><?= e($w['event_name'] ?: 'Todos los servicios') ?></div></th>
          <td><?= e(W::TRIGGERS[$w['trigger_key']] ?? $w['trigger_key']) ?><?php if ($offTxt($w)) : ?><div class="muted"><?= e($offTxt($w)) ?></div><?php endif; ?></td>
          <td class="hide-sm"><?= e(W::ACTIONS[$w['action']] ?? $w['action']) ?><?php if ($w['action'] === 'whatsapp_api' && !$waOn) : ?><div class="text-err">API desactivada</div><?php endif; ?></td>
          <td class="hide-sm mono nowrap"><span class="text-ok"><?= (int) $w['done_n'] ?> ok</span> · <?= (int) $w['pending_n'] ?> en cola<?= (int) $w['failed_n'] ? ' · <span class="text-err">' . (int) $w['failed_n'] . ' con error</span>' : '' ?></td>
          <td><span class="badge <?= (int) $w['active'] ? 'badge-ok' : 'badge-muted' ?>"><?= (int) $w['active'] ? 'Activo' : 'Pausado' ?></span></td>
          <td class="right nowrap">
            <a class="btn btn-ghost btn-sm btn-icon" href="<?= e(url('/admin/flujos/' . (int) $w['id'] . '/editar')) ?>" aria-label="Editar <?= e($w['name']) ?>"><?= icon('edit') ?></a>
            <form class="inline" method="post" action="<?= e(url('/admin/flujos/' . (int) $w['id'] . '/estado')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= (int) $w['active'] ? 'Pausar' : 'Activar' ?></button></form>
            <form class="inline" method="post" action="<?= e(url('/admin/flujos/' . (int) $w['id'] . '/duplicar')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-icon" type="submit" aria-label="Duplicar <?= e($w['name']) ?>"><?= icon('copy') ?></button></form>
            <form class="inline" method="post" action="<?= e(url('/admin/flujos/' . (int) $w['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar el flujo &quot;<?= e($w['name']) ?>&quot;? Se borra también su bitácora."><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-icon" type="submit" aria-label="Eliminar <?= e($w['name']) ?>"><?= icon('trash') ?></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div></div>
  <?php endif; ?>
</div>
