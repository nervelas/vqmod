<?php
/**
 * /vista-previa/<token>. Variables: $token (64 hex), $cfg, $estado (opcional: 'construyendo'|'listo'|'error'), $url (opcional, vista previa lista).
 */
require_once __DIR__ . '/_icons.php';
$cfg = isset($cfg) && is_array($cfg) ? $cfg : [];
$boot = ['token' => (string)($token ?? ''), 'estado' => (string)($estado ?? 'construyendo'), 'url' => (string)($url ?? '')];
$waNum = (string)($cfg['wa_servicom'] ?? '');
?>
<?php include __DIR__ . '/_header.php'; ?>
<main id="main" class="simple">
  <div class="wrap">
    <section class="card panel" id="st" aria-live="polite" aria-labelledby="st-t">
      <span class="ico-big" id="st-ico"><?= ico('refresh', 'spin') ?></span>
      <h1 id="st-t">Preparando tu vista previa</h1>
      <p id="st-m">Estamos armando tu web. Esto toma unos minutos; puedes dejar esta página abierta.</p>
      <div class="bar" role="progressbar" aria-label="Progreso de construcción" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><i id="st-bar"></i></div>
      <p class="help" id="st-pct">0 %</p>
      <ul class="steps" id="st-steps"></ul>
      <div class="acts" id="st-acts" hidden>
        <a class="btn btn--gold" id="st-view" href="#" target="_blank" rel="noopener">Ver mi vista previa<span class="sr"> (se abre en una pestaña nueva)</span></a>
        <a class="btn btn--ghost" id="st-pay" href="#">Aprobar y pagar</a>
        <a class="btn btn--ghost" id="st-edit" href="#">Editar datos</a>
      </div>
      <div class="acts" id="st-retry" hidden>
        <button type="button" class="btn btn--gold" id="st-again">Volver a intentar</button>
<?php if ($waNum !== ''): ?>
        <a class="btn btn--wa" href="<?= e(wa_link($waNum, 'Hola, mi vista previa tarda en prepararse.')) ?>" target="_blank" rel="noopener"><?= ico('wa') ?>Escríbenos<span class="sr"> (se abre en una pestaña nueva)</span></a>
<?php endif; ?>
      </div>
    </section>
  </div>
</main>
<?php include __DIR__ . '/_footer.php'; ?>
<script type="application/json" id="s5-boot"><?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= e(asset('js/s5.js')) ?>" defer<?= !empty($nonce) ? ' nonce="' . e($nonce) . '"' : '' ?>></script>
<script src="<?= e(asset('js/status.js')) ?>" defer<?= !empty($nonce) ? ' nonce="' . e($nonce) . '"' : '' ?>></script>
