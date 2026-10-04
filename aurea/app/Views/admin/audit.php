<div class="tbl-wrap"><table class="tbl"><thead><tr><th><?= e(__('Fecha')) ?></th><th><?= e(__('Usuario')) ?></th><th><?= e(__('Acción')) ?></th><th><?= e(__('Detalle')) ?></th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td class="num"><?= e(fdatetime($r['created_at'])) ?></td><td><?= e($r['user_name']) ?></td><td><b><?= e($r['action']) ?></b><br><span class="hint"><?= e($r['entity']) ?><?= $r['entity_id'] ? ' #' . (int)$r['entity_id'] : '' ?></span></td><td><?= e($r['detail']) ?></td><td class="hint"><?= e($r['ip']) ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="hint"><?= e(__('Sin registros.')) ?></td></tr><?php endif; ?></tbody></table></div>
<?php include __DIR__ . '/../partials/pager.php'; ?>
