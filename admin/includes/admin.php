<?php
/**
 * Admin shared bootstrap + layout helpers.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once BASE_PATH . '/includes/Content.php';
require_once BASE_PATH . '/includes/Media.php';

Auth::require();
$__admin = Auth::user();

/** Admin URL helper. */
function admin_url(string $page = '', array $params = []): string
{
    $q = array_merge($page !== '' ? ['page' => $page] : [], $params);
    return base_url('admin/index.php') . ($q ? '?' . http_build_query($q) : '');
}

/** Current admin page key. */
function admin_page(): string
{
    return preg_replace('/[^a-z0-9_-]/', '', strtolower($_GET['page'] ?? 'dashboard')) ?: 'dashboard';
}

/** Render the sidebar navigation. */
function admin_nav(): array
{
    return [
        ['dashboard',   'Panel',            'target'],
        ['pages',       'Páginas y secciones', 'book'],
        ['menu',        'Menú',             'portal'],
        ['platforms',   'Accesos y plataformas', 'card'],
        ['gallery',     'Galería',          'image'],
        ['media',       'Biblioteca multimedia', 'star'],
        ['submissions', 'Solicitudes',      'chat'],
        ['whatsapp',    'WhatsApp',         'phone'],
        ['settings',    'Configuración general', 'admissions'],
        ['seo',         'SEO',              'eye'],
        ['users',       'Administradores',  'child'],
        ['backup',      'Respaldos',        'briefcase'],
        ['logs',        'Actividad',        'clock'],
    ];
}

function admin_header(string $title): void
{
    global $__admin;
    $current = admin_page();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> — Administración</title>
<link rel="icon" href="<?= e(asset_url(Settings::get('favicon','assets/img/favicon.svg'))) ?>">
<link rel="stylesheet" href="<?= e(asset_url('admin/assets/admin.css')) ?>?v=1">
</head>
<body>
<div class="admin">
  <aside class="admin-side" id="adminSide">
    <div class="admin-side__brand">
      <img src="<?= e(asset_url(Settings::get('logo','assets/img/logo.svg'))) ?>" alt="logo">
    </div>
    <nav class="admin-side__nav">
      <?php foreach (admin_nav() as [$key,$label,$icon]): ?>
        <a href="<?= e(admin_url($key)) ?>" class="<?= $current===$key?'is-active':'' ?>">
          <span class="admin-side__ico"><?= platform_icon($icon) ?></span><?= e($label) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="admin-side__foot">
      <a href="<?= e(base_url()) ?>" target="_blank">Ver sitio ↗</a>
      <a href="<?= e(base_url('admin/logout.php')) ?>">Cerrar sesión</a>
    </div>
  </aside>
  <div class="admin-main">
    <header class="admin-top">
      <button class="admin-burger" id="adminBurger" aria-label="Menú">☰</button>
      <h1 class="admin-top__title"><?= e($title) ?></h1>
      <div class="admin-top__user">
        <span><?= e($__admin['name']) ?></span>
        <a class="btn btn--sm btn--outline" href="<?= e(base_url('admin/logout.php')) ?>">Salir</a>
      </div>
    </header>
    <div class="admin-content">
    <?php foreach (take_flashes() as $f): ?>
      <div class="notice notice--<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?php
}

function admin_footer(): void
{
    ?>
    </div>
  </div>
</div>
<div class="media-modal" id="mediaModal" aria-hidden="true">
  <div class="media-modal__box">
    <div class="media-modal__head"><strong>Biblioteca multimedia</strong><button type="button" id="mediaClose">&times;</button></div>
    <div class="media-modal__body" id="mediaModalBody"><p>Cargando…</p></div>
  </div>
</div>
<script src="<?= e(asset_url('admin/assets/admin.js')) ?>?v=1"></script>
</body>
</html>
    <?php
}

/** Small helper to render an image + media picker field. */
function media_field(string $name, string $value, string $label): string
{
    $id = 'mf_' . preg_replace('/[^a-z0-9]/i', '', $name);
    ob_start(); ?>
    <div class="form-group media-field">
      <label><?= e($label) ?></label>
      <div class="media-field__row">
        <div class="media-field__preview">
          <?php if ($value): ?><img src="<?= e(asset_url($value)) ?>" alt="" id="<?= $id ?>_img"><?php else: ?><span class="media-field__empty" id="<?= $id ?>_img">Sin imagen</span><?php endif; ?>
        </div>
        <div class="media-field__controls">
          <input type="text" name="<?= e($name) ?>" id="<?= $id ?>" value="<?= e($value) ?>" placeholder="ruta o URL de imagen">
          <div class="media-field__btns">
            <button type="button" class="btn btn--sm btn--outline" data-media-pick="<?= $id ?>">Elegir de biblioteca</button>
            <button type="button" class="btn btn--sm btn--ghost-dark" data-media-clear="<?= $id ?>">Quitar</button>
          </div>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
