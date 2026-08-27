<div class="pagina-cab">
  <div><h1>Cobranza y correo</h1><p class="pagina-cab__sub">Recibos, descuentos, recordatorios y servidor SMTP</p></div>
  <div class="acciones"><a href="<?= e(url('configuracion')) ?>" class="btn btn--linea"><?= icono('atras', 17) ?> Volver</a></div>
</div>

<form method="post" action="<?= e(url('configuracion/cobranza')) ?>">
  <?= csrf_field() ?>
  <div class="split">
    <div class="col">
      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Recibos y descuentos</h2></div>
        <div class="fila fila--3">
          <div class="campo">
            <label for="cb-prefijo">Prefijo del recibo</label>
            <input type="text" id="cb-prefijo" name="recibo_prefijo" maxlength="5" value="<?= e($cfg['recibo_prefijo'] ?? 'R') ?>">
          </div>
          <div class="campo">
            <label for="cb-hermanos">Descuento por hermanos (%)</label>
            <input type="number" id="cb-hermanos" name="descuento_hermanos" min="0" max="100" step="0.01" value="<?= e($cfg['descuento_hermanos'] ?? '0') ?>">
          </div>
          <div class="campo">
            <label for="cb-meta">Meta mensual de ingresos</label>
            <input type="number" id="cb-meta" name="meta_ingresos" min="0" step="0.01" value="<?= e($cfg['meta_ingresos'] ?? '0') ?>">
          </div>
        </div>
        <div class="campo">
          <label for="cb-texto">Texto al pie del recibo</label>
          <textarea id="cb-texto" name="recibo_texto" maxlength="500"><?= e($cfg['recibo_texto'] ?? '') ?></textarea>
        </div>
        <div class="campo">
          <label for="cb-link">Enlace de pago en línea</label>
          <input type="url" id="cb-link" name="pago_link" maxlength="255" placeholder="https://pagos.ejemplo.com/colegio"
                 value="<?= e($cfg['pago_link'] ?? '') ?>">
          <p class="ayuda">Si lo deja vacío, el botón de pago en línea se oculta del portal de padres.</p>
        </div>
      </div>

      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Recordatorios automáticos</h2></div>
        <div class="fila">
          <div class="campo">
            <label for="cb-previo">Aviso previo (días antes)</label>
            <input type="number" id="cb-previo" name="recordatorio_previo_dias" min="0" max="30" value="<?= e($cfg['recordatorio_previo_dias'] ?? '3') ?>">
          </div>
          <div class="campo">
            <label for="cb-mora">Repetir en mora cada (días)</label>
            <input type="number" id="cb-mora" name="recordatorio_mora_cada" min="1" max="60" value="<?= e($cfg['recordatorio_mora_cada'] ?? '7') ?>">
          </div>
        </div>
        <div class="campo">
          <label for="cb-wa">Plantilla de WhatsApp</label>
          <textarea id="cb-wa" name="plantilla_wa" maxlength="1000"><?= e($cfg['plantilla_wa'] ?? '') ?></textarea>
          <p class="ayuda">Marcadores: {encargado} {alumno} {monto} {vence} {concepto} {colegio} {portal}</p>
        </div>
        <div class="campo">
          <label for="cb-correo">Plantilla de correo</label>
          <textarea id="cb-correo" name="plantilla_correo" maxlength="4000" style="min-height:140px"><?= e($cfg['plantilla_correo'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Servidor de correo (SMTP)</h2></div>
        <label class="check"><input type="checkbox" name="smtp_activo" value="1"
          <?= ($cfg['smtp_activo'] ?? '0') === '1' ? 'checked' : '' ?>> Usar SMTP (recomendado)</label>
        <div class="campo">
          <label for="sm-host">Servidor</label>
          <input type="text" id="sm-host" name="smtp_host" maxlength="160" placeholder="mail.sudominio.com" value="<?= e($cfg['smtp_host'] ?? '') ?>">
        </div>
        <div class="fila">
          <div class="campo">
            <label for="sm-puerto">Puerto</label>
            <input type="number" id="sm-puerto" name="smtp_puerto" min="1" max="65535" value="<?= e($cfg['smtp_puerto'] ?? '587') ?>">
          </div>
          <div class="campo">
            <label for="sm-seg">Seguridad</label>
            <select id="sm-seg" name="smtp_seguridad">
              <?php foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'Sin cifrado'] as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= ($cfg['smtp_seguridad'] ?? 'tls') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="campo">
          <label for="sm-user">Usuario</label>
          <input type="text" id="sm-user" name="smtp_usuario" maxlength="160" autocomplete="off" value="<?= e($cfg['smtp_usuario'] ?? '') ?>">
        </div>
        <div class="campo">
          <label for="sm-pass">Contraseña</label>
          <input type="password" id="sm-pass" name="smtp_password" autocomplete="new-password" placeholder="<?= ($cfg['smtp_password'] ?? '') !== '' ? '•••••••• (sin cambios)' : '' ?>">
        </div>
        <div class="fila">
          <div class="campo">
            <label for="sm-rem">Correo remitente</label>
            <input type="email" id="sm-rem" name="smtp_remitente" maxlength="160" value="<?= e($cfg['smtp_remitente'] ?? '') ?>">
          </div>
          <div class="campo">
            <label for="sm-nom">Nombre remitente</label>
            <input type="text" id="sm-nom" name="smtp_nombre" maxlength="120" value="<?= e($cfg['smtp_nombre'] ?? '') ?>">
          </div>
        </div>
      </div>

      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Archivos y respaldo</h2></div>
        <div class="campo">
          <label for="cb-max">Tamaño máximo de archivos (MB)</label>
          <input type="number" id="cb-max" name="subida_max_mb" min="1" max="64" value="<?= e($cfg['subida_max_mb'] ?? '8') ?>">
        </div>
        <label class="check"><input type="checkbox" name="backup_semanal" value="1"
          <?= ($cfg['backup_semanal'] ?? '1') === '1' ? 'checked' : '' ?>> Respaldo automático semanal</label>
      </div>

      <button type="submit" class="btn btn--bloque"><?= icono('check', 17) ?> Guardar configuración</button>
    </div>
  </div>
</form>

<div class="tarjeta mt-4">
  <div class="tarjeta__cab"><h2>Probar el envío de correo</h2></div>
  <form method="post" action="<?= e(url('configuracion/correo-prueba')) ?>" class="flex flex--envuelve" style="gap:10px;align-items:flex-end">
    <?= csrf_field() ?>
    <div class="campo" style="flex:1 1 260px;margin:0">
      <label for="pr-destino">Enviar prueba a</label>
      <input type="email" id="pr-destino" name="destino" required maxlength="160" placeholder="usted@correo.com">
    </div>
    <button type="submit" class="btn btn--linea"><?= icono('correo', 17) ?> Enviar prueba</button>
  </form>
</div>
