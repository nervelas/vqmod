<?php
/** @var array $r */
$brand = $r['business']['name'] ?: (string) setting('business_name', 'Agenda Premium');
$bk = $r['booking'];
?>
<!doctype html>
<html lang="es" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title . ' · ' . $brand) ?></title>
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/core.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin-p3.css')) ?>">
</head>
<body class="p3-print-body">
<div class="p3-print-bar no-print">
  <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/citas/' . (int) $bk['id'])) ?>"><?= icon('arrow-left') ?>Volver a la cita</a>
  <button class="btn btn-gold btn-sm" type="button" data-print><?= icon('download') ?>Imprimir o guardar PDF</button>
</div>
<main class="p3-receipt" id="main">
  <header class="p3-rc-head">
    <div>
      <p class="p3-rc-brand serif"><?= e($brand) ?></p>
      <?php if ($r['business']['address']) : ?><p><?= e($r['business']['address']) ?></p><?php endif; ?>
      <p><?= e(trim(($r['business']['phone'] ? $r['business']['phone'] : '') . ($r['business']['phone'] && $r['business']['email'] ? ' · ' : '') . $r['business']['email'])) ?></p>
    </div>
    <div class="p3-rc-num">
      <p class="p3-rc-kicker">Recibo</p>
      <p class="mono p3-rc-id"><?= e($r['receipt_number']) ?></p>
      <p>Emitido el <?= e($r['issued']) ?></p>
    </div>
  </header>
  <section class="p3-rc-block">
    <h2 class="serif">Cliente</h2>
    <p><strong><?= e($r['client']['name']) ?></strong></p>
    <?php if ($r['client']['nit']) : ?><p>NIT: <span class="mono"><?= e($r['client']['nit']) ?></span></p><?php endif; ?>
    <?php if ($r['client']['email']) : ?><p><?= e($r['client']['email']) ?></p><?php endif; ?>
    <?php if ($r['client']['phone']) : ?><p><?= e($r['client']['phone']) ?></p><?php endif; ?>
  </section>
  <section class="p3-rc-block">
    <h2 class="serif">Servicio</h2>
    <p><strong><?= e($bk['event']) ?></strong><?= $bk['host'] ? ' con ' . e($bk['host']) : '' ?></p>
    <p><?= e($bk['when']) ?></p>
  </section>
  <table class="table p3-rc-table">
    <caption class="sr-only">Detalle de importes</caption>
    <tbody>
      <tr><th scope="row">Precio</th><td class="right mono"><?= e(money($r['price'])) ?></td></tr>
      <?php if ($r['discount'] > 0) : ?><tr><th scope="row">Descuento</th><td class="right mono">−<?= e(money($r['discount'])) ?></td></tr><?php endif; ?>
      <?php if ($r['gift_applied'] > 0) : ?><tr><th scope="row">Certificado de regalo aplicado</th><td class="right mono">−<?= e(money($r['gift_applied'])) ?></td></tr><?php endif; ?>
      <tr class="p3-rc-total"><th scope="row">Total</th><td class="right mono"><?= e(money($r['total'])) ?></td></tr>
    </tbody>
  </table>
  <h2 class="serif p3-rc-sub">Pagos recibidos</h2>
  <?php if (!$r['payments']) : ?>
    <p class="muted">Aún no hay pagos verificados en esta cita.</p>
  <?php else : ?>
    <table class="table p3-rc-table">
      <caption class="sr-only">Pagos</caption>
      <thead><tr><th scope="col">N.º</th><th scope="col">Fecha</th><th scope="col">Método</th><th scope="col" class="right">Monto</th></tr></thead>
      <tbody>
      <?php foreach ($r['payments'] as $l) : ?>
        <tr><td class="mono"><?= e($l['number']) ?></td><td><?= e($l['date']) ?></td><td><?= e($l['method_label']) ?><?= $l['refund'] ? ' (reembolso)' : '' ?><?= $l['reference'] ? ' · ' . e($l['reference']) : '' ?></td><td class="right mono"><?= $l['refund'] ? '−' : '' ?><?= e(money($l['amount'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  <dl class="p3-rc-sum">
    <div><dt>Total pagado</dt><dd class="mono"><?= e(money($r['paid'])) ?></dd></div>
    <?php if ($r['refunded'] > 0) : ?><div><dt>Reembolsado</dt><dd class="mono"><?= e(money($r['refunded'])) ?></dd></div><?php endif; ?>
    <div class="p3-rc-balance"><dt>Saldo pendiente</dt><dd class="mono"><?= e(money($r['balance'])) ?></dd></div>
  </dl>
  <footer class="p3-rc-foot">Gracias por tu confianza. Este recibo es un comprobante interno de pago y no sustituye una factura.</footer>
</main>
<script src="<?= e(asset('js/admin-p3.js')) ?>" defer></script>
</body>
</html>
