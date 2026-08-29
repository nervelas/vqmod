<?php
/** Cuenta de una mesa. */
use MenuGold\Core\Csrf;
use MenuGold\Models\Order;
$view->extend('layouts/panel');
$view->set('title', $table['name']);
$cur = $restaurant['currency'];
$tipOptions = array_values(array_filter(array_map('intval', explode(',', $restaurant['tip_options']))));
?>
<?php $view->start('actions') ?>
  <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/panel/mesero')) ?>">Volver al salón</a>
<?php $view->stop() ?>

<?php $view->start('content') ?>
<?php if (!$orders): ?>
  <div class="empty">
    <h3><?= e($table['name']) ?> está libre</h3>
    <p>Cuando alguien pida desde su QR, la cuenta aparecerá aquí.</p>
  </div>
<?php else: ?>
  <div class="grid grid-side">
    <div class="stack">
      <?php foreach ($orders as $o): ?>
        <div class="card">
          <div class="card-head">
            <div>
              <h3><a class="link-line" href="<?= e(mg_url('/panel/pedidos/' . (int)$o['id'])) ?>"><?= e($o['code']) ?></a></h3>
              <p><?= e(mg_date($o['placed_at'], 'H:i')) ?> · <?= e($o['status_label']) ?></p>
            </div>
            <span class="tabular gold"><?= e(mg_money($o['total'], $cur)) ?></span>
          </div>
          <ul class="stack" style="gap:.35rem;font-size:var(--step--1)">
            <?php foreach ($o['items'] as $it): ?>
              <li class="row-between">
                <span><?= (int)$it['qty'] ?>× <?= e($it['name_snapshot']) ?></span>
                <span class="tabular muted"><?= e(mg_money($it['line_total'], $cur)) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="stack">
      <div class="card">
        <div class="card-head"><h3>Total de la mesa</h3></div>
        <p class="display" style="font-size:var(--step-3);color:var(--gold)"><?= e(mg_money($total, $cur)) ?></p>
        <p class="muted" style="font-size:var(--step--1)"><?= count($orders) ?> pedidos abiertos</p>
      </div>

      <form class="card" method="post" action="<?= e(mg_url('/panel/mesero/mesa/' . (int)$table['id'] . '/cerrar')) ?>"
            data-confirm="¿Cobrar y cerrar <?= e($table['name']) ?>?">
        <?= Csrf::field() ?>
        <div class="card-head"><h3>Cobrar todo</h3></div>
        <div class="field"><label for="method">Forma de pago</label>
          <select class="select" id="method" name="method">
            <option value="cash">Efectivo</option><option value="card">Tarjeta</option>
            <option value="transfer">Transferencia</option><option value="link">Link de pago</option>
          </select></div>
        <?php if ((int)$restaurant['tip_enabled'] === 1): ?>
          <p class="label">Propina</p>
          <div class="tip-row" id="tip-row">
            <button class="tip-opt is-on" type="button" data-tip="0">0 %</button>
            <?php foreach ($tipOptions as $t): ?>
              <button class="tip-opt" type="button" data-tip="<?= (int)$t ?>"><?= (int)$t ?> %</button>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="tip_percent" id="tip_percent" value="0">
        <?php endif; ?>
        <button class="btn btn-block mt-2" type="submit">Cobrar <?= e(mg_money($total, $cur)) ?></button>
      </form>

      <div class="card">
        <div class="card-head"><h3>Precuenta</h3></div>
        <p class="muted" style="font-size:var(--step--1)">Imprime el detalle sin cerrar la mesa.</p>
        <div class="row mt-1">
          <?php foreach ($orders as $o): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(mg_url('/panel/pedidos/' . (int)$o['id'] . '/ticket?precuenta=1')) ?>" target="_blank" rel="noopener"><?= e($o['code']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php $view->stop() ?>

<?php $view->start('scripts') ?>
<script>
(function () {
  var row = document.getElementById('tip-row');
  var input = document.getElementById('tip_percent');
  var total = <?= json_encode((float)$total) ?>;
  if (!row || !input) { return; }
  row.addEventListener('click', function (e) {
    var b = e.target.closest('[data-tip]');
    if (!b) { return; }
    Array.prototype.forEach.call(row.children, function (n) { n.classList.toggle('is-on', n === b); });
    input.value = b.dataset.tip;
    var btn = document.querySelector('form button[type="submit"]');
    if (btn) {
      var t = total * (1 + Number(b.dataset.tip) / 100);
      btn.textContent = 'Cobrar <?= e($cur) ?>' + t.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
  });
})();
</script>
<?php $view->stop() ?>
