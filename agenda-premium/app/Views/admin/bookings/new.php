<?php
/** @var array $old @var string $error @var array $events @var array $hosts @var array $eventHosts @var array $eventDur @var string $tz @var array $sources @var bool $scoped @var bool $coreReady */
use App\Core\Fmt;
use App\Core\Tz;

$boot = ['tz' => $tz, 'eventHosts' => $eventHosts, 'eventDur' => $eventDur, 'hosts' => array_map(static fn (array $h): array => ['id' => (int) $h['id'], 'name' => $h['name']], $hosts), 'old' => ['event' => (int) $old['event_id'], 'host' => (int) $old['host_id'], 'duration' => (int) $old['duration'], 'start' => (string) $old['start_utc'], 'hora' => (string) $old['hora'], 'force' => (bool) $old['force']]];
$hostLabel = (string) setting('host_label', 'profesional');
?>
<div class="page page-narrow">
  <div class="page-head">
    <div>
      <p class="crumbs muted"><a href="<?= e(url('/admin/citas')) ?>">Citas</a> / Nueva</p>
      <h1 class="page-title serif">Nueva cita</h1>
      <p class="page-sub">Registra una cita que llegó por llamada, WhatsApp o en persona. Nunca se agenda dos veces el mismo horario.</p>
    </div>
  </div>

  <?php if (!$coreReady) : ?><div class="alert alert-warn" role="alert">El motor de reservas se está preparando. Puedes llenar el formulario, pero la cita no se podrá crear hasta que esté listo.</div><?php endif; ?>
  <?php if ($error !== '') : ?><div class="alert alert-err" role="alert" id="form-error"><?= e($error) ?></div><?php endif; ?>

  <form method="post" action="<?= e(url('/admin/citas/nueva')) ?>" class="stack new-booking" id="booking-form" data-slots-url="<?= e(url('/admin/slots')) ?>" data-search-url="<?= e(url('/admin/buscar')) ?>" novalidate>
    <?= csrf_field() ?>
    <script type="application/json" id="boot"><?= json_script($boot) ?></script>
    <input type="hidden" name="client_id" id="client_id" value="<?= (int) $old['client_id'] ?>">
    <input type="hidden" name="start_utc" id="start_utc" value="<?= e($old['start_utc']) ?>">

    <fieldset class="card fieldset">
      <legend class="serif">1 · Origen de la cita</legend>
      <div class="card-body chips" role="radiogroup" aria-label="Origen de la cita">
        <?php foreach ($sources as $k => $label) : ?>
          <label class="chip chip-radio"><input type="radio" name="origen" value="<?= e($k) ?>"<?= chk($old['origen'] === $k) ?>><span><?= e($label) ?></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset class="card fieldset">
      <legend class="serif">2 · Persona</legend>
      <div class="card-body stack">
        <div class="field" id="client-search-field">
          <label for="client-search">Buscar un cliente existente</label>
          <input class="input" id="client-search" type="search" autocomplete="off" placeholder="Escribe un nombre, correo o teléfono" role="combobox" aria-expanded="false" aria-controls="client-results" aria-autocomplete="list">
          <ul class="client-results" id="client-results" role="listbox" aria-label="Clientes encontrados" hidden></ul>
          <p class="hint">¿Es alguien nuevo? Escribe sus datos abajo y se guardará su ficha.</p>
        </div>
        <p class="alert alert-info" id="client-chosen" hidden><span data-client-label></span> <button type="button" class="btn btn-ghost btn-sm" data-client-clear>Cambiar</button></p>
        <div class="form-grid">
          <div class="field"><label for="name">Nombre completo</label><input class="input" id="name" name="name" value="<?= e($old['name']) ?>" maxlength="160" autocomplete="off" required></div>
          <div class="field"><label for="phone">Teléfono</label><input class="input" id="phone" name="phone" type="tel" value="<?= e($old['phone']) ?>" maxlength="30" inputmode="tel" placeholder="5555 1234" autocomplete="off"></div>
          <div class="field"><label for="email">Correo electrónico</label><input class="input" id="email" name="email" type="email" value="<?= e($old['email']) ?>" maxlength="190" autocomplete="off"></div>
          <div class="field"><label for="nit">NIT <span class="muted">(opcional)</span></label><input class="input" id="nit" name="nit" value="<?= e($old['nit']) ?>" maxlength="30" autocomplete="off"></div>
        </div>
      </div>
    </fieldset>

    <fieldset class="card fieldset">
      <legend class="serif">3 · Fecha y hora</legend>
      <div class="card-body stack">
        <div class="form-grid">
          <div class="field">
            <label for="event_id">Tipo de cita</label>
            <select class="select" id="event_id" name="event_id" required>
              <option value="">Elige uno…</option>
              <?php foreach ($events as $ev) : ?><option value="<?= (int) $ev['id'] ?>"<?= sel($old['event_id'], $ev['id']) ?>><?= e($ev['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="duration">Duración</label>
            <select class="select" id="duration" name="duration"><option value="">Según el tipo de cita</option></select>
          </div>
          <?php if (!$scoped) : ?>
          <div class="field">
            <label for="host_id"><?= e(ucfirst($hostLabel)) ?></label>
            <select class="select" id="host_id" name="host_id">
              <option value="">Asignar automáticamente</option>
              <?php foreach ($hosts as $h) : ?><option value="<?= (int) $h['id'] ?>"<?= sel($old['host_id'], $h['id']) ?>><?= e($h['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="field">
            <label for="fecha">Fecha</label>
            <input class="input" id="fecha" name="fecha" type="date" value="<?= e($old['fecha']) ?>" required>
          </div>
        </div>
        <div class="field">
          <span class="label" id="slots-label">Horarios libres <span class="muted">(hora de <?= e(Fmt::tzLabel($tz)) ?>)</span></span>
          <div class="slot-grid" id="slots" role="group" aria-labelledby="slots-label" aria-live="polite"></div>
          <p class="hint" id="slots-state">Elige el tipo de cita y la fecha para ver los horarios disponibles.</p>
        </div>
        <div class="form-grid">
          <div class="field">
            <label for="hora">Hora de la cita</label>
            <input class="input mono" id="hora" name="hora" type="time" step="300" value="<?= e($old['hora']) ?>">
          </div>
          <div class="field force-field">
            <label class="check"><input type="checkbox" name="force" value="1" id="force"<?= chk($old['force']) ?>><span>Permitir fuera de horario</span></label>
            <p class="hint">Ignora el horario laboral, el aviso mínimo y los límites. Aun así nunca se agenda sobre otra cita.</p>
          </div>
        </div>
        <div class="alert alert-warn" id="force-warn" role="note" hidden>Estás agendando fuera del horario habitual. Revisa bien la fecha y la hora antes de guardar.</div>
      </div>
    </fieldset>

    <fieldset class="card fieldset">
      <legend class="serif">4 · Detalles</legend>
      <div class="card-body stack">
        <div class="form-grid">
          <div class="field">
            <label for="status">Estado inicial</label>
            <select class="select" id="status" name="status">
              <option value="confirmed"<?= sel($old['status'], 'confirmed') ?>>Confirmada</option>
              <option value="pending"<?= sel($old['status'], 'pending') ?>>Pendiente de aprobación</option>
            </select>
          </div>
        </div>
        <div class="field"><label for="notes">Comentarios de la persona <span class="muted">(opcional)</span></label><textarea class="textarea" id="notes" name="notes" rows="2" maxlength="1000"><?= e($old['notes']) ?></textarea></div>
        <div class="field"><label for="internal_note">Nota interna <span class="muted">(solo para el equipo)</span></label><textarea class="textarea" id="internal_note" name="internal_note" rows="2" maxlength="1000"><?= e($old['internal_note']) ?></textarea></div>
      </div>
    </fieldset>

    <div class="form-actions">
      <a class="btn btn-ghost" href="<?= e(url('/admin/citas')) ?>">Cancelar</a>
      <button class="btn btn-gold btn-lg" type="submit"><?= icon('check') ?>Crear cita</button>
    </div>
  </form>
</div>
