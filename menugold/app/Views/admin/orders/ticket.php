<?php
/** Ticket para impresora térmica: HTML preparado para imprimir. */
use MenuGold\Models\Order;
$o = $order;
$cur = $restaurant['currency'];
$w = (int)$width;
?><!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ticket <?= e($o['code']) ?></title>
<meta name="robots" content="noindex">
<style>
  :root { color-scheme: light; }
  * { box-sizing: border-box; }
  body {
    margin: 0; background: #eceae5; color: #000;
    font-family: "Courier New", ui-monospace, monospace; font-size: 12px; line-height: 1.45;
  }
  .paper { width: <?= $w ?>mm; margin: 12px auto; background: #fff; padding: 6mm 4mm; }
  h1 { font-size: 15px; text-align: center; margin: 0 0 3px; letter-spacing: .04em; }
  .sub, .foot { text-align: center; font-size: 10px; }
  hr { border: 0; border-top: 1px dashed #999; margin: 7px 0; }
  .row { display: flex; justify-content: space-between; gap: 6px; }
  .row.b { font-weight: 700; }
  .item { margin-bottom: 4px; }
  .item .mod { padding-left: 8px; font-size: 10.5px; color: #333; }
  .total { font-size: 15px; font-weight: 700; }
  .qr { text-align: center; margin-top: 8px; }
  .qr img { width: 34mm; }
  .actions { text-align: center; margin: 14px 0; }
  .actions button, .actions a {
    font: inherit; background: #111; color: #fff; border: 0; padding: 9px 16px;
    border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; margin: 0 3px;
  }
  @media print { body { background: #fff; } .actions { display: none; } .paper { margin: 0; width: auto; } }
</style>
</head>
<body>
<div class="actions">
  <button type="button" onclick="window.print()">Imprimir</button>
  <a href="<?= e(mg_url('/panel/pedidos/' . (int)$o['id'] . '/ticket?ancho=' . ($w === 80 ? 58 : 80) . ($isPre ? '&precuenta=1' : ''))) ?>"><?= $w === 80 ? '58 mm' : '80 mm' ?></a>
  <a href="<?= e(mg_url('/panel/pedidos/' . (int)$o['id'] . '/ticket?formato=pdf&ancho=' . $w . ($isPre ? '&precuenta=1' : ''))) ?>">PDF</a>
  <a href="<?= e(mg_url('/panel/pedidos/' . (int)$o['id'])) ?>">Volver</a>
</div>

<div class="paper">
  <h1><?= e(mb_strtoupper($restaurant['name'])) ?></h1>
  <?php if ($restaurant['address'] !== ''): ?><p class="sub"><?= e($restaurant['address']) ?></p><?php endif; ?>
  <?php if ($restaurant['phone'] !== ''): ?><p class="sub">Tel. <?= e($restaurant['phone']) ?></p><?php endif; ?>
  <hr>
  <p class="sub b" style="font-size:13px"><?= $isPre ? 'PRECUENTA' : 'PEDIDO' ?> <?= e($o['code']) ?></p>
  <p class="sub"><?= e(mg_date($o['placed_at'])) ?><?= $o['table'] ? ' · ' . e($o['table']['name']) : '' ?></p>
  <p class="sub"><?= e(Order::modeLabel($o['mode'])) ?></p>
  <hr>

  <?php foreach ($o['items'] as $it): ?>
    <div class="item">
      <div class="row"><span><?= (int)$it['qty'] ?> x <?= e($it['name_snapshot']) ?></span><span><?= e(mg_money($it['line_total'], $cur)) ?></span></div>
      <?php foreach ((array)$it['modifiers'] as $m): ?><div class="mod">- <?= e($m['name']) ?></div><?php endforeach; ?>
      <?php if ($it['notes'] !== ''): ?><div class="mod">Nota: <?= e($it['notes']) ?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>

  <hr>
  <div class="row"><span>Subtotal</span><span><?= e(mg_money($o['subtotal'], $cur)) ?></span></div>
  <?php if ((float)$o['discount'] > 0): ?><div class="row"><span>Descuento</span><span>-<?= e(mg_money($o['discount'], $cur)) ?></span></div><?php endif; ?>
  <?php if ((float)$o['delivery_fee'] > 0): ?><div class="row"><span>Envio</span><span><?= e(mg_money($o['delivery_fee'], $cur)) ?></span></div><?php endif; ?>
  <?php if ((float)$o['tax'] > 0): ?><div class="row"><span>Impuesto</span><span><?= e(mg_money($o['tax'], $cur)) ?></span></div><?php endif; ?>
  <?php if ((float)$o['tip'] > 0): ?><div class="row"><span>Propina</span><span><?= e(mg_money($o['tip'], $cur)) ?></span></div><?php endif; ?>
  <hr>
  <div class="row b total"><span>TOTAL</span><span><?= e(mg_money($o['total'], $cur)) ?></span></div>
  <hr>

  <?php if ($isPre): ?><p class="foot">Documento no fiscal</p><?php endif; ?>
  <p class="foot">Gracias por su visita</p>

  <?php if ($restaurant['review_url'] !== ''): ?>
    <div class="qr">
      <img src="<?= e(\MenuGold\Core\Qr::dataUri($restaurant['review_url'], 4)) ?>" alt="Código QR para dejar una reseña">
      <p class="foot">Escanea y déjanos tu reseña</p>
    </div>
  <?php endif; ?>
</div>
</body></html>
