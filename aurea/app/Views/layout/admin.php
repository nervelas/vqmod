<?php
use Aurea\Core\App;
use Aurea\Core\Auth;
use Aurea\Core\Brand;
use Aurea\Core\Db;
$biz = (string)setting('business_name', 'AUREA');
$pendingMsgs = Auth::can('messages') ? count(\Aurea\Services\NotificationService::dueWhatsApp(Auth::scopeProfessional())) : 0;
$pendingAppts = (int)Db::val("SELECT COUNT(*) FROM appointments WHERE status='pending'" . (Auth::scopeProfessional() ? ' AND professional_id=' . (int)Auth::scopeProfessional() : ''));
$items = [
    ['Operación', [
        ['inicio', '/admin', 'home', 'Inicio', 'dashboard', 0],
        ['agenda', '/admin/agenda', 'calendar', 'Agenda', 'agenda', 0],
        ['citas', '/admin/citas', 'list', term('appts'), 'appointments', $pendingAppts],
        ['clientes', '/admin/clientes', 'users', term('clients'), 'clients', 0],
        ['mensajes', '/admin/mensajes', 'message', 'Mensajes de hoy', 'messages', $pendingMsgs],
        ['espera', '/admin/espera', 'clock', 'Lista de espera', 'waitlist', 0],
        ['pagos', '/admin/pagos', 'wallet', 'Pagos', 'payments', 0],
        ['resenas', '/admin/resenas', 'star', 'Reseñas', 'reviews', 0],
        ['reportes', '/admin/reportes', 'chart', 'Reportes', 'reports_basic', 0],
    ]],
    ['Catálogo', [
        ['servicios', '/admin/servicios', 'tag', 'Servicios', 'admin', 0],
        ['categorias', '/admin/categorias', 'folder', 'Categorías', 'admin', 0],
        ['formularios', '/admin/formularios', 'form', 'Formularios', 'admin', 0],
        ['paquetes', '/admin/paquetes', 'gift', 'Paquetes', 'admin', 0],
        ['cupones', '/admin/cupones', 'tag', 'Cupones y regalos', 'admin', 0],
    ]],
    ['Equipo y horarios', [
        ['profesionales', '/admin/profesionales', 'user', term('professionals'), 'schedule_own', 0],
        ['sedes', '/admin/sedes', 'pin', 'Sedes', 'admin', 0],
        ['feriados', '/admin/feriados', 'sun', 'Feriados', 'admin', 0],
        ['ausencias', '/admin/ausencias', 'off', 'Ausencias', 'schedule_own', 0],
    ]],
    ['Ajustes', [
        ['ajustes', '/admin/ajustes', 'settings', 'Marca y ajustes', 'admin', 0],
        ['plantillas', '/admin/plantillas', 'mail', 'Plantillas de mensajes', 'admin', 0],
        ['compartir', '/admin/compartir', 'share', 'Compartir y widget', 'admin', 0],
        ['usuarios', '/admin/usuarios', 'shield', 'Usuarios y roles', 'admin', 0],
        ['sistema', '/admin/sistema', 'activity', 'Sistema', 'admin', 0],
        ['auditoria', '/admin/auditoria', 'list', 'Auditoría', 'admin', 0],
    ]],
];
$logo = Brand::logoUrl();
?>
<!doctype html>
<html lang="es" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e(($title !== '' ? $title . ' · ' : '') . $biz) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">
<script src="<?= e(asset('js/theme.js')) ?>"></script>
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/public.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<?php $bc = Brand::css(); if ($bc !== ''): ?><style nonce="<?= e(App::$nonce) ?>"><?= $bc ?></style><?php endif; ?>
</head>
<body class="admin">
<a class="skip" href="#main"><?= e(__('Saltar al contenido')) ?></a>
<div class="app">
  <aside class="side" id="side" aria-label="<?= e(__('Menú del panel')) ?>">
    <a class="logo" href="<?= e(url('/admin')) ?>"><span class="mono" aria-hidden="true"><?= e(Brand::initial()) ?></span><span><?= e($biz) ?><small>AUREA</small></span></a>
    <nav>
      <?php foreach ($items as [$grp, $links]): $vis = array_filter($links, static fn($l) => Auth::can($l[4]) || ($l[4] === 'admin' && Auth::role() === 'admin')); if (!$vis) { continue; } ?>
        <h4><?= e($grp) ?></h4>
        <?php foreach ($vis as [$key, $href, $ic, $label, , $cnt]): ?>
          <a class="nl" href="<?= e(url($href)) ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= e($label) ?></span><?php if ($cnt > 0): ?><span class="cnt"><?= (int)$cnt ?></span><?php endif; ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="foot"><a href="<?= e(url('/')) ?>" target="_blank" rel="noopener" style="color:var(--gold-3)"><?= e(__('Ver sitio público')) ?> ↗</a></div>
  </aside>
  <div class="main">
    <header class="top">
      <button class="icon-btn side-toggle" type="button" data-side-toggle aria-expanded="false" aria-controls="side" aria-label="<?= e(__('Abrir menú')) ?>"><?= icon('menu') ?></button>
      <h1><?= e($title) ?></h1>
      <div class="who"><b><?= e($user['name']) ?></b><br><?= e(Auth::ROLES[$user['role']] ?? '') ?></div>
      <button class="icon-btn" type="button" data-theme-toggle aria-label="<?= e(__('Cambiar entre modo claro y oscuro')) ?>"><?= icon('moon') ?></button>
      <a class="icon-btn" href="<?= e(url('/admin/perfil')) ?>" aria-label="<?= e(__('Mi perfil')) ?>"><?= icon('user') ?></a>
      <form method="post" action="<?= e(url('/admin/logout')) ?>" class="inline-form"><?= csrf_field() ?><button class="icon-btn" type="submit" aria-label="<?= e(__('Cerrar sesión')) ?>"><?= icon('logout') ?></button></form>
    </header>
    <main class="content" id="main">
      <?php foreach (($_SESSION['flash'] ?? []) as [$ft, $fm]): ?><div class="flash <?= e($ft) ?>" role="status"><?= e($fm) ?></div><?php endforeach; unset($_SESSION['flash']); ?>
      <?php if (!empty($_SESSION['flash_info'])): ?><div class="flash"><?= e($_SESSION['flash_info']) ?></div><?php unset($_SESSION['flash_info']); endif; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
<?php foreach ((array)($scripts ?? []) as $s): ?><script src="<?= e(asset($s)) ?>" defer></script><?php endforeach; ?>
</body>
</html>
