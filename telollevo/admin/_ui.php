<?php
/**
 * Componentes visuales del panel: plantilla y campos de formulario generados desde el esquema.
 */
if (!defined('TLL_BOOT')) { http_response_code(403); exit; }

function unread_count(): int {
    $n = 0;
    foreach ((array)store_read('messages', []) as $m) { if (empty($m['read'])) $n++; }
    return $n;
}

function admin_nav(): array {
    $schema = admin_schema();
    $items = [];
    $sec = function (string $id) use ($schema) { return ['url' => 'edit.php?s=' . $id, 'label' => $schema[$id]['title'], 'icon' => $schema[$id]['icon'], 'key' => $id]; };
    $items[] = ['group' => 'Panel', 'items' => [
        ['url' => 'index.php', 'label' => 'Inicio', 'icon' => 'house-simple', 'key' => 'dashboard'],
        ['url' => 'messages.php', 'label' => 'Mensajes', 'icon' => 'envelope-simple', 'key' => 'messages', 'badge' => unread_count()],
    ]];
    $items[] = ['group' => 'Estructura', 'items' => [
        ['url' => 'layout.php', 'label' => 'Secciones y orden', 'icon' => 'layout', 'key' => 'layout'],
        $sec('nav'),
        $sec('theme'),
    ]];
    $items[] = ['group' => 'General', 'items' => [$sec('site'), $sec('contact')]];
    $content = [];
    foreach ($schema as $id => $s) { if ($s['group'] === 'Secciones de la página') $content[] = $sec($id); }
    $items[] = ['group' => 'Contenido de la página', 'items' => $content];
    $items[] = ['group' => 'Herramientas', 'items' => [
        ['url' => 'media.php', 'label' => 'Imágenes', 'icon' => 'images', 'key' => 'media'],
        ['url' => 'settings.php', 'label' => 'Correo y seguridad', 'icon' => 'shield-check', 'key' => 'settings'],
        ['url' => 'backup.php', 'label' => 'Respaldo', 'icon' => 'cloud-arrow-up', 'key' => 'backup'],
    ]];
    return $items;
}

function admin_header(string $title, string $active = '', string $subtitle = ''): void {
    $C = get_content();
    $brand = $C['site']['brand'];
    $flash = flash_take();
    $logo = media_url($C['site']['logo']);
    header('Content-Type: text/html; charset=UTF-8');
    ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<meta name="base" content="<?= e(url()) ?>">
<title><?= e($title) ?> · Panel <?= e($brand) ?></title>
<link rel="icon" type="image/png" href="<?= e(asset('img/favicon-32.png')) ?>">
<link rel="stylesheet" href="<?= e(url('admin/assets/admin.css')) ?>?v=<?= (int)@filemtime(__DIR__ . '/assets/admin.css') ?>">
</head>
<body class="admin">
<a class="skip" href="#content">Saltar al contenido</a>
<aside class="side" id="side">
  <div class="side-brand">
    <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="" width="40" height="40"><?php endif; ?>
    <div><strong><?= e($brand) ?></strong><small>Panel de administración</small></div>
  </div>
  <nav class="side-nav" aria-label="Panel">
    <?php foreach (admin_nav() as $g): ?>
      <div class="nav-group"><span class="nav-title"><?= e($g['group']) ?></span>
      <?php foreach ($g['items'] as $it): ?>
        <a href="<?= e(url('admin/' . $it['url'])) ?>" class="<?= $active === $it['key'] ? 'active' : '' ?>">
          <?= icon($it['icon'], '') ?><span><?= e($it['label']) ?></span>
          <?php if (!empty($it['badge'])): ?><em class="badge"><?= (int)$it['badge'] ?></em><?php endif; ?>
        </a>
      <?php endforeach; ?></div>
    <?php endforeach; ?>
  </nav>
  <div class="side-foot">
    <a class="btn btn-yellow btn-block" href="<?= e(url()) ?>" target="_blank" rel="noopener"><?= icon('arrow-square-out', '') ?><span>Ver sitio web</span></a>
    <form method="post" action="<?= e(url('admin/logout.php')) ?>"><?= csrf_field() ?><button class="btn btn-ghost-light btn-block" type="submit"><?= icon('sign-out', '') ?><span>Cerrar sesión</span></button></form>
  </div>
</aside>
<div class="side-scrim" id="scrim"></div>
<div class="main">
  <header class="topbar">
    <button class="menu-btn" id="menuBtn" aria-label="Abrir menú" aria-controls="side" aria-expanded="false"><span></span><span></span><span></span></button>
    <div class="topbar-title"><h1><?= e($title) ?></h1><?php if ($subtitle !== ''): ?><p><?= e($subtitle) ?></p><?php endif; ?></div>
    <a class="btn btn-soft top-view" href="<?= e(url()) ?>" target="_blank" rel="noopener"><?= icon('eye', '') ?><span>Ver sitio</span></a>
  </header>
  <main id="content" class="content">
    <?php foreach ($flash as $f): ?><div class="toast <?= e($f[0]) ?>" role="status"><?= icon($f[0] === 'ok' ? 'check-circle' : 'warning-circle', '') ?><span><?= e($f[1]) ?></span><button type="button" class="toast-x" aria-label="Cerrar">×</button></div><?php endforeach; ?>
<?php
}

function admin_footer(): void {
    ?>
  </main>
</div>
<script src="<?= e(url('admin/assets/admin.js')) ?>?v=<?= (int)@filemtime(__DIR__ . '/assets/admin.js') ?>" defer></script>
</body>
</html>
<?php
}

/* ------------------------------------------------------------------ */
/*  Campos de formulario                                               */
/* ------------------------------------------------------------------ */
function field_blank($f) {
    switch ($f['t']) {
        case 'bool': return false;
        case 'list': return [];
        case 'number': return '';
        case 'color': return '#000000';
        default: return '';
    }
}

function render_field(array $f, $val, string $name): string {
    $t = $f['t']; $label = $f['l']; $help = $f['help'] ?? '';
    $id = 'f_' . substr(md5($name), 0, 10);
    $o = '<div class="fld fld-' . e($t) . '">';
    if ($t === 'bool') {
        $o .= '<label class="switch"><input type="checkbox" name="' . e($name) . '" value="1"' . ($val ? ' checked' : '') . '><span class="sw"></span><span class="sw-l">' . e($label) . '</span></label>';
    } elseif ($t === 'list') {
        $o .= render_list($f, is_array($val) ? $val : [], $name);
    } else {
        $o .= '<label for="' . $id . '">' . e($label) . '</label>';
        $max = isset($f['max']) && $t !== 'number' ? ' maxlength="' . (int)$f['max'] . '"' : '';
        $ph = isset($f['ph']) ? ' placeholder="' . e($f['ph']) . '"' : '';
        switch ($t) {
            case 'textarea':
                $o .= '<textarea id="' . $id . '" name="' . e($name) . '" rows="' . (int)($f['rows'] ?? 3) . '"' . $max . '>' . e($val) . '</textarea>';
                break;
            case 'number':
                $o .= '<input id="' . $id . '" type="number" name="' . e($name) . '" value="' . e($val) . '" min="' . e($f['min'] ?? 0) . '" max="' . e($f['max'] ?? 1000000) . '" step="' . e($f['step'] ?? '1') . '" inputmode="decimal">';
                break;
            case 'color':
                $o .= '<div class="color-row"><input type="color" value="' . e($val) . '" data-color-pick aria-label="Elegir color"><input id="' . $id . '" type="text" name="' . e($name) . '" value="' . e($val) . '" maxlength="7" pattern="#[0-9A-Fa-f]{6}" data-color-hex></div>';
                break;
            case 'image':
                $url = media_url($val);
                $o .= '<div class="img-field" data-image><div class="img-prev">' . ($url ? '<img src="' . e($url) . '" alt="">' : '<span>Sin imagen</span>') . '</div>'
                    . '<input type="hidden" id="' . $id . '" name="' . e($name) . '" value="' . e($val) . '">'
                    . '<div class="img-actions"><button type="button" class="btn btn-soft btn-sm" data-pick-image>' . icon('image', '') . '<span>Elegir o subir</span></button>'
                    . '<button type="button" class="btn btn-text btn-sm" data-clear-image>Quitar</button></div></div>';
                break;
            case 'icon':
                $o .= '<div class="icon-field" data-icon><span class="icon-prev">' . ($val ? icon($val, '') : '') . '</span>'
                    . '<input type="hidden" id="' . $id . '" name="' . e($name) . '" value="' . e($val) . '">'
                    . '<button type="button" class="btn btn-soft btn-sm" data-pick-icon>' . icon('squares-four', '') . '<span>Elegir icono</span></button>'
                    . '<code class="icon-name">' . e($val) . '</code></div>';
                break;
            case 'url':
                $list = '';
                if (!empty($f['suggest'])) {
                    $list = ' list="dl_' . $id . '"';
                    $o .= '<datalist id="dl_' . $id . '">';
                    foreach ($f['suggest'] as $s) $o .= '<option value="' . e($s) . '">';
                    $o .= '</datalist>';
                }
                $o .= '<input id="' . $id . '" type="text" name="' . e($name) . '" value="' . e($val) . '"' . $max . $ph . $list . ' autocomplete="off" inputmode="url">';
                break;
            case 'email':
                $o .= '<input id="' . $id . '" type="email" name="' . e($name) . '" value="' . e($val) . '"' . $max . $ph . '>';
                break;
            case 'digits':
                $o .= '<input id="' . $id . '" type="text" inputmode="numeric" name="' . e($name) . '" value="' . e($val) . '"' . $max . ' pattern="[0-9]*">';
                break;
            default:
                $o .= '<input id="' . $id . '" type="text" name="' . e($name) . '" value="' . e($val) . '"' . $max . $ph . '>';
        }
        if ($help !== '') $o .= '<small class="help">' . e($help) . '</small>';
    }
    return $o . '</div>';
}

function render_list_item(array $f, array $row, string $name, $idx, bool $open): string {
    $il = $f['il'] ?? ($f['f'][0]['k'] ?? '');
    $title = trim((string)($row[$il] ?? ''));
    $o = '<div class="li' . ($open ? ' open' : '') . '" data-item data-il="' . e($il) . '">'
       . '<div class="li-head"><button type="button" class="li-toggle" aria-expanded="' . ($open ? 'true' : 'false') . '"><span class="li-n"></span><span class="li-title">' . e($title) . '</span>' . icon('caret-down', 'li-caret') . '</button>'
       . '<div class="li-actions"><button type="button" class="ib" data-up title="Subir" aria-label="Subir">' . icon('arrow-up', '') . '</button>'
       . '<button type="button" class="ib" data-down title="Bajar" aria-label="Bajar">' . icon('arrow-down', '') . '</button>'
       . '<button type="button" class="ib danger" data-del title="Eliminar" aria-label="Eliminar">' . icon('trash-simple', '') . '</button></div></div>'
       . '<div class="li-body">';
    foreach ($f['f'] as $sf) {
        $v = array_key_exists($sf['k'], $row) ? $row[$sf['k']] : field_blank($sf);
        $o .= render_field($sf, $v, $name . '[' . $idx . '][' . $sf['k'] . ']');
    }
    return $o . '</div></div>';
}

function render_list(array $f, array $rows, string $name): string {
    $max = (int)($f['max_items'] ?? 30);
    $o = '<div class="fld-head"><label>' . e($f['l']) . '</label><span class="count" data-count></span></div>';
    $o .= '<div class="list" data-list data-max="' . $max . '" data-name="' . e($name) . '">';
    $o .= '<div class="list-items">';
    foreach (array_values($rows) as $i => $row) { $o .= render_list_item($f, is_array($row) ? $row : [], $name, $i, false); }
    $o .= '</div>';
    $o .= '<button type="button" class="btn btn-add" data-add>' . icon('plus-circle', '') . '<span>Añadir elemento</span></button>';
    $o .= '<template data-tpl>' . render_list_item($f, [], $name, '__i__', true) . '</template>';
    return $o . '</div>';
}

function render_form_fields(array $fields, array $values, string $prefix): string {
    $o = '';
    foreach ($fields as $f) {
        $v = array_key_exists($f['k'], $values) ? $values[$f['k']] : field_blank($f);
        $o .= render_field($f, $v, $prefix . '[' . $f['k'] . ']');
    }
    return $o;
}
