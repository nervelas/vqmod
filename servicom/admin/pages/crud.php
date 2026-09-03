<?php
declare(strict_types=1);
/** Listado y formulario generico para cualquier entidad del panel. */
/** @var Crud $crud @var string $page */

$action = get('action', 'list');
$id     = (int) get('id', '0');
$errors = [];
$values = [];

// ------------------------------------------------------------ Acciones ------
if (is_post()) {
    Csrf::verify();
    $op = post('op', 'save');

    if ($op === 'save') {
        $current = $id > 0 ? $crud->row($id) : null;
        $values  = $crud->collect($current);
        $errors  = $crud->validate($values);

        if ($errors === []) {
            $newId = $crud->save($values, $id > 0 ? $id : null);
            Settings::flush();
            flash($id > 0 ? 'Cambios guardados correctamente.' : 'Registro creado correctamente.');
            redirect('admin/index.php?p=' . $page);
        }
        $action = 'edit';
    }
} elseif ($action === 'delete' && $id > 0 && $crud->canDelete()) {
    if (!Csrf::check(get('token'))) {
        flash('Token inválido. Intente de nuevo.', 'error');
    } else {
        $row = $crud->row($id);
        if ($row !== null && (int) ($row['is_system'] ?? 0) === 1) {
            flash('Este registro es del sistema y no puede eliminarse.', 'error');
        } elseif ($crud->table() === 'users' && (int) $id === (int) (Auth::user()['id'] ?? 0)) {
            flash('No puede eliminar su propio usuario.', 'error');
        } else {
            $crud->remove($id);
            flash('Registro eliminado.');
        }
    }
    redirect('admin/index.php?p=' . $page);
} elseif ($action === 'toggle' && $id > 0) {
    if (Csrf::check(get('token'))) {
        $crud->toggle($id);
        flash('Visibilidad actualizada.');
    }
    redirect('admin/index.php?p=' . $page);
} elseif (($action === 'up' || $action === 'down') && $id > 0) {
    if (Csrf::check(get('token'))) {
        $crud->move($id, $action === 'up' ? -1 : 1);
        flash('Orden actualizado.');
    }
    redirect('admin/index.php?p=' . $page);
}

// ------------------------------------------------------------ Formulario ----
if ($action === 'edit' || $action === 'new') {
    $row = $id > 0 ? $crud->row($id) : [];
    if ($id > 0 && $row === null) {
        flash('El registro no existe.', 'error');
        redirect('admin/index.php?p=' . $page);
    }
    $data = $values !== [] ? array_merge((array) $row, $values) : (array) $row;

    $title = $id > 0
        ? 'Editar ' . $crud->singular()
        : 'Nueva ' . $crud->singular();

    admin_header($title, $page, [['label' => 'Volver al listado', 'url' => admin_url($page), 'icon' => 'flecha']]);
    ?>
    <?php if ($errors !== []): ?>
      <div class="notice notice--error"><?= icon('cerrar', 19) ?><span>Revise los campos marcados: <?= e(implode(' ', $errors)) ?></span></div>
    <?php endif; ?>

    <form class="panel" method="post" enctype="multipart/form-data" action="<?= e(admin_url($page, ['action' => 'edit', 'id' => $id])) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="op" value="save">
      <div class="panel__head">
        <h2><?= icon((string) ($crud->key() === '' ? 'documento' : 'documento'), 19) ?><?= e($title) ?></h2>
      </div>
      <div class="panel__body">
        <div class="form-grid">
          <?php foreach ($crud->fields() as $name => $f):
              $v = $data[$name] ?? ($f['default'] ?? '');
              admin_field($name, $f, $v, $errors);
          endforeach; ?>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn" type="submit"><?= icon('check', 17) ?><span>Guardar cambios</span></button>
        <a class="btn btn--light" href="<?= e(admin_url($page)) ?>"><?= icon('cerrar', 17) ?><span>Cancelar</span></a>
      </div>
    </form>
    <?php
    admin_pickers();
    admin_footer();
    return;
}

// -------------------------------------------------------------- Listado -----
$rows    = $crud->rows();
$columns = $crud->listColumns();
$token   = Csrf::token();
$actions = $crud->canCreate()
    ? [['label' => 'Añadir ' . $crud->singular(), 'url' => admin_url($page, ['action' => 'new']), 'icon' => 'mas', 'class' => 'btn']]
    : [];

admin_header($crud->title(), $page, $actions);
?>
<?php if ($crud->hint() !== ''): ?>
  <div class="row-help"><?= icon('chispa', 16) ?> <?= e($crud->hint()) ?></div>
<?php endif; ?>

<div class="panel">
  <div class="table-wrap">
    <?php if ($rows === []): ?>
      <div class="empty">
        <?= icon('documento', 38) ?>
        <p>Todavía no hay registros.</p>
        <?php if ($crud->canCreate()): ?>
          <a class="btn" href="<?= e(admin_url($page, ['action' => 'new'])) ?>"><?= icon('mas', 17) ?><span>Añadir <?= e($crud->singular()) ?></span></a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <?php foreach ($columns as $label): ?><th><?= e($label) ?></th><?php endforeach; ?>
            <th style="text-align:right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <?php foreach ($columns as $col => $label): ?>
                <td>
                  <?php
                  $v = $r[$col] ?? '';
                  if ($col === 'status' || $col === 'featured' || $col === 'is_button') {
                      echo (int) $v === 1
                          ? '<span class="pill pill--on">' . icon('check', 13) . 'Sí</span>'
                          : '<span class="pill pill--off">No</span>';
                  } elseif ($col === 'image' || $col === 'avatar' || $col === 'og_image') {
                      echo $v !== ''
                          ? '<img class="thumb" src="' . e(asset_url((string) $v)) . '" alt="" loading="lazy">'
                          : '<span class="pill pill--off">Sin imagen</span>';
                  } elseif ($col === 'icon') {
                      echo '<span class="mini-icon">' . icon((string) $v, 17) . '</span>';
                  } elseif ($col === 'published_at' || $col === 'last_login') {
                      echo $v !== '' && $v !== null ? e(date('d/m/Y H:i', strtotime((string) $v) ?: time())) : '<span class="pill pill--off">—</span>';
                  } elseif ($col === 'rating') {
                      echo e(str_repeat('★', max(0, min(5, (int) $v))));
                  } elseif ($col === 'location') {
                      echo e($v === 'footer' ? 'Pie de página' : 'Cabecera');
                  } else {
                      echo e(excerpt((string) $v, 70));
                  }
                  ?>
                </td>
              <?php endforeach; ?>
              <td class="actions">
                <?php if ($crud->hasColumn('sort_order')): ?>
                  <a class="btn btn--light btn--icon" title="Subir" href="<?= e(admin_url($page, ['action' => 'up', 'id' => $r['id'], 'token' => $token])) ?>"><?= icon('flecha-arriba', 15) ?></a>
                  <a class="btn btn--light btn--icon" title="Bajar" style="transform:rotate(180deg)" href="<?= e(admin_url($page, ['action' => 'down', 'id' => $r['id'], 'token' => $token])) ?>"><?= icon('flecha-arriba', 15) ?></a>
                <?php endif; ?>
                <?php if (array_key_exists('status', $r)): ?>
                  <a class="btn btn--light btn--icon" title="Mostrar u ocultar" href="<?= e(admin_url($page, ['action' => 'toggle', 'id' => $r['id'], 'token' => $token])) ?>"><?= icon((int) $r['status'] === 1 ? 'check' : 'cerrar', 15) ?></a>
                <?php endif; ?>
                <a class="btn btn--light btn--sm" href="<?= e(admin_url($page, ['action' => 'edit', 'id' => $r['id']])) ?>"><?= icon('diseno', 15) ?><span>Editar</span></a>
                <?php if ($crud->canDelete() && (int) ($r['is_system'] ?? 0) !== 1): ?>
                  <a class="btn btn--danger btn--icon" title="Eliminar" data-confirm="¿Eliminar «<?= e(excerpt((string) ($r['title'] ?? $r['name'] ?? $r['label'] ?? $r['question'] ?? 'este registro'), 40)) ?>»? Esta acción no se puede deshacer."
                     href="<?= e(admin_url($page, ['action' => 'delete', 'id' => $r['id'], 'token' => $token])) ?>"><?= icon('cerrar', 15) ?></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
