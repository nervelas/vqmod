<?php
use Aurea\Services\BookingService;
$st = $a['status'];
$badge = ['pending' => 'badge-warn', 'confirmed' => 'badge-ok', 'completed' => 'badge-ok', 'cancelled' => 'badge-bad', 'no_show' => 'badge-bad', 'rescheduled' => ''][$st] ?? '';
$msgs = ['confirmada' => 'Gracias, registramos tu asistencia.', 'cancelada' => 'Tu cita fue cancelada.', 'reprogramada' => 'Tu cita fue reprogramada. Te enviamos los nuevos detalles.',
    'politica' => 'Ya no es posible hacer cambios en línea con tan poca anticipación. Por favor comunícate con nosotros.', 'limite' => 'Demasiados intentos. Espera unos minutos.',
    'comprobante' => 'Recibimos tu comprobante. Lo revisaremos y te confirmaremos pronto.', 'comprobante_datos' => 'Indica el monto y adjunta el comprobante.', 'error' => 'No pudimos completar la acción. Intenta de nuevo.'];
$m = (string)$msg; $mtext = strpos($m, 'archivo:') === 0 ? substr($m, 8) : ($msgs[$m] ?? '');
$needsPay = (float)$a['total'] > 0 && $paid + 0.001 < (float)$a['total'] && in_array($st, ['pending', 'confirmed'], true);
$remainingDep = max(0, (float)$a['deposit_required'] - $paid);
$hasBank = setting('bank_account', '') !== '' || setting('payment_link_url', '') !== '';
?>
<div class="wrap" style="padding:40px 0 90px">
  <div class="success">
    <?php if ($new && in_array($st, ['pending', 'confirmed'], true)): ?>
      <div class="seal"><svg viewBox="0 0 40 40" aria-hidden="true"><path d="M8 21l9 9 15-19"/></svg></div>
      <span class="eyebrow"><?= e($st === 'confirmed' ? __('Reserva confirmada') : __('Solicitud recibida')) ?></span>
      <h1><?= e($st === 'confirmed' ? __('¡Todo listo, %s!', explode(' ', trim($a['client_name']))[0]) : __('Recibimos tu solicitud')) ?></h1>
      <p style="color:var(--muted)"><?= e($st === 'confirmed' ? __('Te esperamos. Guarda este enlace: desde aquí puedes confirmar, reprogramar o cancelar.') : __('Te confirmaremos muy pronto. Guarda este enlace para dar seguimiento.')) ?></p>
    <?php else: ?>
      <span class="eyebrow"><?= e(__('Tu %s', mb_strtolower(term('appt')))) ?></span>
      <h1><?= e($a['service_name']) ?></h1>
    <?php endif; ?>
    <?php if ($mtext !== ''): ?><div class="alert <?= in_array($m, ['politica', 'limite', 'error', 'comprobante_datos'], true) || strpos($m, 'archivo:') === 0 ? 'alert-err' : 'alert-ok' ?>" role="status"><?= e(__($mtext)) ?></div><?php endif; ?>

    <dl class="detail">
      <div><dt><?= e(__('Estado')) ?></dt><dd><span class="badge <?= e($badge) ?>"><?= e(BookingService::STATUSES[$st] ?? $st) ?></span><?php if ($a['client_confirmed_at']): ?> <span class="badge badge-ok"><?= e(__('Asistencia confirmada')) ?></span><?php endif; ?></dd></div>
      <div><dt><?= e(__('Servicio')) ?></dt><dd><?= e($a['service_name']) ?></dd></div>
      <div><dt><?= e(term('professional')) ?></dt><dd><?= e($a['prof_name']) ?></dd></div>
      <div><dt><?= e(__('Fecha')) ?></dt><dd><?= e(\Aurea\Core\Util::dateLong($a['start_at'])) ?></dd></div>
      <div><dt><?= e(__('Hora')) ?></dt><dd><?= e(ftime($a['start_at'])) ?> – <?= e(ftime($a['end_at'])) ?></dd></div>
      <div><dt><?= e(__('Lugar')) ?></dt><dd><?= e(\Aurea\Services\NotificationService::whereText($a)) ?></dd></div>
      <?php if ((float)$a['total'] > 0 && setting('show_prices', '1') === '1'): ?><div><dt><?= e(__('Total')) ?></dt><dd><?= e(money($a['total'])) ?><?php if ((float)$a['discount'] > 0): ?> <small style="color:var(--muted)">(<?= e(__('descuento %s', money($a['discount']))) ?>)</small><?php endif; ?></dd></div><?php endif; ?>
      <?php if ((float)$a['total'] > 0): ?><div><dt><?= e(__('Pago')) ?></dt><dd><?= e(['unpaid' => __('Pendiente'), 'partial' => __('Parcial'), 'paid' => __('Pagado')][$a['payment_status']] ?? '') ?><?php if ($paid > 0): ?> · <?= e(money($paid)) ?><?php endif; ?></dd></div><?php endif; ?>
    </dl>

    <?php if (in_array($st, ['pending', 'confirmed'], true)): ?>
      <div class="btn-row">
        <a class="btn btn-ink btn-sm" href="<?= e(url('/cita/' . $a['token'] . '/ics')) ?>"><?= e(__('Agregar a mi calendario (.ics)')) ?></a>
        <a class="btn btn-line btn-sm" href="<?= e($google) ?>" target="_blank" rel="noopener">Google Calendar</a>
        <a class="btn btn-line btn-sm" href="<?= e($outlook) ?>" target="_blank" rel="noopener">Outlook</a>
        <?php if ($wa !== ''): ?><a class="btn btn-line btn-sm" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= e(__('Escribir por WhatsApp al consultorio')) ?></a><?php endif; ?>
      </div>
      <p style="font-size:.84rem;color:var(--muted)"><?= e(__('Apple Calendar: abre el archivo .ics descargado.')) ?></p>
    <?php endif; ?>

    <?php if ($needsPay && $hasBank): ?>
      <div class="box">
        <h3><?= e((float)$a['deposit_required'] > 0 && $remainingDep > 0 ? __('Anticipo requerido: %s', money($remainingDep)) : __('Información de pago')) ?></h3>
        <?php if (setting('bank_account', '') !== ''): ?>
          <p style="margin-bottom:6px"><b><?= e((string)setting('bank_name')) ?></b> <?= e((string)setting('bank_type')) ?><br><?= e(__('Cuenta')) ?>: <b><?= e((string)setting('bank_account')) ?></b><br><?= e(__('A nombre de')) ?>: <?= e((string)setting('bank_holder')) ?></p>
          <?php if (setting('bank_notes', '') !== ''): ?><p style="color:var(--muted);font-size:.9rem"><?= e((string)setting('bank_notes')) ?></p><?php endif; ?>
        <?php endif; ?>
        <?php $pl = \Aurea\Core\Util::safeUrl((string)setting('payment_link_url', '')); if ($pl !== ''): ?><p><a class="btn btn-gold btn-sm" href="<?= e($pl) ?>" target="_blank" rel="noopener"><?= e((string)setting('payment_link_label', 'Pagar en línea')) ?></a></p><?php endif; ?>
        <form method="post" action="<?= e(url('/cita/' . $a['token'] . '/comprobante')) ?>" enctype="multipart/form-data">
          <?= csrf_field('public') ?>
          <div class="row row-2">
            <div class="field"><label for="amt"><?= e(__('Monto pagado')) ?></label><input id="amt" name="amount" type="number" step="0.01" min="1" required value="<?= e(number_format($remainingDep > 0 ? $remainingDep : max(0, (float)$a['total'] - $paid), 2, '.', '')) ?>"></div>
            <div class="field"><label for="ref"><?= e(__('No. de boleta o referencia')) ?></label><input id="ref" name="reference" type="text" maxlength="120"></div>
          </div>
          <div class="field"><label for="file"><?= e(__('Comprobante (PDF o imagen)')) ?></label><input id="file" name="file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
          <button class="btn btn-ink btn-sm" type="submit"><?= e(__('Enviar comprobante')) ?></button>
        </form>
      </div>
    <?php endif; ?>
    <?php foreach ($payments as $p): if ($p['status'] === 'pending'): ?><p class="badge badge-warn"><?= e(__('Comprobante por revisar: %s', money($p['amount']))) ?></p> <?php endif; endforeach; ?>

    <?php if (in_array($st, ['pending', 'confirmed'], true)): ?>
      <div class="box">
        <h3><?= e(__('Gestionar mi %s', mb_strtolower(term('appt')))) ?></h3>
        <?php if (!$a['client_confirmed_at']): ?>
          <form method="post" action="<?= e(url('/cita/' . $a['token'] . '/confirmar')) ?>" style="margin-bottom:12px"><?= csrf_field('public') ?><button class="btn btn-gold btn-sm" type="submit"><?= e(__('Confirmo mi asistencia')) ?></button></form>
        <?php endif; ?>
        <?php if ($canSelf): ?>
          <div class="btn-row" style="justify-content:flex-start">
            <a class="btn btn-line btn-sm" href="<?= e(url('/cita/' . $a['token'] . '/reprogramar')) ?>"><?= e(__('Reprogramar')) ?></a>
          </div>
          <details style="margin-top:14px"><summary style="cursor:pointer;color:var(--danger);font-weight:600"><?= e(__('Cancelar mi %s', mb_strtolower(term('appt')))) ?></summary>
            <form method="post" action="<?= e(url('/cita/' . $a['token'] . '/cancelar')) ?>" style="margin-top:12px"><?= csrf_field('public') ?>
              <div class="field"><label for="why"><?= e(__('Motivo (opcional)')) ?></label><input id="why" name="reason" type="text" maxlength="150"></div>
              <button class="btn btn-danger btn-sm" type="submit"><?= e(__('Sí, cancelar')) ?></button>
            </form>
          </details>
          <p style="font-size:.84rem;color:var(--muted);margin-top:14px"><?= e(__('Puedes cambiar o cancelar hasta %d horas antes de tu %s.', $minHours, mb_strtolower(term('appt')))) ?></p>
        <?php else: ?>
          <p style="color:var(--muted)"><?= e(__('Faltan menos de %d horas, por lo que ya no se puede cambiar en línea. Por favor contáctanos.', $minHours)) ?></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if (in_array($st, ['cancelled', 'no_show', 'completed'], true)): ?>
      <p><a class="btn btn-gold" href="<?= e(url('/reservar?servicio=' . (int)$a['service_id'])) ?>"><?= e(__('Agendar nuevamente')) ?></a></p>
    <?php endif; ?>
  </div>
</div>
