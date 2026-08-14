<?php
/** Form submissions viewer (contact + admissions). */
if (!defined('BASE_PATH')) { exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Csrf::verifyPost();
    $do = post('do');
    if ($do === 'delete') { Database::delete('submissions', ['id' => (int)post('id')]); flash('success','Solicitud eliminada.'); }
    elseif ($do === 'read') { Database::update('submissions', ['is_read'=>1], ['id'=>(int)post('id')]); }
    redirect('admin/index.php?page=submissions' . (post('type')?'&type='.urlencode(post('type')):''));
}

$type = preg_replace('/[^a-z]/','',$_GET['type'] ?? '');
$where = $type ? 'WHERE type = ?' : '';
$params = $type ? [$type] : [];
$subs = Database::all("SELECT * FROM submissions $where ORDER BY created_at DESC LIMIT 300", $params);

admin_header('Solicitudes');
?>
<div class="tabs">
  <a href="<?= e(admin_url('submissions')) ?>" class="<?= $type===''?'is-active':'' ?>">Todas</a>
  <a href="<?= e(admin_url('submissions',['type'=>'contacto'])) ?>" class="<?= $type==='contacto'?'is-active':'' ?>">Contacto</a>
  <a href="<?= e(admin_url('submissions',['type'=>'admision'])) ?>" class="<?= $type==='admision'?'is-active':'' ?>">Admisiones</a>
</div>
<div class="card">
  <table class="table">
    <thead><tr><th>Tipo</th><th>Datos</th><th>Fecha</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($subs as $s): $d = json_decode($s['data'], true) ?: []; ?>
      <tr class="<?= $s['is_read']?'':'is-unread' ?>">
        <td><span class="tag"><?= e($s['type']) ?></span></td>
        <td>
          <details>
            <summary><?= e($d['nombre'] ?? $d['estudiante'] ?? 'Solicitud') ?> <span class="muted"><?= e($d['correo'] ?? $d['telefono'] ?? '') ?></span></summary>
            <dl class="sub-detail">
              <?php foreach ($d as $k => $v): ?>
                <dt><?= e(ucfirst(str_replace('_',' ',$k))) ?></dt><dd><?= nl2br(e((string)$v)) ?></dd>
              <?php endforeach; ?>
            </dl>
          </details>
        </td>
        <td class="muted"><?= e(date('d/m/Y H:i', strtotime($s['created_at']))) ?></td>
        <td class="row-actions">
          <?php if (!$s['is_read']): ?>
          <form method="post" style="display:inline"><?= Csrf::field() ?><input type="hidden" name="do" value="read"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="type" value="<?= e($type) ?>"><button class="btn btn--xs btn--outline">Marcar leída</button></form>
          <?php endif; ?>
          <form method="post" onsubmit="return confirm('¿Eliminar esta solicitud?')" style="display:inline"><?= Csrf::field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="type" value="<?= e($type) ?>"><button class="btn btn--xs btn--danger">Eliminar</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$subs): ?><p class="muted">No hay solicitudes.</p><?php endif; ?>
</div>
<?php admin_footer(); ?>
