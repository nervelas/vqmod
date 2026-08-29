<form method="post" action="<?= e(url('/super/ajustes')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="cols cols--sidebar">
    <div class="stack">
      <div class="card">
        <div class="card__head"><span class="secnum">01/</span><h2>Marca de la plataforma</h2>
          <button class="btn btn--accent btn--sm ml-auto" type="submit">Guardar</button></div>
        <div class="card__body">
          <div class="row-2">
            <div class="field"><label for="platform_name">Nombre de la plataforma</label>
              <input class="input" id="platform_name" name="platform_name" maxlength="80" value="<?= e($s['platform_name'] ?? 'CotizaPro B2B') ?>"></div>
            <div class="field"><label for="platform_tagline">Frase corta</label>
              <input class="input" id="platform_tagline" name="platform_tagline" maxlength="190" value="<?= e($s['platform_tagline'] ?? '') ?>"></div>
          </div>
          <div class="row-3">
            <div class="field"><label for="contact_email">Correo de contacto</label>
              <input class="input" id="contact_email" name="contact_email" type="email" maxlength="150" value="<?= e($s['contact_email'] ?? '') ?>"></div>
            <div class="field"><label for="phone">Teléfono</label>
              <input class="input" id="phone" name="phone" maxlength="40" value="<?= e($s['phone'] ?? '') ?>"></div>
            <div class="field"><label for="whatsapp">WhatsApp (solo números)</label>
              <input class="input" id="whatsapp" name="whatsapp" maxlength="30" value="<?= e($s['whatsapp'] ?? '') ?>" placeholder="50255551234"></div>
          </div>
          <div class="field"><label for="whatsapp_message">Mensaje precargado de WhatsApp</label>
            <input class="input" id="whatsapp_message" name="whatsapp_message" maxlength="400" value="<?= e($s['whatsapp_message'] ?? '') ?>"></div>
          <div class="field"><label for="address">Dirección</label>
            <input class="input" id="address" name="address" maxlength="220" value="<?= e($s['address'] ?? '') ?>"></div>
          <div class="row-2">
            <div class="field"><label for="demo_slug">Empresa demostrativa</label>
              <select class="select" id="demo_slug" name="demo_slug">
                <option value="">— Ninguna —</option>
                <?php foreach ($companies as $c): ?>
                  <option value="<?= e($c['slug']) ?>"<?= ($s['demo_slug'] ?? '') === $c['slug'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select></div>
            <div class="field"><label for="hero_image">Imagen del hero de la landing</label>
              <input class="input" id="hero_image" name="hero_image" type="file" accept="image/*"></div>
          </div>
          <div class="row-2">
            <div class="field"><label for="seo_title">Título SEO</label>
              <input class="input" id="seo_title" name="seo_title" maxlength="190" value="<?= e($s['seo_title'] ?? '') ?>"></div>
            <div class="field"><label for="seo_description">Descripción SEO</label>
              <input class="input" id="seo_description" name="seo_description" maxlength="300" value="<?= e($s['seo_description'] ?? '') ?>"></div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><span class="secnum">02/</span><h2>SMTP de la plataforma</h2></div>
        <div class="card__body">
          <p class="small muted" style="margin-bottom:16px">Se usa para los correos del sistema y como respaldo cuando una empresa no configuró su propio SMTP.</p>
          <div class="row-3">
            <div class="field"><label for="smtp_host">Servidor</label><input class="input" id="smtp_host" name="smtp_host" maxlength="150" value="<?= e($s['smtp_host'] ?? '') ?>"></div>
            <div class="field"><label for="smtp_port">Puerto</label><input class="input" id="smtp_port" name="smtp_port" type="number" value="<?= e($s['smtp_port'] ?? '587') ?>"></div>
            <div class="field"><label for="smtp_secure">Cifrado</label>
              <select class="select" id="smtp_secure" name="smtp_secure">
                <?php foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'ninguna' => 'Sin cifrado'] as $k => $lbl): ?>
                  <option value="<?= e($k) ?>"<?= ($s['smtp_secure'] ?? 'tls') === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
              </select></div>
          </div>
          <div class="row-2">
            <div class="field"><label for="smtp_user">Usuario</label><input class="input" id="smtp_user" name="smtp_user" maxlength="150" value="<?= e($s['smtp_user'] ?? '') ?>" autocomplete="off"></div>
            <div class="field"><label for="smtp_pass">Contraseña</label><input class="input" id="smtp_pass" name="smtp_pass" type="password" autocomplete="new-password" placeholder="<?= !empty($s['smtp_pass']) ? '•••••• (sin cambios)' : '' ?>"></div>
          </div>
          <div class="row-2">
            <div class="field"><label for="smtp_from">Remitente</label><input class="input" id="smtp_from" name="smtp_from" type="email" maxlength="150" value="<?= e($s['smtp_from'] ?? '') ?>"></div>
            <div class="field"><label for="smtp_from_name">Nombre del remitente</label><input class="input" id="smtp_from_name" name="smtp_from_name" maxlength="150" value="<?= e($s['smtp_from_name'] ?? '') ?>"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="stack">
      <div class="card">
        <div class="card__head"><h2>Tarea programada (cron)</h2></div>
        <div class="card__body">
          <p class="small muted">Agregue esta línea en cPanel → Trabajos cron, cada 15 minutos:</p>
          <div class="copyfield" style="margin-top:10px">
            <label class="sr-only" for="cronline">Comando del cron</label>
            <?php $cronCmd = '*/15 * * * * curl -s "' . absUrl('/cron/run.php') . '?token=' . $cronToken . '" >/dev/null 2>&1'; ?>
            <input id="cronline" readonly value="<?= e($cronCmd) ?>">
            <button type="button" data-copy="cronline">Copiar</button>
          </div>
          <p class="hint" style="margin-top:10px">Ejecuta recordatorios de seguimiento, informes mensuales, limpieza y el respaldo semanal.</p>
        </div>
      </div>
      <div class="card">
        <div class="card__head"><h2>Accesos rápidos</h2></div>
        <div class="card__body stack-sm">
          <a class="btn btn--ghost btn--block" href="<?= e(url('/super/empresas')) ?>">Administrar empresas</a>
          <a class="btn btn--ghost btn--block" href="<?= e(url('/super/planes')) ?>">Planes y límites</a>
          <a class="btn btn--ghost btn--block" href="<?= e(url('/super/landing')) ?>">Landing de venta</a>
          <a class="btn btn--ghost btn--block" href="<?= e(url('/super/respaldos')) ?>">Respaldos</a>
        </div>
      </div>
    </div>
  </div>
</form>
