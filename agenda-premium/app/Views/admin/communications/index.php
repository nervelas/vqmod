<?php
/** Correo y WhatsApp. */
use App\Controllers\Admin\A4Controller;

$F = static function (string $name, string $label, array $o = []) use ($f, $errors): void {
    partial('admin/settings/_field', array_merge(['name' => $name, 'label' => $label, 'value' => $f[$name] ?? '', 'error' => $errors[$name] ?? ''], $o));
};
/** Campo de secreto: nunca muestra el valor guardado. */
$S = static function (string $name, string $label, bool $has, string $hint = '') use ($errors): void {
    partial('admin/settings/_field', [
        'name' => $name, 'label' => $label, 'value' => '', 'type' => 'password', 'error' => $errors[$name] ?? '',
        'placeholder' => $has ? '••••••• (guardada)' : 'Aún sin configurar', 'hint' => $hint, 'attrs' => ['autocomplete' => 'new-password', 'spellcheck' => 'false'],
    ]);
    if ($has) {
        echo '<label class="check p4-clear"><input type="checkbox" name="clear_' . e($name) . '" value="1"><span>Borrar la clave guardada</span></label>';
    }
};
$cronAge = $cronLast > 0 ? time() - $cronLast : null;
$nav = ['smtp' => 'Correo', 'whatsapp' => 'WhatsApp', 'captcha' => 'Captcha', 'limites' => 'Límites', 'cron' => 'Tareas programadas'];
?>
<div class="page p4-page">
  <div class="page-head">
    <div>
      <h1 class="page-title">Correo y WhatsApp</h1>
      <p class="page-sub">Cómo salen los avisos a tus clientes, qué protege tus formularios y cómo se ejecutan las tareas automáticas.</p>
    </div>
    <div class="page-actions"><button class="btn btn-gold" type="submit" form="comm-form"><?= icon('check') ?>Guardar cambios</button></div>
  </div>

  <?php if ($errors) : ?><div class="alert alert-err" role="alert">Revisa los campos marcados. No se guardó nada todavía.</div><?php endif; ?>

  <nav class="p4-index" aria-label="Secciones"><?php foreach ($nav as $id => $name) : ?><a class="chip" href="#<?= e($id) ?>"><?= e($name) ?></a><?php endforeach; ?></nav>

  <form id="comm-form" class="stack p4-form" method="post" action="<?= e(url('/admin/comunicaciones')) ?>" novalidate data-settings-form>
    <?= csrf_field() ?>

    <section class="card" id="smtp" aria-labelledby="h-smtp">
      <div class="card-head"><h2 id="h-smtp" class="serif">Servidor de correo (SMTP)</h2></div>
      <div class="card-body stack">
        <p class="muted">Si dejas el servidor vacío, el sistema usa el correo del hosting (menos confiable: los mensajes pueden caer en no deseado). Para mejores resultados usa el SMTP de tu dominio o de tu proveedor de correo.</p>
        <div class="form-grid">
          <?php $F('smtp_host', 'Servidor', ['max' => 190, 'placeholder' => 'smtp.midominio.com', 'attrs' => ['spellcheck' => 'false', 'autocomplete' => 'off']]); ?>
          <?php $F('smtp_port', 'Puerto', ['type' => 'number', 'attrs' => ['min' => 1, 'max' => 65535]]); ?>
          <?php $F('smtp_secure', 'Seguridad', ['type' => 'select', 'options' => ['tls' => 'STARTTLS (puerto 587)', 'ssl' => 'SSL/TLS (puerto 465)', 'none' => 'Ninguna (no recomendado)']]); ?>
          <?php $F('smtp_user', 'Usuario', ['max' => 190, 'attrs' => ['autocomplete' => 'off', 'spellcheck' => 'false']]); ?>
          <div><?php $S('smtp_pass', 'Contraseña', $secrets['smtp_pass'], 'Se guarda cifrada y no se vuelve a mostrar. Déjala vacía para conservar la actual.'); ?></div>
        </div>
        <div class="form-grid">
          <?php $F('mail_from_name', 'Nombre del remitente', ['max' => 120, 'placeholder' => 'Mi Negocio']); ?>
          <?php $F('mail_from_email', 'Correo del remitente', ['type' => 'email', 'max' => 190, 'placeholder' => 'citas@midominio.com', 'hint' => 'Usa una dirección de tu mismo dominio para evitar que lo marquen como sospechoso.']); ?>
          <?php $F('admin_notify_email', 'Correo de aviso al administrador', ['type' => 'email', 'max' => 190, 'hint' => 'Recibe un aviso cuando entra una cita o hay algo por revisar.']); ?>
        </div>
        <label class="check"><input type="checkbox" name="weekly_summary" value="1"<?= chk(($f['weekly_summary'] ?? '0') === '1') ?>><span>Enviarme un resumen semanal de actividad por correo</span></label>
      </div>
    </section>

    <section class="card" id="whatsapp" aria-labelledby="h-wa">
      <div class="card-head row row-between row-wrap"><h2 id="h-wa" class="serif">WhatsApp Business (API oficial)</h2><span class="badge <?= ($f['wa_api_enabled'] ?? '0') === '1' ? 'badge-ok' : 'badge-muted' ?>"><?= ($f['wa_api_enabled'] ?? '0') === '1' ? 'Activada' : 'Desactivada' ?></span></div>
      <div class="card-body stack">
        <div class="alert alert-info" role="note"><?= icon('info') ?><div><strong>Es opcional y está desactivada por defecto.</strong> Sin la API, los recordatorios de WhatsApp aparecen en “Mensajes de hoy” para enviarlos con un toque desde tu propio teléfono, sin costo adicional. La API oficial de WhatsApp Business Cloud envía los mensajes por ti, pero requiere una cuenta verificada en Meta, plantillas aprobadas y tiene costo por conversación.</div></div>
        <label class="check"><input type="checkbox" name="wa_api_enabled" value="1" data-toggle-group="wa-fields"<?= chk(($f['wa_api_enabled'] ?? '0') === '1') ?>><span>Enviar mensajes automáticamente con la API de WhatsApp Business</span></label>
        <?php if (isset($errors['wa_api_enabled'])) : ?><p class="error" role="alert"><?= e($errors['wa_api_enabled']) ?></p><?php endif; ?>
        <div class="stack" data-group="wa-fields">
          <div class="form-grid">
            <div><?php $S('wa_api_token', 'Token de acceso permanente', $secrets['wa_api_token'], 'Se guarda cifrado y no se vuelve a mostrar.'); ?></div>
            <?php $F('wa_api_phone_id', 'Identificador del número', ['max' => 40, 'attrs' => ['inputmode' => 'numeric', 'autocomplete' => 'off']]); ?>
            <?php $F('wa_api_template', 'Nombre de la plantilla aprobada', ['max' => 100, 'hint' => 'Tal como aparece en tu panel de Meta, por ejemplo recordatorio_cita.', 'attrs' => ['spellcheck' => 'false']]); ?>
            <?php $F('wa_api_lang', 'Idioma de la plantilla', ['max' => 10, 'hint' => 'es, es_MX, es_ES…']); ?>
          </div>
        </div>
        <?php if ($waErrors) : ?>
          <div>
            <h3 class="serif">Errores recientes de WhatsApp</h3>
            <div class="table-wrap"><table class="table table-sm"><thead><tr><th scope="col">Fecha</th><th scope="col">Teléfono</th><th scope="col">Detalle</th></tr></thead><tbody>
              <?php foreach ($waErrors as $w) : ?><tr><td class="mono nowrap"><?= e(A4Controller::local($w['created_at'])) ?></td><td class="mono"><?= e($w['phone']) ?></td><td><?= e($w['last_error'] ?: 'Sin detalle') ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="card" id="captcha" aria-labelledby="h-cap">
      <div class="card-head"><h2 id="h-cap" class="serif">Captcha (opcional)</h2></div>
      <div class="card-body stack">
        <p class="muted">Por defecto tus formularios se protegen sin captcha (límite de intentos, tiempo mínimo y campo trampa). Actívalo solo si recibes reservas falsas.</p>
        <div class="alert alert-warn" role="note"><?= icon('alert') ?><span>Al activarlo, las páginas de reserva cargan un script del proveedor elegido (Cloudflare Turnstile o hCaptcha). Es el único recurso externo que usa el sistema y solo se carga si lo activas.</span></div>
        <div class="form-grid">
          <?php $F('captcha_provider', 'Proveedor', ['type' => 'select', 'options' => ['none' => 'Ninguno', 'turnstile' => 'Cloudflare Turnstile', 'hcaptcha' => 'hCaptcha']]); ?>
          <?php $F('captcha_site_key', 'Clave del sitio', ['max' => 200, 'attrs' => ['autocomplete' => 'off', 'spellcheck' => 'false']]); ?>
          <div><?php $S('captcha_secret', 'Clave secreta', $secrets['captcha_secret'], 'Se guarda cifrada y no se vuelve a mostrar.'); ?></div>
        </div>
      </div>
    </section>

    <section class="card" id="limites" aria-labelledby="h-lim">
      <div class="card-head"><h2 id="h-lim" class="serif">Límites y seguridad</h2></div>
      <div class="card-body">
        <div class="form-grid">
          <?php $F('booking_rate_limit', 'Reservas públicas por hora y por dirección', ['type' => 'number', 'attrs' => ['min' => 1, 'max' => 200], 'hint' => 'Frena a quien intente llenar tu agenda con reservas falsas.']); ?>
          <?php $F('booking_min_form_seconds', 'Segundos mínimos para llenar el formulario', ['type' => 'number', 'attrs' => ['min' => 0, 'max' => 60], 'hint' => 'Una persona real tarda más que un programa automático.']); ?>
          <?php $F('api_rate_per_minute', 'Límite de la API (solicitudes por minuto)', ['type' => 'number', 'attrs' => ['min' => 10, 'max' => 600], 'hint' => 'Por cada clave de API.']); ?>
          <?php $F('session_idle_minutes', 'Cerrar sesión del panel por inactividad (minutos)', ['type' => 'number', 'attrs' => ['min' => 15, 'max' => 1440]]); ?>
        </div>
      </div>
    </section>

    <div class="form-actions"><button class="btn btn-gold btn-lg" type="submit"><?= icon('check') ?>Guardar cambios</button></div>
  </form>

  <div class="grid cols-2 mt-4">
    <section class="card" aria-labelledby="h-tm">
      <div class="card-head"><h2 id="h-tm" class="serif">Probar el correo</h2></div>
      <div class="card-body stack">
        <p class="muted">Las pruebas usan los datos <strong>guardados</strong>; si acabas de cambiarlos, guarda primero.</p>
        <?php if (!$mailerReady) : ?><div class="alert alert-warn" role="status">El servicio de correo todavía no está instalado en esta versión.</div><?php endif; ?>
        <form class="stack" method="post" action="<?= e(url('/admin/comunicaciones/probar-correo')) ?>">
          <?= csrf_field() ?>
          <div class="field">
            <label for="f-to">Enviar correo de prueba a</label>
            <input class="input" type="email" id="f-to" name="to" required maxlength="190" value="<?= e($adminEmail) ?>" autocomplete="email">
          </div>
          <div class="form-actions"><button class="btn btn-gold" type="submit"><?= icon('mail') ?>Enviar correo de prueba</button></div>
        </form>
        <form method="post" action="<?= e(url('/admin/comunicaciones/probar-conexion')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-outline" type="submit"><?= icon('zap') ?>Probar conexión</button>
        </form>
        <?php if ($mailErrors) : ?>
          <h3 class="serif">Correos que no salieron</h3>
          <div class="table-wrap"><table class="table table-sm"><thead><tr><th scope="col">Fecha</th><th scope="col">Para</th><th scope="col">Detalle</th></tr></thead><tbody>
            <?php foreach ($mailErrors as $m) : ?><tr><td class="mono nowrap"><?= e(A4Controller::local($m['created_at'])) ?></td><td><?= e($m['to_email']) ?></td><td><?= e($m['last_error'] ?: 'Sin detalle') ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
        <?php endif; ?>
      </div>
    </section>

    <section class="card" aria-labelledby="h-tw">
      <div class="card-head"><h2 id="h-tw" class="serif">Probar WhatsApp</h2></div>
      <div class="card-body stack">
        <?php if (($f['wa_api_enabled'] ?? '0') !== '1') : ?>
          <p class="muted">La API está desactivada. Mientras tanto, usa “Mensajes de hoy” para enviar recordatorios con un toque.</p>
          <a class="btn btn-outline" href="<?= e(url('/admin/mensajes')) ?>"><?= icon('whatsapp') ?>Ir a mensajes de hoy</a>
        <?php else : ?>
          <form class="stack" method="post" action="<?= e(url('/admin/comunicaciones/probar-whatsapp')) ?>">
            <?= csrf_field() ?>
            <div class="field">
              <label for="f-phone">Número que recibirá el mensaje</label>
              <input class="input" type="tel" id="f-phone" name="phone" required maxlength="30" placeholder="+502 5555 1234" autocomplete="tel">
              <p class="hint">Si tu plantilla requiere variables, la prueba puede fallar aunque la conexión esté bien.</p>
            </div>
            <div class="form-actions"><button class="btn btn-gold" type="submit"><?= icon('whatsapp') ?>Enviar mensaje de prueba</button></div>
          </form>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <section class="card mt-4" id="cron" aria-labelledby="h-cron">
    <div class="card-head row row-between row-wrap">
      <h2 id="h-cron" class="serif">Tareas programadas (cron)</h2>
      <?php if ($cronAge === null) : ?><span class="badge badge-err">Nunca se ha ejecutado</span>
      <?php elseif ($cronAge <= 900) : ?><span class="badge badge-ok">Activo</span>
      <?php elseif ($cronAge <= 3600) : ?><span class="badge badge-warn">Retrasado</span>
      <?php else : ?><span class="badge badge-err">Detenido</span><?php endif; ?>
    </div>
    <div class="card-body stack">
      <p class="muted">El cron envía recordatorios, libera citas vencidas, sincroniza calendarios y respalda tus datos. Programa <strong>una</strong> de estas dos opciones para que se ejecute cada 5 minutos. Sin cron, el sistema se apoya en las visitas a tu página, que es menos puntual.</p>
      <div class="field">
        <label for="f-cronurl">Opción 1: URL para servicios de tareas programadas (hosting compartido)</label>
        <div class="input-group">
          <input class="input mono" id="f-cronurl" type="text" readonly value="<?= e($cronUrl) ?>" aria-describedby="cron-warn">
          <button class="btn btn-outline" type="button" data-copy="#f-cronurl"><?= icon('copy') ?>Copiar</button>
        </div>
        <p class="hint" id="cron-warn">Tiene un token secreto: no la compartas. Si se filtra, genera uno nuevo.</p>
      </div>
      <div class="field">
        <label for="f-croncli">Opción 2: línea para crontab (línea de comandos)</label>
        <div class="input-group">
          <input class="input mono" id="f-croncli" type="text" readonly value="*/5 * * * * <?= e($cronCli) ?> >/dev/null 2>&amp;1">
          <button class="btn btn-outline" type="button" data-copy="#f-croncli"><?= icon('copy') ?>Copiar</button>
        </div>
      </div>
      <p class="muted">Última ejecución: <strong><?= $cronLast > 0 ? e(A4Controller::local(gmdate('Y-m-d H:i:s', $cronLast), 'd/m/Y H:i:s')) : 'nunca' ?></strong>. <a class="text-gold" href="<?= e(url('/admin/sistema')) ?>">Ver estado del sistema</a></p>
      <form method="post" action="<?= e(url('/admin/comunicaciones/cron-token')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-danger btn-sm" type="submit" data-confirm="¿Generar un token nuevo? La URL actual dejará de funcionar y tendrás que actualizarla en tu servicio de tareas programadas."><?= icon('refresh') ?>Generar token nuevo</button>
      </form>
    </div>
  </section>
</div>
