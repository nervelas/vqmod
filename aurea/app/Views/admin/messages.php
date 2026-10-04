<div data-csrf="<?= e(\Aurea\Core\Csrf::token()) ?>" data-base="<?= e(base_path()) ?>"></div>
<div class="kpis">
  <div class="kpi"><div class="l"><?= e(__('WhatsApp por enviar')) ?></div><div class="v num"><?= count($due) ?></div></div>
  <div class="kpi"><div class="l"><?= e(__('Correos en cola')) ?></div><div class="v num"><?= (int)$stats['pending_email'] ?></div></div>
  <div class="kpi"><div class="l"><?= e(__('Enviados hoy')) ?></div><div class="v num"><?= (int)$stats['sent_today'] ?></div></div>
  <div class="kpi"><div class="l"><?= e(__('Fallidos')) ?></div><div class="v num"><?= (int)$stats['failed'] ?></div></div>
</div>
<div class="card"><h2><?= e(__('Mensajes de WhatsApp por enviar')) ?></h2>
  <p class="hint"><?= e(__('Toca "Enviar por WhatsApp": se abre la conversación con el mensaje ya redactado (sin costo, sin API) y queda marcado como enviado.')) ?></p>
  <?php if (!$due): ?><div class="empty-state"><div class="big"><?= e(__('Nada pendiente')) ?></div><p><?= e(__('Los recordatorios y confirmaciones aparecerán aquí automáticamente.')) ?></p></div><?php endif; ?>
  <?php foreach ($due as $m): ?>
    <div class="msg">
      <div><b><?= e($m['client_name'] ?: '+' . $m['recipient']) ?></b> <span class="badge badge-gold"><?= e($labels[$m['type']] ?? $m['type']) ?></span> <?php if ($m['start_at']): ?><span class="hint"><?= e(__('Cita')) ?>: <?= e(fdatetime($m['start_at'])) ?></span><?php endif; ?> <span class="badge badge-ok sent-flag" hidden><?= e(__('Enviado')) ?></span>
        <div class="hint" style="white-space:pre-line;margin-top:4px"><?= e(mb_strimwidth($m['body'], 0, 220, '…')) ?></div></div>
      <div class="btn-row" style="margin:0"><a class="btn btn-gold btn-sm" data-wa-id="<?= (int)$m['id'] ?>" href="<?= e($m['wa_url']) ?>" target="_blank" rel="noopener"><?= e(__('Enviar por WhatsApp')) ?></a>
        <form class="inline-form" method="post" action="<?= e(url('/admin/mensajes/' . $m['id'] . '/accion')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="skip"><button class="btn btn-ghost btn-sm" type="submit"><?= e(__('Omitir')) ?></button></form></div></div>
  <?php endforeach; ?>
</div>
<?php if ($failed): ?><div class="card"><h2><?= e(__('Mensajes fallidos')) ?></h2><div class="tbl-wrap"><table class="tbl"><tbody>
  <?php foreach ($failed as $f): ?><tr><td><?= e($f['channel']) ?> · <?= e($f['type']) ?><br><span class="hint"><?= e($f['recipient']) ?></span></td><td class="hint"><?= e($f['last_error']) ?></td><td class="acts"><form class="inline-form" method="post" action="<?= e(url('/admin/mensajes/' . $f['id'] . '/accion')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="retry"><button class="btn btn-line btn-sm" type="submit"><?= e(__('Reintentar')) ?></button></form></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
