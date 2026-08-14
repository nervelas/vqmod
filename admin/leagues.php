<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('leagues.manage');

$action = str_input('action', 'list');
$id = int_input('id');

/* ---- Handle POST (create / update) ------------------------------------- */
if (is_post()) {
    Security::requireCsrf();
    $name = str_input('name');
    if ($name === '') {
        flash('danger', 'El nombre de la liga es obligatorio.');
        redirect(base_url('admin/leagues.php?action=' . ($id ? 'edit&id=' . $id : 'new')));
    }
    $slug = slugify(str_input('slug') ?: $name);
    // Ensure slug uniqueness.
    $exists = Database::scalar("SELECT id FROM leagues WHERE slug = ? AND id <> ?", [$slug, $id ?? 0]);
    if ($exists) { $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4); }

    $data = [
        'name'              => $name,
        'slug'              => $slug,
        'description'       => str_input('description'),
        'info'              => (string)post('info', ''),
        'location'          => str_input('location'),
        'theme_id'          => int_input('theme_id') ?: null,
        'status'            => in_array(str_input('status'), ['active','archived'], true) ? str_input('status') : 'active',
        'visibility'        => in_array(str_input('visibility'), ['public','private'], true) ? str_input('visibility') : 'public',
        'banner_position'   => in_array(str_input('banner_position'), ['top','center','bottom'], true) ? str_input('banner_position') : 'center',
        'banner_overlay'    => preg_match('/^#[0-9A-Fa-f]{6}$/', str_input('banner_overlay')) ? str_input('banner_overlay') : '#000000',
        'overlay_intensity' => max(0, min(90, (int)int_input('overlay_intensity', 55))),
        'seo_title'         => str_input('seo_title'),
        'seo_description'   => str_input('seo_description'),
    ];

    try {
        // Uploads
        $logo   = Upload::image('logo', 'leagues');
        $banner = Upload::image('banner', 'leagues');
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
        redirect(base_url('admin/leagues.php?action=' . ($id ? 'edit&id=' . $id : 'new')));
    }

    if ($id) {
        $before = Database::one("SELECT * FROM leagues WHERE id = ?", [$id]);
        // Remove images if requested
        if (post('remove_logo') && $before['logo'])   { Upload::delete($before['logo']); $data['logo'] = null; }
        if (post('remove_banner') && $before['banner']){ Upload::delete($before['banner']); $data['banner'] = null; }
        if ($logo)   { if ($before['logo'])   Upload::delete($before['logo']);   $data['logo'] = $logo; }
        if ($banner) { if ($before['banner']) Upload::delete($before['banner']); $data['banner'] = $banner; }

        $sets = []; $params = [];
        foreach ($data as $k => $v) { $sets[] = "$k = ?"; $params[] = $v; }
        $params[] = $id;
        Database::q("UPDATE leagues SET " . implode(',', $sets) . " WHERE id = ?", $params);
        Audit::log('update', 'leagues', $id, $before, $data);
        flash('success', 'Liga actualizada correctamente.');
    } else {
        $data['logo']   = $logo;
        $data['banner'] = $banner;
        $cols = implode(',', array_keys($data));
        $ph   = implode(',', array_fill(0, count($data), '?'));
        $newId = Database::insert("INSERT INTO leagues ($cols) VALUES ($ph)", array_values($data));
        Audit::log('create', 'leagues', $newId, null, $data);
        flash('success', 'Liga creada. Ahora puedes crear un torneo y agregar equipos.');
    }
    redirect(base_url('admin/leagues.php'));
}

/* ---- Delete ------------------------------------------------------------- */
if ($action === 'delete' && $id) {
    Security::requireCsrf();
    $before = Database::one("SELECT * FROM leagues WHERE id = ?", [$id]);
    if ($before) {
        Upload::delete($before['logo']);
        Upload::delete($before['banner']);
        Database::q("DELETE FROM leagues WHERE id = ?", [$id]);
        Audit::log('delete', 'leagues', $id, $before, null);
        flash('success', 'Liga eliminada.');
    }
    redirect(base_url('admin/leagues.php'));
}

$themes = Theme::all();
$themeOptions = [];
foreach ($themes as $t) { $themeOptions[$t['id']] = $t['name']; }

$PAGE_TITLE = 'Ligas';
$ACTIVE = 'leagues';

/* ---- Form view (new/edit) ---------------------------------------------- */
if ($action === 'new' || $action === 'edit') {
    $league = $action === 'edit' && $id ? Database::one("SELECT * FROM leagues WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$league) { redirect(base_url('admin/leagues.php')); }
    $v = fn($k, $d = '') => e($league[$k] ?? $d);
    require 'partials/head.php';
    ?>
    <div class="page-head">
        <h1><?= $action === 'edit' ? 'Editar liga' : 'Nueva liga' ?></h1>
        <p>Configura la identidad visual y los datos de la liga.</p>
    </div>
    <form method="post" enctype="multipart/form-data" class="card card-pad-lg">
        <?= Security::csrfField() ?>
        <div class="form-row">
            <div class="field">
                <label for="name">Nombre de la liga *</label>
                <input class="input" id="name" name="name" required value="<?= $v('name') ?>" data-slug-source="#slug">
            </div>
            <div class="field">
                <label for="slug">Slug (URL)</label>
                <input class="input" id="slug" name="slug" value="<?= $v('slug') ?>" placeholder="se-genera-automaticamente">
            </div>
        </div>
        <div class="field">
            <label for="description">Descripción corta</label>
            <input class="input" id="description" name="description" value="<?= $v('description') ?>">
        </div>
        <div class="field">
            <label for="info">Información institucional</label>
            <textarea class="textarea" id="info" name="info"><?= $v('info') ?></textarea>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="location">Ubicación (opcional)</label>
                <input class="input" id="location" name="location" value="<?= $v('location') ?>">
            </div>
            <div class="field">
                <label for="theme_id">Tema visual</label>
                <select class="select" id="theme_id" name="theme_id">
                    <?= options($themeOptions, $league['theme_id'] ?? Settings::get('default_theme_id'), 'Tema por defecto') ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="status">Estado</label>
                <select class="select" id="status" name="status">
                    <option value="active"<?= selected('active', $league['status'] ?? 'active') ?>>Activa</option>
                    <option value="archived"<?= selected('archived', $league['status'] ?? '') ?>>Archivada</option>
                </select>
            </div>
            <div class="field">
                <label for="visibility">Visibilidad</label>
                <select class="select" id="visibility" name="visibility">
                    <option value="public"<?= selected('public', $league['visibility'] ?? 'public') ?>>Pública</option>
                    <option value="private"<?= selected('private', $league['visibility'] ?? '') ?>>Privada</option>
                </select>
            </div>
        </div>

        <h3 class="mt-3">Imágenes</h3>
        <div class="form-row">
            <div class="field">
                <label for="logo">Escudo / Logo (JPG, PNG, WEBP)</label>
                <input class="input" type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.webp">
                <?php if (!empty($league['logo'])): ?>
                    <div class="mt-1 flex items-center gap-1">
                        <?= media_thumb($league['logo'], $league['name'], 'team-logo avatar-lg') ?>
                        <label class="help"><input type="checkbox" name="remove_logo" value="1"> Eliminar</label>
                    </div>
                <?php endif; ?>
            </div>
            <div class="field">
                <label for="banner">Banner de la liga</label>
                <input class="input" type="file" id="banner" name="banner" accept=".jpg,.jpeg,.png,.webp">
                <div class="help">Se usa en el hero y la cabecera. Se ajusta con object-fit: cover.</div>
                <?php if (!empty($league['banner'])): ?>
                    <div class="mt-1"><img src="<?= e(base_url($league['banner'])) ?>" alt="" style="max-height:90px;border-radius:10px">
                    <label class="help"><input type="checkbox" name="remove_banner" value="1"> Eliminar banner</label></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="banner_position">Posición del banner</label>
                <select class="select" id="banner_position" name="banner_position">
                    <option value="top"<?= selected('top', $league['banner_position'] ?? '') ?>>Superior</option>
                    <option value="center"<?= selected('center', $league['banner_position'] ?? 'center') ?>>Centro</option>
                    <option value="bottom"<?= selected('bottom', $league['banner_position'] ?? '') ?>>Inferior</option>
                </select>
            </div>
            <div class="field">
                <label for="banner_overlay">Color de overlay</label>
                <input class="input" type="color" id="banner_overlay" name="banner_overlay" value="<?= $v('banner_overlay', '#000000') ?>">
            </div>
            <div class="field">
                <label for="overlay_intensity">Intensidad overlay (<?= (int)($league['overlay_intensity'] ?? 55) ?>%)</label>
                <input class="input" type="range" min="0" max="90" id="overlay_intensity" name="overlay_intensity" value="<?= (int)($league['overlay_intensity'] ?? 55) ?>">
            </div>
        </div>

        <h3 class="mt-3">SEO</h3>
        <div class="field">
            <label for="seo_title">Título SEO</label>
            <input class="input" id="seo_title" name="seo_title" value="<?= $v('seo_title') ?>">
        </div>
        <div class="field">
            <label for="seo_description">Descripción SEO</label>
            <input class="input" id="seo_description" name="seo_description" value="<?= $v('seo_description') ?>">
        </div>

        <div class="page-actions mt-3">
            <button class="btn" type="submit">Guardar liga</button>
            <a class="btn btn-ghost" href="<?= e(base_url('admin/leagues.php')) ?>">Cancelar</a>
        </div>
    </form>
    <?php
    require 'partials/foot.php';
    exit;
}

/* ---- List view ---------------------------------------------------------- */
$leagues = Database::all("SELECT l.*, t.name AS theme_name,
    (SELECT COUNT(*) FROM tournaments WHERE league_id = l.id) AS tournaments,
    (SELECT COUNT(*) FROM teams WHERE league_id = l.id) AS teams
    FROM leagues l LEFT JOIN themes t ON t.id = l.theme_id ORDER BY l.created_at DESC");
require 'partials/head.php';
?>
<div class="page-head flex justify-between items-center wrap">
    <div>
        <h1>Ligas</h1>
        <p>Gestiona todas las ligas de la plataforma. Cada liga es independiente.</p>
    </div>
    <div class="page-actions"><a class="btn" href="<?= e(base_url('admin/leagues.php?action=new')) ?>">+ Nueva liga</a></div>
</div>

<?php if (!$leagues): ?>
    <div class="empty-state card">
        <div class="es-icon">🏆</div>
        <h2>No hay ligas todavía</h2>
        <p>Crea tu primera liga para comenzar.</p>
        <a class="btn" href="<?= e(base_url('admin/leagues.php?action=new')) ?>">+ Crear Liga</a>
    </div>
<?php else: ?>
    <div class="league-grid">
        <?php foreach ($leagues as $l): ?>
            <div class="card league-card card-hover reveal">
                <div class="lc-banner">
                    <?php if ($l['banner']): ?><img src="<?= e(base_url($l['banner'])) ?>" alt=""><?php endif; ?>
                </div>
                <div class="lc-body">
                    <?= media_thumb($l['logo'], $l['name'], 'lc-logo') ?>
                    <div style="flex:1;min-width:0">
                        <h4 style="margin:0"><?= e($l['name']) ?></h4>
                        <div class="muted" style="font-size:.82rem">
                            <?= (int)$l['tournaments'] ?> torneos · <?= (int)$l['teams'] ?> equipos
                        </div>
                        <div class="mt-1 flex gap-1 wrap">
                            <span class="badge <?= $l['status']==='active'?'badge-success':'badge-muted' ?>"><?= $l['status']==='active'?'Activa':'Archivada' ?></span>
                            <?php if ($l['theme_name']): ?><span class="badge"><?= e($l['theme_name']) ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="lc-body" style="padding-top:0">
                    <div class="page-actions" style="width:100%">
                        <a class="btn btn-sm" href="<?= e(base_url('admin/leagues.php?action=edit&id=' . $l['id'])) ?>">Editar</a>
                        <a class="btn btn-sm btn-ghost" href="<?= e(base_url('liga.php?slug=' . urlencode($l['slug']))) ?>" target="_blank">Ver</a>
                        <form method="post" action="<?= e(base_url('admin/leagues.php?action=delete&id=' . $l['id'])) ?>" data-confirm="¿Eliminar esta liga y TODO su contenido? Esta acción no se puede deshacer." style="margin-left:auto">
                            <?= Security::csrfField() ?>
                            <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require 'partials/foot.php'; ?>
