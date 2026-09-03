<?php
declare(strict_types=1);
/** Biblioteca de imágenes: subida, texto alternativo y eliminación. */

if (is_post()) {
    Csrf::verify();
    $op = post('op');

    if ($op === 'upload') {
        $files = $_FILES['archivos'] ?? null;
        $ok = 0; $fail = 0;
        if (is_array($files) && isset($files['name']) && is_array($files['name'])) {
            $n = count($files['name']);
            for ($i = 0; $i < $n; $i++) {
                if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { continue; }
                $one = [
                    'name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i], 'size' => $files['size'][$i],
                ];
                $res = Media::upload($one, post('alt'));
                if ($res['ok']) { $ok++; } else { $fail++; flash($res['error'] ?? 'Error al subir.', 'error'); }
            }
        }
        if ($ok > 0) { flash($ok . ' imagen(es) subida(s) correctamente.'); }
        elseif ($fail === 0) { flash('No seleccionó ningún archivo.', 'error'); }
        redirect('admin/index.php?p=media');
    }

    if ($op === 'alt') {
        $id = (int) post('id');
        Database::update('media', ['alt' => mb_substr(post('alt'), 0, 200)], 'id = :id', ['id' => $id]);
        flash('Texto alternativo actualizado.');
        redirect('admin/index.php?p=media');
    }
}

if (get('action') === 'delete' && Csrf::check(get('token'))) {
    Media::remove((int) get('id', '0'));
    flash('Imagen eliminada.');
    redirect('admin/index.php?p=media');
}

$items = Media::all(400);
$token = Csrf::token();
$dir   = Media::dir();
$writable = is_dir($dir) && is_writable($dir);

admin_header('Biblioteca de imágenes', 'media');
?>
<?php if (!$writable): ?>
  <div class="notice notice--warn"><?= icon('cerrar', 19) ?><span>La carpeta <code>uploads/media</code> no tiene permisos de escritura. Asígnele permisos 755 desde su panel de hosting para poder subir imágenes.</span></div>
<?php endif; ?>

<form class="panel" method="post" enctype="multipart/form-data">
  <?= Csrf::field() ?>
  <input type="hidden" name="op" value="upload">
  <div class="panel__head"><h2><?= icon('imagen', 19) ?>Subir imágenes</h2></div>
  <div class="panel__body">
    <div class="form-grid">
      <div class="f f--full">
        <label for="archivos">Archivos</label>
        <input type="file" id="archivos" name="archivos[]" accept="image/*" multiple>
        <span class="hint">Formatos permitidos: JPG, PNG, WEBP, AVIF, GIF y SVG. Máximo 6 MB por archivo. Puede seleccionar varias a la vez.</span>
      </div>
      <div class="f f--full">
        <label for="alt">Texto alternativo (SEO)</label>
        <input type="text" id="alt" name="alt" maxlength="200" placeholder="Ej. Diseño de páginas web para restaurantes en Guatemala">
        <span class="hint">Describa la imagen con palabras naturales. Google lo usa para entender el contenido visual.</span>
      </div>
    </div>
  </div>
  <div class="form-actions"><button class="btn" type="submit"><?= icon('mas', 17) ?><span>Subir imágenes</span></button></div>
</form>

<div class="panel">
  <div class="panel__head"><h2><?= icon('imagen', 19) ?>Imágenes disponibles (<?= count($items) ?>)</h2></div>
  <?php if ($items === []): ?>
    <div class="empty"><?= icon('imagen', 38) ?><p>Todavía no ha subido imágenes.</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Imagen</th><th>Archivo</th><th>Texto alternativo (SEO)</th><th>Tamaño</th><th style="text-align:right">Acciones</th></tr></thead>
        <tbody>
          <?php foreach ($items as $m): ?>
            <tr>
              <td><img class="thumb" src="<?= e(asset_url((string) $m['path'])) ?>" alt="" loading="lazy"></td>
              <td>
                <code style="font-size:.76rem"><?= e($m['filename']) ?></code><br>
                <span class="hint"><?= (int) $m['width'] ?>×<?= (int) $m['height'] ?> px</span>
              </td>
              <td>
                <form method="post" style="display:flex;gap:.4rem">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="op" value="alt">
                  <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                  <input type="text" name="alt" value="<?= e((string) $m['alt']) ?>" maxlength="200" style="min-width:180px">
                  <button class="btn btn--light btn--icon" type="submit" title="Guardar"><?= icon('check', 15) ?></button>
                </form>
              </td>
              <td><?= e(number_format((int) $m['size'] / 1024, 0)) ?> KB</td>
              <td class="actions">
                <a class="btn btn--light btn--icon" title="Abrir" target="_blank" rel="noopener" href="<?= e(asset_url((string) $m['path'])) ?>"><?= icon('web', 15) ?></a>
                <a class="btn btn--danger btn--icon" title="Eliminar" data-confirm="¿Eliminar esta imagen? Si está en uso dejará de mostrarse."
                   href="<?= e(admin_url('media', ['action' => 'delete', 'id' => $m['id'], 'token' => $token])) ?>"><?= icon('cerrar', 15) ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
