<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('content.manage');

$action = str_input('action', 'list');
$id = int_input('id');

$leagues = Database::all("SELECT id, name FROM leagues ORDER BY name");
$leagueOptions = [];
foreach ($leagues as $l) { $leagueOptions[$l['id']] = $l['name']; }

/* ---- Handle POST (create / update) ------------------------------------- */
if (is_post()) {
    Security::requireCsrf();
    $title = str_input('title');
    if ($title === '') {
        flash('danger', 'El título de la noticia es obligatorio.');
        redirect(base_url('admin/news.php?action=' . ($id ? 'edit&id=' . $id : 'new')));
    }
    $slug = slugify(str_input('slug') ?: $title);
    $exists = Database::scalar("SELECT id FROM news WHERE slug = ? AND id <> ?", [$slug, $id ?? 0]);
    if ($exists) { $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4); }

    $status = in_array(str_input('status'), ['draft','published'], true) ? str_input('status') : 'draft';
    $publishedAt = str_input('published_at');
    if ($publishedAt !== '') {
        $publishedAt = str_replace('T', ' ', $publishedAt);
        $ts = strtotime($publishedAt);
        $publishedAt = $ts ? date('Y-m-d H:i:s', $ts) : null;
    } else {
        $publishedAt = null;
    }
    if ($status === 'published' && !$publishedAt) {
        $publishedAt = date('Y-m-d H:i:s');
    }

    $data = [
        'league_id'    => int_input('league_id') ?: null,
        'title'        => $title,
        'slug'         => $slug,
        'excerpt'      => str_input('excerpt'),
        'body'         => (string)post('body', ''),
        'status'       => $status,
        'published_at' => $publishedAt,
    ];

    try {
        $image = Upload::image('image', 'news');
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
        redirect(base_url('admin/news.php?action=' . ($id ? 'edit&id=' . $id : 'new')));
    }

    if ($id) {
        $before = Database::one("SELECT * FROM news WHERE id = ?", [$id]);
        if (!$before) { redirect(base_url('admin/news.php')); }
        $data['image'] = $before['image'];
        if (post('remove_image') && $before['image']) { Upload::delete($before['image']); $data['image'] = null; }
        if ($image) { if ($before['image']) Upload::delete($before['image']); $data['image'] = $image; }

        $sets = []; $params = [];
        foreach ($data as $k => $v) { $sets[] = "$k = ?"; $params[] = $v; }
        $params[] = $id;
        Database::q("UPDATE news SET " . implode(',', $sets) . " WHERE id = ?", $params);
        Audit::log('update', 'news', $id, $before, $data);
        flash('success', 'Noticia actualizada correctamente.');
    } else {
        $data['image'] = $image;
        $cols = implode(',', array_keys($data));
        $ph   = implode(',', array_fill(0, count($data), '?'));
        $newId = Database::insert("INSERT INTO news ($cols) VALUES ($ph)", array_values($data));
        Audit::log('create', 'news', $newId, null, $data);
        flash('success', 'Noticia creada.');
    }
    redirect(base_url('admin/news.php'));
}

/* ---- Delete ------------------------------------------------------------- */
if ($action === 'delete' && $id) {
    Security::requireCsrf();
    $before = Database::one("SELECT * FROM news WHERE id = ?", [$id]);
    if ($before) {
        Upload::delete($before['image']);
        Database::q("DELETE FROM news WHERE id = ?", [$id]);
        Audit::log('delete', 'news', $id, $before, null);
        flash('success', 'Noticia eliminada.');
    }
    redirect(base_url('admin/news.php'));
}

$PAGE_TITLE = 'Noticias';
$ACTIVE = 'news';

/* ---- Form view (new/edit) ---------------------------------------------- */
if ($action === 'new' || $action === 'edit') {
    $news = $action === 'edit' && $id ? Database::one("SELECT * FROM news WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$news) { redirect(base_url('admin/news.php')); }
    $v = fn($k, $d = '') => e($news[$k] ?? $d);
    $publishedInput = !empty($news['published_at']) ? date('Y-m-d\TH:i', strtotime($news['published_at'])) : '';
    require 'partials/head.php';
    ?>
    <div class="page-head">
        <h1><?= $action === 'edit' ? 'Editar noticia' : 'Nueva noticia' ?></h1>
        <p>Publica noticias globales o asociadas a una liga.</p>
    </div>
    <form method="post" enctype="multipart/form-data" class="card card-pad-lg">
        <?= Security::csrfField() ?>
        <div class="form-row">
            <div class="field">
                <label for="title">Título *</label>
                <input class="input" id="title" name="title" required value="<?= $v('title') ?>" data-slug-source="#slug">
            </div>
            <div class="field">
                <label for="slug">Slug (URL)</label>
                <input class="input" id="slug" name="slug" value="<?= $v('slug') ?>" placeholder="se-genera-automaticamente">
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="league_id">Liga</label>
                <select class="select" id="league_id" name="league_id">
                    <?= options($leagueOptions, $news['league_id'] ?? null, 'Global / todas las ligas') ?>
                </select>
            </div>
            <div class="field">
                <label for="status">Estado</label>
                <select class="select" id="status" name="status">
                    <option value="draft"<?= selected('draft', $news['status'] ?? 'draft') ?>>Borrador</option>
                    <option value="published"<?= selected('published', $news['status'] ?? '') ?>>Publicada</option>
                </select>
            </div>
        </div>
        <div class="field">
            <label for="excerpt">Extracto</label>
            <input class="input" id="excerpt" name="excerpt" maxlength="300" value="<?= $v('excerpt') ?>" placeholder="Resumen breve para listados">
        </div>
        <div class="field">
            <label for="body">Contenido</label>
            <textarea class="textarea" id="body" name="body" rows="10"><?= $v('body') ?></textarea>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="published_at">Fecha de publicación</label>
                <input class="input" type="datetime-local" id="published_at" name="published_at" value="<?= e($publishedInput) ?>">
                <div class="help">Si publicas sin fecha, se usará la fecha y hora actuales.</div>
            </div>
            <div class="field">
                <label for="image">Imagen destacada (JPG, PNG, WEBP)</label>
                <input class="input" type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp">
                <?php if (!empty($news['image'])): ?>
                    <div class="mt-1 flex items-center gap-1">
                        <img src="<?= e(base_url($news['image'])) ?>" alt="" style="max-height:70px;border-radius:10px">
                        <label class="help"><input type="checkbox" name="remove_image" value="1"> Eliminar</label>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="page-actions mt-3">
            <button class="btn" type="submit">Guardar noticia</button>
            <a class="btn btn-ghost" href="<?= e(base_url('admin/news.php')) ?>">Cancelar</a>
        </div>
    </form>
    <?php
    require 'partials/foot.php';
    exit;
}

/* ---- List view ---------------------------------------------------------- */
$items = Database::all("SELECT n.*, l.name AS league_name
    FROM news n LEFT JOIN leagues l ON l.id = n.league_id
    ORDER BY COALESCE(n.published_at, n.created_at) DESC, n.id DESC");
require 'partials/head.php';
?>
<div class="page-head flex justify-between items-center wrap">
    <div>
        <h1>Noticias</h1>
        <p>Gestiona las noticias del sitio. Pueden ser globales o de una liga específica.</p>
    </div>
    <div class="page-actions"><a class="btn" href="<?= e(base_url('admin/news.php?action=new')) ?>">+ Nueva noticia</a></div>
</div>

<?php if (!$items): ?>
    <div class="empty-state card">
        <div class="es-icon">📰</div>
        <h2>No hay noticias todavía</h2>
        <p>Crea tu primera noticia para el sitio público.</p>
        <a class="btn" href="<?= e(base_url('admin/news.php?action=new')) ?>">+ Nueva noticia</a>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th></th><th>Título</th><th>Liga</th><th>Estado</th><th>Publicación</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $n): ?>
                <tr>
                    <td><?= media_thumb($n['image'], $n['title'], 'team-logo') ?></td>
                    <td><strong><?= e($n['title']) ?></strong></td>
                    <td><?= $n['league_name'] ? e($n['league_name']) : '<span class="muted">Global</span>' ?></td>
                    <td><span class="badge <?= $n['status']==='published'?'badge-success':'badge-muted' ?>"><?= $n['status']==='published'?'Publicada':'Borrador' ?></span></td>
                    <td><?= e(fmt_date($n['published_at'], 'd/m/Y H:i')) ?></td>
                    <td>
                        <div class="flex gap-1 wrap">
                            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/news.php?action=edit&id=' . $n['id'])) ?>">Editar</a>
                            <form method="post" action="<?= e(base_url('admin/news.php?action=delete&id=' . $n['id'])) ?>" data-confirm="¿Eliminar esta noticia?">
                                <?= Security::csrfField() ?><button class="btn btn-sm btn-danger" type="submit">×</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require 'partials/foot.php'; ?>
