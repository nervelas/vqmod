<?php
require __DIR__ . '/_boot.php';
auth_require();

/* --- Descargar respaldo --- */
if (isset($_GET['download'])) {
    $S = get_settings(); unset($S['smtp']['pass']);
    $payload = ['app' => 'telollevo', 'version' => TLL_VERSION, 'exported' => date('c'), 'content' => get_content(true), 'settings' => ['mail_to' => $S['mail_to'], 'mail_from' => $S['mail_from']]];
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="respaldo-telollevo-' . date('Y-m-d') . '.json"');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'import') {
        $f = $_FILES['backup'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || $f['size'] > 2097152) {
            flash('error', 'Selecciona un archivo de respaldo válido (máx. 2 MB).');
        } else {
            $j = json_decode((string)file_get_contents($f['tmp_name']), true);
            if (!is_array($j) || ($j['app'] ?? '') !== 'telollevo' || !is_array($j['content'] ?? null)) {
                flash('error', 'El archivo no es un respaldo de este sitio.');
            } else {
                $C = get_content(true);
                foreach (admin_schema() as $id => $sc) {
                    if (isset($j['content'][$id]) && is_array($j['content'][$id])) {
                        $C[$id] = array_merge((array)($C[$id] ?? []), sanitize_fields($sc['fields'], $j['content'][$id], (array)($C[$id] ?? [])));
                    }
                }
                if (isset($j['content']['layout']) && is_array($j['content']['layout'])) $C['layout'] = sanitize_layout($j['content']['layout']);
                save_content($C);
                flash('ok', 'Respaldo restaurado correctamente.');
            }
        }
    } elseif ($act === 'reset') {
        if (trim((string)($_POST['confirm'] ?? '')) === 'RESTAURAR') {
            if (is_file(store_path('content'))) { @copy(store_path('content'), TLL_DATA . '/content-anterior-' . date('Ymd-His') . '.php'); }
            save_content(default_content());
            flash('ok', 'El contenido volvió a los valores originales. (Se guardó una copia del contenido anterior en la carpeta data).');
        } else flash('error', 'Escribe RESTAURAR en mayúsculas para confirmar.');
    }
    header('Location: ' . url('admin/backup.php'), true, 303);
    exit;
}
admin_header('Respaldo', 'backup', 'Guarda una copia de tu contenido o vuelve al diseño original.');
?>
<div class="grid-2">
  <section class="card">
    <h2><?= icon('download', '') ?> Descargar respaldo</h2>
    <p class="muted">Guarda en tu computadora todos los textos, precios y opciones del sitio. Las imágenes subidas se respaldan copiando la carpeta <code>uploads</code>.</p>
    <a class="btn btn-primary" href="<?= e(url('admin/backup.php?download=1')) ?>"><?= icon('download', '') ?><span>Descargar respaldo</span></a>
  </section>
  <section class="card">
    <h2><?= icon('upload-simple', '') ?> Restaurar respaldo</h2>
    <p class="muted">Sube un archivo de respaldo descargado antes. Reemplaza el contenido actual.</p>
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="import">
      <div class="fld"><input type="file" name="backup" accept="application/json,.json" required></div>
      <button class="btn btn-soft" type="submit" data-confirm="Esto reemplaza el contenido actual por el del respaldo. ¿Continuar?"><?= icon('upload-simple', '') ?><span>Restaurar</span></button>
    </form>
  </section>
</div>
<section class="card danger-zone">
  <h2><?= icon('arrow-counter-clockwise', '') ?> Volver al contenido original</h2>
  <p class="muted">Restaura todos los textos, servicios, precios y colores a los que traía la página al inicio. Se guarda una copia del contenido actual por seguridad.</p>
  <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="reset">
    <div class="fld"><label for="rc">Escribe <strong>RESTAURAR</strong> para confirmar</label><input id="rc" type="text" name="confirm" autocomplete="off" placeholder="RESTAURAR"></div>
    <button class="btn btn-danger" type="submit"><?= icon('arrow-counter-clockwise', '') ?><span>Restaurar valores originales</span></button>
  </form>
</section>
<?php admin_footer();
