<div class="page-head"><div><p class="eyebrow">Alertas</p><h1>¿Cómo quieres que te <em>avisemos</em>?</h1></div></div>
<form method="post" class="card form narrow-form"><?= csrf_field() ?>
  <?= f_check('alert_email', 'Avisarme por correo (' . $u['email'] . ')', (bool) $p['alert_email']) ?>
  <?= f_check('alert_wa', 'Avisarme por WhatsApp', (bool) $p['alert_wa']) ?>
  <?= f_input('wa_phone', 'Número de WhatsApp', $p['wa_phone'], 'tel', 'inputmode="tel" placeholder="5555 1234"') ?>
  <?= f_select('freq', 'Frecuencia', ['diaria' => 'Resumen diario (una vez al día)', 'inmediata' => 'Inmediata (cuando aparece algo)'], $p['freq']) ?>
  <button class="btn btn-gold">Guardar alertas</button>
</form>
<p class="muted">Los avisos por WhatsApp también quedan listos para abrir con un clic en <a href="<?= e(url('app/mensajes')) ?>">Mensajes de WhatsApp</a>.</p>
