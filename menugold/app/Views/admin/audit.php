<?php
/** Bitácora de cambios sensibles. */
$view->extend('layouts/panel');
$view->set('title', 'Bitácora');
?>
<?php $view->start('content') ?>
<p class="page-intro">Registro de los cambios importantes: precios, usuarios, anulaciones, cobros y accesos. Se conservan las últimas 200 entradas.</p>
<div class="card">
  <?php if ($entries): ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Cuándo</th><th>Quién</th><th>Acción</th><th>Sobre</th><th>Detalle</th></tr></thead>
        <tbody>
          <?php foreach ($entries as $a): ?>
            <tr>
              <td class="muted" style="font-size:12px;white-space:nowrap"><?= e(mg_date($a['created_at'])) ?></td>
              <td><?= e($a['user_name'] ? $a['user_name'] : 'Sistema') ?></td>
              <td><span class="chip chip-dim"><?= e($a['action']) ?></span></td>
              <td class="muted"><?= e($a['entity']) ?><?= (int)$a['entity_id'] > 0 ? ' #' . (int)$a['entity_id'] : '' ?></td>
              <td class="faint" style="font-size:11.5px;max-width:280px;overflow:hidden;text-overflow:ellipsis"><?= e(\MenuGold\Core\Str::limit((string)$a['details'], 90)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><h3>Sin registros todavía</h3></div>
  <?php endif; ?>
</div>
<?php $view->stop() ?>
