<?php
/** @var array $b @var array $event @var array $host @var array $hosts @var ?array $client @var array $attendees @var array $answers @var array $history @var array $series @var string $tz @var array $actions @var bool $canCancel @var bool $canMove @var string $publicUrl @var string $wa @var bool $paymentsPanel */
use App\Controllers\Admin\A1Support;
use App\Core\Fmt;
use App\Core\Str;

$id = (int) $b['id'];
$status = (string) $b['status'];
$labels = ['confirmed' => ['Aprobar', 'btn-gold', 'check'], 'rejected' => ['Rechazar', 'btn-outline', 'x'], 'completed' => ['Marcar completada', 'btn-gold', 'check'], 'no_show' => ['No asistió', 'btn-outline', 'alert']];
$modes = ['in_person' => 'Presencial', 'video_auto' => 'Videollamada', 'video_custom' => 'Videollamada', 'phone' => 'Llamada telefónica', 'home' => 'A domicilio'];
$origin = $b['created_via'] === 'admin' && $b['utm_source'] && isset(A1Support::SOURCES[$b['utm_source']]) ? 'Manual · ' . A1Support::SOURCES[$b['utm_source']] : ['public' => 'Reserva en línea', 'admin' => 'Manual', 'api' => 'API', 'waitlist' => 'Lista de espera', 'poll' => 'Encuesta de horarios'][$b['created_via']] ?? (string) $b['created_via'];
$pstat = ['none' => 'Sin cobro', 'pending' => 'Pago pendiente', 'partial' => 'Pago parcial', 'paid' => 'Pagado', 'refunded' => 'Reembolsado'];
?>
<div class="page">
  <div class="page-head">
    <div>
      <p class="crumbs muted"><a href="<?= e(url('/admin/citas')) ?>">Citas</a> / #<?= $id ?></p>
      <h1 class="page-title serif"><?= e($b['guest_name']) ?></h1>
      <p class="page-sub"><span class="badge <?= e(A1Support::statusBadge($status)) ?>"><?= e(A1Support::statusLabel($status)) ?></span>
        <span class="mono"><?= e(ucfirst(Fmt::dateTime($b['starts_at'], $tz))) ?></span></p>
    </div>
    <div class="page-actions">
      <?php foreach ($actions as $a) : [$lbl, $cls, $ic] = $labels[$a]; ?>
        <form method="post" action="<?= e(url('/admin/citas/' . $id . '/estado')) ?>" class="inline"<?= $a === 'rejected' ? ' data-confirm="¿Rechazar esta cita? Se avisará a la persona."' : '' ?>>
          <?= csrf_field() ?><input type="hidden" name="status" value="<?= e($a) ?>">
          <button class="btn <?= e($cls) ?>" type="submit"><?= icon($ic) ?><?= e($lbl) ?></button>
        </form>
      <?php endforeach; ?>
      <?php if ($canMove) : ?><button class="btn btn-outline" type="button" data-reschedule="<?= ej(['id' => $id, 'event' => (int) $b['event_type_id'], 'duration' => (int) $b['duration'], 'host' => (int) $b['host_id'], 'name' => $b['guest_name'], 'date' => \App\Core\Tz::format($b['starts_at'], $tz, 'Y-m-d'), 'time' => \App\Core\Tz::format($b['starts_at'], $tz, 'H:i')]) ?>"><?= icon('refresh') ?>Reprogramar</button><?php endif; ?>
      <?php if ($canCancel) : ?><button class="btn btn-danger" type="button" data-modal-open="#cancel-modal"><?= icon('x') ?>Cancelar cita</button><?php endif; ?>
    </div>
  </div>

  <div class="split detail-split">
    <div class="stack">
      <section class="card">
        <div class="card-head"><h2 class="serif">La cita</h2></div>
        <dl class="dl card-body">
          <div><dt>Tipo de cita</dt><dd><span class="dot-c" <?= vars(['--c' => (string) ($event['color'] ?? '#C9A050')]) ?>></span> <?= e($event['name'] ?? '—') ?></dd></div>
          <div><dt><?= e(ucfirst((string) setting('host_label', 'profesional'))) ?></dt><dd><?php if (count($hosts) > 1) : echo e(implode(', ', array_column($hosts, 'name'))); else : echo e($host['name'] ?? '—'); endif; ?></dd></div>
          <div><dt>Fecha</dt><dd><?= e(ucfirst(Fmt::dateLong($b['starts_at'], $tz))) ?></dd></div>
          <div><dt>Hora</dt><dd class="mono"><?= e(Fmt::time($b['starts_at'], $tz)) ?> – <?= e(Fmt::time($b['ends_at'], $tz)) ?> <span class="muted">(<?= e(Fmt::duration((int) $b['duration'])) ?>)</span></dd></div>
          <div><dt>Modalidad</dt><dd><?= e($modes[$b['mode']] ?? $b['mode']) ?><?php if ($b['location']) : ?> · <?= e($b['location']) ?><?php endif; ?></dd></div>
          <?php if ($b['video_url']) : ?><div><dt>Videollamada</dt><dd><a href="<?= e($b['video_url']) ?>" target="_blank" rel="noopener noreferrer">Abrir enlace</a></dd></div><?php endif; ?>
          <div><dt>Zona de la persona</dt><dd><?= e(Fmt::tzLabel((string) $b['guest_timezone'], (string) $b['starts_at'])) ?></dd></div>
          <div><dt>Origen</dt><dd><?= e($origin) ?></dd></div>
          <?php if ($b['utm_source'] && $b['created_via'] !== 'admin') : ?><div><dt>Campaña</dt><dd class="muted"><?= e($b['utm_source']) ?><?= $b['utm_medium'] ? ' / ' . e($b['utm_medium']) : '' ?><?= $b['utm_campaign'] ? ' / ' . e($b['utm_campaign']) : '' ?></dd></div><?php endif; ?>
          <div><dt>Creada</dt><dd class="muted"><?= e(Fmt::dateShort($b['created_at'], $tz)) ?> · <?= e(Fmt::time($b['created_at'], $tz)) ?></dd></div>
          <?php if ((float) $b['total'] > 0 || $b['payment_status'] !== 'none') : ?>
          <div><dt>Importe</dt><dd><span class="mono"><?= e(money($b['total'])) ?></span> <span class="badge badge-muted"><?= e($pstat[$b['payment_status']] ?? $b['payment_status']) ?></span></dd></div>
          <?php endif; ?>
          <?php if ($status === 'cancelled' || $status === 'rejected') : ?>
          <div><dt>Motivo</dt><dd><?= e($b['cancel_reason'] ?: 'Sin motivo indicado') ?><?php if ($b['cancelled_by']) : ?> <span class="muted">· por <?= e($b['cancelled_by']) ?></span><?php endif; ?></dd></div>
          <?php endif; ?>
        </dl>
      </section>

      <?php if ($b['notes']) : ?>
      <section class="card"><div class="card-head"><h2 class="serif">Comentarios de la persona</h2></div><div class="card-body"><p class="pre"><?= e($b['notes']) ?></p></div></section>
      <?php endif; ?>

      <?php if ($answers) : ?>
      <section class="card">
        <div class="card-head"><h2 class="serif">Respuestas</h2></div>
        <dl class="dl card-body">
          <?php foreach ($answers as $a) : ?>
            <div><dt><?= e($a['label']) ?></dt><dd><?php if ($a['file_token']) : ?><a href="<?= e(url('/f/' . $a['file_token'])) ?>"><?= icon('paperclip') ?><?= e($a['original_name']) ?></a><?php else : ?><?= e($a['value'] ?? '') !== '' ? e($a['value']) : '<span class="muted">Sin respuesta</span>' ?><?php endif; ?></dd></div>
          <?php endforeach; ?>
        </dl>
      </section>
      <?php endif; ?>

      <?php if ($attendees) : ?>
      <section class="card">
        <div class="card-head"><h2 class="serif">Invitados</h2></div>
        <ul class="card-body list-plain">
          <?php foreach ($attendees as $a) : ?><li><strong><?= e($a['name']) ?></strong> <span class="muted"><?= e(trim(($a['email'] ?? '') . ' ' . ($a['phone'] ? Str::phoneDisplay($a['phone']) : ''))) ?></span></li><?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <?php if ($paymentsPanel) { partial('admin/payments/_booking_panel', ['booking' => $b]); } ?>

      <?php if (count($series) > 1) : ?>
      <section class="card">
        <div class="card-head"><h2 class="serif">Serie de sesiones</h2></div>
        <ul class="card-body list-plain">
          <?php foreach ($series as $s) : ?>
            <li><a href="<?= e(url('/admin/citas/' . $s['id'])) ?>"<?= (int) $s['id'] === $id ? ' aria-current="page"' : '' ?>>Sesión <?= (int) $s['series_index'] ?> · <?= e(Fmt::dateShort($s['starts_at'], $tz)) ?> <?= e(Fmt::time($s['starts_at'], $tz)) ?></a> <span class="badge <?= e(A1Support::statusBadge($s['status'])) ?>"><?= e(A1Support::statusLabel($s['status'])) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <section class="card">
        <div class="card-head"><h2 class="serif">Historial</h2></div>
        <?php if (!$history) : ?>
          <div class="empty"><p class="empty-text">Aún no hay movimientos registrados.</p></div>
        <?php else : ?>
          <div class="card-body"><ol class="timeline">
            <?php foreach ($history as $h) : ?>
              <li><span class="tl-dot"></span><div><strong><?= e($h['action']) ?></strong><?php if ($h['detail']) : ?> <span class="muted">· <?= e($h['detail']) ?></span><?php endif; ?><br><span class="small muted"><?= e(Fmt::dateShort($h['created_at'], $tz)) ?> <?= e(Fmt::time($h['created_at'], $tz)) ?><?= $h['actor'] ? ' · ' . e($h['actor']) : '' ?></span></div></li>
            <?php endforeach; ?>
          </ol></div>
        <?php endif; ?>
      </section>
    </div>

    <aside class="stack">
      <section class="card card-gold">
        <div class="card-head"><h2 class="serif">Contacto</h2></div>
        <div class="card-body stack">
          <p><strong><?= e($b['guest_name']) ?></strong></p>
          <?php if ($b['guest_phone']) : ?><p><a href="tel:+<?= e($b['guest_phone']) ?>"><?= icon('phone') ?><span class="mono"><?= e(Str::phoneDisplay($b['guest_phone'])) ?></span></a></p><?php endif; ?>
          <?php if ($b['guest_email']) : ?><p><a href="mailto:<?= e($b['guest_email']) ?>"><?= icon('mail') ?><?= e($b['guest_email']) ?></a></p><?php endif; ?>
          <?php if ($wa !== '') : ?><a class="btn btn-outline btn-block" href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer"><?= icon('whatsapp') ?>Escribir por WhatsApp</a><?php endif; ?>
          <?php if ($client) : ?>
            <a class="btn btn-ghost btn-block" href="<?= e(url('/admin/clientes/' . $client['id'])) ?>"><?= icon('user') ?>Ver ficha del cliente</a>
            <?php if ((int) $client['noshow_count'] > 0) : ?><p class="alert alert-warn small"><?= (int) $client['noshow_count'] ?> <?= (int) $client['noshow_count'] === 1 ? 'inasistencia previa' : 'inasistencias previas' ?><?= (int) $client['blocked'] ? ' · cliente bloqueado' : '' ?></p><?php endif; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="card">
        <div class="card-head"><h2 class="serif">Enlace de gestión</h2></div>
        <div class="card-body stack">
          <p class="muted small">La persona puede ver, reprogramar o cancelar su cita desde este enlace privado.</p>
          <div class="input-group">
            <input class="input mono small" type="text" readonly value="<?= e($publicUrl) ?>" aria-label="Enlace público de gestión" id="public-link">
            <button class="btn btn-outline btn-icon" type="button" data-copy="#public-link" aria-label="Copiar enlace"><?= icon('copy') ?></button>
          </div>
          <a class="btn btn-ghost btn-block" href="<?= e(url('/admin/citas/' . $id . '/ics')) ?>"><?= icon('download') ?>Descargar para el calendario (.ics)</a>
        </div>
      </section>

      <section class="card">
        <div class="card-head"><h2 class="serif">Nota interna</h2></div>
        <form class="card-body stack" method="post" action="<?= e(url('/admin/citas/' . $id . '/nota')) ?>">
          <?= csrf_field() ?>
          <div class="field"><label class="sr-only" for="internal_note">Nota interna</label><textarea class="textarea" id="internal_note" name="internal_note" rows="4" maxlength="2000" placeholder="Solo la ve tu equipo."><?= e($b['internal_note'] ?? '') ?></textarea></div>
          <button class="btn btn-outline" type="submit">Guardar nota</button>
        </form>
      </section>
    </aside>
  </div>
</div>

<?php if ($canCancel) : ?>
<dialog class="modal" id="cancel-modal" aria-labelledby="cm-title">
  <form method="post" action="<?= e(url('/admin/citas/' . $id . '/cancelar')) ?>">
    <?= csrf_field() ?>
    <div class="modal-head row row-between"><h2 class="serif" id="cm-title">Cancelar la cita</h2><button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Cerrar"><?= icon('x') ?></button></div>
    <div class="modal-body stack">
      <p>Se liberará el horario y se avisará a la persona según tus flujos de mensajes.</p>
      <div class="field"><label for="cancel-reason">Motivo <span class="muted">(opcional)</span></label><textarea class="textarea" id="cancel-reason" name="reason" rows="3" maxlength="500"></textarea></div>
    </div>
    <div class="modal-foot row"><button type="button" class="btn btn-ghost" data-modal-close>Volver</button><button class="btn btn-danger" type="submit">Cancelar la cita</button></div>
  </form>
</dialog>
<?php endif; ?>
<?php if ($canMove) { partial('admin/bookings/_reschedule'); } ?>
