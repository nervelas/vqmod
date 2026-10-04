<?php
/** Sello de confirmación (trazos animados con stroke-dashoffset). Variables: $mode 'ok'|'wait' */
$mode = $mode ?? 'ok';
$pts = [];
for ($i = 0; $i <= 360; $i++) {
    $t = deg2rad($i);
    $r = 86 + 3.2 * sin(24 * $t) + 1.6 * sin(48 * $t);
    $pts[] = round(100 + $r * cos($t), 2) . ' ' . round(100 + $r * sin($t), 2);
}
$rosette = 'M' . implode(' L', $pts) . 'Z';
?>
<div class="seal<?= $mode === 'wait' ? ' seal-wait' : '' ?>" data-seal aria-hidden="true">
  <svg viewBox="0 0 200 200" width="168" height="168" focusable="false">
    <defs>
      <linearGradient id="sealGold" x1="0" y1="0" x2="1" y2="1"><stop offset="0" class="sg-3"/><stop offset=".5" class="sg-2"/><stop offset="1" class="sg-1"/></linearGradient>
      <path id="sealArc" d="M100 100m-69 0a69 69 0 1 1 138 0a69 69 0 1 1-138 0"/>
    </defs>
    <path class="seal-rosette" d="<?= e($rosette) ?>" pathLength="1"/>
    <circle class="seal-ring" cx="100" cy="100" r="76" pathLength="1"/>
    <circle class="seal-ring2" cx="100" cy="100" r="58" pathLength="1"/>
    <text class="seal-text"><textPath href="#sealArc" startOffset="0"><?= $mode === 'wait' ? 'EN REVISIÓN · SOLICITUD RECIBIDA · ' : 'CITA CONFIRMADA · RESERVA EN LÍNEA · ' ?></textPath></text>
    <?php if ($mode === 'wait') : ?>
      <path class="seal-mark" d="M100 76V100L117 112" pathLength="1"/>
    <?php else : ?>
      <path class="seal-mark" d="M78 101l16 16 30-34" pathLength="1"/>
    <?php endif; ?>
  </svg>
  <span class="seal-ripple"></span>
</div>
