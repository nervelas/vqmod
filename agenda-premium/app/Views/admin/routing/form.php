<?php
$f = $form ?: ['id' => 0, 'name' => '', 'slug' => '', 'description' => '', 'active' => 1, 'default_action' => 'message', 'default_value' => '', 'default_message' => 'Gracias por tus respuestas. Escríbenos por WhatsApp y con gusto te orientamos.'];
$da = (string) $f['default_action'];
$dv = (string) $f['default_value'];
?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title"><?= $f['id'] ? 'Editar formulario' : 'Nuevo formulario de enrutamiento' ?></h1><p class="page-sub">Arma las preguntas y define las reglas. Se evalúan de arriba hacia abajo y gana la primera que coincida.</p></div>
    <div class="page-actions"><a class="btn btn-ghost" href="<?= e(url('/admin/enrutamiento')) ?>"><?= icon('arrow-left') ?>Volver</a><?php if ($f['id']) : ?><a class="btn btn-outline" href="<?= e(url('/admin/enrutamiento/' . (int) $f['id'] . '/bitacora')) ?>"><?= icon('list') ?>Bitácora</a><?php endif; ?></div>
  </div>

  <form id="routing-form" method="post" action="<?= e(url('/admin/enrutamiento/guardar')) ?>" class="stack" data-sprite="<?= e(asset('img/icons.svg')) ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
    <input type="hidden" name="questions_json" value="[]"><input type="hidden" name="rules_json" value="[]">

    <section class="card" aria-labelledby="h-basic">
      <div class="card-head"><h2 id="h-basic" class="serif">Datos generales</h2></div>
      <div class="card-body stack">
        <div class="form-grid">
          <div class="field"><label for="rf-name">Nombre</label><input class="input" id="rf-name" name="name" maxlength="160" required value="<?= e($f['name']) ?>" placeholder="¿Qué servicio necesitas?"></div>
          <div class="field"><label for="rf-slug">Dirección corta</label><input class="input mono" id="rf-slug" name="slug" maxlength="80" value="<?= e($f['slug']) ?>" placeholder="se genera del nombre"><p class="hint">El enlace será /enrutar/<?= e($f['slug'] ?: 'direccion-corta') ?></p></div>
        </div>
        <div class="field"><label for="rf-desc">Texto de bienvenida</label><textarea class="textarea" id="rf-desc" name="description" rows="2" maxlength="2000" placeholder="Responde unas preguntas y te llevamos al horario ideal."><?= e($f['description']) ?></textarea></div>
        <label class="check"><input type="checkbox" name="active" value="1"<?= chk((int) $f['active'] === 1) ?>><span>Formulario activo</span></label>
        <?php if ($link) : ?><div class="input-group"><input class="input mono" id="rf-link" readonly value="<?= e($link) ?>" aria-label="Enlace público"><button class="btn btn-outline" type="button" data-copy="#rf-link"><?= icon('copy') ?>Copiar</button></div><?php endif; ?>
      </div>
    </section>

    <section class="card" aria-labelledby="h-q">
      <div class="card-head row row-between row-wrap"><h2 id="h-q" class="serif">Preguntas</h2><button class="btn btn-outline btn-sm" type="button" data-add-question><?= icon('plus') ?>Agregar pregunta</button></div>
      <div class="card-body"><div class="stack" data-questions><noscript><p class="alert alert-warn">Para armar el formulario necesitas activar JavaScript en tu navegador.</p></noscript></div></div>
    </section>

    <section class="card" aria-labelledby="h-r">
      <div class="card-head row row-between row-wrap"><h2 id="h-r" class="serif">Reglas</h2><button class="btn btn-outline btn-sm" type="button" data-add-rule><?= icon('plus') ?>Agregar regla</button></div>
      <div class="card-body"><p class="hint mb-2">La primera regla de la lista tiene la prioridad más alta. Usa las flechas para reordenar.</p><div class="stack" data-rules></div></div>
    </section>

    <section class="card" aria-labelledby="h-d">
      <div class="card-head"><h2 id="h-d" class="serif">Si ninguna regla coincide</h2></div>
      <div class="card-body stack">
        <div class="form-grid">
          <div class="field"><label for="rf-da">Acción por defecto</label>
            <select class="select" id="rf-da" name="default_action">
              <option value="message"<?= sel($da, 'message') ?>>Mostrar un mensaje</option>
              <option value="event"<?= sel($da, 'event') ?>>Mostrar un tipo de cita</option>
              <option value="host"<?= sel($da, 'host') ?>>Enviar a un anfitrión</option>
              <option value="team"<?= sel($da, 'team') ?>>Enviar a un equipo</option>
              <option value="url"<?= sel($da, 'url') ?>>Ir a una dirección web</option>
            </select></div>
          <div class="field" data-default-for="event"><label for="rf-dv-e">Tipo de cita</label><select class="select" id="rf-dv-e" name="dv_event"><option value="">Elige…</option><?php foreach ($boot['events'] as $x) : ?><option value="<?= (int) $x['id'] ?>"<?= sel($da === 'event' ? $dv : '', $x['id']) ?>><?= e($x['name']) ?></option><?php endforeach; ?></select></div>
          <div class="field" data-default-for="host" hidden><label for="rf-dv-h">Anfitrión</label><select class="select" id="rf-dv-h" name="dv_host"><option value="">Elige…</option><?php foreach ($boot['hosts'] as $x) : ?><option value="<?= (int) $x['id'] ?>"<?= sel($da === 'host' ? $dv : '', $x['id']) ?>><?= e($x['name']) ?></option><?php endforeach; ?></select></div>
          <div class="field" data-default-for="team" hidden><label for="rf-dv-t">Equipo</label><select class="select" id="rf-dv-t" name="dv_team"><option value="">Elige…</option><?php foreach ($boot['teams'] as $x) : ?><option value="<?= (int) $x['id'] ?>"<?= sel($da === 'team' ? $dv : '', $x['id']) ?>><?= e($x['name']) ?></option><?php endforeach; ?></select></div>
          <div class="field" data-default-for="url" hidden><label for="rf-dv-u">Dirección web</label><input class="input" id="rf-dv-u" type="url" name="dv_url" maxlength="500" value="<?= e($da === 'url' ? $dv : '') ?>" placeholder="https://…"></div>
        </div>
        <div class="field" data-default-for="message"><label for="rf-dm">Mensaje</label><textarea class="textarea" id="rf-dm" name="default_message" rows="3" maxlength="2000"><?= e($f['default_message']) ?></textarea></div>
      </div>
    </section>

    <div class="form-actions"><button class="btn btn-gold btn-lg" type="submit">Guardar formulario</button></div>
  </form>
  <script type="application/json" id="boot"><?= json_script($boot) ?></script>
</div>
