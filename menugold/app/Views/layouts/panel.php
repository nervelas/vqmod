<?php
/** Layout del panel. @var \MenuGold\Core\View $view */
use MenuGold\Core\Auth;
use MenuGold\Core\Url;

$active = isset($nav_active) ? $nav_active : '';
$base = Url::basePath();
$is = function ($path, $exact = false) use ($active, $base) {
    $p = $base . $path;
    return $exact ? ($active === $path) : (strpos($active, $path) === 0);
};
$icon = function ($name) {
    $paths = array(
        'home'    => '<path d="M2.5 7.5 9 2.5l6.5 5v7a1 1 0 0 1-1 1h-3v-4h-5v4h-3a1 1 0 0 1-1-1v-7Z"/>',
        'menu'    => '<path d="M3 4.5h12M3 9h12M3 13.5h8"/>',
        'orders'  => '<path d="M4 2.5h10v13l-2.5-1.6L9 15.5l-2.5-1.6L4 15.5v-13Z"/><path d="M6.8 6h4.4M6.8 9h4.4"/>',
        'fire'    => '<path d="M9 2.5s3.5 3 3.5 6a3.5 3.5 0 0 1-7 0c0-1 .5-2 .5-2S7 8.5 7 9.5c0 0 2-2.5 2-7Z"/>',
        'tables'  => '<rect x="2.5" y="2.5" width="5.5" height="5.5" rx="1"/><rect x="10" y="2.5" width="5.5" height="5.5" rx="1"/><rect x="2.5" y="10" width="5.5" height="5.5" rx="1"/><rect x="10" y="10" width="5.5" height="5.5" rx="1"/>',
        'qr'      => '<rect x="2.5" y="2.5" width="5" height="5" rx="1"/><rect x="10.5" y="2.5" width="5" height="5" rx="1"/><rect x="2.5" y="10.5" width="5" height="5" rx="1"/><path d="M10.5 10.5h2v2h-2zM14 14h1.5v1.5H14z"/>',
        'chart'   => '<path d="M3 15V8M7 15V4M11 15v-5M15 15v-8"/>',
        'people'  => '<circle cx="7" cy="6" r="2.5"/><path d="M2.5 15c0-2.5 2-4 4.5-4s4.5 1.5 4.5 4"/><circle cx="13.5" cy="6.5" r="1.8"/>',
        'ticket'  => '<path d="M2.5 6.5V4.5h13v2a2 2 0 0 0 0 5v2h-13v-2a2 2 0 0 0 0-5Z"/>',
        'gear'    => '<circle cx="9" cy="9" r="2.4"/><path d="M9 2.5v2M9 13.5v2M2.5 9h2M13.5 9h2M4.4 4.4l1.4 1.4M12.2 12.2l1.4 1.4M13.6 4.4l-1.4 1.4M5.8 12.2l-1.4 1.4"/>',
        'shield'  => '<path d="M9 2.5 3.5 5v4c0 3.2 2.3 6 5.5 6.8C12.2 15 14.5 12.2 14.5 9V5L9 2.5Z"/>',
        'plus'    => '<path d="M9 3.5v11M3.5 9h11"/>',
        'building'=> '<path d="M3.5 15.5v-13h7v13M10.5 6.5h4v9M5.8 5.5h2.4M5.8 8.2h2.4M5.8 11h2.4"/>',
        'tag'     => '<path d="M8.5 2.5H15v6.5l-6 6-6.5-6.5 6-6Z"/><circle cx="11.8" cy="5.8" r="1"/>',
        'out'     => '<path d="M11 12.5v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M7.5 9h8M13 6.5 15.5 9 13 11.5"/>',
    );
    return '<svg width="17" height="17" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . (isset($paths[$name]) ? $paths[$name] : '') . '</svg>';
};
$role = Auth::role();
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<script>document.documentElement.classList.add('js');</script>
<title><?= e($view->section('title', 'Panel')) ?> · MenúGold</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0C0B09">
<meta name="color-scheme" content="dark">
<link rel="icon" href="<?= e(mg_url('/assets/icons/icon-192.png')) ?>" sizes="192x192">
<link rel="icon" href="<?= e(mg_url('/favicon.ico')) ?>" sizes="32x32">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/core.css')) ?>">
<link rel="stylesheet" href="<?= e(mg_asset('assets/css/panel.css')) ?>">
<?= $view->section('head') ?>
</head>
<body class="panel-body" data-cursor="off" data-curtain="off">
<a class="skip-link" href="#panel-main">Ir al contenido</a>

<div class="layout">
  <aside class="side" id="side">
    <a class="side-brand" href="<?= e(mg_url('/panel')) ?>">
      <span class="brand-mark" aria-hidden="true">M</span><span>MenúGold</span>
    </a>

    <?php if (!empty($restaurant)): ?>
      <div class="side-place">
        <b><?= e($restaurant['name']) ?></b>
        <span>/r/<?= e($restaurant['slug']) ?></span>
        <p style="margin-top:.5rem"><a class="link-line gold" href="<?= e(mg_url('/r/' . $restaurant['slug'])) ?>" target="_blank" rel="noopener">Ver el menú →</a></p>
      </div>
    <?php endif; ?>

    <nav aria-label="Menú del panel">
      <div class="side-group">
        <a class="side-link <?= $is('/panel', true) ? 'is-on' : '' ?>" href="<?= e(mg_url('/panel')) ?>"><?= $icon('home') ?> Resumen</a>
        <?php if (Auth::can('orders')): ?>
          <a class="side-link <?= $is('/panel/pedidos') ? 'is-on' : '' ?>" href="<?= e(mg_url('/panel/pedidos')) ?>">
            <?= $icon('orders') ?> Pedidos
            <?php if (!empty($badge_orders)): ?><span class="side-badge"><?= (int)$badge_orders ?></span><?php endif; ?>
          </a>
        <?php endif; ?>
        <?php if (Auth::can('kds')): ?>
          <a class="side-link <?= $is('/panel/cocina') ? 'is-on' : '' ?>" href="<?= e(mg_url('/panel/cocina')) ?>"><?= $icon('fire') ?> Cocina</a>
        <?php endif; ?>
        <?php if (Auth::can('waiter')): ?>
          <a class="side-link <?= $is('/panel/mesero') ? 'is-on' : '' ?>" href="<?= e(mg_url('/panel/mesero')) ?>">
            <?= $icon('tables') ?> Salón
            <?php if (!empty($badge_calls)): ?><span class="side-badge"><?= (int)$badge_calls ?></span><?php endif; ?>
          </a>
        <?php endif; ?>
      </div>

      <?php if (Auth::can('menu')): ?>
        <div class="side-group">
          <h6>Carta</h6>
          <a class="side-link <?= $is('/panel/menu', true) ? 'is-on' : '' ?>" href="<?= e(mg_url('/panel/menu')) ?>"><?= $icon('menu') ?> Menú</a>
          <a class="side-link <?= $is('/panel/menu/modificadores') ? 'is-on' : '' ?>" href="<?= e(mg_url('/panel/menu/modificadores')) ?>"><?= $icon('plus') ?> Modificadores</a>
          <a class="side-link <?= $is('/panel/menu/promociones') ? 'is-on' : '' ?>" href="<?= e(mg_url('/panel/menu/promociones')) ?>"><?= $icon('tag') ?> Promociones</a>
          <a class="side-link <?= $is('/panel/menu/combos') ? 'is-on' : '' ?>" href="<?= e(mg_url('/panel/menu/combos')) ?>"><?= $icon('ticket') ?> Combos</a>
          <a class="side-link <?= $is('/panel/menu/importar') ? 'is-on' : '' ?>" href="<?= e(mg_url('/panel/menu/importar')) ?>"><?= $icon('orders') ?> Importar Excel</a>
        </div>
      <?php endif; ?>

      <div class="side-group">
        <h6>Operación</h6>
        <?php if (Auth::can('tables')): ?>
          <a class="side-link <?= $is('/panel/mesas') ? 'is-on' : '' ?>" href="<?= e(mg_url('/panel/mesas')) ?>"><?= $icon('qr') ?> Mesas y QR</a>
        <?php endif; ?>
        <?php if (Auth::can('customers')): ?>
          <a class="side-link <?= $is('/panel/clientes') ? 'is-on' : '' ?>" href="<?= e(mg_url('/panel/clientes')) ?>"><?= $icon('people') ?> Clientes</a>
          <a class="side-link <?= $is('/panel/cupones') ? 'is-on' : '' ?>" href="<?= e(mg_url('/panel/cupones')) ?>"><?= $icon('tag') ?> Cupones</a>
        <?php endif; ?>
        <?php if (Auth::can('reports')): ?>
          <a class="side-link <?= $is('/panel/reportes') ? 'is-on' : '' ?>" href="<?= e(mg_url('/panel/reportes')) ?>"><?= $icon('chart') ?> Reportes</a>
        <?php endif; ?>
      </div>

      <?php if (Auth::can('settings')): ?>
        <div class="side-group">
          <h6>Configuración</h6>
          <a class="side-link <?= $is('/panel/ajustes') ? 'is-on' : '' ?>" href="<?= e(mg_url('/panel/ajustes')) ?>"><?= $icon('gear') ?> Ajustes</a>
          <?php if (Auth::can('users')): ?>
            <a class="side-link <?= $is('/panel/usuarios') ? 'is-on' : '' ?>" href="<?= e(mg_url('/panel/usuarios')) ?>"><?= $icon('people') ?> Usuarios</a>
          <?php endif; ?>
          <a class="side-link <?= $is('/panel/bitacora') ? 'is-on' : '' ?>" href="<?= e(mg_url('/panel/bitacora')) ?>"><?= $icon('shield') ?> Bitácora</a>
        </div>
      <?php endif; ?>

      <?php if (Auth::isSuper()): ?>
        <div class="side-group">
          <h6>Plataforma</h6>
          <a class="side-link" href="<?= e(mg_url('/super')) ?>"><?= $icon('building') ?> Consola general</a>
          <?php if (!empty($impersonating)): ?>
            <a class="side-link" href="<?= e(mg_url('/super/salir-de-restaurante')) ?>"><?= $icon('out') ?> Salir del restaurante</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </nav>

    <div class="side-foot">
      <p><b><?= e($auth_user['name']) ?></b></p>
      <p class="faint" style="font-size:11px;text-transform:uppercase;letter-spacing:.12em"><?= e($role) ?></p>
      <p style="margin-top:.7rem"><a class="link-line" href="<?= e(mg_url('/panel/salir')) ?>">Cerrar sesión</a></p>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn burger" id="burger" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="side">
        <svg width="17" height="17" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M3 5h12M3 9h12M3 13h12"/></svg>
      </button>
      <h1><?= e($view->section('title', 'Panel')) ?></h1>
      <div class="topbar-actions"><?= $view->section('actions') ?></div>
    </header>

    <main class="page" id="panel-main">
      <?php if (!empty($impersonating)): ?>
        <div class="alert">Estás viendo el panel como <b><?= e($restaurant['name']) ?></b>. <a class="link-line gold" href="<?= e(mg_url('/super/salir-de-restaurante')) ?>">Volver a la consola</a>.</div>
      <?php endif; ?>
      <?php $view->partial('admin/partials/flash'); ?>
      <?= $view->section('content') ?>
    </main>
  </div>
</div>

<script>window.MG_PANEL = <?= json_encode(array(
    'csrf' => \MenuGold\Core\Csrf::token(),
    'base' => Url::basePath(),
    'maxUpload' => (int)\MenuGold\Core\Config::get('uploads.max_bytes', 8388608),
), JSON_UNESCAPED_SLASHES) ?>;</script>
<?= $view->section('scripts') ?>
<script src="<?= e(mg_asset('assets/js/panel.js')) ?>" defer></script>
</body>
</html>
