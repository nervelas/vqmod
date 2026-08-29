<?php
/** Salón: estado de mesas, llamadas y pedidos para llevar. */
use MenuGold\Core\Csrf;
use MenuGold\Models\Order;
$view->extend('layouts/panel');
$view->set('title', 'Salón');
$cur = $restaurant['currency'];
?>
<?php $view->start('content') ?>

<?php if ($calls): ?>
  <div class="card" style="border-color:rgba(196,80,43,.45);background:rgba(196,80,43,.06)">
    <div class="card-head"><h2>Te están llamando</h2></div>
    <div class="row">
      <?php foreach ($calls as $c): ?>
        <form method="post" action="<?= e(mg_url('/panel/llamadas/' . (int)$c['id'] . '/atender')) ?>">
          <?= Csrf::field() ?>
          <button class="btn btn-sm btn-ember" type="submit">
            <?= e($c['table_name'] ? $c['table_name'] : 'Mesa') ?> · <?= e($c['type'] === 'bill' ? 'la cuenta' : 'mesero') ?> · <?= e(mg_ago($c['created_at'])) ?>
          </button>
        </form>
      <?php endforeach; ?>
    </div>
    <p class="field-hint">Toca el aviso cuando ya la hayas atendido.</p>
  </div>
<?php endif; ?>

<div class="card mt-2">
  <div class="card-head">
    <div><h2>Mesas</h2><p>Toca una mesa para ver su cuenta y cobrar.</p></div>
    <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/panel/mesas')) ?>">Administrar mesas</a>
  </div>

  <?php if ($tables): ?>
    <div class="floor" id="floor-board">
      <?php foreach ($tables as $t):
        $cls = '';
        if (!empty($t['calls']))          { $cls = ' is-call'; }
        elseif ((int)$t['ready_count'] > 0) { $cls = ' is-ready'; }
        elseif ((int)$t['open_orders'] > 0) { $cls = ' is-occupied'; }
      ?>
        <a class="table-card<?= $cls ?>" href="<?= e(mg_url('/panel/mesero/mesa/' . (int)$t['id'])) ?>">
          <b><?= e($t['name']) ?></b>
          <small><?= (int)$t['seats'] ?> lugares<?= $t['zone'] !== '' ? ' · ' . e($t['zone']) : '' ?></small>
          <?php if ((int)$t['open_orders'] > 0): ?>
            <span class="table-total"><?= e(mg_money($t['open_total'], $cur)) ?></span>
            <small><?= (int)$t['open_orders'] ?> pedidos · <?= e(mg_ago($t['since'])) ?></small>
          <?php else: ?>
            <span class="chip chip-dim">Libre</span>
          <?php endif; ?>
          <?php if (!empty($t['calls'])): ?><span class="chip chip-ember">Llamando</span><?php endif; ?>
          <?php if ((int)$t['ready_count'] > 0): ?><span class="chip chip-green">Listo para servir</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty">
      <h3>Todavía no hay mesas</h3>
      <p>Créalas y descarga sus códigos QR para imprimir.</p>
      <p class="mt-2"><a class="btn btn-sm" href="<?= e(mg_url('/panel/mesas')) ?>">Crear mesas</a></p>
    </div>
  <?php endif; ?>
</div>

<?php if ($takeaway): ?>
  <div class="card mt-2">
    <div class="card-head"><h2>Para llevar y domicilio</h2></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Código</th><th>Cliente</th><th>Modo</th><th>Estado</th><th class="num">Total</th></tr></thead>
        <tbody>
          <?php foreach ($takeaway as $o): ?>
            <tr>
              <td><a class="cell-title link-line" href="<?= e(mg_url('/panel/pedidos/' . (int)$o['id'])) ?>"><?= e($o['code']) ?></a></td>
              <td><?= e($o['customer_name'] !== '' ? $o['customer_name'] : '—') ?>
                <?php if ($o['customer_phone'] !== ''): ?><span class="muted" style="display:block;font-size:11.5px"><?= e($o['customer_phone']) ?></span><?php endif; ?></td>
              <td class="muted"><?= e(Order::modeLabel($o['mode'])) ?></td>
              <td><span class="chip"><?= e(Order::$statusLabels[$o['status']]) ?></span></td>
              <td class="num tabular"><?= e(mg_money($o['total'], $cur)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
<?php $view->stop() ?>
