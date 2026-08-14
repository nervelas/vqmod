<?php
/** Pages manager: list pages, edit page meta + SEO, list its sections. */
if (!defined('BASE_PATH')) { exit; }

$editId = (int)($_GET['edit'] ?? 0);

// Save page meta
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('do') === 'save_page') {
    Csrf::verifyPost();
    $id = (int)post('id');
    $data = [
        'title'          => post('title'),
        'h1'             => post('h1'),
        'intro'          => post('intro'),
        'is_active'      => isset($_POST['is_active']) ? 1 : 0,
        'show_in_menu'   => isset($_POST['show_in_menu']) ? 1 : 0,
        'seo_title'      => post('seo_title'),
        'seo_description'=> post('seo_description'),
        'seo_canonical'  => post('seo_canonical'),
        'og_title'       => post('og_title'),
        'og_description' => post('og_description'),
        'og_image'       => post('og_image'),
        'updated_at'     => date('Y-m-d H:i:s'),
    ];
    Database::update('pages', $data, ['id' => $id]);
    Auth::log('page_update', 'Editó la página #' . $id);
    flash('success', 'Página actualizada correctamente.');
    redirect('admin/index.php?page=pages&edit=' . $id);
}

// Toggle section active
if (($_GET['toggle_section'] ?? '') !== '') {
    $sid = (int)$_GET['toggle_section'];
    $cur = Database::scalar('SELECT is_active FROM sections WHERE id=?', [$sid]);
    Database::update('sections', ['is_active' => $cur ? 0 : 1], ['id' => $sid]);
    flash('success', 'Sección ' . ($cur ? 'desactivada' : 'activada') . '.');
    redirect('admin/index.php?page=pages&edit=' . (int)($_GET['edit'] ?? 0));
}

admin_header('Páginas y secciones');

if ($editId) {
    $pg = Content::pageById($editId);
    if (!$pg) { echo '<div class="notice notice--error">Página no encontrada.</div>'; admin_footer(); return; }
    $secs = Content::sections($editId, false);
    ?>
    <a class="back" href="<?= e(admin_url('pages')) ?>">← Todas las páginas</a>
    <div class="card">
      <div class="card__head">
        <h2>Editar: <?= e($pg['title']) ?></h2>
        <a class="btn btn--sm btn--outline" href="<?= e($pg['slug']==='inicio'?base_url():base_url($pg['slug'])) ?>" target="_blank">Ver página ↗</a>
      </div>
      <form method="post" class="form">
        <?= Csrf::field() ?>
        <input type="hidden" name="do" value="save_page">
        <input type="hidden" name="id" value="<?= (int)$pg['id'] ?>">
        <div class="grid-2">
          <div class="form-group"><label>Título</label><input type="text" name="title" value="<?= e($pg['title']) ?>" required></div>
          <div class="form-group"><label>Slug (URL)</label><input type="text" value="<?= e($pg['slug']) ?>" disabled></div>
        </div>
        <div class="form-group"><label>Encabezado principal (H1)</label><input type="text" name="h1" value="<?= e($pg['h1']) ?>"></div>
        <div class="form-group"><label>Introducción</label><textarea name="intro" rows="2"><?= e($pg['intro']) ?></textarea></div>
        <div class="switches">
          <label class="switch"><input type="checkbox" name="is_active" <?= $pg['is_active']?'checked':'' ?>> Página activa</label>
          <label class="switch"><input type="checkbox" name="show_in_menu" <?= $pg['show_in_menu']?'checked':'' ?>> Mostrar en menú</label>
        </div>
        <details class="seo-box">
          <summary>SEO de esta página</summary>
          <div class="form-group"><label>Meta title</label><input type="text" name="seo_title" value="<?= e($pg['seo_title']) ?>" maxlength="200"></div>
          <div class="form-group"><label>Meta description</label><textarea name="seo_description" rows="2" maxlength="300"><?= e($pg['seo_description']) ?></textarea></div>
          <div class="form-group"><label>Canonical URL</label><input type="text" name="seo_canonical" value="<?= e($pg['seo_canonical']) ?>"></div>
          <div class="grid-2">
            <div class="form-group"><label>Open Graph title</label><input type="text" name="og_title" value="<?= e($pg['og_title']) ?>"></div>
            <div class="form-group"><label>Open Graph description</label><input type="text" name="og_description" value="<?= e($pg['og_description']) ?>"></div>
          </div>
          <?= media_field('og_image', $pg['og_image'] ?? '', 'Open Graph image') ?>
        </details>
        <button class="btn btn--primary">Guardar página</button>
      </form>
    </div>

    <div class="card">
      <div class="card__head"><h2>Secciones de la página</h2></div>
      <p class="muted">Edita el contenido de cada bloque. Los cambios se reflejan de inmediato en la página pública.</p>
      <table class="table table--sections">
        <thead><tr><th>Orden</th><th>Sección</th><th>Tipo</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($secs as $s): ?>
          <tr>
            <td><?= (int)$s['sort'] ?></td>
            <td><strong><?= e($s['title'] ?: $s['block_key']) ?></strong><br><span class="muted"><?= e($s['block_key']) ?></span></td>
            <td><span class="tag"><?= e($s['type']) ?></span></td>
            <td>
              <a href="<?= e(admin_url('pages',['edit'=>$editId,'toggle_section'=>$s['id']])) ?>" class="badge <?= $s['is_active']?'badge--on':'badge--off' ?>">
                <?= $s['is_active']?'Activa':'Inactiva' ?>
              </a>
            </td>
            <td><a class="btn btn--sm btn--outline" href="<?= e(admin_url('section',['id'=>$s['id']])) ?>">Editar</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (!$secs): ?><p class="muted">Esta página no tiene secciones editables.</p><?php endif; ?>
    </div>
    <?php
} else {
    $pages = Database::all('SELECT * FROM pages ORDER BY sort ASC, id ASC');
    ?>
    <div class="card">
      <h2>Todas las páginas</h2>
      <table class="table">
        <thead><tr><th>Página</th><th>URL</th><th>Secciones</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pages as $p):
          $sc = Database::scalar('SELECT COUNT(*) FROM sections WHERE page_id=?', [$p['id']]); ?>
          <tr>
            <td><strong><?= e($p['title']) ?></strong></td>
            <td class="muted">/<?= e($p['slug']==='inicio'?'':$p['slug']) ?></td>
            <td><?= (int)$sc ?></td>
            <td><span class="badge <?= $p['is_active']?'badge--on':'badge--off' ?>"><?= $p['is_active']?'Activa':'Inactiva' ?></span></td>
            <td><a class="btn btn--sm btn--primary" href="<?= e(admin_url('pages',['edit'=>$p['id']])) ?>">Editar</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}
admin_footer();
