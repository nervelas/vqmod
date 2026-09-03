<?php
declare(strict_types=1);

/** Renderiza un campo del formulario del panel. */
function admin_field(string $name, array $f, mixed $value, array $errors = []): void
{
    $type  = (string) ($f['type'] ?? 'text');
    $label = (string) ($f['label'] ?? $name);
    $hint  = (string) ($f['hint'] ?? '');
    $id    = 'f_' . preg_replace('/[^a-z0-9_]/i', '', $name);
    $full  = ($f['full'] ?? false) === true || in_array($type, ['textarea', 'lines', 'media'], true);
    $err   = $errors[$name] ?? '';
    $ro    = ($f['readonly'] ?? false) === true;

    echo '<div class="f ' . ($full ? 'f--full' : '') . ($type === 'checkbox' ? ' f--check' : '') . '">';

    if ($type === 'checkbox') {
        echo '<input type="checkbox" id="' . e($id) . '" name="' . e($name) . '" value="1"' . ((int) $value === 1 ? ' checked' : '') . '>';
        echo '<label for="' . e($id) . '">' . e($label) . '</label>';
        if ($hint !== '') { echo '<span class="hint" style="width:100%">' . e($hint) . '</span>'; }
        echo '</div>';
        return;
    }

    echo '<label for="' . e($id) . '">' . e($label);
    if (($f['required'] ?? false) === true) { echo ' <span style="color:var(--a-danger)">*</span>'; }
    echo '</label>';

    switch ($type) {
        case 'textarea':
        case 'lines':
            $rows = ($f['tall'] ?? false) ? ' class="tall"' : '';
            echo '<textarea id="' . e($id) . '" name="' . e($name) . '"' . $rows
               . ($ro ? ' readonly' : '') . '>' . e((string) $value) . '</textarea>';
            break;

        case 'select':
            echo '<select id="' . e($id) . '" name="' . e($name) . '"' . ($ro ? ' disabled' : '') . '>';
            foreach ((array) ($f['options'] ?? []) as $ov => $ol) {
                echo '<option value="' . e((string) $ov) . '"' . ((string) $value === (string) $ov ? ' selected' : '') . '>' . e((string) $ol) . '</option>';
            }
            echo '</select>';
            break;

        case 'media':
            $val = (string) $value;
            echo '<div class="media-field" data-media-field>';
            echo '  <span class="media-field__prev">';
            echo '    <img src="' . ($val !== '' ? e(asset_url($val)) : '') . '" alt="" data-media-preview' . ($val === '' ? ' hidden' : '') . '>';
            echo '  </span>';
            echo '  <span class="media-field__ctl">';
            echo '    <input type="text" id="' . e($id) . '" name="' . e($name) . '" value="' . e($val) . '" placeholder="uploads/media/archivo.jpg" data-media-input>';
            echo '    <span style="display:flex;gap:.4rem;flex-wrap:wrap">';
            echo '      <button class="btn btn--light btn--sm" type="button" data-media-open>' . icon('imagen', 16) . '<span>Elegir de la biblioteca</span></button>';
            echo '      <button class="btn btn--light btn--sm" type="button" data-media-clear>' . icon('cerrar', 16) . '<span>Quitar</span></button>';
            echo '    </span>';
            echo '    <input type="file" name="upload_' . e($name) . '" accept="image/*" style="font-size:.82rem">';
            echo '    <span class="hint">O suba una imagen nueva desde su computadora (máx. 6 MB).</span>';
            echo '  </span>';
            echo '</div>';
            break;

        case 'icon':
            echo '<div class="icon-picker" data-icon-field>';
            echo '  <span class="icon-picker__prev" data-icon-preview>' . icon((string) $value, 21) . '</span>';
            echo '  <input type="hidden" name="' . e($name) . '" value="' . e((string) $value) . '" data-icon-input>';
            echo '  <button class="btn btn--light btn--sm" type="button" data-icon-open>' . icon('diseno', 16) . '<span>Cambiar icono</span></button>';
            echo '  <code style="font-size:.78rem;color:var(--a-muted)" data-icon-name>' . e((string) $value) . '</code>';
            echo '</div>';
            break;

        case 'number':
            echo '<input type="number" id="' . e($id) . '" name="' . e($name) . '" value="' . e((string) $value) . '"'
               . (isset($f['min']) ? ' min="' . (int) $f['min'] . '"' : '')
               . (isset($f['max_value']) ? ' max="' . (int) $f['max_value'] . '"' : '') . '>';
            break;

        case 'password':
            echo '<input type="password" id="' . e($id) . '" name="' . e($name) . '" value="" autocomplete="new-password" minlength="8">';
            break;

        case 'datetime':
            $dt = trim((string) $value);
            $dt = $dt !== '' ? date('Y-m-d\TH:i', strtotime($dt) ?: time()) : date('Y-m-d\TH:i');
            echo '<input type="datetime-local" id="' . e($id) . '" name="' . e($name) . '" value="' . e($dt) . '">';
            break;

        case 'color':
            echo '<input type="color" id="' . e($id) . '" name="' . e($name) . '" value="' . e((string) ($value ?: '#000000')) . '">';
            break;

        case 'email':
        case 'url':
        case 'tel':
            echo '<input type="' . e($type) . '" id="' . e($id) . '" name="' . e($name) . '" value="' . e((string) $value) . '"'
               . (isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '') . ($ro ? ' readonly' : '') . '>';
            break;

        default: // text, slug
            echo '<input type="text" id="' . e($id) . '" name="' . e($name) . '" value="' . e((string) $value) . '"'
               . (isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '')
               . (isset($f['placeholder']) ? ' placeholder="' . e((string) $f['placeholder']) . '"' : '')
               . ($ro ? ' readonly' : '') . '>';
            break;
    }

    if ($err !== '') {
        echo '<span class="hint" style="color:var(--a-danger)">' . e($err) . '</span>';
    } elseif ($hint !== '') {
        echo '<span class="hint">' . e($hint) . '</span>';
    }
    echo '</div>';
}

/** Modales compartidos: biblioteca de imágenes y selector de iconos. */
function admin_pickers(): void
{
    $media = Media::all(300);
    ?>
    <dialog id="dlg-media">
      <div class="dialog__head">
        <?= icon('imagen', 20) ?><h3>Biblioteca de imágenes</h3>
        <button class="btn btn--light btn--icon" type="button" data-close><?= icon('cerrar', 16) ?></button>
      </div>
      <div class="dialog__body">
        <?php if ($media === []): ?>
          <div class="empty"><?= icon('imagen', 38) ?><p>Todavía no hay imágenes. Suba una desde el campo «Subir imagen» o desde <a href="<?= e(admin_url('media')) ?>">Biblioteca de imágenes</a>.</p></div>
        <?php else: ?>
          <div class="media-grid">
            <?php foreach ($media as $m): ?>
              <button class="media-item" type="button" data-path="<?= e($m['path']) ?>">
                <img src="<?= e(asset_url((string) $m['path'])) ?>" alt="<?= e((string) $m['alt']) ?>" loading="lazy">
                <span><?= e($m['filename']) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </dialog>

    <dialog id="dlg-icons">
      <div class="dialog__head">
        <?= icon('diseno', 20) ?><h3>Elegir icono</h3>
        <button class="btn btn--light btn--icon" type="button" data-close><?= icon('cerrar', 16) ?></button>
      </div>
      <div class="dialog__body">
        <div class="icon-grid">
          <?php foreach (icon_names() as $n): ?>
            <button class="icon-opt" type="button" data-icon="<?= e($n) ?>"><?= icon($n, 22) ?><span><?= e($n) ?></span></button>
          <?php endforeach; ?>
        </div>
      </div>
    </dialog>
    <?php
}
