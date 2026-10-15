<?php require_once __DIR__ . '/partials.php'; $imp = !empty($_SESSION['impersonator']); $cur = request_path(); $kind = 'client';
$sub = $u['sub_ends'] ? days_left($u['sub_ends']) : null; $menu = app_menu($kind); $ini = mb_strtoupper(mb_substr($u['name'], 0, 1)); ?>
<!doctype html>
<html lang="es" class="no-js" data-theme="dark">
<head><?= head_html($title ?? 'Mi panel') ?></head>
<body class="app">
<a class="skip" href="#main">Saltar al contenido</a>
<?php if ($imp): ?><div class="imp-bar"><span>Modo soporte: viendo el panel de <strong><?= e($u['name']) ?></strong></span><form method="post" action="<?= e(url('admin/volver')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" style="color:#1a1405;border-color:#1a1405">Volver al admin</button></form></div><?php endif; ?>
<header class="app-top">
  <a href="<?= e(url('app')) ?>" aria-label="Inicio"><?= brand_html() ?></a>
  <div class="app-top-r">
    <?php if ($sub !== null && $sub <= 5): ?><a class="pill <?= $sub < 0 ? 'p-urgent' : 'p-soon' ?>" href="<?= e(url('app/suscripcion')) ?>"><?= $sub < 0 ? 'Plan vencido' : 'Vence en ' . max(0, $sub) . ' d' ?></a><?php endif; ?>
    <button class="icon-btn" type="button" data-theme-toggle aria-label="Cambiar tema"><?= icon('moon') ?></button>
    <a class="avatar" href="<?= e(url('app/perfil')) ?>" aria-label="Mi perfil"><?= e($ini) ?></a>
    <button class="icon-btn menu-btn" type="button" data-sheet-open="menu" aria-label="Abrir menú"><?= icon('menu') ?></button>
  </div>
</header>
<div class="sheet" id="menu" aria-hidden="true"><div class="sheet-bg" data-sheet-close></div>
  <div class="sheet-panel" role="dialog" aria-label="Menú"><div class="sheet-head"><?= brand_html() ?><button class="icon-btn" type="button" data-sheet-close aria-label="Cerrar"><?= icon('x') ?></button></div>
    <?php foreach ($menu as $g => $items): ?><div class="grp"><?= e($g) ?></div><?php foreach ($items as $it): ?><a class="lnk <?= menu_on($it[0], $cur, $kind) ? 'on' : '' ?>" href="<?= e(url($it[0])) ?>"><?= icon($it[2]) ?><?= e($it[1]) ?></a><?php endforeach; endforeach; ?>
    <div class="sep"></div><form method="post" action="<?= e(url('salir')) ?>"><?= csrf_field() ?><button class="lnk"><?= icon('logout') ?>Cerrar sesión</button></form></div></div>
<div class="app-shell">
  <nav class="app-nav" aria-label="Panel">
    <?php foreach ($menu as $g => $items): ?><div class="grp"><?= e($g) ?></div><?php foreach ($items as $it): ?><a href="<?= e(url($it[0])) ?>" class="<?= menu_on($it[0], $cur, $kind) ? 'on' : '' ?>"><?= icon($it[2]) ?><span><?= e($it[1]) ?></span></a><?php endforeach; endforeach; ?>
    <div class="navfoot"><div style="display:flex;gap:10px;align-items:center;margin-bottom:12px"><span class="avatar"><?= e($ini) ?></span><div><strong style="color:var(--text);font-size:.85rem"><?= e(mb_substr($u['name'], 0, 22)) ?></strong><br><small><?= e($u['email']) ?></small></div></div>
      <form method="post" action="<?= e(url('salir')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-block"><?= icon('logout') ?>Cerrar sesión</button></form></div>
  </nav>
  <main id="main" class="app-main"><?= flash_html() ?><?= $content ?></main>
</div>
<nav class="tabbar" aria-label="Navegación rápida">
  <?php foreach ([['app', 'Radar', 'radar'], ['app/calendario', 'Fechas', 'calendar'], ['app/alertas', 'Alertas', 'bell'], ['app/suscripcion', 'Plan', 'card']] as $t): ?><a href="<?= e(url($t[0])) ?>" class="<?= menu_on($t[0], $cur, $kind) ? 'on' : '' ?>"><?= icon($t[2]) ?><span><?= $t[1] ?></span></a><?php endforeach; ?>
  <button type="button" data-sheet-open="menu"><?= icon('grid') ?><span>Más</span></button>
</nav>
<script src="<?= e(asset('js/app.min.js')) ?>" defer></script>
</body></html>
