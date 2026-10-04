<?php
/**
 * Confirmación y gestión de la cita. Variables: $biz $b $event $host $d $tz $tzLabel $statusInfo $when $whenDate $whenTime $rules $deadline
 * $series $active $future $due $payState $bank $payLink $waLink $google $outlook $modeLabel $videoUrl $flash $resched $boot $tzGroups
 */
$st = (string) $b['status'];
$titles = [
    'confirmed' => 'Tu cita está confirmada',
    'pending' => 'Recibimos tu solicitud',
    'cancelled' => 'Esta cita fue cancelada',
    'completed' => 'Esta cita ya se realizó',
    'no_show' => 'Registramos que no asististe',
    'rejected' => 'No pudimos aprobar esta solicitud',
];
$token = (string) $b['token'];
$first = explode(' ', trim((string) $b['guest_name']))[0];
$tzGroups = \App\Controllers\Pub\PubSupport::tzGroups();
?>
<?php partial('public/_header', ['biz' => $biz]); ?>
<main id="main" class="pm" data-manage <?= vars(['--ev' => $boot['event_color']]) ?>>
  <div class="container pm-in">
    <?php if ($flash) : ?><div class="alert alert-<?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>" role="<?= $flash['type'] === 'ok' ? 'status' : 'alert' ?>"><?= icon($flash['type'] === 'ok' ? 'check' : 'alert') ?><div><?= e($flash['text']) ?></div></div><?php endif; ?>

    <header class="pm-hero">
      <?php if ($st === 'confirmed' || $st === 'pending') : ?>
        <div class="seal-wrap"><?php partial('public/_seal', ['mode' => $st === 'pending' ? 'wait' : 'ok']); ?></div>
      <?php endif; ?>
      <p class="eyebrow">Hola, <?= e($first) ?></p>
      <h1 class="serif pm-title" tabindex="-1"><?= e($titles[$st] ?? 'Tu cita') ?></h1>
      <p><span class="badge badge-<?= e($statusInfo[1]) ?>"><?= e($statusInfo[0]) ?></span></p>
      <?php if ($st === 'pending') : ?><p class="muted">El equipo revisará tu solicitud y te avisaremos por correo en cuanto la apruebe.</p><?php endif; ?>
    </header>

    <div class="pm-grid">
      <section class="card pm-card" aria-labelledby="pm-res">
        <div class="card-body">
          <h2 id="pm-res" class="serif">Resumen</h2>
          <dl class="bk-dl">
            <div><dt>Servicio</dt><dd><?= e($event['name'] ?? '') ?></dd></div>
            <div><dt>Fecha</dt><dd><?= e($whenDate) ?></dd></div>
            <div><dt>Hora</dt><dd class="mono"><?= e($whenTime) ?></dd></div>
            <div><dt>Duración</dt><dd><?= e(\App\Core\Fmt::duration((int) $b['duration'])) ?></dd></div>
            <?php if (!empty($host['name'])) : ?><div><dt>Con</dt><dd><?= e($host['name']) ?></dd></div><?php endif; ?>
            <div><dt>Modalidad</dt><dd><?= e($modeLabel) ?></dd></div>
            <?php if (trim((string) ($b['location'] ?? '')) !== '') : ?><div><dt>Lugar</dt><dd><?= e($b['location']) ?></dd></div><?php endif; ?>
            <?php if ($videoUrl !== '') : ?><div><dt>Videollamada</dt><dd><a class="btn btn-gold btn-sm" href="<?= e($videoUrl) ?>" target="_blank" rel="noopener noreferrer"><?= icon('video') ?> Entrar a la sala</a></dd></div><?php endif; ?>
            <div><dt>Tu zona horaria</dt><dd><?= e($tzLabel) ?></dd></div>
            <?php if ((float) $b['total'] > 0) : ?><div><dt>Total</dt><dd class="mono"><?= e(\App\Core\Fmt::money((float) $b['total'])) ?></dd></div><?php endif; ?>
          </dl>
          <?php if (count($series) > 1) : ?><p class="eyebrow">Todas tus sesiones</p><ol class="bk-series-list mono"><?php foreach ($series as $s) : ?><li><?= e($s['date']) ?> · <?= e($s['time']) ?></li><?php endforeach; ?></ol><?php endif; ?>
          <?php if (trim((string) ($event['confirm_message'] ?? '')) !== '' && in_array($st, ['confirmed', 'pending'], true)) : ?><div class="prose pm-msg"><?= \App\Core\Str::richText($event['confirm_message']) ?></div><?php endif; ?>
        </div>
      </section>

      <div class="pm-side stack">
        <?php if ($boot['target_iso'] !== '') : ?>
        <section class="card pm-card" aria-label="Cuenta regresiva">
          <div class="card-body">
            <p class="eyebrow center">Faltan</p>
            <div class="mech" data-mech data-target="<?= e($boot['target_iso']) ?>" role="timer" aria-live="off">
              <span class="mech-unit"><span class="mech-d mono" data-u="d">0</span><small>días</small></span>
              <span class="mech-unit"><span class="mech-d mono" data-u="h">00</span><small>horas</small></span>
              <span class="mech-unit"><span class="mech-d mono" data-u="m">00</span><small>min</small></span>
              <span class="mech-unit"><span class="mech-d mono" data-u="s">00</span><small>seg</small></span>
            </div>
          </div>
        </section>
        <?php endif; ?>

        <?php if ($active) : ?>
        <section class="card pm-card" aria-labelledby="pm-cal">
          <div class="card-body stack">
            <h2 id="pm-cal" class="serif">Agrégala a tu calendario</h2>
            <div class="row row-wrap">
              <?php if ($google !== '') : ?><a class="btn btn-outline btn-sm" href="<?= e($google) ?>" target="_blank" rel="noopener noreferrer"><?= icon('calendar') ?> Google</a><?php endif; ?>
              <a class="btn btn-outline btn-sm" href="<?= e(url('/reserva/' . $token . '/evento.ics')) ?>"><?= icon('download') ?> Apple / .ics</a>
              <?php if ($outlook !== '') : ?><a class="btn btn-outline btn-sm" href="<?= e($outlook) ?>" target="_blank" rel="noopener noreferrer"><?= icon('calendar') ?> Outlook</a><?php endif; ?>
            </div>
          </div>
        </section>
        <?php endif; ?>

        <?php if ($payState === 'due' || $payState === 'review') : ?>
        <section class="card card-gold pm-card" aria-labelledby="pm-pay">
          <div class="card-body stack">
            <h2 id="pm-pay" class="serif">Pago</h2>
            <?php if ($payState === 'review') : ?>
              <div class="alert alert-info"><?= icon('info') ?><div>Recibimos tu comprobante y lo estamos verificando. Te avisaremos al confirmarlo.</div></div>
            <?php else : ?>
              <p><?= (float) $b['deposit_due'] > 0 ? 'Anticipo para asegurar tu cita' : 'Monto a pagar' ?>: <strong class="mono pm-due"><?= e(\App\Core\Fmt::money($due)) ?></strong></p>
              <?php if ($bank !== '') : ?><div><p class="eyebrow">Datos para transferencia o depósito</p><pre class="pm-bank"><?= e($bank) ?></pre></div><?php endif; ?>
              <?php if ($payLink !== '') : ?><a class="btn btn-gold" href="<?= e($payLink) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external') ?> Pagar en línea</a><?php endif; ?>
              <?php if ($bank === '' && $payLink === '') : ?><p class="muted">Escríbenos y te indicamos cómo realizar el pago.</p><?php endif; ?>
              <form method="post" action="<?= e(url('/reserva/' . $token . '/comprobante')) ?>" enctype="multipart/form-data" class="stack">
                <?= public_csrf_field('manage') ?>
                <div class="field"><label for="proof">Sube tu comprobante (foto o PDF)</label><input class="input" id="proof" type="file" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf" required><p class="hint">Hasta 6 MB.</p></div>
                <button class="btn btn-outline" type="submit"><?= icon('upload') ?> Enviar comprobante</button>
              </form>
            <?php endif; ?>
          </div>
        </section>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($active) : ?>
    <section class="pm-manage" aria-labelledby="pm-ger">
      <h2 id="pm-ger" class="serif">¿Necesitas hacer un cambio?</h2>
      <?php if ($deadline !== '') : ?><p class="muted">Puedes cambiar o cancelar hasta el <?= e($deadline) ?></p><?php endif; ?>

      <details class="bk-fold" data-resched-fold<?= $resched ? '' : ' data-disabled' ?>>
        <summary><?= icon('refresh') ?> Reprogramar mi cita</summary>
        <div class="bk-fold-body">
          <?php if ($resched) : ?>
          <form method="post" action="<?= e(url('/reserva/' . $token . '/reprogramar')) ?>" class="stack" data-resched-form>
            <?= public_csrf_field('manage') ?>
            <input type="hidden" name="start" data-start>
            <input type="hidden" name="tz" data-tzfield value="<?= e($tz) ?>">
            <?php partial('public/_scheduler', ['sid' => 'rs', 'tzGroups' => $tzGroups, 'waitlist' => false]); ?>
            <p class="bk-chosen-line" data-resched-chosen hidden></p>
            <div class="form-actions"><button class="btn btn-gold" type="submit" data-resched-submit disabled>Confirmar nuevo horario</button></div>
          </form>
          <?php else : ?>
            <p class="muted"><?= e($rules['reason'] !== '' ? $rules['reason'] : 'Esta cita ya no se puede reprogramar desde aquí.') ?> <?php if ($waLink !== '') : ?><a href="<?= e($waLink) ?>" target="_blank" rel="noopener noreferrer">Escríbenos por WhatsApp</a><?php endif; ?></p>
          <?php endif; ?>
        </div>
      </details>

      <details class="bk-fold">
        <summary><?= icon('x') ?> Cancelar mi cita</summary>
        <div class="bk-fold-body">
          <?php if (!empty($rules['cancel'])) : ?>
          <form method="post" action="<?= e(url('/reserva/' . $token . '/cancelar')) ?>" class="stack" data-cancel-form>
            <?= public_csrf_field('manage') ?>
            <div class="field"><label for="cr">Cuéntanos el motivo (opcional)</label><textarea class="textarea" id="cr" name="reason" rows="3" maxlength="500"></textarea></div>
            <div class="form-actions"><button class="btn btn-danger" type="submit" data-confirm="¿Seguro que quieres cancelar esta cita?">Sí, cancelar mi cita</button></div>
          </form>
          <?php else : ?>
            <p class="muted"><?= e($rules['reason'] !== '' ? $rules['reason'] : 'Esta cita ya no se puede cancelar desde aquí.') ?> <?php if ($waLink !== '') : ?><a href="<?= e($waLink) ?>" target="_blank" rel="noopener noreferrer">Escríbenos por WhatsApp</a><?php endif; ?></p>
          <?php endif; ?>
        </div>
      </details>
    </section>
    <?php endif; ?>

    <p class="pm-foot row row-wrap">
      <?php if ($waLink !== '') : ?><a class="btn btn-outline" href="<?= e($waLink) ?>" target="_blank" rel="noopener noreferrer"><?= icon('whatsapp') ?> Escribir por WhatsApp</a><?php endif; ?>
      <a class="btn btn-ghost" href="<?= e(url('/reserva/' . $token . '/recibo')) ?>"><?= icon('receipt') ?> Ver recibo</a>
      <a class="btn btn-ghost" href="<?= e(url('/')) ?>">Volver al inicio</a>
    </p>
  </div>
</main>
<script type="application/json" id="boot"><?= json_script($boot + ['csrf' => \App\Controllers\Pub\PubSupport::csrfSet()]) ?></script>
<?php partial('public/_footer', ['biz' => $biz]); ?>
