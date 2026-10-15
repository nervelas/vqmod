<?php require_once __DIR__ . '/partials.php'; $cur = request_path(); $kind = 'admin'; $me = user(); $ini = mb_strtoupper(mb_substr($me['name'] ?? 'A', 0, 1));
$menu = []; foreach (app_menu($kind) as $g => $items) { $ok = array_values(array_filter($items, fn($it) => can($it[3]))); if ($ok) $menu[$g] = $ok; } ?>
<!doctype html>
<html lang="es" class="no-js" data-theme="dark">
<head><?= head_html(($title ?? 'Admin') . ' · Admin') ?></head>
<body class="app admin">
<a class="skip" href="#main">Saltar al contenido</a>
<header class="app-top">
  <a href="<?= e(url('admin')) ?>" aria-label="Tablero"><?= brand_html('ADMIN') ?></a>
  <div class="app-top-r"><a class="btn btn-quiet btn-sm hide-sm" href="<?= e(url('')) ?>" target="_blank" rel="noopener"><?= icon('external') ?>Ver sitio</a>
    <button class="icon-btn" type="button" data-theme-toggle aria-label="Cambiar tema"><?= icon('moon') ?></button>
    <span class="avatar" title="<?= e($me['name'] ?? '') ?>"><?= e($ini) ?></span>
    <button class="icon-btn menu-btn" type="button" data-sheet-open="menu" aria-label="Abrir menú"><?= icon('menu') ?></button></div>
</header>
<div class="sheet" id="menu" aria-hidden="true"><div class="sheet-bg" data-sheet-close></div>
  <div class="sheet-panel" role="dialog" aria-label="Menú"><div class="sheet-head"><?= brand_html('ADMIN') ?><button class="icon-btn" type="button" data-sheet-close aria-label="Cerrar"><?= icon('x') ?></button></div>
    <?php foreach ($menu as $g => $items): ?><div class="grp"><?= e($g) ?></div><?php foreach ($items as $it): ?><a class="lnk <?= menu_on($it[0], $cur, $kind) ? 'on' : '' ?>" href="<?= e(url($it[0])) ?>"><?= icon($it[2]) ?><?= e($it[1]) ?></a><?php endforeach; endforeach; ?>
    <div class="sep"></div><a class="lnk" href="<?= e(url('')) ?>" target="_blank" rel="noopener"><?= icon('external') ?>Ver sitio público</a>
    <form method="post" action="<?= e(url('salir')) ?>"><?= csrf_field() ?><button class="lnk"><?= icon('logout') ?>Cerrar sesión</button></form></div></div>
<div class="app-shell">
  <nav class="app-nav" aria-label="Administración">
    <?php foreach ($menu as $g => $items): ?><div class="grp"><?= e($g) ?></div><?php foreach ($items as $it): ?><a href="<?= e(url($it[0])) ?>" class="<?= menu_on($it[0], $cur, $kind) ? 'on' : '' ?>"><?= icon($it[2]) ?><span><?= e($it[1]) ?></span></a><?php endforeach; endforeach; ?>
    <div class="navfoot"><div style="display:flex;gap:10px;align-items:center;margin-bottom:12px"><span class="avatar"><?= e($ini) ?></span><div><strong style="color:var(--text);font-size:.85rem"><?= e($me['name'] ?? '') ?></strong><br><small><?= e($me['admin_role'] ?? '') ?></small></div></div>
      <form method="post" action="<?= e(url('salir')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-block"><?= icon('logout') ?>Cerrar sesión</button></form></div>
  </nav>
  <main id="main" class="app-main"><?= flash_html() ?><?= $content ?></main>
</div>
<nav class="tabbar" aria-label="Navegación rápida">
  <?php foreach ([['admin', 'Tablero', 'chart', 'dashboard'], ['admin/clientes', 'Clientes', 'users', 'clientes'], ['admin/pagos', 'Pagos', 'card', 'pagos'], ['admin/concursos', 'Concursos', 'gavel', 'concursos']] as $t): if (!can($t[3])) { echo '<span></span>'; continue; } ?><a href="<?= e(url($t[0])) ?>" class="<?= menu_on($t[0], $cur, $kind) ? 'on' : '' ?>"><?= icon($t[2]) ?><span><?= $t[1] ?></span></a><?php endforeach; ?>
  <button type="button" data-sheet-open="menu"><?= icon('grid') ?><span>Más</span></button>
</nav>
<script src="<?= e(asset('js/app.min.js')) ?>" defer></script>
</body></html>
