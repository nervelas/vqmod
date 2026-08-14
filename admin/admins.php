<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('admins.manage');

$action = str_input('action', 'list');
$id = int_input('id');

/** Count active super admins. */
function active_super_admins(): int
{
    return (int)Database::scalar(
        "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
         WHERE r.slug = 'super_admin' AND u.status = 'active'"
    );
}

/* ---- Handle POST (create / update) ------------------------------------- */
if (is_post() && ($action === 'new' || $action === 'edit')) {
    Security::requireCsrf();
    $name     = str_input('name');
    $username = str_input('username');
    $email    = str_input('email');
    $password = (string)post('password', '');
    $roleId   = int_input('role_id');
    $status   = in_array(str_input('status'), ['active','disabled'], true) ? str_input('status') : 'active';

    $back = base_url('admin/admins.php?action=' . ($id ? 'edit&id=' . $id : 'new'));

    if ($name === '' || $username === '' || $email === '' || !$roleId) {
        flash('danger', 'Nombre, usuario, correo y rol son obligatorios.');
        redirect($back);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'El correo electrónico no es válido.');
        redirect($back);
    }
    if (!$id && $password === '') {
        flash('danger', 'La contraseña es obligatoria al crear un administrador.');
        redirect($back);
    }

    $role = Database::one("SELECT * FROM roles WHERE id = ?", [$roleId]);
    if (!$role) {
        flash('danger', 'El rol seleccionado no existe.');
        redirect($back);
    }

    // A non-super user cannot assign the super_admin role.
    if ($role['slug'] === 'super_admin' && !Auth::isSuper()) {
        flash('danger', 'Solo un super administrador puede crear o editar super administradores.');
        redirect(base_url('admin/admins.php'));
    }

    // Uniqueness checks for username / email.
    $dupUser = Database::scalar("SELECT id FROM users WHERE username = ? AND id <> ?", [$username, $id ?? 0]);
    if ($dupUser) { flash('danger', 'El nombre de usuario ya está en uso.'); redirect($back); }
    $dupMail = Database::scalar("SELECT id FROM users WHERE email = ? AND id <> ?", [$email, $id ?? 0]);
    if ($dupMail) { flash('danger', 'El correo electrónico ya está en uso.'); redirect($back); }

    if ($id) {
        $before = Database::one("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$before) { redirect(base_url('admin/admins.php')); }

        // A non-super user cannot edit an existing super_admin.
        $beforeRole = Database::one("SELECT slug FROM roles WHERE id = ?", [$before['role_id']]);
        if (($beforeRole['slug'] ?? '') === 'super_admin' && !Auth::isSuper()) {
            flash('danger', 'Solo un super administrador puede editar super administradores.');
            redirect(base_url('admin/admins.php'));
        }

        // Never disable the last active super_admin.
        if (($beforeRole['slug'] ?? '') === 'super_admin'
            && $before['status'] === 'active'
            && ($status === 'disabled' || $role['slug'] !== 'super_admin')
            && active_super_admins() <= 1) {
            flash('danger', 'No se puede desactivar ni cambiar el rol del último super administrador activo.');
            redirect($back);
        }

        $data = [
            'name'     => $name,
            'username' => $username,
            'email'    => $email,
            'role_id'  => $roleId,
            'status'   => $status,
        ];
        $sets = ['name = ?', 'username = ?', 'email = ?', 'role_id = ?', 'status = ?'];
        $params = [$name, $username, $email, $roleId, $status];
        if ($password !== '') {
            $sets[] = 'password_hash = ?';
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $params[] = $id;
        Database::q("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?", $params);
        $after = $data; $after['password_hash'] = $password !== '' ? '***' : $before['password_hash'];
        $before['password_hash'] = '***';
        Audit::log('update', 'admins', $id, $before, $after);
        flash('success', 'Administrador actualizado correctamente.');
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $newId = Database::insert(
            "INSERT INTO users (name, username, email, password_hash, role_id, status)
             VALUES (?,?,?,?,?,?)",
            [$name, $username, $email, $hash, $roleId, $status]
        );
        Audit::log('create', 'admins', $newId, null,
            ['name' => $name, 'username' => $username, 'email' => $email, 'role_id' => $roleId, 'status' => $status]);
        flash('success', 'Administrador creado correctamente.');
    }
    redirect(base_url('admin/admins.php'));
}

/* ---- Delete ------------------------------------------------------------- */
if ($action === 'delete' && $id) {
    Security::requireCsrf();
    $me = Auth::user();
    if ($id === (int)$me['id']) {
        flash('danger', 'No puede eliminar su propia cuenta.');
        redirect(base_url('admin/admins.php'));
    }
    $before = Database::one("SELECT * FROM users WHERE id = ?", [$id]);
    if ($before) {
        $role = Database::one("SELECT slug FROM roles WHERE id = ?", [$before['role_id']]);
        if (($role['slug'] ?? '') === 'super_admin' && !Auth::isSuper()) {
            flash('danger', 'Solo un super administrador puede eliminar super administradores.');
            redirect(base_url('admin/admins.php'));
        }
        if (($role['slug'] ?? '') === 'super_admin'
            && $before['status'] === 'active'
            && active_super_admins() <= 1) {
            flash('danger', 'No se puede eliminar al último super administrador activo.');
            redirect(base_url('admin/admins.php'));
        }
        Database::q("DELETE FROM users WHERE id = ?", [$id]);
        $before['password_hash'] = '***';
        Audit::log('delete', 'admins', $id, $before, null);
        flash('success', 'Administrador eliminado.');
    }
    redirect(base_url('admin/admins.php'));
}

$roles = Database::all("SELECT id, slug, name FROM roles ORDER BY name");
$roleOptions = [];
foreach ($roles as $r) { $roleOptions[$r['id']] = $r['name']; }

$PAGE_TITLE = 'Administradores';
$ACTIVE = 'admins';

/* ---- Form view (new/edit) ---------------------------------------------- */
if ($action === 'new' || $action === 'edit') {
    $user = $action === 'edit' && $id ? Database::one("SELECT * FROM users WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$user) { redirect(base_url('admin/admins.php')); }
    $v = fn($k, $d = '') => e($user[$k] ?? $d);
    require 'partials/head.php';
    ?>
    <div class="page-head">
        <h1><?= $action === 'edit' ? 'Editar administrador' : 'Nuevo administrador' ?></h1>
        <p>Gestione las cuentas con acceso al panel de administración.</p>
    </div>
    <form method="post" action="<?= e(base_url('admin/admins.php?action=' . ($action === 'edit' ? 'edit&id=' . $id : 'new'))) ?>" class="card card-pad-lg">
        <?= Security::csrfField() ?>
        <div class="form-row">
            <div class="field">
                <label for="name">Nombre completo *</label>
                <input class="input" id="name" name="name" required value="<?= $v('name') ?>">
            </div>
            <div class="field">
                <label for="username">Usuario *</label>
                <input class="input" id="username" name="username" required value="<?= $v('username') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="email">Correo electrónico *</label>
                <input class="input" type="email" id="email" name="email" required value="<?= $v('email') ?>">
            </div>
            <div class="field">
                <label for="password">Contraseña <?= $action === 'edit' ? '' : '*' ?></label>
                <input class="input" type="password" id="password" name="password" <?= $action === 'edit' ? '' : 'required' ?> autocomplete="new-password" placeholder="<?= $action === 'edit' ? 'Dejar en blanco para no cambiar' : '' ?>">
                <?php if ($action === 'edit'): ?><div class="help">Deje este campo vacío para mantener la contraseña actual.</div><?php endif; ?>
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="role_id">Rol *</label>
                <select class="select" id="role_id" name="role_id" required>
                    <?= options($roleOptions, $user['role_id'] ?? null, 'Seleccione…') ?>
                </select>
            </div>
            <div class="field">
                <label for="status">Estado</label>
                <select class="select" id="status" name="status">
                    <option value="active"<?= selected('active', $user['status'] ?? 'active') ?>>Activo</option>
                    <option value="disabled"<?= selected('disabled', $user['status'] ?? '') ?>>Deshabilitado</option>
                </select>
            </div>
        </div>
        <div class="page-actions mt-3">
            <button class="btn" type="submit">Guardar administrador</button>
            <a class="btn btn-ghost" href="<?= e(base_url('admin/admins.php')) ?>">Cancelar</a>
        </div>
    </form>
    <?php
    require 'partials/foot.php';
    exit;
}

/* ---- List view ---------------------------------------------------------- */
$users = Database::all(
    "SELECT u.*, r.name AS role_name, r.slug AS role_slug
     FROM users u JOIN roles r ON r.id = u.role_id
     ORDER BY u.created_at ASC"
);
$me = Auth::user();
require 'partials/head.php';
?>
<div class="page-head flex justify-between items-center wrap">
    <div>
        <h1>Administradores</h1>
        <p>Cuentas con acceso al panel de administración.</p>
    </div>
    <div class="page-actions"><a class="btn" href="<?= e(base_url('admin/admins.php?action=new')) ?>">+ Nuevo administrador</a></div>
</div>

<?php if (!$users): ?>
    <div class="empty-state card">
        <div class="es-icon">👥</div>
        <h2>No hay administradores</h2>
        <p>Crea la primera cuenta de administrador.</p>
        <a class="btn" href="<?= e(base_url('admin/admins.php?action=new')) ?>">+ Nuevo administrador</a>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr><th>Nombre</th><th>Usuario</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= e($u['name']) ?></strong><?= (int)$u['id'] === (int)$me['id'] ? ' <span class="badge badge-accent">Tú</span>' : '' ?></td>
                    <td><?= e($u['username']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e($u['role_name']) ?></td>
                    <td><span class="badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-muted' ?>"><?= $u['status'] === 'active' ? 'Activo' : 'Deshabilitado' ?></span></td>
                    <td><?= e(fmt_date($u['last_login'], 'd/m/Y H:i')) ?></td>
                    <td>
                        <div class="flex gap-1 wrap">
                            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/admins.php?action=edit&id=' . $u['id'])) ?>">Editar</a>
                            <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                                <form method="post" action="<?= e(base_url('admin/admins.php?action=delete&id=' . $u['id'])) ?>" data-confirm="¿Eliminar este administrador? Esta acción no se puede deshacer.">
                                    <?= Security::csrfField() ?><button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require 'partials/foot.php'; ?>
