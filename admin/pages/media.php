<?php
/** Media library: upload, list, delete, and JSON feed for the picker. */
if (!defined('BASE_PATH')) { exit; }

// AJAX list for the media picker modal.
if (($_GET['ajax'] ?? '') === 'list') {
    header('Content-Type: application/json; charset=utf-8');
    $out = array_map(function ($m) {
        return [
            'id' => (int)$m['id'],
            'path' => $m['path'],
            'url' => asset_url($m['path']),
            'name' => $m['original_name'] ?: $m['filename'],
            'size' => human_size((int)$m['size']),
            'dim' => ($m['width'] && $m['height']) ? ($m['width'].'×'.$m['height']) : '',
        ];
    }, Media::all());
    echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Csrf::verifyPost();
    $do = post('do');
    if ($do === 'upload' && !empty($_FILES['file']['name'])) {
        try {
            Media::upload($_FILES['file'], post('alt'));
            Auth::log('media_upload', 'Subió imagen');
            flash('success', 'Imagen subida correctamente.');
        } catch (Throwable $ex) {
            flash('error', $ex->getMessage());
        }
    } elseif ($do === 'delete') {
        Media::delete((int)post('id'));
        Auth::log('media_delete', 'Eliminó imagen #' . (int)post('id'));
        flash('success', 'Imagen eliminada.');
    }
    redirect('admin/index.php?page=media');
}

$items = Media::all();
admin_header('Biblioteca multimedia');
?>
<div class="card">
  <h2>Subir imagen</h2>
  <form method="post" enctype="multipart/form-data" class="form upload-form">
    <?= Csrf::field() ?>
    <input type="hidden" name="do" value="upload">
    <div class="grid-2">
      <div class="form-group"><label>Archivo (JPG, PNG, WEBP, GIF, SVG · máx 6 MB)</label>
        <input type="file" name="file" accept=".jpg,.jpeg,.png,.webp,.gif,.svg" required></div>
      <div class="form-group"><label>Texto alternativo (alt)</label><input type="text" name="alt" placeholder="Descripción de la imagen"></div>
    </div>
    <button class="btn btn--primary">Subir</button>
  </form>
</div>

<div class="card">
  <h2>Imágenes (<?= count($items) ?>)</h2>
  <div class="media-grid">
    <?php foreach ($items as $m): ?>
      <div class="media-item">
        <div class="media-item__thumb"><img src="<?= e(asset_url($m['path'])) ?>" alt="<?= e($m['alt']) ?>" loading="lazy"></div>
        <div class="media-item__meta">
          <span class="break" title="<?= e($m['original_name']) ?>"><?= e($m['original_name'] ?: $m['filename']) ?></span>
          <span class="muted"><?= human_size((int)$m['size']) ?><?= ($m['width']?' · '.$m['width'].'×'.$m['height']:'') ?></span>
        </div>
        <div class="media-item__actions">
          <button type="button" class="btn btn--xs btn--outline" onclick="navigator.clipboard&&navigator.clipboard.writeText('<?= e($m['path']) ?>');this.textContent='Copiado'">Copiar ruta</button>
          <form method="post" onsubmit="return confirm('¿Eliminar esta imagen? Esta acción no se puede deshacer.')">
            <?= Csrf::field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn--xs btn--danger">Eliminar</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (!$items): ?><p class="muted">Aún no has subido imágenes.</p><?php endif; ?>
</div>
<?php admin_footer(); ?>
