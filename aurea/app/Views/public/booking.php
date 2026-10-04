<?php use Aurea\Core\App; ?>
<div class="wrap booking" id="booking-root">
  <noscript><div class="alert alert-err"><?= e(__('Necesitas activar JavaScript para reservar en línea. También puedes escribirnos por WhatsApp.')) ?></div></noscript>
  <ol class="stepper" id="stepper" aria-label="<?= e(__('Progreso de la reserva')) ?>">
    <li data-step="1"><button type="button"><span class="n">01</span><span class="lbl"><?= e(__('Servicio')) ?></span></button></li>
    <li data-step="2"><button type="button"><span class="n">02</span><span class="lbl"><?= e(term('professional')) ?></span></button></li>
    <li data-step="3"><button type="button"><span class="n">03</span><span class="lbl"><?= e(__('Fecha y hora')) ?></span></button></li>
    <li data-step="4"><button type="button"><span class="n">04</span><span class="lbl"><?= e(__('Tus datos')) ?></span></button></li>
  </ol>
  <div id="panel" aria-live="polite"><div class="skeleton"></div></div>
</div>
<script type="application/json" id="boot"><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
