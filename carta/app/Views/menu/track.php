<?php
/** Seguimiento en vivo del pedido. */
use MenuGold\Core\Lang;
use MenuGold\Models\Order;
$view->extend('layouts/menu');
$r = $cfg;
$o = $order;
$view->set('title', 'Pedido ' . $o['code'] . ' · ' . $r['name']);
$flow = array('new' => 0, 'cooking' => 1, 'ready' => 2, 'served' => 3, 'closed' => 3);
$current = isset($flow[$o['status']]) ? $flow[$o['status']] : 0;
$cancelled = $o['status'] === 'cancelled';
$steps = array(
    array(Lang::get('track.received'), $o['placed_at']),
    array(Lang::get('track.preparing'), $current >= 1 ? $o['updated_at'] : null),
    array(Lang::get('track.ready'), $o['ready_at']),
    array(Lang::get('track.delivered'), $o['closed_at']),
);
$showReview = true;
?>
<div class="track-wrap" data-track="<?= e($o['public_token']) ?>" data-status="<?= e($o['status']) ?>">

  <p class="eyebrow is-centered" style="justify-content:center"><?= e($r['name']) ?></p>
  <p class="track-code"><?= e($o['code']) ?></p>
  <p class="muted" style="text-align:center;margin-top:.6rem">
    <?= e(Order::modeLabel($o['mode'])) ?><?= $o['table'] ? ' · ' . e($o['table']['name']) : '' ?> · <?= e(mg_date($o['placed_at'], 'H:i')) ?>
  </p>

  <?php if ($cancelled): ?>
    <div class="alert alert-error mt-3">
      Este pedido fue anulado<?= $o['cancel_reason'] !== '' ? ': ' . e($o['cancel_reason']) : '.' ?>
      Acércate al mostrador o llama al restaurante si tienes dudas.
    </div>
  <?php else: ?>
    <div class="track-steps">
      <?php foreach ($steps as $i => $s): ?>
        <div class="track-step <?= $i < $current ? 'is-done' : ($i === $current ? 'is-current' : '') ?>">
          <span class="track-dot" aria-hidden="true">
            <?php if ($i < $current): ?>
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M2.5 6.8 5.3 9.6l5.2-6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <?php else: ?><?= $i + 1 ?><?php endif; ?>
          </span>
          <div>
            <b><?= e($s[0]) ?></b>
            <span><?= $s[1] ? e(mg_date($s[1], 'H:i')) : ($i === $current ? 'En curso' : 'Pendiente') ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="card" style="background:linear-gradient(170deg,var(--carbon-2),var(--carbon));border:1px solid var(--line-soft);border-radius:var(--r-md);padding:1.3rem">
    <?php foreach ($o['items'] as $it): ?>
      <div class="cart-line" style="grid-template-columns:1fr auto">
        <div>
          <b><?= (int)$it['qty'] ?>× <?= e($it['name']) ?></b>
          <?php foreach ((array)$it['modifiers'] as $m): ?>
            <small>· <?= e($m['name']) ?></small>
          <?php endforeach; ?>
          <?php if ($it['notes'] !== ''): ?><small>“<?= e($it['notes']) ?>”</small><?php endif; ?>
        </div>
        <div class="cart-line-price"><?= e(mg_money($it['line_total'], $r['currency'])) ?></div>
      </div>
    <?php endforeach; ?>

    <div class="totals">
      <div><span><?= e(Lang::get('menu.subtotal')) ?></span><span><?= e(mg_money($o['subtotal'], $r['currency'])) ?></span></div>
      <?php if ((float)$o['discount'] > 0): ?><div><span><?= e(Lang::get('menu.discount')) ?></span><span>−<?= e(mg_money($o['discount'], $r['currency'])) ?></span></div><?php endif; ?>
      <?php if ((float)$o['delivery_fee'] > 0): ?><div><span><?= e(Lang::get('menu.delivery')) ?></span><span><?= e(mg_money($o['delivery_fee'], $r['currency'])) ?></span></div><?php endif; ?>
      <?php if ((float)$o['tax'] > 0): ?><div><span>Impuesto</span><span><?= e(mg_money($o['tax'], $r['currency'])) ?></span></div><?php endif; ?>
      <?php if ((float)$o['tip'] > 0): ?><div><span><?= e(Lang::get('menu.tip')) ?></span><span><?= e(mg_money($o['tip'], $r['currency'])) ?></span></div><?php endif; ?>
      <div class="is-total"><span><?= e(Lang::get('menu.total')) ?></span><span><?= e(mg_money($o['total'], $r['currency'])) ?></span></div>
    </div>
  </div>

  <?php if ($r['bank_info'] !== '' && $o['payment_status'] !== 'paid' && !$cancelled): ?>
    <div class="card mt-2" style="border:1px solid var(--line);border-radius:var(--r-md);padding:1.3rem">
      <p class="label">Datos para transferencia</p>
      <div class="copy-box">
        <pre><?= e($r['bank_info']) ?></pre>
        <button class="btn btn-ghost btn-sm" type="button" data-copy="<?= e($r['bank_info']) ?>">Copiar</button>
      </div>
      <?php if ($r['payment_link'] !== ''): ?>
        <a class="btn btn-block mt-2" href="<?= e($r['payment_link']) ?>" target="_blank" rel="noopener">Pagar en línea</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($o['table'] && !$cancelled): ?>
    <div class="table-actions" style="padding-top:1.6rem">
      <button class="btn btn-ghost btn-sm" type="button" data-call="waiter"><?= e(Lang::get('menu.call_waiter')) ?></button>
      <button class="btn btn-ghost btn-sm" type="button" data-call="bill"><?= e(Lang::get('menu.request_bill')) ?></button>
    </div>
  <?php endif; ?>

  <?php if ($showReview && $r['review_url'] !== '' && in_array($o['status'], array('served', 'closed'), true)): ?>
    <div class="review-nudge">
      <div class="stars" aria-hidden="true">
        <?php for ($i = 0; $i < 5; $i++): ?>
          <svg width="18" height="18" viewBox="0 0 18 18" fill="currentColor"><path d="m9 1.6 2.2 4.6 5 .7-3.6 3.5.9 5L9 13l-4.5 2.4.9-5L1.8 6.9l5-.7L9 1.6Z"/></svg>
        <?php endfor; ?>
      </div>
      <p class="muted" style="margin-bottom:1.2rem"><?= e(Lang::get('menu.review')) ?></p>
      <a class="btn btn-sm" href="<?= e($r['review_url']) ?>" target="_blank" rel="noopener">Dejar reseña en Google</a>
    </div>
  <?php endif; ?>

  <p style="text-align:center;margin-top:2.5rem">
    <a class="link-line muted" href="<?= e(mg_url('/')) ?>">Volver al menú</a>
  </p>
</div>
