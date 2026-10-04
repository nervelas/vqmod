<?php $st = $a['status']; $active = in_array($st, ['pending', 'confirmed'], true); $canPay = \Aurea\Core\Auth::can('payments') || \Aurea\Core\Auth::role() === 'professional'; ?>
<div class="card">
  <div class="card-head">
    <div><span class="badge <?= ['pending' => 'badge-warn', 'confirmed' => 'badge-ok', 'completed' => 'badge-ok', 'cancelled' => 'badge-bad', 'no_show' => 'badge-bad'][$st] ?? '' ?>"><?= e($statuses[$st]) ?></span>
      <?php if ($a['client_confirmed_at']): ?><span class="badge badge-ok"><?= e(__('Asistencia confirmada por el cliente')) ?></span><?php endif; ?>
      <h2 style="margin:10px 0 0;font-size:1.8rem"><?= e($a['service_name']) ?></h2>
      <div class="hint"><?= e(\Aurea\Core\Util::dateLong($a['start_at'])) ?> · <?= e(ftime($a['start_at'])) ?>–<?= e(ftime($a['end_at'])) ?> · <span class="dot" style="background:<?= e($a['prof_color']) ?>"></span><?= e($a['prof_name']) ?></div>
      <?php if ($child): ?><div class="hint"><?= e(__('Reprogramada a la %s #%d', mb_strtolower(term('appt')), $child)) ?> → <a href="<?= e(url('/admin/citas/' . $child)) ?>"><?= e(__('Ver')) ?></a></div><?php endif; ?>
      <?php if ($a['rescheduled_from']): ?><div class="hint"><?= e(__('Reprogramada desde')) ?> <a href="<?= e(url('/admin/citas/' . $a['rescheduled_from'])) ?>">#<?= (int)$a['rescheduled_from'] ?></a></div><?php endif; ?>
    </div>
    <div class="btn-row" style="margin:0">
      <?php if ($wa): ?><a class="btn btn-line btn-sm" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= e(__('WhatsApp al cliente')) ?></a><?php endif; ?>
      <a class="btn btn-line btn-sm" href="tel:+<?= e($a['phone_cc'] . $a['client_phone']) ?>"><?= e(__('Llamar')) ?></a>
      <a class="btn btn-line btn-sm" href="<?= e(url('/admin/citas/' . $a['id'] . '/recibo')) ?>" target="_blank"><?= e(__('Recibo')) ?></a>
      <a class="btn btn-line btn-sm" href="<?= e(url('/cita/' . $a['token'])) ?>" target="_blank" rel="noopener"><?= e(__('Página del cliente')) ?></a>
    </div>
  </div>
  <?php if ($st !== 'rescheduled'): ?>
  <div class="btn-row" style="justify-content:flex-start">
    <?php foreach (['confirmed' => ['Confirmar', 'btn-gold'], 'completed' => ['Marcar asistió / completada', 'btn-ink'], 'no_show' => ['No asistió', 'btn-line'], 'pending' => ['Pendiente', 'btn-line']] as $to => [$lbl, $cls]): if ($to === $st) { continue; } ?>
      <form class="inline-form" method="post" action="<?= e(url('/admin/citas/' . $a['id'] . '/estado')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="<?= e($to) ?>"><button class="btn <?= $cls ?> btn-sm" type="submit"><?= e(__($lbl)) ?></button></form>
    <?php endforeach; ?>
    <?php if ($st !== 'cancelled'): ?><form class="inline-form" method="post" action="<?= e(url('/admin/citas/' . $a['id'] . '/estado')) ?>" data-confirm="<?= e(__('¿Cancelar esta %s? Se avisará al cliente.', mb_strtolower(term('appt')))) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="cancelled"><input name="reason" placeholder="<?= e(__('Motivo (opcional)')) ?>" style="width:200px;min-height:36px" aria-label="<?= e(__('Motivo')) ?>"> <button class="btn btn-danger btn-sm" type="submit"><?= e(__('Cancelar')) ?></button></form><?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<div class="grid grid-2">
  <div class="card"><h3><?= e(term('client')) ?></h3>
    <dl class="kv"><dt><?= e(__('Nombre')) ?></dt><dd><a href="<?= e(url('/admin/clientes/' . $a['client_id'])) ?>"><?= e($a['client_name']) ?></a><?php if ($a['client_blocked']): ?> <span class="badge badge-bad"><?= e(__('Bloqueado')) ?></span><?php endif; ?></dd>
      <dt><?= e(__('Teléfono')) ?></dt><dd>+<?= e($a['phone_cc']) ?> <?= e($a['client_phone']) ?></dd><dt><?= e(__('Correo')) ?></dt><dd><?= e($a['client_email'] ?: '—') ?></dd><dt>NIT</dt><dd><?= e($a['client_nit'] ?: '—') ?></dd>
      <dt><?= e(__('No asistió')) ?></dt><dd><?= (int)$a['noshow_count'] ?> <?= e(__('veces')) ?></dd><dt><?= e(__('Origen')) ?></dt><dd><?= e($a['source']) ?></dd>
      <dt><?= e(__('Lugar')) ?></dt><dd><?= e(\Aurea\Services\NotificationService::whereText(\Aurea\Services\NotificationService::appointment((int)$a['id']))) ?></dd>
      <?php if ($a['client_note']): ?><dt><?= e(__('Comentario')) ?></dt><dd><?= e($a['client_note']) ?></dd><?php endif; ?>
      <?php if ($a['cancel_reason']): ?><dt><?= e(__('Motivo cancelación')) ?></dt><dd><?= e($a['cancel_reason']) ?></dd><?php endif; ?></dl>
    <?php if ($answers): ?><h3 style="margin-top:22px"><?= e(__('Formulario de ingreso')) ?></h3><dl class="kv"><?php foreach ($answers as $r): ?><dt><?= e($r['label']) ?></dt><dd><?= e($r['value']) ?></dd><?php endforeach; ?></dl><?php endif; ?>
    <?php if ($files): ?><h3 style="margin-top:22px"><?= e(__('Archivos')) ?></h3><?php foreach ($files as $f): ?><p><a href="<?= e(url('/admin/archivos/' . $f['id'])) ?>"><?= e($f['original_name']) ?></a> <span class="hint">(<?= e(number_format($f['size'] / 1024, 0)) ?> KB)</span></p><?php endforeach; endif; ?>
  </div>
  <div class="card"><h3><?= e(__('Cobro')) ?></h3>
    <dl class="kv"><dt><?= e(__('Precio')) ?></dt><dd><?= e(money($a['price'])) ?></dd><?php if ((float)$a['discount'] > 0): ?><dt><?= e(__('Descuento')) ?></dt><dd>− <?= e(money($a['discount'])) ?></dd><?php endif; ?>
      <dt><?= e(__('Total')) ?></dt><dd><b><?= e(money($a['total'])) ?></b></dd><?php if ((float)$a['deposit_required'] > 0): ?><dt><?= e(__('Anticipo')) ?></dt><dd><?= e(money($a['deposit_required'])) ?></dd><?php endif; ?>
      <dt><?= e(__('Pagado')) ?></dt><dd><?= e(money($paid)) ?> <span class="badge <?= $a['payment_status'] === 'paid' ? 'badge-ok' : 'badge-warn' ?>"><?= e(['unpaid' => __('Pendiente'), 'partial' => __('Parcial'), 'paid' => __('Pagado')][$a['payment_status']]) ?></span></dd></dl>
    <?php if ($payments): ?><div class="tbl-wrap" style="margin-top:14px"><table class="tbl"><tbody><?php foreach ($payments as $p): ?><tr><td><?= e(fdatetime($p['paid_at'])) ?><br><span class="hint"><?= e($methods[$p['method']] ?? $p['method']) ?> <?= e($p['reference']) ?></span></td><td><b><?= e(money($p['amount'])) ?></b><br><span class="badge <?= ['confirmed' => 'badge-ok', 'pending' => 'badge-warn', 'rejected' => 'badge-bad'][$p['status']] ?? '' ?>"><?= e(['confirmed' => __('Confirmado'), 'pending' => __('Por revisar'), 'rejected' => __('Rechazado')][$p['status']] ?? $p['status']) ?></span></td>
      <td class="acts"><?php if ($p['file_id']): ?><a class="btn btn-line btn-sm" href="<?= e(url('/admin/archivos/' . $p['file_id'])) ?>"><?= e(__('Comprobante')) ?></a><?php endif; ?>
      <?php if ($p['status'] === 'pending' && $canPay): foreach (['confirmed' => ['Confirmar', 'btn-gold'], 'rejected' => ['Rechazar', 'btn-danger']] as $to => [$l, $c]): ?><form class="inline-form" method="post" action="<?= e(url('/admin/pagos/' . $p['id'] . '/estado')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="<?= $to ?>"><button class="btn <?= $c ?> btn-sm" type="submit"><?= e(__($l)) ?></button></form> <?php endforeach; endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    <?php if ($canPay): ?><details style="margin-top:16px"><summary style="cursor:pointer;font-weight:600">+ <?= e(__('Registrar pago o anticipo')) ?></summary>
      <form method="post" action="<?= e(url('/admin/citas/' . $a['id'] . '/pago')) ?>" enctype="multipart/form-data" style="margin-top:12px"><?= csrf_field() ?>
        <div class="row row-2"><div class="field"><label for="am"><?= e(__('Monto')) ?></label><input id="am" name="amount" type="number" step="0.01" min="0.01" required value="<?= e(number_format(max(0, (float)$a['total'] - $paid), 2, '.', '')) ?>"></div>
        <div class="field"><label for="mt"><?= e(__('Método')) ?></label><select id="mt" name="method"><?php foreach ($methods as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div></div>
        <div class="field"><label for="rf"><?= e(__('Referencia / No. de boleta')) ?></label><input id="rf" name="reference" maxlength="190"></div>
        <div class="field"><label for="fl"><?= e(__('Comprobante (opcional)')) ?></label><input id="fl" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
        <button class="btn btn-gold btn-sm" type="submit"><?= e(__('Registrar')) ?></button></form></details><?php endif; ?>
  </div>
</div>

<?php if ($active): ?>
<div class="card" data-slot-picker data-api="<?= e($api) ?>" data-exclude="<?= (int)$a['id'] ?>">
  <h3><?= e(__('Reprogramar')) ?></h3>
  <form method="post" action="<?= e(url('/admin/citas/' . $a['id'] . '/reprogramar')) ?>">
    <?= csrf_field() ?><input type="hidden" name="service_id" value="<?= (int)$a['service_id'] ?>"><input type="hidden" name="professional_id" value="<?= (int)$a['professional_id'] ?>">
    <div class="field"><label for="rd"><?= e(__('Nueva fecha')) ?></label><input id="rd" type="date" data-slot-date value="<?= e(substr($a['start_at'], 0, 10)) ?>"></div>
    <div class="field"><span class="label"><?= e(__('Horarios disponibles')) ?></span><div data-slot-list class="hint"></div></div>
    <input type="hidden" name="start" data-slot-value>
    <details><summary style="cursor:pointer;font-weight:600"><?= e(__('Hora manual')) ?></summary><div class="row row-2" style="margin-top:12px"><div class="field"><input type="datetime-local" name="start_manual" aria-label="<?= e(__('Fecha y hora')) ?>"></div><label class="check"><input type="checkbox" name="ignore_schedule" value="1"><span><?= e(__('Ignorar horario laboral')) ?></span></label></div></details>
    <button class="btn btn-ink btn-sm" type="submit" style="margin-top:12px"><?= e(__('Reprogramar y avisar')) ?></button>
  </form>
</div>
<?php endif; ?>

<div class="grid grid-2">
  <div class="card"><h3><?= e(__('Nota interna (privada)')) ?></h3>
    <form method="post" action="<?= e(url('/admin/citas/' . $a['id'] . '/notas')) ?>"><?= csrf_field() ?><div class="field"><textarea name="internal_note" maxlength="2000" aria-label="<?= e(__('Nota interna')) ?>"><?= e($a['internal_note']) ?></textarea></div><button class="btn btn-ink btn-sm" type="submit"><?= e(__('Guardar nota')) ?></button></form>
    <?php if ($queue): ?><h3 style="margin-top:24px"><?= e(__('Mensajes')) ?></h3><?php foreach ($queue as $q): ?><div class="hint" style="padding:3px 0"><?= e($q['channel'] === 'email' ? '✉' : '💬') ?> <?= e($q['type']) ?> · <span class="badge"><?= e($q['status']) ?></span> <?= e(fdatetime($q['send_after'])) ?><?= $q['last_error'] ? ' · ' . e($q['last_error']) : '' ?></div><?php endforeach; endif; ?>
  </div>
  <div class="card"><h3><?= e(__('Historial')) ?></h3>
    <?php foreach ($history as $h): ?><div style="padding:8px 0;border-bottom:1px solid var(--border);font-size:.9rem"><b><?= e($h['actor']) ?></b> · <?= e($h['detail']) ?><br><span class="hint"><?= e(fdatetime($h['created_at'])) ?></span></div><?php endforeach; ?>
  </div>
</div>
