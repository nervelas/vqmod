<?php
$selAction = (string) $wf['action'];
?>
<div class="page" data-workflow-page data-preview-url="<?= e(url('/admin/flujos/vista-previa')) ?>">
  <div class="page-head">
    <div><h1 class="page-title"><?= $wf['id'] ? 'Editar flujo' : 'Nuevo flujo' ?></h1><p class="page-sub">Elige cuándo se activa, qué hace y escribe el mensaje con variables que se llenan solas.</p></div>
    <div class="page-actions"><a class="btn btn-ghost" href="<?= e(url('/admin/flujos')) ?>"><?= icon('arrow-left') ?>Volver</a></div>
  </div>

  <div class="grid cols-2 p3-wf-grid">
    <form method="post" action="<?= e(url('/admin/flujos/guardar')) ?>" class="card" id="wf-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $wf['id'] ?>">
      <div class="card-body stack">
        <div class="field"><label for="wf-name">Nombre del flujo</label><input class="input" id="wf-name" name="name" maxlength="160" required value="<?= e($wf['name']) ?>" placeholder="Recordatorio 24 horas antes"></div>

        <fieldset class="fieldset"><legend>¿Cuándo?</legend>
          <div class="field"><label for="wf-trigger">Disparador</label>
            <select class="select" id="wf-trigger" name="trigger_key" data-wf-trigger><?php foreach ($triggers as $k => $l) : ?><option value="<?= e($k) ?>"<?= sel($wf['trigger_key'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
          <div class="form-row" data-wf-offset>
            <div class="field"><label for="wf-ov">Tiempo</label><input class="input mono" id="wf-ov" name="offset_value" type="number" min="0" max="100000" value="<?= (int) $offVal ?>"></div>
            <div class="field"><label for="wf-ou">Unidad</label><select class="select" id="wf-ou" name="offset_unit"><option value="minutes"<?= sel($offUnit, 'minutes') ?>>minutos</option><option value="hours"<?= sel($offUnit, 'hours') ?>>horas</option><option value="days"<?= sel($offUnit, 'days') ?>>días</option></select></div>
            <p class="hint p3-offset-hint" data-wf-offset-hint></p>
          </div>
          <div class="field"><label for="wf-ev">Aplica a</label><select class="select" id="wf-ev" name="event_type_id"><option value="0">Todos los servicios</option><?php foreach ($events as $ev) : ?><option value="<?= (int) $ev['id'] ?>"<?= sel($wf['event_type_id'], $ev['id']) ?>><?= e($ev['name']) ?></option><?php endforeach; ?></select></div>
        </fieldset>

        <fieldset class="fieldset"><legend>¿Qué hace?</legend>
          <div class="field"><label for="wf-action">Acción</label>
            <select class="select" id="wf-action" name="action" data-wf-action><?php foreach ($actions as $k => $l) : ?><option value="<?= e($k) ?>"<?= sel($selAction, $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
          <?php if (!$waOn) : ?><div class="alert alert-warn" data-wf-show="whatsapp_api" hidden>La API de WhatsApp está desactivada. Puedes guardar el flujo, pero no enviará nada hasta que la actives en Correo y WhatsApp.</div><?php endif; ?>
          <div class="field" data-wf-show="email whatsapp whatsapp_api"><label for="wf-rec">Para quién</label><select class="select" id="wf-rec" name="recipient"><?php foreach ($recipients as $k => $l) : ?><option value="<?= e($k) ?>"<?= sel($wf['recipient'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
          <div class="field" data-wf-show="webhook"><label for="wf-url">Nombre del evento</label><input class="input mono" id="wf-url" name="action_value" maxlength="60" value="<?= e($selAction === 'webhook' && $wf['action_value'] ? (string) $wf['action_value'] : 'workflow.custom') ?>"><p class="hint">Se envía a los webhooks suscritos a ese evento (los que reciben "todos" siempre lo reciben). Configúralos en la sección Webhooks.</p></div>
          <div class="field" data-wf-show="set_status"><label for="wf-st">Nuevo estado</label><select class="select" id="wf-st" name="action_value_status"><?php foreach ($statusValues as $k => $l) : ?><option value="<?= e($k) ?>"<?= sel($selAction === 'set_status' ? $wf['action_value'] : '', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
          <div class="field" data-wf-show="add_tag"><label for="wf-tag">Etiqueta</label><input class="input" id="wf-tag" name="action_value_tag" maxlength="40" value="<?= e($selAction === 'add_tag' ? (string) $wf['action_value'] : '') ?>" placeholder="cliente-frecuente"></div>
          <p class="muted" data-wf-show="review_request">Se enviará al cliente un enlace para que deje su reseña. Puedes personalizar el texto abajo; si lo dejas vacío usamos uno por defecto.</p>
        </fieldset>

        <fieldset class="fieldset" data-wf-show="email whatsapp whatsapp_api review_request"><legend>Mensaje</legend>
          <div class="field" data-wf-show="email review_request"><label for="wf-subject">Asunto</label><input class="input" id="wf-subject" name="subject" maxlength="255" value="<?= e($wf['subject']) ?>" data-wf-insert-target></div>
          <div class="field"><label for="wf-template">Texto</label><textarea class="textarea" id="wf-template" name="template" rows="10" maxlength="8000" data-wf-insert-target><?= e($wf['template']) ?></textarea></div>
          <div><p class="hint" id="wf-vars-label">Insertar variable en el cursor:</p>
            <div class="chips" role="group" aria-labelledby="wf-vars-label"><?php foreach ($vars as $k => $l) : ?><button type="button" class="chip" data-wf-var="{<?= e($k) ?>}" title="<?= e($l) ?>">{<?= e($k) ?>}</button><?php endforeach; ?></div></div>
        </fieldset>

        <label class="check"><input type="checkbox" name="active" value="1"<?= chk((int) $wf['active'] === 1) ?>><span>Flujo activo</span></label>
      </div>
      <div class="card-foot form-actions"><button class="btn btn-gold" type="submit">Guardar flujo</button><a class="btn btn-ghost" href="<?= e(url('/admin/flujos')) ?>">Cancelar</a></div>
    </form>

    <aside class="stack">
      <section class="card card-gold" aria-labelledby="h-prev" data-wf-show="email whatsapp whatsapp_api review_request">
        <div class="card-head row row-between row-wrap"><h2 id="h-prev" class="serif">Vista previa</h2>
          <div class="field p3-inline-field"><label class="sr-only" for="wf-prev-b">Cita para la vista previa</label>
            <select class="select" id="wf-prev-b" data-wf-prev-booking><option value="0">Cita de ejemplo</option><?php foreach ($recentBookings as $b) : ?><option value="<?= (int) $b['id'] ?>">#<?= (int) $b['id'] ?> · <?= e($b['guest_name']) ?></option><?php endforeach; ?></select></div></div>
        <div class="card-body stack" aria-live="polite">
          <p class="muted" data-wf-prev-note>Escribe tu mensaje y mira cómo lo recibirá la persona.</p>
          <p class="p3-prev-subject serif" data-wf-prev-subject hidden></p>
          <div class="p3-prev-body" data-wf-prev-body hidden></div>
          <p class="text-err" data-wf-prev-error hidden></p>
        </div>
      </section>

      <?php if ($wf['id']) : ?>
        <section class="card" aria-labelledby="h-test">
          <div class="card-head"><h2 id="h-test" class="serif">Probar ejecución</h2></div>
          <form method="post" action="<?= e(url('/admin/flujos/' . (int) $wf['id'] . '/prueba')) ?>" class="card-body stack">
            <?= csrf_field() ?><input type="hidden" name="volver" value="editar">
            <p class="muted">Comprueba con una cita existente qué se enviaría y a quién. Nada llega al cliente; la prueba por correo va a la dirección de la administración.</p>
            <div class="field"><label for="wf-test-b">Cita</label><select class="select" id="wf-test-b" name="booking_id"><?php foreach ($recentBookings as $b) : ?><option value="<?= (int) $b['id'] ?>">#<?= (int) $b['id'] ?> · <?= e($b['guest_name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-actions"><button class="btn btn-outline" type="submit"<?= $recentBookings ? '' : ' disabled' ?>><?= icon('play') ?>Probar sin enviar</button><?php if ($selAction === 'email') : ?><button class="btn btn-outline" type="submit" name="enviar" value="1"<?= $recentBookings ? '' : ' disabled' ?>><?= icon('mail') ?>Enviarme una prueba</button><?php endif; ?></div>
            <?php if (!$recentBookings) : ?><p class="hint">Necesitas al menos una cita para probar.</p><?php endif; ?>
          </form>
        </section>
      <?php endif; ?>
    </aside>
  </div>
</div>
