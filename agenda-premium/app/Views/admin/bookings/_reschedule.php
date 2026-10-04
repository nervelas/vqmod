<?php /** Diálogo de reprogramación (lo usan el detalle de la cita y el calendario). Lo maneja admin-bookings.js */ ?>
<dialog class="modal" id="reschedule-modal" aria-labelledby="rs-title" data-slots-url="<?= e(url('/admin/slots')) ?>" data-move-base="<?= e(url('/admin/citas')) ?>">
  <form method="post" action="" id="reschedule-form" novalidate>
    <?= csrf_field() ?>
    <div class="modal-head row row-between">
      <h2 class="serif" id="rs-title">Reprogramar cita</h2>
      <button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Cerrar"><?= icon('x') ?></button>
    </div>
    <div class="modal-body stack">
      <p class="muted" data-rs-summary></p>
      <div class="field">
        <label for="rs-fecha">Nueva fecha</label>
        <input class="input" type="date" id="rs-fecha" name="fecha" required>
      </div>
      <div class="field">
        <span class="label" id="rs-slots-label">Horarios libres</span>
        <div class="slot-grid" data-rs-slots role="group" aria-labelledby="rs-slots-label" aria-live="polite"></div>
        <p class="hint" data-rs-state>Elige una fecha para ver los horarios disponibles.</p>
      </div>
      <div class="field">
        <label for="rs-hora">Hora</label>
        <input class="input mono" type="time" step="300" id="rs-hora" name="hora" required>
      </div>
      <label class="check"><input type="checkbox" name="force" value="1" id="rs-force"><span>Permitir fuera de horario (nunca sobre otra cita)</span></label>
      <div class="alert alert-err" data-rs-error role="alert" hidden></div>
    </div>
    <div class="modal-foot row">
      <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
      <button class="btn btn-gold" type="submit">Reprogramar</button>
    </div>
  </form>
</dialog>
