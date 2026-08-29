<?php $sym = (string) $c['currency_symbol']; ?>
<form method="post" action="<?= e(url('/panel/ajustes')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="cols cols--sidebar">
    <div class="stack">
      <div class="card">
        <div class="card__head"><span class="secnum">01/</span><h2>Identidad</h2></div>
        <div class="card__body">
          <div class="row-2">
            <div class="field"><label for="name">Nombre comercial *</label><input class="input" id="name" name="name" maxlength="140" required value="<?= e($c['name']) ?>"></div>
            <div class="field"><label for="legal_name">Razón social</label><input class="input" id="legal_name" name="legal_name" maxlength="180" value="<?= e($c['legal_name']) ?>"></div>
          </div>
          <div class="row-3">
            <div class="field"><label for="nit">NIT</label><input class="input" id="nit" name="nit" maxlength="30" value="<?= e($c['nit']) ?>"></div>
            <div class="field"><label for="years_experience">Años en la industria</label><input class="input" id="years_experience" name="years_experience" type="number" min="0" max="200" value="<?= e((int) $c['years_experience']) ?>"></div>
            <div class="field"><label for="app_name">Nombre del sistema</label><input class="input" id="app_name" name="app_name" maxlength="80" value="<?= e($appName) ?>">
              <p class="hint">Aparece en la pantalla de acceso y en el crédito del sitio.</p></div>
          </div>
          <div class="field"><label for="tagline">Frase del hero (se parte en líneas)</label>
            <input class="input" id="tagline" name="tagline" maxlength="190" value="<?= e($c['tagline']) ?>" placeholder="Repuestos industriales con respaldo técnico"></div>
          <div class="field"><label for="about">Quiénes somos</label>
            <textarea class="textarea" id="about" name="about" rows="5" maxlength="6000"><?= e($c['about']) ?></textarea></div>
          <div class="row-3">
            <div class="field"><label for="logo">Logo (PNG con fondo claro)</label><input class="input" id="logo" name="logo" type="file" accept="image/*">
              <?php if ($c['logo']): ?><p class="hint">Actual: <a href="<?= e(upload($c['logo'])) ?>" target="_blank" rel="noopener">ver</a>. Al cambiarlo se regeneran los iconos PWA.</p><?php endif; ?></div>
            <div class="field"><label for="hero_image">Imagen del hero</label><input class="input" id="hero_image" name="hero_image" type="file" accept="image/*"></div>
            <div class="field"><label for="og_image">Imagen para redes</label><input class="input" id="og_image" name="og_image" type="file" accept="image/*"></div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><span class="secnum">02/</span><h2>Tema visual</h2></div>
        <div class="card__body">
          <div class="swatches" style="margin-bottom:18px">
            <?php foreach ($themes as $key => $t): ?>
              <label class="swatch">
                <input type="radio" name="theme" value="<?= e($key) ?>"<?= $c['theme'] === $key ? ' checked' : '' ?>
                       data-theme-pick data-accent="<?= e($t['accent']) ?>" data-ink="<?= e($t['ink']) ?>" data-paper="<?= e($t['paper']) ?>">
                <span style="background:linear-gradient(135deg,<?= e($t['paper']) ?> 0 42%,<?= e($t['accent']) ?> 42% 72%,<?= e($t['ink']) ?> 72%)"></span>
                <small><?= e($t['label']) ?></small>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="row-3">
            <div class="field"><label for="color_accent">Acento</label><input class="input" id="color_accent" name="color_accent" type="color" value="<?= e($c['color_accent']) ?>"></div>
            <div class="field"><label for="color_ink">Tinta</label><input class="input" id="color_ink" name="color_ink" type="color" value="<?= e($c['color_ink']) ?>"></div>
            <div class="field"><label for="color_paper">Papel</label><input class="input" id="color_paper" name="color_paper" type="color" value="<?= e($c['color_paper']) ?>"></div>
          </div>
          <p class="hint">Elija un tema técnico o afine los tres colores. Se aplican al sitio público, al panel y al PDF.</p>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><span class="secnum">03/</span><h2>Contacto</h2></div>
        <div class="card__body">
          <div class="row-3">
            <div class="field"><label for="email">Correo</label><input class="input" id="email" name="email" type="email" maxlength="150" value="<?= e($c['email']) ?>"></div>
            <div class="field"><label for="phone">Teléfono</label><input class="input" id="phone" name="phone" maxlength="40" value="<?= e($c['phone']) ?>"></div>
            <div class="field"><label for="whatsapp">WhatsApp (solo números)</label><input class="input" id="whatsapp" name="whatsapp" maxlength="30" value="<?= e($c['whatsapp']) ?>" placeholder="50255551234"></div>
          </div>
          <div class="row-3">
            <div class="field"><label for="address">Dirección</label><input class="input" id="address" name="address" maxlength="220" value="<?= e($c['address']) ?>"></div>
            <div class="field"><label for="city">Ciudad</label><input class="input" id="city" name="city" maxlength="90" value="<?= e($c['city']) ?>"></div>
            <div class="field"><label for="maps_url">Enlace de Google Maps</label><input class="input" id="maps_url" name="maps_url" type="url" maxlength="255" value="<?= e($c['maps_url']) ?>"></div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><span class="secnum">04/</span><h2>Cotizaciones y moneda</h2></div>
        <div class="card__body">
          <div class="row-3">
            <div class="field"><label for="currency_symbol">Símbolo de moneda</label><input class="input" id="currency_symbol" name="currency_symbol" maxlength="6" value="<?= e($c['currency_symbol']) ?>"></div>
            <div class="field"><label for="tax_label">Nombre del impuesto</label><input class="input" id="tax_label" name="tax_label" maxlength="20" value="<?= e($c['tax_label']) ?>"></div>
            <div class="field"><label for="tax_rate">Porcentaje</label><input class="input" id="tax_rate" name="tax_rate" type="number" step="0.001" min="0" max="100" value="<?= e((float) $c['tax_rate']) ?>"></div>
          </div>
          <div class="row-3">
            <div class="field"><label for="quote_prefix">Prefijo de numeración</label><input class="input" id="quote_prefix" name="quote_prefix" maxlength="16" value="<?= e($c['quote_prefix']) ?>"></div>
            <div class="field"><label for="quote_pad">Dígitos del correlativo</label><input class="input" id="quote_pad" name="quote_pad" type="number" min="3" max="8" value="<?= e((int) $c['quote_pad']) ?>"></div>
            <div class="field"><label for="validity_days">Validez por defecto (días)</label><input class="input" id="validity_days" name="validity_days" type="number" min="1" max="365" value="<?= e((int) $c['validity_days']) ?>"></div>
          </div>
          <p class="hint" style="margin-top:-6px">Próximo número: <strong><?= e($c['quote_prefix']) ?>-<?= date('Y') ?>-<?= e(str_pad((string) $c['quote_next'], max(3, (int) $c['quote_pad']), '0', STR_PAD_LEFT)) ?></strong></p>
          <div class="row-2" style="margin-top:14px">
            <div class="field"><label for="delivery_terms">Tiempo de entrega por defecto</label><input class="input" id="delivery_terms" name="delivery_terms" maxlength="190" value="<?= e($c['delivery_terms']) ?>"></div>
            <div class="field"><label for="payment_terms">Condiciones de pago por defecto</label><input class="input" id="payment_terms" name="payment_terms" maxlength="190" value="<?= e($c['payment_terms']) ?>"></div>
          </div>
          <div class="field"><label for="pdf_terms">Términos que salen en el PDF</label>
            <textarea class="textarea" id="pdf_terms" name="pdf_terms" rows="4" maxlength="4000"><?= e($c['pdf_terms']) ?></textarea></div>
          <div class="field"><label for="pdf_footer">Pie del PDF</label><input class="input" id="pdf_footer" name="pdf_footer" maxlength="255" value="<?= e($c['pdf_footer']) ?>"></div>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><span class="secnum">05/</span><h2>Correo saliente (SMTP)</h2></div>
        <div class="card__body">
          <div class="row-3">
            <div class="field"><label for="smtp_host">Servidor</label><input class="input" id="smtp_host" name="smtp_host" maxlength="150" value="<?= e($c['smtp_host']) ?>" placeholder="mail.suempresa.gt"></div>
            <div class="field"><label for="smtp_port">Puerto</label><input class="input" id="smtp_port" name="smtp_port" type="number" value="<?= e((int) $c['smtp_port'] ?: 587) ?>"></div>
            <div class="field"><label for="smtp_secure">Cifrado</label>
              <select class="select" id="smtp_secure" name="smtp_secure">
                <?php foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'ninguna' => 'Sin cifrado'] as $k => $lbl): ?>
                  <option value="<?= e($k) ?>"<?= $c['smtp_secure'] === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
              </select></div>
          </div>
          <div class="row-2">
            <div class="field"><label for="smtp_user">Usuario</label><input class="input" id="smtp_user" name="smtp_user" maxlength="150" value="<?= e($c['smtp_user']) ?>" autocomplete="off"></div>
            <div class="field"><label for="smtp_pass">Contraseña</label><input class="input" id="smtp_pass" name="smtp_pass" type="password" autocomplete="new-password" placeholder="<?= $c['smtp_pass'] ? '•••••• (sin cambios)' : '' ?>"></div>
          </div>
          <div class="row-2">
            <div class="field"><label for="smtp_from">Remitente</label><input class="input" id="smtp_from" name="smtp_from" type="email" maxlength="150" value="<?= e($c['smtp_from']) ?>"></div>
            <div class="field"><label for="smtp_from_name">Nombre del remitente</label><input class="input" id="smtp_from_name" name="smtp_from_name" maxlength="150" value="<?= e($c['smtp_from_name']) ?>"></div>
          </div>
          <p class="hint">Si lo deja vacío se usa la función mail() del servidor.</p>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><span class="secnum">06/</span><h2>SEO</h2></div>
        <div class="card__body">
          <div class="field"><label for="seo_title">Título del sitio</label><input class="input" id="seo_title" name="seo_title" maxlength="190" value="<?= e($c['seo_title']) ?>"></div>
          <div class="field"><label for="seo_description">Descripción</label><input class="input" id="seo_description" name="seo_description" maxlength="300" value="<?= e($c['seo_description']) ?>"></div>
        </div>
      </div>
    </div>

    <!-- lateral -->
    <div class="stack">
      <div class="card">
        <div class="card__body">
          <button class="btn btn--accent btn--block" type="submit">Guardar toda la configuración</button>
          <a class="btn btn--ghost btn--block" style="margin-top:8px" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Ver mi sitio público</a>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><h2>Precios y seguimiento</h2></div>
        <div class="card__body">
          <div class="field"><label for="price_visibility">Visibilidad de precios</label>
            <select class="select" id="price_visibility" name="price_visibility">
              <?php foreach (['oculto' => 'Ocultos (solo a cotizar)', 'clientes' => 'Solo para clientes registrados', 'publico' => 'Visibles para todos'] as $k => $lbl): ?>
                <option value="<?= e($k) ?>"<?= $c['price_visibility'] === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="hint">Cada producto puede tener su propia excepción.</p></div>
          <div class="field"><label for="reminder_days_seller">Recordar al vendedor tras X días sin respuesta</label>
            <input class="input" id="reminder_days_seller" name="reminder_days_seller" type="number" min="0" max="60" value="<?= e((int) $c['reminder_days_seller']) ?>"></div>
          <div class="field"><label for="reminder_days_client">Recordar al cliente tras X días (0 = no enviar)</label>
            <input class="input" id="reminder_days_client" name="reminder_days_client" type="number" min="0" max="60" value="<?= e((int) $c['reminder_days_client']) ?>"></div>
          <div class="field"><label for="assign_mode">Asignación de solicitudes web</label>
            <select class="select" id="assign_mode" name="assign_mode">
              <option value="rotativo"<?= $c['assign_mode'] === 'rotativo' ? ' selected' : '' ?>>Rotativa automática</option>
              <option value="manual"<?= $c['assign_mode'] === 'manual' ? ' selected' : '' ?>>Manual (las recibe el administrador)</option>
            </select></div>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><h2>Contenido de la instalación</h2></div>
        <div class="card__body">
          <?php foreach ([['Productos', 'products'], ['Usuarios', 'users'], ['Cotizaciones del mes', 'quotes']] as $row): ?>
            <div class="flex small" style="justify-content:space-between;margin-bottom:10px">
              <span><?= e($row[0]) ?></span><b><?= e(number_format((int) ($stats[$row[1]] ?? 0))) ?></b>
            </div>
          <?php endforeach; ?>
          <a class="btn btn--ghost btn--block" style="margin-top:8px" href="<?= e(url('/panel/respaldos')) ?>">Respaldos de la base</a>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><h2>Dirección pública</h2></div>
        <div class="card__body">
          <div class="copyfield">
            <label class="sr-only" for="pubu">Dirección del sitio</label>
            <input id="pubu" value="<?= e(absUrl('/')) ?>" readonly>
            <button type="button" data-copy="pubu">Copiar</button>
          </div>
          <p class="hint" style="margin-top:10px">El sitio público de su empresa se sirve en la raíz de esta instalación.</p>
        </div>
      </div>
    </div>
  </div>
</form>
