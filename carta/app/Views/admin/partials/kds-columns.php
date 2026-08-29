<?php
/** Columnas de la pantalla de cocina; se refresca sin recargar la página. */
use MenuGold\Models\Order;
$cols = array(
    'new'       => array('id' => 'col-new',   'title' => 'Nuevo',      'next' => 'cooking', 'cta' => 'Empezar'),
    'cooking' => array('id' => 'col-prep',  'title' => 'Preparando', 'next' => 'ready',     'cta' => 'Listo'),
    'ready'     => array('id' => 'col-ready', 'title' => 'Listo',      'next' => 'served', 'cta' => 'Entregado'),
);
foreach ($cols as $status => $c):
    $orders = isset($board[$status]) ? $board[$status] : array();
?>
  <section class="kds-column" id="<?= e($c['id']) ?>">
    <header>
      <h2><?= e($c['title']) ?></h2>
      <span class="kds-count"><?= count($orders) ?></span>
    </header>
    <div class="kds-list">
      <?php foreach ($orders as $o):
        $mins = (int)$o['minutes'];
        $cls = $mins >= 18 ? ' late' : ($mins >= 10 ? ' warn' : '');
      ?>
        <article class="ticket<?= $cls ?>" data-placed="<?= (int)strtotime($o['placed_at']) ?>">
          <div class="ticket-top">
            <span class="ticket-code"><?= e($o['code']) ?></span>
            <span class="ticket-time"><?= $mins ?> min</span>
          </div>
          <div class="ticket-meta">
            <span><?= e($o['table_name'] ? $o['table_name'] : Order::modeLabel($o['mode'])) ?></span>
            <span><?= e(mg_date($o['placed_at'], 'H:i')) ?></span>
          </div>
          <ul class="ticket-items">
            <?php foreach ($o['items'] as $it): ?>
              <li>
                <b><?= (int)$it['qty'] ?>×</b>
                <span>
                  <?= e($it['name']) ?>
                  <?php foreach ((array)$it['modifiers'] as $m): ?>
                    <small>· <?= e($m['name']) ?></small>
                  <?php endforeach; ?>
                  <?php if ($it['notes'] !== ''): ?><small class="gold">“<?= e($it['notes']) ?>”</small><?php endif; ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php if ($o['notes'] !== ''): ?>
            <p class="ticket-note"><?= e($o['notes']) ?></p>
          <?php endif; ?>
          <div class="ticket-actions">
            <button class="btn btn-sm" type="button" data-order="<?= (int)$o['id'] ?>" data-status="<?= e($c['next']) ?>"><?= e($c['cta']) ?></button>
            <?php if ($status === 'new'): ?>
              <button class="btn btn-sm btn-ghost" type="button" data-order="<?= (int)$o['id'] ?>" data-status="cancelled">Anular</button>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (!$orders): ?>
        <p class="faint" style="font-size:12px;text-align:center;padding:2rem 0">Sin pedidos aquí.</p>
      <?php endif; ?>
    </div>
  </section>
<?php endforeach; ?>
