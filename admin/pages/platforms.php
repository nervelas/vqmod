<?php
/** "Accesos y plataformas" manager (quick access external/internal links). */
if (!defined('BASE_PATH')) { exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Csrf::verifyPost();
    $do = post('do');
    if ($do === 'save') {
        $id = (int)post('id');
        $data = [
            'name'        => post('name'),
            'description' => post('description'),
            'icon'        => post('icon'),
            'image'       => post('image'),
            'url'         => post('url') ?: '#',
            'target'      => post('target') === '_self' ? '_self' : '_blank',
            'sort'        => (int)post('sort'),
            'is_active'   => isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($id) { Database::update('platforms', $data, ['id' => $id]); Auth::log('platform_update','Editó acceso #'.$id); }
        else { Database::insert('platforms', $data); Auth::log('platform_create','Creó acceso'); }
        flash('success', 'Acceso guardado.');
    } elseif ($do === 'delete') {
        Database::delete('platforms', ['id' => (int)post('id')]);
        flash('success', 'Acceso eliminado.');
    } elseif ($do === 'toggle') {
        $id=(int)post('id'); $c=Database::scalar('SELECT is_active FROM platforms WHERE id=?',[$id]);
        Database::update('platforms',['is_active'=>$c?0:1],['id'=>$id]);
    }
    redirect('admin/index.php?page=platforms');
}

$items = Database::all('SELECT * FROM platforms ORDER BY sort ASC, id ASC');
$edit = null;
if (($eid = (int)($_GET['edit'] ?? 0))) { $edit = Database::first('SELECT * FROM platforms WHERE id=?', [$eid]); }

admin_header('Accesos y plataformas');
?>
<p class="muted">Administra los accesos rápidos (Portal Académico, Pagos, Radio, etc.). Los enlaces externos mantienen su destino real; puedes cambiarlos aquí sin tocar código.</p>
<div class="grid-2">
  <div class="card">
    <h2>Accesos</h2>
    <table class="table">
      <thead><tr><th>#</th><th>Nombre</th><th>URL</th><th>Estado</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($items as $p): ?>
        <tr>
          <td><span class="mini-ico"><?= platform_icon($p['icon'] ?: 'star') ?></span></td>
          <td><strong><?= e($p['name']) ?></strong><br><span class="muted"><?= e($p['description']) ?></span></td>
          <td class="muted break"><?= e($p['url']) ?> <?= $p['target']==='_blank'?'↗':'' ?></td>
          <td>
            <form method="post" style="display:inline"><?= Csrf::field() ?><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="badge <?= $p['is_active']?'badge--on':'badge--off' ?>"><?= $p['is_active']?'Activo':'Inactivo' ?></button>
            </form>
          </td>
          <td class="row-actions">
            <a class="btn btn--sm btn--outline" href="<?= e(admin_url('platforms',['edit'=>$p['id']])) ?>">Editar</a>
            <form method="post" onsubmit="return confirm('¿Eliminar este acceso?')"><?= Csrf::field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="btn btn--sm btn--danger">Eliminar</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card">
    <h2><?= $edit?'Editar acceso':'Nuevo acceso' ?></h2>
    <form method="post" class="form">
      <?= Csrf::field() ?>
      <input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="form-group"><label>Nombre</label><input type="text" name="name" value="<?= e($edit['name'] ?? '') ?>" required></div>
      <div class="form-group"><label>Descripción</label><input type="text" name="description" value="<?= e($edit['description'] ?? '') ?>"></div>
      <div class="form-group"><label>URL (destino real)</label><input type="text" name="url" value="<?= e($edit['url'] ?? '') ?>" placeholder="https://… ó slug interno"></div>
      <div class="grid-2">
        <div class="form-group"><label>Ícono</label><input type="text" name="icon" value="<?= e($edit['icon'] ?? 'star') ?>" list="iconlist2">
          <datalist id="iconlist2"><?php foreach (['portal','card','admissions','briefcase','bus','image','radio','book','graduation','chat','clock','star'] as $ic): ?><option value="<?= $ic ?>"><?php endforeach; ?></datalist></div>
        <div class="form-group"><label>Orden</label><input type="number" name="sort" value="<?= (int)($edit['sort'] ?? count($items)+1) ?>"></div>
      </div>
      <?= media_field('image', $edit['image'] ?? '', 'Imagen (opcional, reemplaza al ícono)') ?>
      <div class="form-group"><label>Abrir en</label>
        <select name="target"><option value="_blank" <?= (($edit['target']??'_blank')==='_blank')?'selected':'' ?>>Pestaña nueva</option>
        <option value="_self" <?= (($edit['target']??'')==='_self')?'selected':'' ?>>Misma pestaña</option></select></div>
      <label class="switch"><input type="checkbox" name="is_active" <?= (!$edit || $edit['is_active'])?'checked':'' ?>> Activo</label>
      <div class="form-actions"><button class="btn btn--primary"><?= $edit?'Guardar':'Agregar' ?></button>
      <?php if ($edit): ?><a class="btn btn--outline" href="<?= e(admin_url('platforms')) ?>">Cancelar</a><?php endif; ?></div>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
