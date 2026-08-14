<?php
/** Edit a single content section (title/text/image/button/etc.). */
if (!defined('BASE_PATH')) { exit; }

$id = (int)($_GET['id'] ?? 0);
$sec = Database::first('SELECT * FROM sections WHERE id=?', [$id]);
if (!$sec) { admin_header('Sección'); echo '<div class="notice notice--error">Sección no encontrada.</div>'; admin_footer(); return; }
$pg = Content::pageById((int)$sec['page_id']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Csrf::verifyPost();
    $data = [
        'title'        => post('title'),
        'subtitle'     => post('subtitle'),
        'body'         => post('body'),
        'image'        => post('image'),
        'background'   => post('background'),
        'icon'         => post('icon'),
        'button_text'  => post('button_text'),
        'button_url'   => post('button_url'),
        'button_target'=> post('button_target') === '_blank' ? '_blank' : '_self',
        'sort'         => (int)post('sort'),
        'is_active'    => isset($_POST['is_active']) ? 1 : 0,
    ];
    Database::update('sections', $data, ['id' => $id]);
    Auth::log('section_update', 'Editó sección "' . $sec['block_key'] . '" (#' . $id . ')');
    flash('success', 'Sección guardada. Los cambios ya están publicados.');
    redirect('admin/index.php?page=section&id=' . $id);
}

admin_header('Editar sección');
?>
<a class="back" href="<?= e(admin_url('pages',['edit'=>$sec['page_id']])) ?>">← Volver a <?= e($pg['title'] ?? 'la página') ?></a>
<div class="card">
  <div class="card__head">
    <h2><?= e($sec['title'] ?: $sec['block_key']) ?> <span class="tag"><?= e($sec['type']) ?></span></h2>
    <a class="btn btn--sm btn--outline" href="<?= e($pg && $pg['slug']==='inicio'?base_url():base_url($pg['slug'] ?? '')) ?>" target="_blank">Ver en el sitio ↗</a>
  </div>
  <form method="post" class="form">
    <?= Csrf::field() ?>
    <div class="grid-2">
      <div class="form-group"><label>Título</label><input type="text" name="title" value="<?= e($sec['title']) ?>"></div>
      <div class="form-group"><label>Subtítulo / etiqueta</label><input type="text" name="subtitle" value="<?= e($sec['subtitle']) ?>"></div>
    </div>
    <div class="form-group"><label>Texto / párrafo</label><textarea name="body" rows="5"><?= e($sec['body']) ?></textarea></div>

    <?= media_field('image', $sec['image'] ?? '', 'Imagen') ?>
    <?php if (in_array($sec['type'], ['hero','cta'], true)): ?>
      <?= media_field('background', $sec['background'] ?? '', 'Imagen de fondo') ?>
    <?php endif; ?>

    <div class="grid-2">
      <div class="form-group"><label>Ícono <span class="muted">(nombre)</span></label>
        <input type="text" name="icon" value="<?= e($sec['icon']) ?>" list="iconlist" placeholder="ej. book, target, heart">
        <datalist id="iconlist"><?php foreach (['portal','card','admissions','briefcase','bus','image','radio','child','book','graduation','target','eye','heart','star','chat','clock'] as $ic): ?><option value="<?= $ic ?>"><?php endforeach; ?></datalist>
      </div>
      <div class="form-group"><label>Orden</label><input type="number" name="sort" value="<?= (int)$sec['sort'] ?>"></div>
    </div>

    <fieldset class="fieldset">
      <legend>Botón</legend>
      <div class="grid-3">
        <div class="form-group"><label>Texto del botón</label><input type="text" name="button_text" value="<?= e($sec['button_text']) ?>"></div>
        <div class="form-group"><label>Enlace (slug o URL)</label><input type="text" name="button_url" value="<?= e($sec['button_url']) ?>"></div>
        <div class="form-group"><label>Abrir en</label>
          <select name="button_target">
            <option value="_self" <?= $sec['button_target']==='_self'?'selected':'' ?>>Misma pestaña</option>
            <option value="_blank" <?= $sec['button_target']==='_blank'?'selected':'' ?>>Pestaña nueva</option>
          </select>
        </div>
      </div>
    </fieldset>

    <label class="switch"><input type="checkbox" name="is_active" <?= $sec['is_active']?'checked':'' ?>> Sección visible</label>
    <div class="form-actions"><button class="btn btn--primary btn--lg">Guardar cambios</button></div>
  </form>
</div>
<?php admin_footer(); ?>
