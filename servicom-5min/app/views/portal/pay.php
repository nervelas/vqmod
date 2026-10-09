<?php
/**
 * /pago/<token>. Variables: $token, $cfg (banco, precios), $plan ('info'|'tienda'), $tarjeta (bool), $total (opcional).
 */
require_once __DIR__ . '/_icons.php';
$cfg = isset($cfg) && is_array($cfg) ? $cfg : [];
$boot = ['token' => (string)($token ?? '')];
?>
<?php include __DIR__ . '/_header.php'; ?>
<main id="main" class="simple">
  <div class="wrap">
    <section class="card panel panel--l" aria-labelledby="pg-t">
      <span class="ico-big"><?= ico('bank') ?></span>
      <h1 id="pg-t">Aprueba y paga tu web</h1>
      <p>Último paso: realiza tu pago y sube el comprobante. Publicamos tu sitio en cuanto lo confirmemos.</p>
      <div class="pg-box"><?php include __DIR__ . '/_pay.php'; ?></div>
    </section>
  </div>
</main>
<?php include __DIR__ . '/_footer.php'; ?>
<script type="application/json" id="s5-boot"><?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="/assets/js/s5.js?v=1" defer<?= !empty($nonce) ? ' nonce="' . e($nonce) . '"' : '' ?>></script>
<script src="/assets/js/pay.js?v=1" defer<?= !empty($nonce) ? ' nonce="' . e($nonce) . '"' : '' ?>></script>
