<?php /** @var array $events @var array $hosts @var array $boot */
$modes = ['link' => 'Enlace directo', 'inline' => 'En tu página', 'popup' => 'Ventana emergente', 'float' => 'Botón flotante', 'wp' => 'WordPress', 'qr' => 'Código QR'];
?>
<div class="page p2-page">
  <header class="page-head">
    <div>
      <h1 class="page-title">Insertar y compartir</h1>
      <p class="page-sub">Lleva tu agenda a donde están tus clientes: tu sitio web, redes sociales, tarjetas impresas o WhatsApp.</p>
    </div>
  </header>

  <?php if (!$events) : ?>
    <div class="empty p2-empty"><?= icon('qr') ?><h2 class="empty-title">Primero crea un tipo de evento</h2><p class="empty-text">Cuando tengas al menos uno, aquí obtienes su enlace, su código de inserción y su QR.</p><a class="btn btn-gold" href="<?= e(url('/admin/eventos/nuevo')) ?>"><?= icon('plus') ?> Crear evento</a></div>
  <?php else : ?>
  <script type="application/json" id="embed-boot"><?= json_script($boot) ?></script>
  <div class="p2-embed" data-p2-embed>
    <section class="card p2-embed-controls"><div class="card-body stack">
      <h2 class="p2-h3">Personaliza</h2>
      <div class="field">
        <label for="em-event">¿Qué quieres compartir?</label>
        <select class="select" id="em-event">
          <option value="">Todos mis eventos (página principal)</option>
          <?php foreach ($events as $ev) : ?><option value="<?= e($ev['slug']) ?>"<?= (int) $ev['active'] === 0 ? ' data-off="1"' : '' ?>><?= e($ev['name']) ?><?= (int) $ev['active'] === 0 ? ' (pausado)' : '' ?></option><?php endforeach; ?>
        </select>
      </div>
      <?php if ($hosts) : ?>
        <div class="field">
          <label for="em-host">Anfitrión (opcional)</label>
          <select class="select" id="em-host"><option value="">El que esté libre</option><?php foreach ($hosts as $h) : ?><option value="<?= e($h['slug']) ?>"><?= e($h['name']) ?></option><?php endforeach; ?></select>
          <p class="hint">Con un anfitrión, la reserva se hace directamente con esa persona.</p>
        </div>
      <?php endif; ?>
      <div class="form-row">
        <div class="field"><label for="em-color">Color del botón</label><input class="p2-colorinput" type="color" id="em-color" value="<?= e($boot['color']) ?>"></div>
        <div class="field"><label for="em-label">Texto del botón</label><input class="input" type="text" id="em-label" maxlength="40" value="Reservar cita"></div>
      </div>
      <div class="field" data-only="inline">
        <label for="em-target">Dónde aparece (selector)</label>
        <input class="input mono" type="text" id="em-target" maxlength="60" value="#agenda-premium">
        <p class="hint">Crea un contenedor vacío con ese identificador en tu página.</p>
      </div>
    </div></section>

    <section class="card p2-embed-out"><div class="card-body stack">
      <div class="tabs p2-tabs" role="tablist" aria-label="Forma de compartir" data-p2-modes>
        <?php $first = true; foreach ($modes as $k => $label) : ?>
          <button type="button" class="tab<?= $first ? ' is-active' : '' ?>" role="tab" id="em-tab-<?= e($k) ?>" data-mode="<?= e($k) ?>" aria-controls="em-panel" aria-selected="<?= $first ? 'true' : 'false' ?>" tabindex="<?= $first ? '0' : '-1' ?>"><?= e($label) ?></button>
        <?php $first = false; endforeach; ?>
      </div>
      <div id="em-panel" role="tabpanel" class="stack" aria-live="polite">
        <p class="muted" data-mode-desc></p>
        <div data-code-box>
          <label for="em-code" class="sr-only">Código para copiar</label>
          <textarea class="textarea mono p2-code" id="em-code" readonly rows="6"></textarea>
          <div class="row gap-2 mt-1">
            <button class="btn btn-gold" type="button" data-copy="#em-code" data-copied="Código copiado."><?= icon('copy') ?> Copiar código</button>
            <a class="btn btn-ghost" href="#" target="_blank" rel="noopener" data-open-link><?= icon('external') ?> Abrir enlace</a>
          </div>
          <details class="p2-more mt-2" data-wp-extra hidden>
            <summary>Si tu WordPress aún no tiene el shortcode</summary>
            <p class="hint">Pega esto una vez en el archivo functions.php de tu tema (o en un plugin de fragmentos de código).</p>
            <textarea class="textarea mono p2-code" id="em-wp-php" readonly rows="10"></textarea>
            <button class="btn btn-outline btn-sm mt-1" type="button" data-copy="#em-wp-php" data-copied="Código copiado."><?= icon('copy') ?> Copiar fragmento</button>
          </details>
        </div>
        <div class="p2-qrbox" data-qr-box hidden>
          <canvas id="em-qr" class="p2-qr" width="320" height="320" role="img" aria-label="Código QR de tu enlace"></canvas>
          <div class="stack">
            <p class="muted">Imprímelo en tarjetas, recibos o vitrinas. Al escanearlo se abre tu página de reserva.</p>
            <div class="field"><label for="em-qr-size">Tamaño de la imagen PNG</label><select class="select" id="em-qr-size"><option value="512">Pequeño (512 px)</option><option value="1024" selected>Mediano (1024 px)</option><option value="2048">Grande (2048 px)</option></select></div>
            <div class="row gap-2 row-wrap">
              <button class="btn btn-gold" type="button" data-qr-png><?= icon('download') ?> Descargar PNG</button>
              <button class="btn btn-outline" type="button" data-qr-svg><?= icon('download') ?> Descargar SVG</button>
            </div>
            <p class="hint mono" data-qr-url></p>
          </div>
        </div>
      </div>
    </div></section>

    <section class="card p2-embed-preview"><div class="card-body stack">
      <h2 class="p2-h3">Vista previa</h2>
      <div class="p2-stage" data-stage aria-label="Vista previa de la inserción"></div>
    </div></section>
  </div>
  <?php endif; ?>
</div>
