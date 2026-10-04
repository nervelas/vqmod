<?php /** @var array $hosts */ ?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title">Nueva encuesta de horarios</h1><p class="page-sub">Las fechas que escribas se interpretan en la zona horaria que elijas y se guardan en UTC.</p></div>
    <div class="page-actions"><a class="btn btn-ghost" href="<?= e(url('/admin/encuestas')) ?>"><?= icon('arrow-left') ?>Volver</a></div>
  </div>
  <form method="post" action="<?= e(url('/admin/encuestas/crear')) ?>" class="card" data-poll-form>
    <?= csrf_field() ?>
    <div class="card-body stack">
      <div class="field"><label for="po-title">Título</label><input class="input" id="po-title" name="title" maxlength="190" required placeholder="Reunión de planificación de octubre"></div>
      <div class="field"><label for="po-desc">Descripción</label><textarea class="textarea" id="po-desc" name="description" rows="3" maxlength="2000" placeholder="Cuéntales de qué trata y cuánto durará."></textarea></div>
      <div class="form-grid">
        <div class="field"><label for="po-host">Anfitrión</label><select class="select" id="po-host" name="host_id" required><?php foreach ($hosts as $h) : ?><option value="<?= (int) $h['id'] ?>"><?= e($h['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="po-ev">Tipo de cita base</label><select class="select" id="po-ev" name="event_type_id" required data-poll-event><?php foreach ($events as $ev) : ?><option value="<?= (int) $ev['id'] ?>" data-duration="<?= (int) $ev['default_duration'] ?>"><?= e($ev['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="po-dur">Duración (minutos)</label><input class="input mono" id="po-dur" name="duration" type="number" min="5" max="480" value="60" required data-poll-duration></div>
        <div class="field"><label for="po-tz">Zona horaria de las opciones</label><select class="select" id="po-tz" name="timezone"><?php foreach (\App\Core\Tz::list() as $z) : ?><option value="<?= e($z) ?>"<?= sel($tz, $z) ?>><?= e($z) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="po-dl">Fecha límite para votar (opcional)</label><input class="input" id="po-dl" type="datetime-local" name="deadline"></div>
      </div>
      <fieldset class="fieldset"><legend>Opciones de fecha y hora</legend>
        <p class="hint">Propón al menos dos. Puedes agregar hasta 20.</p>
        <div class="stack" data-poll-options>
          <?php for ($i = 1; $i <= 4; $i++) : ?>
            <div class="field p3-opt"><label for="po-o<?= $i ?>">Opción <?= $i ?></label><input class="input" id="po-o<?= $i ?>" type="datetime-local" name="opts[]"<?= $i <= 2 ? ' required' : '' ?>></div>
          <?php endfor; ?>
        </div>
        <button class="btn btn-outline btn-sm mt-2" type="button" data-poll-add><?= icon('plus') ?>Agregar otra opción</button>
      </fieldset>
    </div>
    <div class="card-foot form-actions"><button class="btn btn-gold" type="submit">Crear encuesta</button></div>
  </form>
</div>
