<?php
/** Navigation menu manager. */
if (!defined('BASE_PATH')) { exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Csrf::verifyPost();
    $do = post('do');
    if ($do === 'save') {
        $id = (int)post('id');
        $data = [
            'label'     => post('label'),
            'url'       => post('url') ?: '#',
            'target'    => post('target') === '_blank' ? '_blank' : '_self',
            'sort'      => (int)post('sort'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($id) { Database::update('menu_items', $data, ['id' => $id]); }
        else { Database::insert('menu_items', $data); }
        flash('success', 'Menú actualizado.');
    } elseif ($do === 'delete') {
        Database::delete('menu_items', ['id' => (int)post('id')]);
        flash('success', 'Elemento eliminado.');
    }
    redirect('admin/index.php?page=menu');
}

$items = Database::all('SELECT * FROM menu_items ORDER BY sort ASC, id ASC');
$edit = null;
if (($eid = (int)($_GET['edit'] ?? 0))) { $edit = Database::first('SELECT * FROM menu_items WHERE id=?', [$eid]); }

admin_header('Menú de navegación');
?>
<div class="grid-2">
  <div class="card">
    <h2>Elementos del menú</h2>
    <table class="table">
      <thead><tr><th>#</th><th>Etiqueta</th><th>Enlace</th><th>Estado</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($items as $m): ?>
        <tr>
          <td><?= (int)$m['sort'] ?></td>
          <td><strong><?= e($m['label']) ?></strong></td>
          <td class="muted"><?= e($m['url']) ?></td>
          <td><span class="badge <?= $m['is_active']?'badge--on':'badge--off' ?>"><?= $m['is_active']?'Activo':'Inactivo' ?></span></td>
          <td class="row-actions">
            <a class="btn btn--sm btn--outline" href="<?= e(admin_url('menu',['edit'=>$m['id']])) ?>">Editar</a>
            <form method="post" onsubmit="return confirm('¿Eliminar este elemento del menú?')">
              <?= Csrf::field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <button class="btn btn--sm btn--danger">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card">
    <h2><?= $edit ? 'Editar elemento' : 'Nuevo elemento' ?></h2>
    <form method="post" class="form">
      <?= Csrf::field() ?>
      <input type="hidden" name="do" value="save">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="form-group"><label>Etiqueta</label><input type="text" name="label" value="<?= e($edit['label'] ?? '') ?>" required></div>
      <div class="form-group"><label>Enlace (slug interno o URL)</label><input type="text" name="url" value="<?= e($edit['url'] ?? '') ?>" placeholder="nosotros ó https://…"></div>
      <div class="grid-2">
        <div class="form-group"><label>Abrir en</label>
          <select name="target"><option value="_self" <?= (($edit['target']??'')==='_self')?'selected':'' ?>>Misma pestaña</option>
          <option value="_blank" <?= (($edit['target']??'')==='_blank')?'selected':'' ?>>Nueva pestaña</option></select></div>
        <div class="form-group"><label>Orden</label><input type="number" name="sort" value="<?= (int)($edit['sort'] ?? count($items)+1) ?>"></div>
      </div>
      <label class="switch"><input type="checkbox" name="is_active" <?= (!$edit || $edit['is_active'])?'checked':'' ?>> Activo</label>
      <div class="form-actions">
        <button class="btn btn--primary"><?= $edit?'Guardar':'Agregar' ?></button>
        <?php if ($edit): ?><a class="btn btn--outline" href="<?= e(admin_url('menu')) ?>">Cancelar</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
