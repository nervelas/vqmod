<?php
/** Marca y ajustes. Variables: $f (valores), $errors, $warn, $files, $zones, $professions, $contrast, $darkBg */
$F = static function (string $name, string $label, array $o = []) use ($f, $errors): void {
    partial('admin/settings/_field', array_merge(['name' => $name, 'label' => $label, 'value' => $f[$name] ?? '', 'error' => $errors[$name] ?? ''], $o));
};
$zoneOpts = [];
foreach ($zones as $z) {
    $zoneOpts[$z] = $z;
}
$profOpts = $professions;
if (!isset($profOpts[$f['profession']])) {
    $profOpts[$f['profession']] = ucfirst(str_replace('_', ' ', (string) $f['profession']));
}
$sections = [
    'marca' => 'Marca',
    'contacto' => 'Contacto',
    'regional' => 'Región y formato',
    'terminos' => 'Terminología',
    'reservas' => 'Reservas',
    'pagos' => 'Pagos',
];
?>
<div class="page p4-page">
  <div class="page-head">
    <div>
      <h1 class="page-title">Marca y ajustes</h1>
      <p class="page-sub">La identidad de tu negocio y las reglas generales de tu agenda. Los cambios se ven de inmediato en tu página pública.</p>
    </div>
    <div class="page-actions">
      <button class="btn btn-gold" type="submit" form="ajustes-form"><?= icon('check') ?>Guardar cambios</button>
    </div>
  </div>

  <?php if ($errors) : ?>
    <div class="alert alert-err" role="alert">Revisa los campos marcados: hay <?= count($errors) ?> dato<?= count($errors) === 1 ? '' : 's' ?> por corregir. No se guardó nada todavía.</div>
  <?php endif; ?>
  <?php foreach ($warn as $w) : ?><div class="alert alert-warn" role="status"><?= e($w) ?></div><?php endforeach; ?>

  <nav class="p4-index" aria-label="Secciones de ajustes">
    <?php foreach ($sections as $id => $name) : ?><a class="chip" href="#<?= e($id) ?>"><?= e($name) ?></a><?php endforeach; ?>
  </nav>

  <div class="p4-cols">
    <form id="ajustes-form" class="stack p4-form" method="post" action="<?= e(url('/admin/ajustes')) ?>" enctype="multipart/form-data" novalidate data-settings-form>
      <?= csrf_field() ?>

      <section class="card" id="marca" aria-labelledby="h-marca">
        <div class="card-head"><h2 id="h-marca" class="serif">Marca</h2></div>
        <div class="card-body stack">
          <div class="form-grid">
            <?php $F('business_name', 'Nombre del negocio', ['max' => 120, 'attrs' => ['required' => true, 'data-preview-src' => 'name']]); ?>
            <?php $F('tagline', 'Eslogan', ['max' => 160, 'hint' => 'Una frase corta que aparece bajo tu nombre.', 'attrs' => ['data-preview-src' => 'tagline']]); ?>
          </div>
          <?php $F('about', 'Acerca de', ['type' => 'textarea', 'rows' => 4, 'max' => 2000, 'hint' => 'Cuenta en pocas líneas quién eres y qué ofreces. Se muestra en tu página pública.']); ?>

          <div class="p4-assets">
            <?php foreach ([
                'logo' => ['Logo', 'Fondo transparente o sólido; PNG, JPG o WebP de hasta 4 MB.'],
                'favicon' => ['Favicon', 'Ícono cuadrado que aparece en la pestaña del navegador.'],
                'hero' => ['Imagen de portada', 'Fotografía horizontal para tu página pública (mínimo 1600 px de ancho).'],
            ] as $slot => [$lbl, $hint]) : $cur = $files[$slot] ?? ''; ?>
              <div class="field p4-asset" data-asset="<?= e($slot) ?>">
                <label for="f-<?= e($slot) ?>"><?= e($lbl) ?></label>
                <div class="p4-asset-box<?= $cur === '' ? ' is-empty' : '' ?>">
                  <?php if ($cur !== '') : ?><img src="<?= e($cur) ?>" alt="<?= e($lbl) ?> actual" loading="lazy" data-asset-img><?php else : ?><img alt="" data-asset-img hidden><span class="muted p4-asset-none" data-asset-none>Sin imagen</span><?php endif; ?>
                </div>
                <input class="input" type="file" id="f-<?= e($slot) ?>" name="<?= e($slot) ?>" accept="image/png,image/jpeg,image/webp" data-asset-input aria-describedby="f-<?= e($slot) ?>-hint<?= isset($errors[$slot]) ? ' f-' . e($slot) . '-err' : '' ?>">
                <p class="hint" id="f-<?= e($slot) ?>-hint"><?= e($hint) ?></p>
                <?php if (isset($errors[$slot])) : ?><p class="error" id="f-<?= e($slot) ?>-err" role="alert"><?= e($errors[$slot]) ?></p><?php endif; ?>
                <?php if ($cur !== '') : ?><label class="check"><input type="checkbox" name="remove_<?= e($slot) ?>" value="1"><span>Quitar <?= e(mb_strtolower($lbl)) ?></span></label><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="field p4-gold">
            <label for="f-color_gold">Color de oro de marca</label>
            <div class="input-group p4-colorrow">
              <input type="color" id="f-color_gold-picker" class="p4-picker" value="<?= e($f['color_gold']) ?>" aria-label="Elegir color con el selector" data-gold-picker>
              <input class="input mono" type="text" id="f-color_gold" name="color_gold" value="<?= e($f['color_gold']) ?>" maxlength="7" spellcheck="false" autocomplete="off" data-gold-input aria-describedby="gold-contrast<?= isset($errors['color_gold']) ? ' f-color_gold-err' : '' ?>"<?= isset($errors['color_gold']) ? ' aria-invalid="true"' : '' ?>>
              <button class="btn btn-outline btn-sm" type="button" data-gold-reset data-default="#C9A050">Restaurar oro clásico</button>
            </div>
            <p class="hint" id="gold-contrast" data-contrast data-bg="<?= e($darkBg) ?>" aria-live="polite">Contraste sobre fondo oscuro: <strong data-contrast-value><?= e(number_format($contrast, 1)) ?>:1</strong> <span class="badge <?= $contrast >= 4.5 ? 'badge-ok' : 'badge-warn' ?>" data-contrast-badge><?= $contrast >= 4.5 ? 'Cumple AA' : 'Bajo para texto' ?></span></p>
            <?php if (isset($errors['color_gold'])) : ?><p class="error" id="f-color_gold-err" role="alert"><?= e($errors['color_gold']) ?></p><?php endif; ?>
          </div>
        </div>
      </section>

      <section class="card" id="contacto" aria-labelledby="h-contacto">
        <div class="card-head"><h2 id="h-contacto" class="serif">Contacto y redes</h2></div>
        <div class="card-body stack">
          <div class="form-grid">
            <?php $F('whatsapp', 'WhatsApp', ['type' => 'tel', 'max' => 30, 'placeholder' => '+502 5555 1234', 'hint' => 'Se usa para los botones “Escríbenos” y los mensajes de un toque.']); ?>
            <?php $F('phone', 'Teléfono', ['type' => 'tel', 'max' => 30, 'placeholder' => '2222 3333']); ?>
            <?php $F('email', 'Correo de contacto', ['type' => 'email', 'max' => 190, 'placeholder' => 'hola@minegocio.com']); ?>
            <?php $F('website', 'Sitio web', ['type' => 'url', 'max' => 300, 'placeholder' => 'https://minegocio.com']); ?>
          </div>
          <?php $F('address', 'Dirección', ['max' => 255, 'placeholder' => '12 calle 3-45, zona 10, Ciudad de Guatemala']); ?>
          <?php $F('map_url', 'Enlace del mapa', ['type' => 'url', 'max' => 500, 'hint' => 'Solo enlace (https). No incrustamos mapas externos para cuidar la privacidad de tus clientes.']); ?>
          <div class="form-grid">
            <?php $F('social_facebook', 'Facebook', ['type' => 'url', 'max' => 300, 'placeholder' => 'https://facebook.com/minegocio']); ?>
            <?php $F('social_instagram', 'Instagram', ['type' => 'url', 'max' => 300, 'placeholder' => 'https://instagram.com/minegocio']); ?>
            <?php $F('social_tiktok', 'TikTok', ['type' => 'url', 'max' => 300, 'placeholder' => 'https://tiktok.com/@minegocio']); ?>
            <?php $F('social_youtube', 'YouTube', ['type' => 'url', 'max' => 300, 'placeholder' => 'https://youtube.com/@minegocio']); ?>
          </div>
        </div>
      </section>

      <section class="card" id="regional" aria-labelledby="h-regional">
        <div class="card-head"><h2 id="h-regional" class="serif">Región y formato</h2></div>
        <div class="card-body">
          <div class="form-grid">
            <?php $F('timezone', 'Zona horaria del negocio', ['type' => 'select', 'options' => $zoneOpts, 'hint' => 'Guatemala no cambia de hora durante el año (GMT-6).']); ?>
            <?php $F('time_format', 'Formato de hora', ['type' => 'select', 'options' => ['12' => '12 horas (2:30 p. m.)', '24' => '24 horas (14:30)']]); ?>
            <?php $F('currency_symbol', 'Símbolo de moneda', ['max' => 6, 'hint' => 'Por ejemplo Q para quetzales.']); ?>
            <?php $F('phone_cc', 'Prefijo telefónico del país', ['max' => 4, 'hint' => 'Sin el signo +. Guatemala es 502.', 'attrs' => ['inputmode' => 'numeric']]); ?>
          </div>
        </div>
      </section>

      <section class="card" id="terminos" aria-labelledby="h-terminos">
        <div class="card-head"><h2 id="h-terminos" class="serif">Profesión y terminología</h2></div>
        <div class="card-body stack">
          <p class="muted">Adapta las palabras del sistema a tu oficio. Esto cambia solo los textos; para cargar eventos y recordatorios sugeridos usa el <a class="text-gold" href="<?= e(url('/admin/asistente')) ?>">asistente de inicio</a>.</p>
          <div class="form-grid">
            <?php $F('profession', 'Profesión', ['type' => 'select', 'options' => $profOpts]); ?>
            <?php $F('terms_label', 'Cómo llamas a una cita (singular)', ['max' => 30, 'hint' => 'Ejemplos: cita, consulta, sesión, reunión.']); ?>
            <?php $F('terms_label_plural', 'Plural', ['max' => 30]); ?>
            <?php $F('host_label', 'Quien atiende', ['max' => 30, 'hint' => 'Ejemplos: doctor, terapeuta, asesor, profesional.']); ?>
            <?php $F('client_label', 'Quien reserva', ['max' => 30, 'hint' => 'Ejemplos: paciente, cliente, alumno.']); ?>
          </div>
        </div>
      </section>

      <section class="card" id="reservas" aria-labelledby="h-reservas">
        <div class="card-head"><h2 id="h-reservas" class="serif">Reglas de reserva</h2></div>
        <div class="card-body stack">
          <label class="check"><input type="checkbox" name="public_home_enabled" value="1"<?= chk($f['public_home_enabled'] === '1') ?>><span>Mostrar la página de inicio pública con todos mis servicios</span></label>
          <div class="form-grid">
            <?php $F('pending_expire_hours', 'Expiración de citas pendientes (horas)', ['type' => 'number', 'attrs' => ['min' => 1, 'max' => 720], 'hint' => 'Las citas que esperan aprobación o pago se liberan pasado este tiempo.']); ?>
            <?php $F('waitlist_offer_minutes', 'Oferta de lista de espera (minutos)', ['type' => 'number', 'attrs' => ['min' => 5, 'max' => 1440], 'hint' => 'Tiempo que tiene la persona para aceptar un horario liberado.']); ?>
          </div>
          <fieldset class="fieldset">
            <legend>Política de inasistencias</legend>
            <div class="form-grid">
              <?php $F('noshow_deposit_after', 'Pedir anticipo después de (inasistencias)', ['type' => 'number', 'attrs' => ['min' => 0, 'max' => 20], 'hint' => '0 = nunca pedir anticipo.']); ?>
              <?php $F('noshow_deposit_percent', 'Porcentaje de anticipo', ['type' => 'number', 'attrs' => ['min' => 1, 'max' => 100]]); ?>
              <?php $F('noshow_block_after', 'Bloquear reservas en línea después de (inasistencias)', ['type' => 'number', 'attrs' => ['min' => 0, 'max' => 20], 'hint' => '0 = nunca bloquear. La persona verá un mensaje amable para contactarte.']); ?>
            </div>
          </fieldset>
          <div class="form-grid">
            <?php $F('video_provider_domain', 'Proveedor de videollamada', ['max' => 120, 'hint' => 'Dominio usado para crear salas automáticas. Por defecto meet.jit.si (Jitsi).', 'attrs' => ['spellcheck' => 'false']]); ?>
            <?php $F('embed_allowed_origins', 'Sitios donde se puede insertar tu agenda', ['max' => 1000, 'hint' => 'Orígenes completos separados por espacios (https://mi-sitio.com) o * para cualquiera.', 'attrs' => ['spellcheck' => 'false']]); ?>
          </div>
        </div>
      </section>

      <section class="card" id="pagos" aria-labelledby="h-pagos">
        <div class="card-head"><h2 id="h-pagos" class="serif">Pagos</h2></div>
        <div class="card-body stack">
          <div class="alert alert-info" role="note"><?= icon('shield') ?> <span>Agenda Premium <strong>no almacena datos de tarjetas</strong>. Los cobros se hacen por transferencia o con el enlace de pago de tu proveedor.</span></div>
          <?php $F('bank_info', 'Datos bancarios para transferencias', ['type' => 'textarea', 'rows' => 4, 'max' => 1000, 'hint' => 'Banco, tipo y número de cuenta, nombre del titular. Se muestra a quien debe pagar un anticipo.']); ?>
          <?php $F('payment_link', 'Enlace de pago externo', ['type' => 'url', 'max' => 500, 'hint' => 'Opcional. Debe empezar con https://']); ?>
        </div>
      </section>

      <div class="form-actions p4-sticky-actions">
        <button class="btn btn-gold btn-lg" type="submit"><?= icon('check') ?>Guardar cambios</button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/ajustes')) ?>">Descartar</a>
      </div>
    </form>

    <aside class="p4-preview" aria-label="Vista previa de tu marca">
      <div class="card card-gold p4-preview-card" data-brand-preview data-vars-live>
        <div class="card-head"><h2 class="serif">Vista previa</h2><span class="badge badge-muted">En vivo</span></div>
        <div class="card-body stack">
          <div class="p4-pv-hero guilloche">
            <div class="p4-pv-logo">
              <?php if (($files['logo'] ?? '') !== '') : ?><img src="<?= e($files['logo']) ?>" alt="" data-preview-logo><?php else : ?><img alt="" data-preview-logo hidden><span class="p4-pv-mark" data-preview-mark><?= icon('sparkle') ?></span><?php endif; ?>
            </div>
            <p class="p4-pv-name serif" data-preview="name"><?= e($f['business_name']) ?></p>
            <p class="p4-pv-tag" data-preview="tagline"><?= e($f['tagline']) ?></p>
            <span class="p4-pv-btn">Reservar cita</span>
          </div>
          <p class="p4-pv-sample">Así se ve tu <span class="p4-pv-gold">oro de marca</span> sobre fondo oscuro.</p>
          <p class="hint">La vista previa usa los datos que escribes aunque aún no los hayas guardado.</p>
        </div>
      </div>
    </aside>
  </div>
</div>
