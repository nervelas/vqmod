<?php
require __DIR__ . '/_boot.php';
auth_require();

$labels = section_labels();
$editMap = ['contact' => 'form'];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $C = get_content(true);
    $C['layout'] = sanitize_layout($_POST['layout'] ?? []);
    $ok = save_content($C);
    flash($ok ? 'ok' : 'error', $ok ? 'Orden y visibilidad de secciones actualizados.' : 'No se pudo guardar.');
    header('Location: ' . url('admin/layout.php'), true, 303);
    exit;
}
$C = get_content(true);
$layout = $C['layout'];
admin_header('Secciones y orden', 'layout', 'Activa, oculta y reordena las secciones de la página principal.');
?>
<form method="post" data-dirty-guard>
  <?= csrf_field() ?>
  <div class="card">
    <p class="muted">Usa las flechas para cambiar el orden. Las secciones desactivadas no se muestran en el sitio, pero su contenido se conserva.</p>
    <ul class="sort" data-sort>
      <?php foreach ($layout['order'] as $sid): if (!isset($labels[$sid])) continue; $en = !empty($layout['enabled'][$sid]); ?>
      <li class="sort-row" data-row>
        <input type="hidden" name="layout[order][]" value="<?= e($sid) ?>">
        <span class="grip"><?= icon('dots-six-vertical', '') ?></span>
        <div class="sort-name"><strong><?= e($labels[$sid]) ?></strong>
          <a href="<?= e(url('admin/edit.php?s=' . ($editMap[$sid] ?? $sid))) ?>">Editar contenido →</a></div>
        <div class="li-actions">
          <button type="button" class="ib" data-up aria-label="Subir"><?= icon('arrow-up', '') ?></button>
          <button type="button" class="ib" data-down aria-label="Bajar"><?= icon('arrow-down', '') ?></button>
        </div>
        <label class="switch"><input type="checkbox" name="layout[enabled][<?= e($sid) ?>]" value="1" <?= $en ? 'checked' : '' ?> <?= $sid === 'hero' ? 'checked disabled' : '' ?>><span class="sw"></span><span class="sw-l"><?= $sid === 'hero' ? 'Siempre visible' : 'Visible' ?></span></label>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="savebar">
    <span class="dirty-msg" hidden><?= icon('warning-circle', '') ?> Tienes cambios sin guardar</span>
    <button class="btn btn-primary" type="submit"><?= icon('floppy-disk', '') ?><span>Guardar cambios</span></button>
  </div>
</form>
<?php admin_footer();
