<?php $errs = $errors; $f = static fn($k) => $errs[$k] ?? null; ?>
<form method="post" action="<?= e(url('/admin/citas')) ?>" class="card form-wide">
  <?= csrf_field() ?>
  <?php if ($client): ?><div class="alert"><?= e(term('client')) ?>: <b><?= e($client['name']) ?></b> <span class="hint">(+<?= e($client['phone_cc']) ?> <?= e($client['phone']) ?>)</span></div><?php endif; ?>
  <div class="fieldset"><legend><?= e(term('client')) ?></legend>
    <div class="row row-2">
      <div class="field"><label class="req" for="name"><?= e(__('Nombre')) ?></label><input id="name" name="name" required value="<?= e($old['name']) ?>"><?php if ($f('name')): ?><div class="err"><?= e($f('name')) ?></div><?php endif; ?></div>
      <div class="field"><label class="req" for="phone"><?= e(__('Teléfono / WhatsApp')) ?></label><div class="phone-row"><input name="cc" value="<?= e($old['cc']) ?>" aria-label="<?= e(__('Código de país')) ?>"><input id="phone" name="phone" required value="<?= e($old['phone']) ?>" placeholder="5555 1234"></div><?php if ($f('phone')): ?><div class="err"><?= e($f('phone')) ?></div><?php endif; ?><div class="help"><?= e(__('Si el teléfono ya existe, se usa la ficha del cliente.')) ?></div></div>
    </div>
    <div class="row row-2"><div class="field"><label for="email"><?= e(__('Correo (opcional)')) ?></label><input id="email" name="email" type="email" value="<?= e($old['email'] ?? '') ?>"></div><div class="field"><label for="nit">NIT</label><input id="nit" name="nit" value="<?= e($old['nit'] ?? '') ?>"></div></div>
  </div>
  <div class="fieldset" data-slot-picker data-api="<?= e($api) ?>"><legend><?= e(__('Servicio y horario')) ?></legend>
    <div class="row row-2">
      <div class="field"><label class="req" for="svc"><?= e(__('Servicio')) ?></label><select id="svc" name="service_id" required><option value=""><?= e(__('Selecciona…')) ?></option><?php foreach ($services as $s): ?><option value="<?= (int)$s['id'] ?>"<?= sel($s['id'], $old['service_id'] ?? 0) ?>><?= e($s['name']) ?> (<?= (int)$s['duration_min'] ?> min)</option><?php endforeach; ?></select></div>
      <div class="field"><label for="pro"><?= e(term('professional')) ?></label><select id="pro" name="professional_id"><?php if (count($profs) > 1): ?><option value=""><?= e(__('Cualquiera disponible')) ?></option><?php endif; ?><?php foreach ($profs as $p): ?><option value="<?= (int)$p['id'] ?>"<?= sel($p['id'], $old['prof']) ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="field"><label for="date"><?= e(__('Fecha')) ?></label><input id="date" type="date" data-slot-date value="<?= e($old['date']) ?>"></div>
    <div class="field"><span class="label"><?= e(__('Horarios disponibles')) ?></span><div data-slot-list class="hint"></div></div>
    <input type="hidden" name="start" data-slot-value value="<?= e($old['date'] . ($old['hora'] ? 'T' . $old['hora'] : '')) ?>">
    <details <?= $old['hora'] ? 'open' : '' ?>><summary style="cursor:pointer;font-weight:600"><?= e(__('Hora manual (incluso fuera de horario)')) ?></summary>
      <div class="row row-2" style="margin-top:12px"><div class="field"><label for="sm"><?= e(__('Fecha y hora')) ?></label><input id="sm" type="datetime-local" name="start_manual" value="<?= e($old['hora'] ? $old['date'] . 'T' . $old['hora'] : '') ?>"></div>
      <label class="check" style="align-self:end"><input type="checkbox" name="ignore_schedule" value="1"><span><?= e(__('Ignorar horario laboral, feriados y aviso mínimo (nunca permite traslapes)')) ?></span></label></div>
    </details>
    <?php if ($f('start')): ?><div class="err"><?= e($f('start')) ?></div><?php endif; ?>
  </div>
  <div class="row row-2">
    <div class="field"><label for="source"><?= e(__('Origen')) ?></label><select id="source" name="source"><?php foreach (['phone' => 'Llamada', 'whatsapp' => 'WhatsApp', 'walkin' => 'Presencial', 'manual' => 'Otro'] as $k => $l): ?><option value="<?= e($k) ?>"<?= sel($k, $old['source'] ?? 'phone') ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="status"><?= e(__('Estado inicial')) ?></label><select id="status" name="status"><option value="confirmed"><?= e(__('Confirmada')) ?></option><option value="pending"><?= e(__('Pendiente')) ?></option></select></div>
  </div>
  <div class="row row-2">
    <div class="field"><label for="home"><?= e(__('Dirección (solo servicios a domicilio)')) ?></label><input id="home" name="home_address" value="<?= e($old['home_address'] ?? '') ?>"><?php if ($f('home_address')): ?><div class="err"><?= e($f('home_address')) ?></div><?php endif; ?></div>
    <div class="field"><label for="coupon"><?= e(__('Cupón (opcional)')) ?></label><input id="coupon" name="coupon" value=""><?php if ($f('coupon')): ?><div class="err"><?= e($f('coupon')) ?></div><?php endif; ?></div>
  </div>
  <?php if ($packages): ?><div class="field"><label for="pkg"><?= e(__('Usar paquete de sesiones')) ?></label><select id="pkg" name="client_package_id"><option value=""><?= e(__('No usar paquete')) ?></option><?php foreach ($packages as $pk): ?><option value="<?= (int)$pk['id'] ?>"><?= e($pk['name']) ?> — <?= (int)$pk['sessions_total'] - (int)$pk['sessions_used'] ?> <?= e(__('sesiones restantes')) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <div class="field"><label for="cn"><?= e(__('Comentarios del cliente')) ?></label><textarea id="cn" name="client_note" style="min-height:70px"><?= e($old['client_note'] ?? '') ?></textarea></div>
  <div class="field"><label for="in"><?= e(__('Nota interna (privada)')) ?></label><textarea id="in" name="internal_note" style="min-height:70px"><?= e($old['internal_note'] ?? '') ?></textarea></div>
  <button class="btn btn-gold" type="submit"><?= e(__('Crear %s', mb_strtolower(term('appt')))) ?></button> <a class="btn btn-ghost" href="<?= e(url('/admin/agenda')) ?>"><?= e(__('Cancelar')) ?></a>
</form>
