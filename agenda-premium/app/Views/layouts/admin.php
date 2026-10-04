<?php
/**
 * Layout del panel. Variables: $content, $title, $styles (array extra), $scripts (array extra), $bodyClass, $active (ruta activa)
 */
use App\Core\AdminNav;
use App\Core\Auth;
use App\Core\Session;

$user = Auth::user();
$styles = $styles ?? [];
$scripts = $scripts ?? [];
$active = $active ?? '';
$brand = (string) setting('business_name', 'Agenda Premium');
$theme = $user['theme'] ?? 'dark';
$flash = Session::pullFlash();
?>
<!doctype html>
<html lang="es" data-theme="<?= e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-path" content="<?= e(BASE_PATH) ?>">
<title><?= e(($title ?? 'Panel') . ' · ' . $brand) ?></title>
<meta name="theme-color" content="#06080D">
<link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/core.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<?php foreach (['admin-p1.css', 'admin-p2.css', 'admin-p3.css', 'admin-p4.css'] as $f) : if (is_file(APP_ROOT . '/assets/css/' . $f)) : ?><link rel="stylesheet" href="<?= e(asset('css/' . $f)) ?>">
<?php endif; endforeach; ?>
<?php foreach ($styles as $css) : ?><link rel="stylesheet" href="<?= e(asset($css)) ?>">
<?php endforeach; ?>
<style nonce="<?= e(nonce()) ?>">:root{--gold-user:<?= e((string) setting('color_gold', '#C9A050')) ?>}</style>
<script src="<?= e(asset('js/theme-init.js')) ?>"></script>
</head>
<body class="admin <?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#main">Saltar al contenido</a>
<div class="app">
  <aside class="sidebar" id="sidebar" aria-label="Navegación principal">
    <a class="sb-brand" href="<?= e(url('/admin')) ?>"><?= icon('sparkle', 'sb-mark') ?><span class="serif"><?= e($brand) ?></span></a>
    <nav class="sb-nav">
      <?php foreach (AdminNav::visible() as $sec) : ?>
        <div class="sb-section">
          <p class="sb-title"><?= e($sec['title']) ?></p>
          <?php foreach ($sec['items'] as $it) :
              $isActive = $it['path'] === '/admin' ? $active === '/admin' : strpos($active, $it['path']) === 0; ?>
            <a class="sb-link<?= $isActive ? ' is-active' : '' ?>" href="<?= e(url($it['path'])) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>><?= icon($it['icon']) ?><span><?= e($it['label']) ?></span></a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
  </aside>
  <div class="sb-backdrop" data-sidebar-close></div>
  <div class="app-main">
    <header class="topbar">
      <button class="btn btn-ghost btn-icon sb-toggle" type="button" data-sidebar-toggle aria-label="Abrir menú"><?= icon('menu') ?></button>
      <button class="cmdk-trigger" type="button" data-cmdk-open aria-label="Buscar o ir a… (Ctrl K)"><?= icon('search') ?><span class="hide-sm">Buscar o ir a…</span><kbd class="kbd hide-sm">Ctrl K</kbd></button>
      <div class="topbar-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= icon('external') ?><span class="hide-sm">Ver página pública</span></a>
        <button class="btn btn-ghost btn-icon" type="button" data-theme-toggle data-theme-url="<?= e(url('/admin/perfil/tema')) ?>" aria-label="Cambiar entre modo claro y oscuro"><?= icon('moon') ?></button>
        <a class="avatar" href="<?= e(url('/admin/perfil')) ?>" aria-label="Mi cuenta"><?= e(mb_strtoupper(mb_substr((string) ($user['name'] ?? '?'), 0, 1))) ?></a>
        <form method="post" action="<?= e(url('/admin/salir')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-ghost btn-icon" type="submit" aria-label="Cerrar sesión"><?= icon('logout') ?></button></form>
      </div>
    </header>
    <main class="main" id="main">
      <?php foreach ($flash as $f) : ?>
        <div class="alert alert-<?= e($f['type'] === 'error' ? 'err' : ($f['type'] === 'success' ? 'ok' : $f['type'])) ?>" role="status"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<div id="cmdk-root" data-search-url="<?= e(url('/admin/buscar')) ?>" data-nav="<?= ej(AdminNav::visible()) ?>" data-base="<?= e(BASE_PATH) ?>"></div>
<div id="toasts" class="toasts" aria-live="polite"></div>
<?php foreach (array_merge(['js/ui.js', 'js/admin.js'], $scripts) as $js) : ?><script src="<?= e(asset($js)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
