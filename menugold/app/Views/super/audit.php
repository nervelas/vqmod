<?php
/** Bitácora general de la plataforma. */
$view->extend('layouts/panel');
$view->set('title', 'Bitácora general');
?>
<?php $view->start('content') ?>
<div class="card">
  <?php if ($entries): ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Cuándo</th><th>Restaurante</th><th>Quién</th><th>Acción</th><th>IP</th></tr></thead>
        <tbody>
          <?php foreach ($entries as $a): ?>
            <tr>
              <td class="muted" style="font-size:12px;white-space:nowrap"><?= e(mg_date($a['created_at'])) ?></td>
              <td><?= e($a['restaurant_name'] ? $a['restaurant_name'] : '—') ?></td>
              <td class="muted"><?= e($a['user_name'] ? $a['user_name'] : 'Sistema') ?></td>
              <td><span class="chip chip-dim"><?= e($a['action']) ?></span> <span class="faint" style="font-size:11.5px"><?= e($a['entity']) ?></span></td>
              <td class="faint tabular" style="font-size:11.5px"><?= e($a['ip']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><h3>Sin registros</h3></div>
  <?php endif; ?>
</div>
<?php $view->stop() ?>
