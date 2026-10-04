<?php $dn = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo']; $id = (int)$p['id']; ?>
<form method="post" action="<?= e(url('/admin/profesionales/guardar')) ?>" enctype="multipart/form-data" class="card form-wide">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
  <h2><?= e(__('Perfil')) ?></h2>
  <div class="row row-2">
    <div class="field"><label class="req" for="name"><?= e(__('Nombre')) ?></label><input id="name" name="name" required value="<?= e($p['name']) ?>" <?= $full ? '' : 'readonly' ?>></div>
    <div class="field"><label for="title"><?= e(__('Título o especialidad')) ?></label><input id="title" name="title" value="<?= e($p['title']) ?>"></div>
  </div>
  <div class="field"><label for="bio"><?= e(__('Biografía (se muestra en la página pública)')) ?></label><textarea id="bio" name="bio" maxlength="3000"><?= e($p['bio']) ?></textarea></div>
  <div class="field"><label for="spec"><?= e(__('Especialidades (separadas por coma)')) ?></label><input id="spec" name="specialties" value="<?= e($p['specialties']) ?>"></div>
  <div class="row row-3">
    <div class="field"><label for="email"><?= e(__('Correo (avisos de citas)')) ?></label><input id="email" name="email" type="email" value="<?= e($p['email']) ?>"></div>
    <div class="field"><label for="phone"><?= e(__('Teléfono')) ?></label><input id="phone" name="phone" value="<?= e($p['phone']) ?>"></div>
    <div class="field"><label for="wa"><?= e(__('WhatsApp')) ?></label><input id="wa" name="whatsapp" value="<?= e($p['whatsapp']) ?>"></div>
  </div>
  <div class="row row-2">
    <div class="field"><label for="photo"><?= e(__('Fotografía')) ?></label><input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp"><?php if ($p['photo']): ?><div class="help"><img src="<?= e(url('uploads/' . rawurlencode($p['photo']))) ?>" alt="" width="64" height="64" style="border-radius:50%;object-fit:cover;margin-top:8px"></div><?php endif; ?></div>
    <?php if ($full): ?><div class="field"><label for="color"><?= e(__('Color en la agenda')) ?></label><input id="color" name="color" type="color" value="<?= e($p['color']) ?>" style="padding:4px;height:48px"></div><?php endif; ?>
  </div>
  <?php if ($full): ?>
  <div class="row row-2"><label class="check"><input type="checkbox" name="active" value="1" <?= chk($p['active']) ?>><span><?= e(__('Activo (recibe reservas)')) ?></span></label><div class="field"><label for="sort"><?= e(__('Orden')) ?></label><input id="sort" name="sort" type="number" value="<?= (int)$p['sort'] ?>"></div></div>
  <div class="fieldset"><legend><?= e(__('Servicios que ofrece')) ?></legend>
    <?php if (!$services): ?><p class="hint"><?= e(__('Primero crea servicios.')) ?></p><?php endif; ?>
    <?php foreach ($services as $s): ?>
      <div class="svc-price-row">
        <label class="check" style="margin:0"><input type="checkbox" name="svc[<?= (int)$s['id'] ?>]" value="1" <?= chk($s['linked'] !== null || !$id) ?>><span><?= e($s['name']) ?> <span class="hint">(<?= e(money($s['price'])) ?>)</span></span></label>
        <input type="number" step="0.01" min="0" name="price_ov[<?= (int)$s['id'] ?>]" placeholder="<?= e(__('Precio propio')) ?>" value="<?= e($s['price_override'] !== null ? (string)$s['price_override'] : '') ?>" aria-label="<?= e(__('Precio propio para %s', $s['name'])) ?>">
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (count($locs) > 0): ?><div class="fieldset"><legend><?= e(__('Sedes donde atiende')) ?></legend><div class="checklist"><?php foreach ($locs as $l): ?><label class="check"><input type="checkbox" name="loc[<?= (int)$l['id'] ?>]" value="1" <?= chk($l['linked'] !== null || !$id) ?>><span><?= e($l['name']) ?></span></label><?php endforeach; ?></div></div><?php endif; ?>
  <?php endif; ?>
  <button class="btn btn-gold" type="submit"><?= e(__('Guardar perfil')) ?></button>
</form>

<?php if ($id): ?>
<form method="post" action="<?= e(url('/admin/profesionales/' . $id . '/horario')) ?>" class="card form-wide" id="horario">
  <?= csrf_field() ?>
  <h2><?= e(__('Horario semanal')) ?></h2>
  <p class="hint"><?= e(__('Puedes agregar varios bloques por día (por ejemplo 8:00–12:00 y 14:00–18:00). Los huecos entre bloques son pausas. Los feriados y ausencias se bloquean automáticamente.')) ?></p>
  <?php foreach ($dn as $wd => $label): ?>
    <div class="sched-day">
      <div><b><?= e($label) ?></b><br><button type="button" class="btn btn-ghost btn-sm" data-add-block="<?= $wd ?>">+ <?= e(__('Bloque')) ?></button></div>
      <div class="sched-blocks" data-blocks="<?= $wd ?>">
        <?php foreach ($sched[$wd] ?? [] as $i => $b): ?>
          <div class="blk">
            <input type="time" name="sch[<?= $wd ?>][<?= $i ?>][start]" value="<?= e(substr($b['start_time'], 0, 5)) ?>" aria-label="<?= e(__('Inicio')) ?>"> <span>–</span>
            <input type="time" name="sch[<?= $wd ?>][<?= $i ?>][end]" value="<?= e(substr($b['end_time'], 0, 5)) ?>" aria-label="<?= e(__('Fin')) ?>">
            <?php if (count($allLocs) > 1): ?><select name="sch[<?= $wd ?>][<?= $i ?>][loc]" aria-label="<?= e(__('Sede')) ?>"><option value=""><?= e(__('Todas las sedes')) ?></option><?php foreach ($allLocs as $l): ?><option value="<?= (int)$l['id'] ?>"<?= sel($l['id'], $b['location_id']) ?>><?= e($l['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
            <button type="button" class="btn btn-ghost btn-sm" data-rm-block aria-label="<?= e(__('Quitar bloque')) ?>">✕</button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <template id="blk-tpl"><div class="blk">
    <input type="time" data-n="start" aria-label="<?= e(__('Inicio')) ?>" value="08:00"> <span>–</span><input type="time" data-n="end" aria-label="<?= e(__('Fin')) ?>" value="12:00">
    <?php if (count($allLocs) > 1): ?><select data-n="loc" aria-label="<?= e(__('Sede')) ?>"><option value=""><?= e(__('Todas las sedes')) ?></option><?php foreach ($allLocs as $l): ?><option value="<?= (int)$l['id'] ?>"><?= e($l['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
    <button type="button" class="btn btn-ghost btn-sm" data-rm-block aria-label="<?= e(__('Quitar bloque')) ?>">✕</button></div></template>
  <button class="btn btn-gold" type="submit"><?= e(__('Guardar horario')) ?></button>
</form>
<div class="card form-wide">
  <h2><?= e(__('Enlaces')) ?></h2>
  <div class="field"><label><?= e(__('Página pública')) ?></label><input readonly value="<?= e(abs_url('/profesional/' . $p['slug'])) ?>"></div>
  <div class="field"><label for="ics"><?= e(__('Calendario privado (suscripción ICS para Google/Apple Calendar)')) ?></label><input id="ics" readonly value="<?= e(abs_url('/ics/' . $p['ics_token'] . '.ics')) ?>"><div class="help"><?= e(__('Tiene un código secreto: no lo compartas.')) ?></div></div>
  <button class="btn btn-ink btn-sm" type="button" data-copy="#ics"><?= e(__('Copiar enlace ICS')) ?></button>
</div>
<?php endif; ?>
