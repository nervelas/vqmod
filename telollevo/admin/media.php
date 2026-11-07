<?php
require __DIR__ . '/_boot.php';

const MEDIA_MAX_BYTES = 8388608;   // 8 MB
const MEDIA_MAX_SIDE  = 2200;      // px

function media_list(): array {
    $items = [];
    foreach ((array)glob(TLL_UPLOADS . '/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) as $f) {
        if (!is_file($f)) continue;
        $n = basename($f);
        $items[] = ['name' => $n, 'path' => 'uploads/' . $n, 'url' => url('uploads/' . $n) . '?v=' . filemtime($f), 'size' => filesize($f), 'time' => filemtime($f)];
    }
    usort($items, function ($a, $b) { return $b['time'] <=> $a['time']; });
    return $items;
}
function media_builtin(): array {
    $o = [];
    foreach (['courier.webp' => 'Motorizado (portada)', 'logo-mark.png' => 'Logo Te Lo Llevo', 'og-image.jpg' => 'Imagen para compartir'] as $f => $label) {
        if (is_file(TLL_ROOT . '/assets/img/' . $f)) $o[] = ['name' => $label, 'path' => 'assets/img/' . $f, 'url' => url('assets/img/' . $f), 'size' => filesize(TLL_ROOT . '/assets/img/' . $f)];
    }
    return $o;
}

/** Valida y guarda una imagen subida. Devuelve [ok, ruta|mensaje]. */
function media_store(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $m = [UPLOAD_ERR_INI_SIZE => 'La imagen supera el límite del servidor.', UPLOAD_ERR_FORM_SIZE => 'La imagen es demasiado grande.', UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo.'];
        return [false, $m[$file['error'] ?? 0] ?? 'Error al subir el archivo.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) return [false, 'Archivo no válido.'];
    if ($file['size'] > MEDIA_MAX_BYTES) return [false, 'La imagen pesa más de 8 MB.'];
    $info = @getimagesize($file['tmp_name']);
    if (!$info) return [false, 'El archivo no es una imagen válida.'];
    $type = $info[2];
    $mime = function_exists('finfo_open') ? (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : $info['mime'];
    $map = [IMAGETYPE_JPEG => ['jpg', 'image/jpeg'], IMAGETYPE_PNG => ['png', 'image/png'], IMAGETYPE_GIF => ['gif', 'image/gif']];
    if (defined('IMAGETYPE_WEBP')) $map[IMAGETYPE_WEBP] = ['webp', 'image/webp'];
    if (!isset($map[$type]) || $mime !== $map[$type][1]) return [false, 'Solo se permiten imágenes JPG, PNG, WEBP o GIF.'];
    if ($info[0] < 1 || $info[1] < 1 || $info[0] > 12000 || $info[1] > 12000) return [false, 'Dimensiones de imagen no válidas.'];
    $ext = $map[$type][0];
    $name = date('Ymd') . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    $dest = TLL_UPLOADS . '/' . $name;
    if (!is_dir(TLL_UPLOADS)) @mkdir(TLL_UPLOADS, 0755, true);
    $done = false;
    // Se vuelve a codificar con GD: elimina metadatos y cualquier contenido oculto.
    if (extension_loaded('gd')) {
        $src = null;
        if ($type === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) $src = @imagecreatefromjpeg($file['tmp_name']);
        elseif ($type === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) $src = @imagecreatefrompng($file['tmp_name']);
        elseif ($type === IMAGETYPE_GIF && function_exists('imagecreatefromgif')) $src = @imagecreatefromgif($file['tmp_name']);
        elseif (defined('IMAGETYPE_WEBP') && $type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($file['tmp_name']);
        if ($src) {
            $w = imagesx($src); $h = imagesy($src);
            $scale = min(1, MEDIA_MAX_SIDE / max($w, $h));
            $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            if ($ext !== 'jpg') { imagealphablending($dst, false); imagesavealpha($dst, true); imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127)); }
            else { imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            if ($ext === 'jpg') $done = imagejpeg($dst, $dest, 86);
            elseif ($ext === 'png') $done = imagepng($dst, $dest, 6);
            elseif ($ext === 'gif') $done = imagegif($dst, $dest);
            elseif ($ext === 'webp' && function_exists('imagewebp')) $done = imagewebp($dst, $dest, 88);
            imagedestroy($src); imagedestroy($dst);
        }
    }
    if (!$done) { $done = @move_uploaded_file($file['tmp_name'], $dest); }
    if (!$done) return [false, 'No se pudo guardar la imagen. Revisa los permisos de la carpeta "uploads".'];
    @chmod($dest, 0644);
    return [true, 'uploads/' . $name];
}

$wantsJson = isset($_GET['json']) || isset($_POST['json']);

if (!auth_check()) {
    if ($wantsJson) { http_response_code(401); header('Content-Type: application/json'); echo '{"ok":false,"message":"Sesión expirada"}'; exit; }
    header('Location: ' . url('admin/login.php')); exit;
}

/* --- JSON para el selector de imágenes --- */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['json'])) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['uploads' => media_list(), 'builtin' => media_builtin()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        if ($wantsJson) { http_response_code(400); header('Content-Type: application/json'); echo '{"ok":false,"message":"Solicitud no válida. Recarga la página."}'; exit; }
        csrf_require();
    }
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'upload') {
        $results = []; $okN = 0; $last = '';
        $files = $_FILES['files'] ?? null;
        if ($files && is_array($files['name'])) {
            for ($i = 0; $i < min(count($files['name']), 12); $i++) {
                [$ok, $res] = media_store(['name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i], 'size' => $files['size'][$i], 'error' => $files['error'][$i]]);
                $results[] = [$files['name'][$i], $ok, $res]; if ($ok) { $okN++; $last = $res; }
            }
        } elseif ($files) {
            [$ok, $res] = media_store($files); $results[] = [$files['name'], $ok, $res]; if ($ok) { $okN++; $last = $res; }
        }
        if ($wantsJson) {
            header('Content-Type: application/json; charset=UTF-8');
            $errs = array_values(array_map(function ($r) { return $r[0] . ': ' . $r[2]; }, array_filter($results, function ($r) { return !$r[1]; })));
            echo json_encode(['ok' => $okN > 0, 'path' => $last, 'url' => $last ? url($last) : '', 'count' => $okN, 'message' => $okN ? '' : ($errs[0] ?? 'No se subió ninguna imagen.'), 'errors' => $errs], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($okN) flash('ok', $okN . ' imagen(es) subida(s) correctamente.');
        foreach ($results as $r) { if (!$r[1]) flash('error', $r[0] . ': ' . $r[2]); }
        if (!$results) flash('error', 'No se seleccionó ningún archivo.');
    } elseif ($act === 'delete') {
        $n = basename((string)($_POST['name'] ?? ''));
        if (preg_match('/^[A-Za-z0-9_\-]+\.(jpg|jpeg|png|webp|gif)$/', $n) && is_file(TLL_UPLOADS . '/' . $n)) {
            @unlink(TLL_UPLOADS . '/' . $n);
            flash('ok', 'Imagen eliminada.');
        } else flash('error', 'Imagen no encontrada.');
    }
    header('Location: ' . url('admin/media.php'), true, 303);
    exit;
}

$items = media_list();
admin_header('Imágenes', 'media', 'Sube y administra las imágenes que usas en la página.');
?>
<section class="card">
  <h2><?= icon('upload-simple', '') ?> Subir imágenes</h2>
  <form method="post" enctype="multipart/form-data" class="dropzone" data-dropzone>
    <?= csrf_field() ?><input type="hidden" name="action" value="upload">
    <?= icon('cloud-arrow-up', 'dz-ic') ?>
    <p><strong>Arrastra tus imágenes aquí</strong> o</p>
    <label class="btn btn-primary"><span>Elegir archivos</span><input type="file" name="files[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden></label>
    <small>JPG, PNG, WEBP o GIF · Máximo 8 MB por imagen · Se optimizan automáticamente</small>
    <div class="dz-status" hidden></div>
  </form>
</section>
<section class="card">
  <h2><?= icon('images', '') ?> Tus imágenes <span class="muted">(<?= count($items) ?>)</span></h2>
  <?php if (!$items): ?><p class="empty">Aún no has subido imágenes. Las imágenes originales del sitio (logo y motorizado) siempre están disponibles al elegir una imagen.</p><?php endif; ?>
  <div class="media-grid">
    <?php foreach ($items as $it): ?>
      <figure class="media-item">
        <img src="<?= e($it['url']) ?>" alt="" loading="lazy">
        <figcaption><span><?= e(round($it['size'] / 1024)) ?> KB</span>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="name" value="<?= e($it['name']) ?>">
          <button class="ib danger" type="submit" aria-label="Eliminar" data-confirm="¿Eliminar esta imagen? Si la usas en la página dejará de verse."><?= icon('trash-simple', '') ?></button></form></figcaption>
      </figure>
    <?php endforeach; ?>
  </div>
</section>
<?php admin_footer();
