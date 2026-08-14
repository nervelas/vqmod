<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('roles.manage');

$action = str_input('action', 'list');
$id = int_input('id');

/* ---- Handle POST (create / update) ------------------------------------- */
if (is_post() && ($action === 'new' || $action === 'edit')) {
    Security::requireCsrf();
    $name = str_input('name');
    $back = base_url('admin/roles.php?action=' . ($id ? 'edit&id=' . $id : 'new'));

    if ($name === '') {
        flash('danger', 'El nombre del rol es obligatorio.');
        redirect($back);
    }
    $slug = slugify(str_input('slug') ?: $name);
    $dup = Database::scalar("SELECT id FROM roles WHERE slug = ? AND id <> ?", [$slug, $id ?? 0]);
    if ($dup) { $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4); }

    $chosen = array_map('intval', (array)post('permissions', []));

    if ($id) {
        $before = Database::one("SELECT * FROM roles WHERE id = ?", [$id]);
        if (!$before) { redirect(base_url('admin/roles.php')); }
        if ((int)$before['is_system'] === 1) {
            // System roles: allow renaming, but never editing the permission set.
            Database::q("UPDATE roles SET name = ? WHERE id = ?", [$name, $id]);
            Audit::log('update', 'roles', $id, $before, ['name' => $name]);
            flash('success', 'Rol del sistema actualizado. Sus permisos no se pueden modificar.');
            redirect(base_url('admin/roles.php'));
        }
        Database::q("UPDATE roles SET name = ?, slug = ? WHERE id = ?", [$name, $slug, $id]);
        Database::q("DELETE FROM role_permissions WHERE role_id = ?", [$id]);
        $ins = "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
        foreach ($chosen as $pid) {
            if ($pid > 0) { Database::q($ins, [$id, $pid]); }
        }
        Audit::log('update', 'roles', $id,
            ['name' => $before['name'], 'slug' => $before['slug']],
            ['name' => $name, 'slug' => $slug, 'permissions' => $chosen]);
        flash('success', 'Rol actualizado correctamente.');
    } else {
        $newId = Database::insert("INSERT INTO roles (slug, name, is_system) VALUES (?, ?, 0)", [$slug, $name]);
        $ins = "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
        foreach ($chosen as $pid) {
            if ($pid > 0) { Database::q($ins, [$newId, $pid]); }
        }
        Audit::log('create', 'roles', $newId, null,
            ['name' => $name, 'slug' => $slug, 'permissions' => $chosen]);
        flash('success', 'Rol creado correctamente.');
    }
    redirect(base_url('admin/roles.php'));
}

/* ---- Delete ------------------------------------------------------------- */
if ($action === 'delete' && $id) {
    Security::requireCsrf();
    $before = Database::one("SELECT * FROM roles WHERE id = ?", [$id]);
    if ($before) {
        if ((int)$before['is_system'] === 1) {
            flash('danger', 'No se puede eliminar un rol del sistema.');
            redirect(base_url('admin/roles.php'));
        }
        $userCount = (int)Database::scalar("SELECT COUNT(*) FROM users WHERE role_id = ?", [$id]);
        if ($userCount > 0) {
            flash('danger', 'No se puede eliminar un rol con usuarios asignados. Reasígnelos primero.');
            redirect(base_url('admin/roles.php'));
        }
        Database::q("DELETE FROM roles WHERE id = ?", [$id]);
        Audit::log('delete', 'roles', $id, $before, null);
        flash('success', 'Rol eliminado.');
    }
    redirect(base_url('admin/roles.php'));
}

$PAGE_TITLE = 'Roles y permisos';
$ACTIVE = 'roles';

/* ---- Form view (new/edit) ---------------------------------------------- */
if ($action === 'new' || $action === 'edit') {
    $role = $action === 'edit' && $id ? Database::one("SELECT * FROM roles WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$role) { redirect(base_url('admin/roles.php')); }
    $isSystem = $role ? (int)$role['is_system'] === 1 : false;
    $isSuper  = $role && $role['slug'] === 'super_admin';

    $permissions = Database::all("SELECT * FROM permissions ORDER BY module, name");
    $grouped = [];
    foreach ($permissions as $p) { $grouped[$p['module']][] = $p; }

    $held = [];
    if ($role) {
        foreach (Database::all("SELECT permission_id FROM role_permissions WHERE role_id = ?", [$role['id']]) as $rp) {
            $held[(int)$rp['permission_id']] = true;
        }
    }
    $v = fn($k, $d = '') => e($role[$k] ?? $d);
    require 'partials/head.php';
    ?>
    <div class="page-head">
        <h1><?= $action === 'edit' ? 'Editar rol' : 'Nuevo rol' ?></h1>
        <p>Defina el nombre del rol y los permisos que otorga.</p>
    </div>
    <form method="post" action="<?= e(base_url('admin/roles.php?action=' . ($action === 'edit' ? 'edit&id=' . $id : 'new'))) ?>" class="card card-pad-lg">
        <?= Security::csrfField() ?>
        <div class="form-row">
            <div class="field">
                <label for="name">Nombre del rol *</label>
                <input class="input" id="name" name="name" required value="<?= $v('name') ?>">
            </div>
            <div class="field">
                <label for="slug">Slug</label>
                <input class="input" id="slug" name="slug" value="<?= $v('slug') ?>" placeholder="se-genera-automaticamente" <?= $isSystem ? 'readonly' : '' ?>>
            </div>
        </div>

        <h3 class="mt-3">Permisos</h3>
        <?php if ($isSuper): ?>
            <p class="muted" style="font-size:.85rem;margin-top:-.5rem">El rol Super Administrador posee <strong>todos los permisos</strong> de forma implícita y no puede modificarse.</p>
        <?php elseif ($isSystem): ?>
            <p class="muted" style="font-size:.85rem;margin-top:-.5rem">Este es un rol del sistema. Su conjunto de permisos no se puede modificar.</p>
        <?php else: ?>
            <p class="muted" style="font-size:.85rem;margin-top:-.5rem">Marque los permisos que este rol debe otorgar, agrupados por módulo.</p>
        <?php endif; ?>

        <?php foreach ($grouped as $module => $perms): ?>
            <h4 class="mt-2" style="text-transform:capitalize"><?= e($module) ?></h4>
            <div class="check-grid">
                <?php foreach ($perms as $p): ?>
                    <label class="check-item">
                        <input type="checkbox" name="permissions[]" value="<?= (int)$p['id'] ?>"
                            <?= checked($isSuper || isset($held[(int)$p['id']])) ?>
                            <?= $isSystem ? 'disabled' : '' ?>>
                        <?= e($p['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <div class="page-actions mt-3">
            <button class="btn" type="submit">Guardar rol</button>
            <a class="btn btn-ghost" href="<?= e(base_url('admin/roles.php')) ?>">Cancelar</a>
        </div>
    </form>
    <?php
    require 'partials/foot.php';
    exit;
}

/* ---- List view ---------------------------------------------------------- */
$roles = Database::all(
    "SELECT r.*,
        (SELECT COUNT(*) FROM role_permissions WHERE role_id = r.id) AS perm_count,
        (SELECT COUNT(*) FROM users WHERE role_id = r.id) AS user_count
     FROM roles r ORDER BY r.is_system DESC, r.name"
);
$totalPerms = (int)Database::scalar("SELECT COUNT(*) FROM permissions");
require 'partials/head.php';
?>
<div class="page-head flex justify-between items-center wrap">
    <div>
        <h1>Roles y permisos</h1>
        <p>Controle qué puede hacer cada tipo de administrador.</p>
    </div>
    <div class="page-actions"><a class="btn" href="<?= e(base_url('admin/roles.php?action=new')) ?>">+ Nuevo rol</a></div>
</div>

<?php if (!$roles): ?>
    <div class="empty-state card">
        <div class="es-icon">🔐</div>
        <h2>No hay roles</h2>
        <p>Crea un rol para asignar permisos.</p>
        <a class="btn" href="<?= e(base_url('admin/roles.php?action=new')) ?>">+ Nuevo rol</a>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr><th>Rol</th><th>Slug</th><th>Tipo</th><th class="num">Permisos</th><th class="num">Usuarios</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($roles as $r): ?>
                <?php $isSuper = $r['slug'] === 'super_admin'; ?>
                <tr>
                    <td><strong><?= e($r['name']) ?></strong></td>
                    <td><span class="muted"><?= e($r['slug']) ?></span></td>
                    <td>
                        <?php if ((int)$r['is_system'] === 1): ?>
                            <span class="badge badge-accent">Sistema</span>
                        <?php else: ?>
                            <span class="badge badge-muted">Personalizado</span>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= $isSuper ? e($totalPerms) . ' (todos)' : (int)$r['perm_count'] ?></td>
                    <td class="num"><?= (int)$r['user_count'] ?></td>
                    <td>
                        <div class="flex gap-1 wrap">
                            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/roles.php?action=edit&id=' . $r['id'])) ?>"><?= (int)$r['is_system'] === 1 ? 'Ver' : 'Editar' ?></a>
                            <?php if ((int)$r['is_system'] !== 1): ?>
                                <form method="post" action="<?= e(base_url('admin/roles.php?action=delete&id=' . $r['id'])) ?>" data-confirm="¿Eliminar este rol?">
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
