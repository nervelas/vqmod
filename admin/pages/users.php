<?php
/** Administrator accounts CRUD. */
if (!defined('BASE_PATH')) { exit; }
$me = Auth::user();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Csrf::verifyPost();
    $do = post('do');
    if ($do === 'save') {
        $id = (int)post('id');
        $name = post('name'); $username = post('username'); $email = post('email');
        $pass = (string)($_POST['password'] ?? '');
        $errors = [];
        if (mb_strlen($name) < 2) $errors[] = 'Nombre inválido.';
        if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $username)) $errors[] = 'Usuario inválido (3-60, letras/números).';
        if (!is_email($email)) $errors[] = 'Correo inválido.';
        if (!$id && strlen($pass) < 8) $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        if ($pass !== '' && strlen($pass) < 8) $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        // uniqueness
        $dup = Database::scalar('SELECT COUNT(*) FROM admins WHERE (username=? OR email=?) AND id<>?', [$username,$email,$id]);
        if ($dup) $errors[] = 'El usuario o correo ya existe.';
        if ($errors) { flash('error', implode(' ', $errors)); redirect('admin/index.php?page=users'.($id?'&edit='.$id:'')); }
        $data = ['name'=>$name,'username'=>$username,'email'=>$email,'is_active'=>isset($_POST['is_active'])?1:0];
        if ($pass !== '') { $data['password_hash'] = password_hash($pass, PASSWORD_DEFAULT); }
        if ($id) { Database::update('admins', $data, ['id'=>$id]); }
        else { $data['role']='admin'; $data['created_at']=date('Y-m-d H:i:s'); Database::insert('admins', $data); }
        Auth::log('user_save','Guardó administrador '.$username);
        flash('success','Administrador guardado.');
    } elseif ($do === 'delete') {
        $id=(int)post('id');
        if ($id === (int)$me['id']) { flash('error','No puedes eliminar tu propia cuenta.'); }
        elseif (Database::scalar('SELECT COUNT(*) FROM admins WHERE is_active=1') <= 1) { flash('error','Debe existir al menos un administrador.'); }
        else { Database::delete('admins', ['id'=>$id]); Auth::log('user_delete','Eliminó administrador #'.$id); flash('success','Administrador eliminado.'); }
    }
    redirect('admin/index.php?page=users');
}
$users = Database::all('SELECT * FROM admins ORDER BY id ASC');
$edit = ($eid=(int)($_GET['edit']??0)) ? Database::first('SELECT * FROM admins WHERE id=?',[$eid]) : null;
admin_header('Administradores');
?>
<div class="grid-2">
  <div class="card">
    <h2>Cuentas</h2>
    <table class="table">
      <thead><tr><th>Nombre</th><th>Usuario</th><th>Estado</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><strong><?= e($u['name']) ?></strong><br><span class="muted"><?= e($u['email']) ?></span></td>
          <td><?= e($u['username']) ?></td>
          <td><span class="badge <?= $u['is_active']?'badge--on':'badge--off' ?>"><?= $u['is_active']?'Activo':'Inactivo' ?></span></td>
          <td class="row-actions">
            <a class="btn btn--sm btn--outline" href="<?= e(admin_url('users',['edit'=>$u['id']])) ?>">Editar</a>
            <?php if ((int)$u['id'] !== (int)$me['id']): ?>
            <form method="post" onsubmit="return confirm('¿Eliminar este administrador?')"><?= Csrf::field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn--sm btn--danger">Eliminar</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card">
    <h2><?= $edit?'Editar administrador':'Nuevo administrador' ?></h2>
    <form method="post" class="form">
      <?= Csrf::field() ?><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
      <div class="form-group"><label>Nombre completo</label><input type="text" name="name" value="<?= e($edit['name']??'') ?>" required></div>
      <div class="form-group"><label>Usuario</label><input type="text" name="username" value="<?= e($edit['username']??'') ?>" required></div>
      <div class="form-group"><label>Correo</label><input type="email" name="email" value="<?= e($edit['email']??'') ?>" required></div>
      <div class="form-group"><label>Contraseña <?= $edit?'<span class="muted">(dejar en blanco para no cambiar)</span>':'' ?></label><input type="password" name="password" autocomplete="new-password" <?= $edit?'':'required' ?>></div>
      <label class="switch"><input type="checkbox" name="is_active" <?= (!$edit || $edit['is_active'])?'checked':'' ?>> Activo</label>
      <div class="form-actions"><button class="btn btn--primary"><?= $edit?'Guardar':'Crear' ?></button>
      <?php if ($edit): ?><a class="btn btn--outline" href="<?= e(admin_url('users')) ?>">Cancelar</a><?php endif; ?></div>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
