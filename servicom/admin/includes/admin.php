<?php
declare(strict_types=1);

/** Arranque del panel de administracion. */
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if (!headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store, no-cache, must-revalidate');
}

/** Menu del panel: [clave => [etiqueta, icono, grupo]] */
function admin_menu(): array
{
    return [
        'dashboard'   => ['Escritorio',        'grafica',    'Principal'],
        'general'     => ['Datos del sitio',   'engranaje',  'Principal'],
        'temas'       => ['Temas visuales',    'diseno',     'Principal'],
        'slider'      => ['Slider principal',  'imagen',     'Contenido'],
        'secciones'   => ['Secciones y textos', 'documento', 'Contenido'],
        'servicios'   => ['Servicios',         'servicios',  'Contenido'],
        'planes'      => ['Planes',            'planes',     'Contenido'],
        'proyectos'   => ['Portafolio',        'portafolio', 'Contenido'],
        'proceso'     => ['Proceso de trabajo', 'engranaje', 'Contenido'],
        'indicadores' => ['Indicadores',       'grafica',    'Contenido'],
        'testimonios' => ['Testimonios',       'comilla',    'Contenido'],
        'faqs'        => ['Preguntas frecuentes', 'documento', 'Contenido'],
        'blog'        => ['Actualidad Web',    'blog',       'Contenido'],
        'paginas'     => ['Páginas',           'web',        'Contenido'],
        'menu'        => ['Menú de navegación', 'servicios', 'Estructura'],
        'media'       => ['Biblioteca de imágenes', 'imagen', 'Estructura'],
        'seo'         => ['SEO y buscadores',  'seo',        'Estructura'],
        'mensajes'    => ['Mensajes recibidos', 'contacto',  'Estructura'],
        'usuarios'    => ['Usuarios',          'usuarios',   'Cuenta'],
    ];
}

/** Cuenta mensajes sin leer para el distintivo del menu. */
function admin_unread(): int
{
    try {
        return (int) Database::value('SELECT COUNT(*) FROM submissions WHERE is_read = 0', [], 0);
    } catch (Throwable) {
        return 0;
    }
}

function admin_url(string $page = 'dashboard', array $params = []): string
{
    $params = array_merge(['p' => $page], $params);
    return base('admin/index.php?' . http_build_query($params));
}

/** Cabecera del panel. */
function admin_header(string $title, string $current, array $actions = []): void
{
    $user   = Auth::user() ?? [];
    $menu   = admin_menu();
    $unread = admin_unread();
    $logo   = Settings::get('logo', 'assets/img/logo.svg');
    $flash  = flash();
    ?><!DOCTYPE html>
<html lang="es" data-admin-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Panel de <?= e(Settings::get('site_name', 'Servicom')) ?></title>
<link rel="icon" href="<?= e(asset_url(Settings::get('favicon', 'assets/img/favicon.svg'))) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(base('admin/assets/admin.css?v=1.0.0')) ?>">
</head>
<body>
<?= icon_sprite() ?>
<div class="shell">
  <aside class="side">
    <div class="side__brand">
      <img src="<?= e(asset_url($logo)) ?>" alt="<?= e(Settings::get('site_name')) ?>">
    </div>
    <nav class="side__nav">
      <?php $group = ''; foreach ($menu as $key => [$label, $ic, $grp]):
          if ($grp !== $group) { $group = $grp; echo '<div class="side__group">' . e($grp) . '</div>'; } ?>
        <a class="side__link<?= $key === $current ? ' is-active' : '' ?>" href="<?= e(admin_url($key)) ?>">
          <?= icon($ic, 18) ?><span><?= e($label) ?></span>
          <?php if ($key === 'mensajes' && $unread > 0): ?><span class="side__badge"><?= $unread ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="side__foot">
      <a class="btn btn--light btn--sm" href="<?= e(base('')) ?>" target="_blank" rel="noopener">
        <?= icon('web', 16) ?><span>Ver el sitio</span>
      </a>
      <a class="btn btn--light btn--sm" href="<?= e(base('admin/logout.php')) ?>">
        <?= icon('candado', 16) ?><span>Cerrar sesión (<?= e($user['name'] ?? '') ?>)</span>
      </a>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <button class="btn btn--light btn--icon menu-toggle" type="button" data-side-toggle aria-label="Abrir menú"><?= icon('menu', 18) ?></button>
      <h1><?= e($title) ?></h1>
      <div class="topbar__actions">
        <?php foreach ($actions as $a): ?>
          <a class="btn <?= e($a['class'] ?? 'btn--light') ?>" href="<?= e($a['url']) ?>"><?= icon($a['icon'] ?? 'mas', 17) ?><span><?= e($a['label']) ?></span></a>
        <?php endforeach; ?>
        <button class="btn btn--light btn--icon" type="button" data-admin-theme-toggle aria-label="Cambiar apariencia del panel"><?= icon('diseno', 18) ?></button>
      </div>
    </div>
    <div class="content">
    <?php if ($flash !== null): ?>
      <div class="notice notice--<?= e($flash['type'] === 'ok' ? 'ok' : ($flash['type'] === 'error' ? 'error' : 'info')) ?>">
        <?= icon($flash['type'] === 'error' ? 'cerrar' : 'check', 19) ?><span><?= e($flash['message']) ?></span>
      </div>
    <?php endif; ?>
<?php
}

function admin_footer(): void
{
    ?>
    </div>
  </div>
</div>
<script>window.ADMIN_BASE = <?= jsvalue(base('')) ?>;</script>
<script src="<?= e(base('admin/assets/admin.js?v=1.0.0')) ?>"></script>
</body>
</html>
<?php
}
