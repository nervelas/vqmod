<?php $steps = ['Profesión', 'Horario', 'Profesional', 'Servicios', 'Compartir']; $dn = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']; ?>
<ol class="stepper" style="max-width:900px;grid-template-columns:repeat(5,1fr)">
  <?php foreach ($steps as $i => $l): ?><li class="<?= $i + 1 < $step ? 'done' : ($i + 1 === $step ? 'cur' : '') ?>"><a href="<?= e(url('/admin/onboarding?paso=' . ($i + 1))) ?>" style="all:unset;cursor:pointer"><span class="n">0<?= $i + 1 ?></span><span class="lbl"><?= e($l) ?></span></a></li><?php endforeach; ?>
</ol>
<div class="card form-wide">
<?php if ($step === 1): ?>
  <h2><?= e(__('¿A qué se dedica tu negocio?')) ?></h2>
  <p class="hint"><?= e(__('Cargaremos terminología, servicios con precios referenciales en GTQ, formulario de ingreso y plantillas de mensajes. Todo es editable después.')) ?></p>
  <form method="post" action="<?= e(url('/admin/onboarding')) ?>" data-confirm="<?= e(__('Esto reemplaza los servicios, categorías y formulario que no tengan citas. ¿Continuar?')) ?>">
    <?= csrf_field() ?><input type="hidden" name="paso" value="1">
    <div class="field"><label for="preset"><?= e(__('Tipo de profesión')) ?></label><select id="preset" name="preset"><?php foreach ($presets as $k => $l): ?><option value="<?= e($k) ?>"<?= sel($k, $current) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-gold" type="submit"><?= e(__('Cargar y continuar')) ?></button> <a class="btn btn-ghost" href="<?= e(url('/admin/onboarding?paso=2')) ?>"><?= e(__('Omitir')) ?></a>
  </form>
<?php elseif ($step === 2): ?>
  <h2><?= e(__('Horario de atención base')) ?></h2>
  <p class="hint"><?= e(__('Se aplica a todos los profesionales; luego puedes ajustar el horario de cada uno.')) ?></p>
  <form method="post" action="<?= e(url('/admin/onboarding')) ?>"><?= csrf_field() ?><input type="hidden" name="paso" value="2">
    <div class="fieldset"><legend><?= e(__('Días')) ?></legend><div class="checklist"><?php foreach ([1, 2, 3, 4, 5, 6, 0] as $d): ?><label class="check"><input type="checkbox" name="days[]" value="<?= $d ?>"<?= chk(in_array($d, $sched['days'], true)) ?>><span><?= e($dn[$d]) ?></span></label><?php endforeach; ?></div></div>
    <div class="row row-2"><div class="field"><label><?= e(__('Mañana: desde')) ?></label><input type="time" name="am_s" value="<?= e($sched['am_s']) ?>"></div><div class="field"><label><?= e(__('hasta')) ?></label><input type="time" name="am_e" value="<?= e($sched['am_e']) ?>"></div></div>
    <div class="row row-2"><div class="field"><label><?= e(__('Tarde: desde (opcional)')) ?></label><input type="time" name="pm_s" value="<?= e($sched['pm_s']) ?>"></div><div class="field"><label><?= e(__('hasta')) ?></label><input type="time" name="pm_e" value="<?= e($sched['pm_e']) ?>"></div></div>
    <button class="btn btn-gold" type="submit"><?= e(__('Guardar y continuar')) ?></button>
  </form>
<?php elseif ($step === 3): ?>
  <h2><?= e(__('Tu primer %s', mb_strtolower(term('professional')))) ?></h2>
  <p class="hint"><?= e(__('Quedará vinculado a todos los servicios y con el horario base. Podrás agregar foto, biografía y más después.')) ?></p>
  <form method="post" action="<?= e(url('/admin/onboarding')) ?>"><?= csrf_field() ?><input type="hidden" name="paso" value="3">
    <div class="row row-2"><div class="field"><label class="req" for="n"><?= e(__('Nombre')) ?></label><input id="n" name="name" required></div><div class="field"><label for="t"><?= e(__('Título o especialidad')) ?></label><input id="t" name="title"></div></div>
    <div class="row row-2"><div class="field"><label for="e"><?= e(__('Correo (avisos de nuevas citas)')) ?></label><input id="e" name="email" type="email"></div><div class="field"><label for="w"><?= e(__('WhatsApp')) ?></label><input id="w" name="whatsapp" placeholder="5555 1234"></div></div>
    <button class="btn btn-gold" type="submit"><?= e(__('Crear y continuar')) ?></button> <a class="btn btn-ghost" href="<?= e(url('/admin/onboarding?paso=4')) ?>"><?= e(__('Omitir')) ?></a>
  </form>
<?php elseif ($step === 4): ?>
  <h2><?= e(__('Revisa tus servicios')) ?></h2>
  <form method="post" action="<?= e(url('/admin/onboarding')) ?>"><?= csrf_field() ?><input type="hidden" name="paso" value="4">
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th><?= e(__('Servicio')) ?></th><th><?= e(__('Duración (min)')) ?></th><th><?= e(__('Precio')) ?></th></tr></thead><tbody>
      <?php foreach ($services as $s): ?><tr><td><?= e($s['name']) ?></td><td><input type="number" min="5" max="600" name="svc[<?= (int)$s['id'] ?>][duration]" value="<?= (int)$s['duration_min'] ?>"></td><td><input type="number" step="0.01" min="0" name="svc[<?= (int)$s['id'] ?>][price]" value="<?= e((string)$s['price']) ?>"></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <p style="margin-top:18px"><button class="btn btn-gold" type="submit"><?= e(__('Guardar y continuar')) ?></button> <a class="btn btn-ghost" href="<?= e(url('/admin/servicios')) ?>"><?= e(__('Editar servicios en detalle')) ?></a></p>
  </form>
<?php else: ?>
  <h2><?= e(__('Comparte tu enlace de reservas')) ?></h2>
  <div class="grid grid-2">
    <div><div class="field"><label for="lnk"><?= e(__('Enlace')) ?></label><input id="lnk" readonly value="<?= e($link) ?>"></div>
      <p><button class="btn btn-ink btn-sm" type="button" data-copy="#lnk"><?= e(__('Copiar enlace')) ?></button> <a class="btn btn-line btn-sm" href="<?= e('https://wa.me/?text=' . rawurlencode(__('Agenda tu %s aquí: %s', mb_strtolower(term('appt')), $link))) ?>" target="_blank" rel="noopener"><?= e(__('Compartir por WhatsApp')) ?></a></p>
      <p class="hint"><?= e(__('En Compartir y widget encontrarás el código para tu sitio web y el QR para imprimir.')) ?></p></div>
    <div><div data-qr="<?= e($link) ?>" data-size="220" data-download="#dlqr"></div><p><a id="dlqr" href="#" class="btn btn-line btn-sm"><?= e(__('Descargar QR')) ?></a></p></div>
  </div>
  <form method="post" action="<?= e(url('/admin/onboarding')) ?>"><?= csrf_field() ?><input type="hidden" name="paso" value="5"><button class="btn btn-gold" type="submit"><?= e(__('Finalizar')) ?></button></form>
<?php endif; ?>
</div>
