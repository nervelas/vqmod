<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('content.manage');

$action = str_input('action', 'list');
$id = int_input('id');

$tournaments = Database::all("SELECT id, name FROM tournaments ORDER BY name");
$tournamentOptions = [];
foreach ($tournaments as $t) { $tournamentOptions[$t['id']] = $t['name']; }

/* ---- Handle POST (create / update) ------------------------------------- */
if (is_post()) {
    Security::requireCsrf();
    $title = str_input('title');
    if ($title === '') {
        flash('danger', 'El título de la regla es obligatorio.');
        redirect(base_url('admin/rules.php?action=' . ($id ? 'edit&id=' . $id : 'new')));
    }

    $data = [
        'league_id'     => the_league_id(),
        'tournament_id' => int_input('tournament_id') ?: null,
        'category'      => str_input('category'),
        'title'         => $title,
        'body'          => (string)post('body', ''),
        'sort_order'    => (int)int_input('sort_order', 0),
    ];

    if ($id) {
        $before = Database::one("SELECT * FROM rules WHERE id = ?", [$id]);
        if (!$before) { redirect(base_url('admin/rules.php')); }
        $sets = []; $params = [];
        foreach ($data as $k => $v) { $sets[] = "$k = ?"; $params[] = $v; }
        $params[] = $id;
        Database::q("UPDATE rules SET " . implode(',', $sets) . " WHERE id = ?", $params);
        Audit::log('update', 'rules', $id, $before, $data);
        flash('success', 'Regla actualizada correctamente.');
    } else {
        $cols = implode(',', array_keys($data));
        $ph   = implode(',', array_fill(0, count($data), '?'));
        $newId = Database::insert("INSERT INTO rules ($cols) VALUES ($ph)", array_values($data));
        Audit::log('create', 'rules', $newId, null, $data);
        flash('success', 'Regla creada.');
    }
    redirect(base_url('admin/rules.php'));
}

/* ---- Delete ------------------------------------------------------------- */
if ($action === 'delete' && $id) {
    Security::requireCsrf();
    $before = Database::one("SELECT * FROM rules WHERE id = ?", [$id]);
    if ($before) {
        Database::q("DELETE FROM rules WHERE id = ?", [$id]);
        Audit::log('delete', 'rules', $id, $before, null);
        flash('success', 'Regla eliminada.');
    }
    redirect(base_url('admin/rules.php'));
}

$PAGE_TITLE = 'Reglamento';
$ACTIVE = 'rules';

/* ---- Form view (new/edit) ---------------------------------------------- */
if ($action === 'new' || $action === 'edit') {
    $rule = $action === 'edit' && $id ? Database::one("SELECT * FROM rules WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$rule) { redirect(base_url('admin/rules.php')); }
    $v = fn($k, $d = '') => e($rule[$k] ?? $d);
    require 'partials/head.php';
    ?>
    <div class="page-head">
        <h1><?= $action === 'edit' ? 'Editar regla' : 'Nueva regla' ?></h1>
        <p>Define el reglamento por categoría, con alcance opcional a una liga o torneo.</p>
    </div>
    <form method="post" class="card card-pad-lg">
        <?= Security::csrfField() ?>
        <div class="form-row">
            <div class="field">
                <label for="category">Categoría</label>
                <input class="input" id="category" name="category" value="<?= $v('category') ?>" placeholder="Ej: Disciplina, Inscripciones" list="rule-categories">
            </div>
            <div class="field">
                <label for="sort_order">Orden</label>
                <input class="input" type="number" id="sort_order" name="sort_order" value="<?= e($rule['sort_order'] ?? 0) ?>">
                <div class="help">Menor número aparece primero dentro de la categoría.</div>
            </div>
        </div>
        <div class="field">
            <label for="title">Título *</label>
            <input class="input" id="title" name="title" required value="<?= $v('title') ?>">
        </div>
        <div class="field">
            <label for="body">Contenido</label>
            <textarea class="textarea" id="body" name="body" rows="10"><?= $v('body') ?></textarea>
        </div>
        <div class="field">
            <label for="tournament_id">Torneo (opcional)</label>
            <select class="select" id="tournament_id" name="tournament_id">
                <?= options($tournamentOptions, $rule['tournament_id'] ?? null, 'Todos los torneos') ?>
            </select>
        </div>
        <div class="page-actions mt-3">
            <button class="btn" type="submit">Guardar regla</button>
            <a class="btn btn-ghost" href="<?= e(base_url('admin/rules.php')) ?>">Cancelar</a>
        </div>
    </form>
    <?php
    require 'partials/foot.php';
    exit;
}

/* ---- List view ---------------------------------------------------------- */
$rules = Database::all("SELECT r.*, t.name AS tournament_name
    FROM rules r
    LEFT JOIN tournaments t ON t.id = r.tournament_id
    ORDER BY r.category, r.sort_order, r.title");
require 'partials/head.php';
?>
<div class="page-head flex justify-between items-center wrap">
    <div>
        <h1>Reglamento</h1>
        <p>Reglas agrupadas por categoría. Ajusta el orden editando el campo de orden.</p>
    </div>
    <div class="page-actions"><a class="btn" href="<?= e(base_url('admin/rules.php?action=new')) ?>">+ Nueva regla</a></div>
</div>

<?php if (!$rules): ?>
    <div class="empty-state card">
        <div class="es-icon">📋</div>
        <h2>No hay reglas todavía</h2>
        <p>Crea la primera regla del reglamento.</p>
        <a class="btn" href="<?= e(base_url('admin/rules.php?action=new')) ?>">+ Nueva regla</a>
    </div>
<?php else: ?>
    <?php
    $grouped = [];
    foreach ($rules as $r) {
        $cat = $r['category'] !== '' && $r['category'] !== null ? $r['category'] : 'Sin categoría';
        $grouped[$cat][] = $r;
    }
    foreach ($grouped as $cat => $catRules): ?>
        <h3 class="mt-3"><?= e($cat) ?></h3>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th class="num">Orden</th><th>Título</th><th>Alcance</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($catRules as $r): ?>
                    <tr>
                        <td class="num"><?= (int)$r['sort_order'] ?></td>
                        <td><strong><?= e($r['title']) ?></strong></td>
                        <td>
                            <?php if ($r['tournament_name']): ?>
                                <span class="badge badge-muted"><?= e($r['tournament_name']) ?></span>
                            <?php else: ?>
                                <span class="muted">General</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex gap-1 wrap">
                                <a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/rules.php?action=edit&id=' . $r['id'])) ?>">Editar</a>
                                <form method="post" action="<?= e(base_url('admin/rules.php?action=delete&id=' . $r['id'])) ?>" data-confirm="¿Eliminar esta regla?">
                                    <?= Security::csrfField() ?><button class="btn btn-sm btn-danger" type="submit">×</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php require 'partials/foot.php'; ?>
