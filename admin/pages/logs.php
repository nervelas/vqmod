<?php
/** Admin activity log. */
if (!defined('BASE_PATH')) { exit; }
$logs = Database::all('SELECT l.*, a.name AS admin_name FROM admin_logs l LEFT JOIN admins a ON a.id=l.admin_id ORDER BY l.created_at DESC LIMIT 200');
admin_header('Actividad');
?>
<div class="card">
  <h2>Registro de actividad</h2>
  <table class="table">
    <thead><tr><th>Fecha</th><th>Administrador</th><th>Acción</th><th>Detalle</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td class="muted"><?= e(date('d/m/Y H:i', strtotime($l['created_at']))) ?></td>
        <td><?= e($l['admin_name'] ?? '—') ?></td>
        <td><span class="tag"><?= e($l['action']) ?></span></td>
        <td><?= e($l['detail']) ?></td>
        <td class="muted"><?= e($l['ip']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$logs): ?><p class="muted">Sin registros.</p><?php endif; ?>
</div>
<?php admin_footer(); ?>
