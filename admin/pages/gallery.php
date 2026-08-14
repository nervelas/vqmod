<?php
/** Gallery manager: albums CRUD + photos per album. */
if (!defined('BASE_PATH')) { exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Csrf::verifyPost();
    $do = post('do');
    if ($do === 'save_album') {
        $id = (int)post('id');
        $title = post('title');
        $slug = post('slug') ?: slugify($title);
        // ensure unique slug
        $exists = Database::scalar('SELECT COUNT(*) FROM albums WHERE slug=? AND id<>?', [$slug, $id]);
        if ($exists) { $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4); }
        $data = [
            'title' => $title, 'slug' => $slug,
            'description' => post('description'),
            'cover_image' => post('cover_image'),
            'event_date' => post('event_date') ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'sort' => (int)post('sort'),
        ];
        if ($id) { Database::update('albums', $data, ['id' => $id]); }
        else { $data['created_at'] = date('Y-m-d H:i:s'); $id = (int)Database::insert('albums', $data); }
        Auth::log('album_save', 'Guardó álbum "' . $title . '"');
        flash('success', 'Álbum guardado.');
        redirect('admin/index.php?page=gallery&album=' . $id);
    } elseif ($do === 'delete_album') {
        Database::delete('albums', ['id' => (int)post('id')]);
        flash('success', 'Álbum eliminado.');
        redirect('admin/index.php?page=gallery');
    } elseif ($do === 'add_photos') {
        $albumId = (int)post('album_id');
        $n = 0;
        if (!empty($_FILES['photos'])) {
            $files = $_FILES['photos'];
            $count = is_array($files['name']) ? count($files['name']) : 0;
            for ($i = 0; $i < $count; $i++) {
                if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { continue; }
                try {
                    $res = Media::upload([
                        'name' => $files['name'][$i], 'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i],
                    ]);
                    Database::insert('photos', ['album_id' => $albumId, 'image' => $res['path'], 'sort' => $i]);
                    $n++;
                } catch (Throwable $ex) { flash('error', $ex->getMessage()); }
            }
        }
        flash('success', $n . ' fotografía(s) agregada(s).');
        redirect('admin/index.php?page=gallery&album=' . $albumId);
    } elseif ($do === 'delete_photo') {
        Database::delete('photos', ['id' => (int)post('id')]);
        flash('success', 'Fotografía eliminada.');
        redirect('admin/index.php?page=gallery&album=' . (int)post('album_id'));
    } elseif ($do === 'photo_caption') {
        Database::update('photos', ['caption' => post('caption'), 'sort' => (int)post('sort')], ['id' => (int)post('id')]);
        flash('success', 'Fotografía actualizada.');
        redirect('admin/index.php?page=gallery&album=' . (int)post('album_id'));
    }
}

$albumId = (int)($_GET['album'] ?? 0);
admin_header('Galería');

if ($albumId) {
    $album = Database::first('SELECT * FROM albums WHERE id=?', [$albumId]);
    if (!$album) { echo '<div class="notice notice--error">Álbum no encontrado.</div>'; admin_footer(); return; }
    $photos = Content::photos($albumId);
    ?>
    <a class="back" href="<?= e(admin_url('gallery')) ?>">← Todos los álbumes</a>
    <div class="card">
      <h2>Datos del álbum</h2>
      <form method="post" class="form">
        <?= Csrf::field() ?><input type="hidden" name="do" value="save_album"><input type="hidden" name="id" value="<?= (int)$album['id'] ?>">
        <div class="grid-2">
          <div class="form-group"><label>Título</label><input type="text" name="title" value="<?= e($album['title']) ?>" required></div>
          <div class="form-group"><label>Slug</label><input type="text" name="slug" value="<?= e($album['slug']) ?>"></div>
        </div>
        <div class="form-group"><label>Descripción</label><textarea name="description" rows="2"><?= e($album['description']) ?></textarea></div>
        <div class="grid-2">
          <div class="form-group"><label>Fecha</label><input type="date" name="event_date" value="<?= e($album['event_date']) ?>"></div>
          <div class="form-group"><label>Orden</label><input type="number" name="sort" value="<?= (int)$album['sort'] ?>"></div>
        </div>
        <?= media_field('cover_image', $album['cover_image'] ?? '', 'Imagen de portada') ?>
        <label class="switch"><input type="checkbox" name="is_active" <?= $album['is_active']?'checked':'' ?>> Álbum visible</label>
        <div class="form-actions"><button class="btn btn--primary">Guardar álbum</button></div>
      </form>
    </div>

    <div class="card">
      <h2>Agregar fotografías</h2>
      <form method="post" enctype="multipart/form-data" class="form">
        <?= Csrf::field() ?><input type="hidden" name="do" value="add_photos"><input type="hidden" name="album_id" value="<?= (int)$album['id'] ?>">
        <div class="form-group"><label>Selecciona una o varias imágenes</label><input type="file" name="photos[]" accept=".jpg,.jpeg,.png,.webp,.gif" multiple required></div>
        <button class="btn btn--primary">Subir fotografías</button>
      </form>
    </div>

    <div class="card">
      <h2>Fotografías (<?= count($photos) ?>)</h2>
      <div class="media-grid">
        <?php foreach ($photos as $ph): ?>
          <div class="media-item">
            <div class="media-item__thumb"><img src="<?= e(asset_url($ph['image'])) ?>" alt="" loading="lazy"></div>
            <form method="post" class="form photo-edit">
              <?= Csrf::field() ?><input type="hidden" name="do" value="photo_caption"><input type="hidden" name="id" value="<?= (int)$ph['id'] ?>"><input type="hidden" name="album_id" value="<?= (int)$album['id'] ?>">
              <input type="text" name="caption" value="<?= e($ph['caption']) ?>" placeholder="Descripción">
              <div class="photo-edit__row">
                <input type="number" name="sort" value="<?= (int)$ph['sort'] ?>" style="width:70px" title="Orden">
                <button class="btn btn--xs btn--outline">Guardar</button>
              </div>
            </form>
            <form method="post" onsubmit="return confirm('¿Eliminar esta fotografía?')">
              <?= Csrf::field() ?><input type="hidden" name="do" value="delete_photo"><input type="hidden" name="id" value="<?= (int)$ph['id'] ?>"><input type="hidden" name="album_id" value="<?= (int)$album['id'] ?>">
              <button class="btn btn--xs btn--danger">Eliminar</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if (!$photos): ?><p class="muted">Este álbum aún no tiene fotografías.</p><?php endif; ?>
    </div>
    <?php
} else {
    $albums = Database::all('SELECT a.*, (SELECT COUNT(*) FROM photos p WHERE p.album_id=a.id) c FROM albums a ORDER BY a.sort ASC, a.id DESC');
    ?>
    <div class="card">
      <div class="card__head"><h2>Álbumes</h2></div>
      <table class="table">
        <thead><tr><th>Título</th><th>Fotos</th><th>Fecha</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($albums as $a): ?>
          <tr>
            <td><strong><?= e($a['title']) ?></strong></td>
            <td><?= (int)$a['c'] ?></td>
            <td class="muted"><?= e($a['event_date']) ?></td>
            <td><span class="badge <?= $a['is_active']?'badge--on':'badge--off' ?>"><?= $a['is_active']?'Activo':'Inactivo' ?></span></td>
            <td class="row-actions">
              <a class="btn btn--sm btn--primary" href="<?= e(admin_url('gallery',['album'=>$a['id']])) ?>">Administrar</a>
              <form method="post" onsubmit="return confirm('¿Eliminar el álbum y todas sus fotos?')"><?= Csrf::field() ?><input type="hidden" name="do" value="delete_album"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button class="btn btn--sm btn--danger">Eliminar</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card">
      <h2>Nuevo álbum</h2>
      <form method="post" class="form">
        <?= Csrf::field() ?><input type="hidden" name="do" value="save_album"><input type="hidden" name="id" value="0">
        <div class="grid-2">
          <div class="form-group"><label>Título</label><input type="text" name="title" required></div>
          <div class="form-group"><label>Fecha</label><input type="date" name="event_date"></div>
        </div>
        <div class="form-group"><label>Descripción</label><textarea name="description" rows="2"></textarea></div>
        <label class="switch"><input type="checkbox" name="is_active" checked> Activo</label>
        <div class="form-actions"><button class="btn btn--primary">Crear álbum</button></div>
      </form>
    </div>
    <?php
}
admin_footer();
