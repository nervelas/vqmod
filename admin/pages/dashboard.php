<?php
/** Admin dashboard. */
if (!defined('BASE_PATH')) { exit; }

$stats = [
    'Páginas'      => Database::scalar('SELECT COUNT(*) FROM pages'),
    'Secciones'    => Database::scalar('SELECT COUNT(*) FROM sections'),
    'Plataformas'  => Database::scalar('SELECT COUNT(*) FROM platforms'),
    'Álbumes'      => Database::scalar('SELECT COUNT(*) FROM albums'),
    'Fotografías'  => Database::scalar('SELECT COUNT(*) FROM photos'),
    'Multimedia'   => Database::scalar('SELECT COUNT(*) FROM media'),
];
$newSubs = Database::scalar('SELECT COUNT(*) FROM submissions WHERE is_read = 0');
$recentSubs = Database::all('SELECT * FROM submissions ORDER BY created_at DESC LIMIT 6');
$recentLogs = Database::all('SELECT l.*, a.name AS admin_name FROM admin_logs l LEFT JOIN admins a ON a.id=l.admin_id ORDER BY l.created_at DESC LIMIT 8');

admin_header('Panel');
?>
<div class="cards">
  <?php foreach ($stats as $label => $n): ?>
    <div class="card stat">
      <div class="stat__n"><?= (int)$n ?></div>
      <div class="stat__l"><?= e($label) ?></div>
    </div>
  <?php endforeach; ?>
  <div class="card stat stat--accent">
    <div class="stat__n"><?= (int)$newSubs ?></div>
    <div class="stat__l">Solicitudes nuevas</div>
    <a href="<?= e(admin_url('submissions')) ?>" class="stat__link">Ver todas →</a>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <h2>Solicitudes recientes</h2>
    <?php if (!$recentSubs): ?><p class="muted">Aún no hay solicitudes.</p><?php else: ?>
      <table class="table">
        <thead><tr><th>Tipo</th><th>Resumen</th><th>Fecha</th></tr></thead>
        <tbody>
        <?php foreach ($recentSubs as $s): $d = json_decode($s['data'], true) ?: []; ?>
          <tr>
            <td><span class="tag"><?= e($s['type']) ?></span></td>
            <td><?= e($d['nombre'] ?? $d['estudiante'] ?? '—') ?></td>
            <td class="muted"><?= e(date('d/m/Y H:i', strtotime($s['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2>Actividad reciente</h2>
    <?php if (!$recentLogs): ?><p class="muted">Sin actividad.</p><?php else: ?>
      <ul class="timeline">
        <?php foreach ($recentLogs as $l): ?>
          <li><strong><?= e($l['admin_name'] ?? 'Sistema') ?></strong> <?= e($l['detail'] ?: $l['action']) ?>
            <span class="muted"><?= e(date('d/m H:i', strtotime($l['created_at']))) ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>Accesos rápidos</h2>
  <div class="quicklinks">
    <a href="<?= e(admin_url('pages')) ?>">Editar páginas</a>
    <a href="<?= e(admin_url('platforms')) ?>">Accesos y plataformas</a>
    <a href="<?= e(admin_url('gallery')) ?>">Galería</a>
    <a href="<?= e(admin_url('settings')) ?>">Configuración</a>
    <a href="<?= e(admin_url('whatsapp')) ?>">WhatsApp</a>
    <a href="<?= e(base_url()) ?>" target="_blank">Ver sitio público</a>
  </div>
</div>
<?php admin_footer(); ?>
