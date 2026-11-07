<?php
require __DIR__ . '/_boot.php';
auth_require();

$schema = admin_schema();
$id = (string)($_GET['s'] ?? '');
if (!isset($schema[$id])) { header('Location: ' . url('admin/')); exit; }
$sc = $schema[$id];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $C = get_content(true);
    $input = $_POST['c'][$id] ?? [];
    $clean = sanitize_fields($sc['fields'], $input, (array)($C[$id] ?? []));
    $C[$id] = array_merge((array)($C[$id] ?? []), $clean);
    if (save_content($C)) flash('ok', 'Cambios guardados. Ya están publicados en tu sitio web.');
    else flash('error', 'No se pudo guardar. Revisa los permisos de la carpeta "data".');
    header('Location: ' . url('admin/edit.php?s=' . $id), true, 303);
    exit;
}

$C = get_content(true);
admin_header($sc['title'], $id, $sc['desc']);
?>
<form method="post" class="edit-form" data-dirty-guard>
  <?= csrf_field() ?>
  <div class="card">
    <div class="fields">
      <?= render_form_fields($sc['fields'], (array)$C[$id], 'c[' . $id . ']') ?>
    </div>
    <?php if ($id === 'theme'): $d = default_content()['theme']; ?>
      <div class="theme-preview" aria-hidden="true"><span class="tp-a"></span><span class="tp-b"></span><span class="tp-c"></span></div>
      <button type="button" class="btn btn-soft" data-reset-theme data-blue="<?= e($d['blue']) ?>" data-navy="<?= e($d['navy']) ?>" data-yellow="<?= e($d['yellow']) ?>"><?= icon('arrow-counter-clockwise', '') ?><span>Restaurar colores del logo</span></button>
    <?php endif; ?>
  </div>
  <div class="savebar">
    <span class="dirty-msg" hidden><?= icon('warning-circle', '') ?> Tienes cambios sin guardar</span>
    <a class="btn btn-text" href="<?= e(url('admin/edit.php?s=' . $id)) ?>">Descartar</a>
    <button class="btn btn-primary" type="submit"><?= icon('floppy-disk', '') ?><span>Guardar cambios</span></button>
  </div>
</form>
<?php admin_footer();
