<?php
/** Detalle de un pedido. */
use MenuGold\Core\Csrf;
use MenuGold\Models\Order;
$view->extend('layouts/panel');
$o = $order;
$view->set('title', 'Pedido ' . $o['code']);
$cur = $restaurant['currency'];
$open = !in_array($o['status'], array('paid', 'cancelled'), true);
?>
<?php $view->start('actions') ?>
  <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/panel/pedidos/' . (int)$o['id'] . '/ticket')) ?>" target="_blank" rel="noopener">Ticket</a>
  <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/panel/pedidos/' . (int)$o['id'] . '/ticket?formato=pdf')) ?>" target="_blank" rel="noopener">PDF</a>
<?php $view->stop() ?>

<?php $view->start('content') ?>
<div class="grid grid-side">
  <div class="stack">
    <div class="card">
      <div class="card-head">
        <div>
          <h2 class="display" style="font-size:var(--step-2)"><?= e($o['code']) ?></h2>
          <p><?= e(Order::modeLabel($o['mode'])) ?><?= $o['table'] ? ' · ' . e($o['table']['name']) : '' ?> · <?= e(mg_date($o['placed_at'])) ?></p>
        </div>
        <span class="chip <?= $o['status'] === 'paid' ? 'chip-green' : ($o['status'] === 'cancelled' ? 'chip-ember' : '') ?>"><?= e($o['status_label']) ?></span>
      </div>

      <?php foreach ($o['items'] as $it): ?>
        <div class="row-between" style="padding:.8rem 0;border-bottom:1px solid var(--line-soft);align-items:flex-start">
          <div>
            <b class="cell-title"><?= (int)$it['qty'] ?>× <?= e($it['name_snapshot']) ?></b>
            <?php foreach ((array)$it['modifiers'] as $m): ?>
              <span class="muted" style="display:block;font-size:12px">· <?= e($m['name']) ?><?= (float)$m['price'] > 0 ? ' (+' . e(mg_money($m['price'], $cur)) . ')' : '' ?></span>
            <?php endforeach; ?>
            <?php if ($it['notes'] !== ''): ?><span class="gold" style="display:block;font-size:12px">“<?= e($it['notes']) ?>”</span><?php endif; ?>
          </div>
          <span class="tabular nowrap"><?= e(mg_money($it['line_total'], $cur)) ?></span>
        </div>
      <?php endforeach; ?>

      <div class="totals">
        <div><span>Subtotal</span><span class="tabular"><?= e(mg_money($o['subtotal'], $cur)) ?></span></div>
        <?php if ((float)$o['discount'] > 0): ?><div><span>Descuento<?= $o['coupon_code'] !== '' ? ' (' . e($o['coupon_code']) . ')' : '' ?></span><span class="tabular">−<?= e(mg_money($o['discount'], $cur)) ?></span></div><?php endif; ?>
        <?php if ((float)$o['delivery_fee'] > 0): ?><div><span>Envío</span><span class="tabular"><?= e(mg_money($o['delivery_fee'], $cur)) ?></span></div><?php endif; ?>
        <?php if ((float)$o['tax'] > 0): ?><div><span>Impuesto</span><span class="tabular"><?= e(mg_money($o['tax'], $cur)) ?></span></div><?php endif; ?>
        <?php if ((float)$o['tip'] > 0): ?><div><span>Propina</span><span class="tabular"><?= e(mg_money($o['tip'], $cur)) ?></span></div><?php endif; ?>
        <div class="is-total"><span>Total</span><span><?= e(mg_money($o['total'], $cur)) ?></span></div>
      </div>

      <?php if ($o['notes'] !== ''): ?>
        <p class="alert mt-2"><span><b>Notas:</b> <?= e($o['notes']) ?></span></p>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-head"><h3>Historial</h3></div>
      <ul class="stack" style="gap:.6rem;font-size:var(--step--1)">
        <?php foreach ($events as $ev): ?>
          <li class="row-between">
            <span><?= e(isset(Order::$statusLabels[$ev['to_status']]) ? Order::$statusLabels[$ev['to_status']] : $ev['to_status']) ?>
              <?php if ($ev['note'] !== ''): ?><span class="muted">· <?= e($ev['note']) ?></span><?php endif; ?>
              <?php if ($ev['user_name']): ?><span class="faint">· <?= e($ev['user_name']) ?></span><?php endif; ?>
            </span>
            <span class="faint" style="font-size:12px"><?= e(mg_date($ev['created_at'], 'd/m H:i')) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <div class="stack">
    <?php if ($o['customer_name'] !== '' || $o['customer_phone'] !== '' || $o['address'] !== ''): ?>
      <div class="card">
        <div class="card-head"><h3>Cliente</h3></div>
        <?php if ($o['customer_name'] !== ''): ?><p><b><?= e($o['customer_name']) ?></b></p><?php endif; ?>
        <?php if ($o['customer_phone'] !== ''): ?>
          <p class="mt-1"><a class="link-line" href="tel:<?= e($o['customer_phone']) ?>"><?= e($o['customer_phone']) ?></a></p>
          <p class="mt-1"><a class="link-line gold" href="<?= e(mg_wa($o['customer_phone'], 'Hola, le escribimos de ' . $restaurant['name'] . ' por su pedido ' . $o['code'] . '.')) ?>" target="_blank" rel="noopener">Escribir por WhatsApp</a></p>
        <?php endif; ?>
        <?php if ($o['address'] !== ''): ?><p class="muted mt-1" style="font-size:var(--step--1)"><?= e($o['address']) ?></p><?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($open): ?>
      <div class="card">
        <div class="card-head"><h3>Cambiar estado</h3></div>
        <div class="row">
          <?php
          $next = array('new' => 'preparing', 'preparing' => 'ready', 'ready' => 'delivered', 'delivered' => 'paid');
          foreach (array('preparing' => 'Preparando', 'ready' => 'Listo', 'delivered' => 'Entregado') as $st => $label):
            if ($st === $o['status']) { continue; }
          ?>
            <form method="post" action="<?= e(mg_url('/panel/pedidos/' . (int)$o['id'] . '/estado')) ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="status" value="<?= e($st) ?>">
              <button class="btn btn-sm btn-ghost" type="submit"><?= e($label) ?></button>
            </form>
          <?php endforeach; ?>
        </div>
      </div>

      <form class="card" method="post" action="<?= e(mg_url('/panel/pedidos/' . (int)$o['id'] . '/cobrar')) ?>">
        <?= Csrf::field() ?>
        <div class="card-head"><h3>Cobrar</h3></div>
        <div class="field"><label for="method">Forma de pago</label>
          <select class="select" id="method" name="method">
            <option value="cash">Efectivo</option><option value="card">Tarjeta</option>
            <option value="transfer">Transferencia</option><option value="link">Link de pago</option>
          </select></div>
        <div class="field"><label for="tip_percent">Propina (%)</label>
          <input class="input" id="tip_percent" name="tip_percent" type="number" min="0" max="50" step="1" value="0"></div>
        <button class="btn btn-block" type="submit">Cobrar y cerrar</button>
      </form>

      <form class="card" method="post" action="<?= e(mg_url('/panel/pedidos/' . (int)$o['id'] . '/descuento')) ?>">
        <?= Csrf::field() ?>
        <div class="card-head"><h3>Descuento manual</h3></div>
        <div class="grid grid-2">
          <div class="field"><label for="percent">Porcentaje</label><input class="input" id="percent" name="percent" type="number" min="0" max="100" step="1" value="0"></div>
          <div class="field"><label for="amount">O monto</label><input class="input" id="amount" name="amount" type="number" min="0" step="0.01" value="0"></div>
        </div>
        <div class="field"><label for="reason">Motivo</label><input type="text" class="input" id="reason" name="reason" maxlength="200" placeholder="Cortesía de la casa"></div>
        <button class="btn btn-ghost btn-block" type="submit">Aplicar descuento</button>
      </form>

      <form class="card" method="post" action="<?= e(mg_url('/panel/pedidos/' . (int)$o['id'] . '/anular')) ?>"
            data-confirm="¿Anular el pedido <?= e($o['code']) ?>?">
        <?= Csrf::field() ?>
        <div class="card-head"><h3>Anular</h3></div>
        <div class="field"><label for="reason2">Motivo *</label><input type="text" class="input" id="reason2" name="reason" required maxlength="200"></div>
        <button class="btn btn-ghost btn-block" type="submit" style="color:var(--ember);border-color:rgba(196,80,43,.4)">Anular pedido</button>
      </form>
    <?php else: ?>
      <div class="card">
        <div class="card-head"><h3>Cierre</h3></div>
        <p class="muted" style="font-size:var(--step--1)">
          <?= $o['status'] === 'paid'
            ? 'Cobrado el ' . e(mg_date($o['paid_at'])) . ' en ' . e($o['payment_method']) . '.'
            : 'Anulado' . ($o['cancel_reason'] !== '' ? ': ' . e($o['cancel_reason']) : '.') ?>
        </p>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php $view->stop() ?>
