<div class="pagina-cab">
  <div><h1>Configuración del colegio</h1><p class="pagina-cab__sub">Identidad, apariencia y parámetros académicos</p></div>
  <div class="acciones">
    <a href="<?= e(url('configuracion/cobranza')) ?>" class="btn btn--linea"><?= icono('dinero', 17) ?> Cobranza y correo</a>
    <a href="<?= e(url('configuracion/academico')) ?>" class="btn btn--linea"><?= icono('libro', 17) ?> Estructura académica</a>
  </div>
</div>

<form method="post" enctype="multipart/form-data" action="<?= e(url('configuracion')) ?>">
  <?= csrf_field() ?>
  <div class="split">
    <div class="col">
      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Identidad</h2></div>
        <div class="campo">
          <label for="cf-nombre">Nombre del colegio <span class="oro">*</span></label>
          <input type="text" id="cf-nombre" name="colegio_nombre" required maxlength="120" value="<?= e($cfg['colegio_nombre'] ?? '') ?>">
        </div>
        <div class="campo">
          <label for="cf-lema">Lema institucional</label>
          <input type="text" id="cf-lema" name="colegio_lema" maxlength="180" value="<?= e($cfg['colegio_lema'] ?? '') ?>">
        </div>
        <div class="campo">
          <label for="cf-dir">Dirección</label>
          <input type="text" id="cf-dir" name="colegio_direccion" maxlength="255" value="<?= e($cfg['colegio_direccion'] ?? '') ?>">
        </div>
        <div class="fila fila--3">
          <div class="campo">
            <label for="cf-tel">Teléfonos</label>
            <input type="text" id="cf-tel" name="colegio_telefono" maxlength="60" value="<?= e($cfg['colegio_telefono'] ?? '') ?>">
          </div>
          <div class="campo">
            <label for="cf-wa">WhatsApp</label>
            <input type="tel" id="cf-wa" name="colegio_whatsapp" maxlength="40" value="<?= e($cfg['colegio_whatsapp'] ?? '') ?>">
          </div>
          <div class="campo">
            <label for="cf-nit">NIT</label>
            <input type="text" id="cf-nit" name="colegio_nit" maxlength="30" value="<?= e($cfg['colegio_nit'] ?? 'C/F') ?>">
          </div>
        </div>
        <div class="fila">
          <div class="campo">
            <label for="cf-email">Correo institucional</label>
            <input type="email" id="cf-email" name="colegio_email" maxlength="160" value="<?= e($cfg['colegio_email'] ?? '') ?>">
          </div>
          <div class="campo">
            <label for="cf-dir-nombre">Nombre del director(a)</label>
            <input type="text" id="cf-dir-nombre" name="director_nombre" maxlength="120" value="<?= e($cfg['director_nombre'] ?? '') ?>">
          </div>
        </div>
      </div>

      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Escala de calificación</h2></div>
        <div class="fila">
          <div class="campo">
            <label for="cf-min">Nota mínima de promoción <span class="oro">*</span></label>
            <input type="number" id="cf-min" name="nota_minima" required min="1" max="100" step="0.01" value="<?= e($cfg['nota_minima'] ?? '60') ?>">
          </div>
          <div class="campo">
            <label for="cf-max">Nota máxima <span class="oro">*</span></label>
            <input type="number" id="cf-max" name="nota_maxima" required min="10" max="100" step="0.01" value="<?= e($cfg['nota_maxima'] ?? '100') ?>">
          </div>
        </div>
        <div class="fila">
          <div class="campo">
            <label for="cf-zona">Ponderación de zona <span class="oro">*</span></label>
            <input type="number" id="cf-zona" name="pond_zona" required min="0" max="100" step="0.01" value="<?= e($cfg['pond_zona'] ?? '60') ?>">
          </div>
          <div class="campo">
            <label for="cf-examen">Ponderación de examen <span class="oro">*</span></label>
            <input type="number" id="cf-examen" name="pond_examen" required min="0" max="100" step="0.01" value="<?= e($cfg['pond_examen'] ?? '40') ?>">
          </div>
        </div>
        <label class="check"><input type="checkbox" name="ranking_boleta" value="1"
          <?= ($cfg['ranking_boleta'] ?? '0') === '1' ? 'checked' : '' ?>> Mostrar la posición del alumno en la boleta</label>
      </div>

      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Regional</h2></div>
        <div class="fila">
          <div class="campo">
            <label for="cf-moneda">Símbolo de moneda <span class="oro">*</span></label>
            <input type="text" id="cf-moneda" name="moneda" required maxlength="5" value="<?= e($cfg['moneda'] ?? 'Q') ?>">
          </div>
          <div class="campo">
            <label for="cf-tz">Zona horaria <span class="oro">*</span></label>
            <select id="cf-tz" name="zona_horaria" required>
              <?php foreach (DateTimeZone::listIdentifiers(DateTimeZone::AMERICA) as $tz): ?>
                <option value="<?= e($tz) ?>" <?= ($cfg['zona_horaria'] ?? 'America/Guatemala') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Tema visual</h2></div>
        <div class="rejilla" style="grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px">
          <?php foreach ($temas as $clave => $t): ?>
            <label class="tarjeta" style="padding:10px;cursor:pointer;border-color:<?= ($cfg['tema'] ?? 'default') === $clave ? 'var(--acento)' : 'var(--borde)' ?>">
              <input type="radio" name="tema" value="<?= e($clave) ?>" <?= ($cfg['tema'] ?? 'default') === $clave ? 'checked' : '' ?>
                     style="width:auto;margin-bottom:6px">
              <span style="display:flex;gap:4px;margin-bottom:6px">
                <i style="display:block;width:26px;height:26px;border-radius:7px;background:<?= e($t['primario']) ?>"></i>
                <i style="display:block;width:26px;height:26px;border-radius:7px;background:<?= e($t['acento']) ?>"></i>
              </span>
              <span class="xs"><?= e($t['nombre']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="campo mt-4">
          <label for="cf-color">Color de acento personalizado</label>
          <input type="text" id="cf-color" name="color_personalizado" maxlength="7" placeholder="#C9A961"
                 pattern="^#[0-9a-fA-F]{6}$" value="<?= e($cfg['color_personalizado'] ?? '') ?>">
          <p class="ayuda">Deje vacío para usar el color del tema elegido.</p>
        </div>
      </div>

      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Logo y firma</h2></div>
        <div class="campo">
          <label for="cf-logo">Logo del colegio</label>
          <?php if (!empty($cfg['colegio_logo'])): ?>
            <img src="<?= e(archivo_url($cfg['colegio_logo'])) ?>" alt="" style="max-height:70px;margin-bottom:8px">
          <?php endif; ?>
          <input type="file" id="cf-logo" name="colegio_logo" accept="image/png,image/jpeg,image/webp">
          <p class="ayuda">Al cargar un logo nuevo se regeneran los iconos de la aplicación móvil.</p>
        </div>
        <div class="campo">
          <label for="cf-favicon">Favicon</label>
          <input type="file" id="cf-favicon" name="colegio_favicon" accept="image/png,image/jpeg,image/webp">
        </div>
        <div class="campo">
          <label for="cf-firma">Firma digital del director</label>
          <?php if (!empty($cfg['director_firma'])): ?>
            <img src="<?= e(archivo_url($cfg['director_firma'])) ?>" alt="" style="max-height:60px;margin-bottom:8px">
          <?php endif; ?>
          <input type="file" id="cf-firma" name="director_firma" accept="image/png,image/jpeg,image/webp">
          <p class="ayuda">Se imprime en recibos y boletas. Preferible PNG con fondo transparente.</p>
        </div>
      </div>

      <button type="submit" class="btn btn--bloque"><?= icono('check', 17) ?> Guardar configuración</button>
    </div>
  </div>
</form>

<form method="post" action="<?= e(url('configuracion/iconos')) ?>" class="mt-4">
  <?= csrf_field() ?>
  <button type="submit" class="btn btn--linea"><?= icono('subir', 17) ?> Regenerar iconos de la aplicación móvil</button>
</form>
