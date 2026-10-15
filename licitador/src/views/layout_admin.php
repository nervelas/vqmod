<?php require_once __DIR__ . '/partials.php'; $cur = request_path();
$nav = [['admin', 'Tablero', 'dashboard'], ['admin/clientes', 'Clientes', 'clientes'], ['admin/planes', 'Planes', 'planes'], ['admin/pagos', 'Pagos', 'pagos'], ['admin/concursos', 'Concursos', 'concursos'], ['admin/fuente', 'Fuente de datos', 'fuente'], ['admin/notificaciones', 'Notificaciones', 'notificaciones'], ['admin/contenido', 'Contenido', 'contenido'], ['admin/config/sitio', 'Textos y contacto', 'contenido'], ['admin/config/seo', 'SEO y colores', 'contenido'], ['admin/config/general', 'Generales', 'ajustes'], ['admin/config/correo', 'Correo (SMTP)', 'ajustes'], ['admin/config/whatsapp', 'WhatsApp', 'ajustes'], ['admin/config/ia', 'Inteligencia artificial', 'ajustes'], ['admin/ajustes', 'Cron, equipo y respaldo', 'ajustes']]; ?>
<!doctype html>
<html lang="es" class="no-js" data-theme="dark">
<head><?= head_html(($title ?? 'Admin') . ' · Admin') ?></head>
<body class="app admin">
<a class="skip" href="#main">Saltar al contenido</a>
<header class="app-top">
  <a href="<?= e(url('admin')) ?>"><?= brand_html() ?> <small class="muted">ADMIN</small></a>
  <div class="app-top-r"><a class="btn btn-quiet btn-sm hide-sm" href="<?= e(url('')) ?>" target="_blank" rel="noopener">Ver sitio</a>
    <button class="icon-btn" type="button" data-theme-toggle aria-label="Cambiar tema"><span aria-hidden="true">◐</span></button>
    <form method="post" action="<?= e(url('salir')) ?>"><?= csrf_field() ?><button class="btn btn-quiet btn-sm">Salir</button></form></div>
</header>
<div class="app-shell">
  <nav class="app-nav" aria-label="Administración">
    <?php foreach ($nav as [$p, $lbl, $mod]): if (!can($mod)) continue; $on = ('/' . $p) === $cur || ($p !== 'admin' && str_starts_with($cur, '/' . $p) && $p !== 'admin/config/sitio'); ?>
      <a href="<?= e(url($p)) ?>" class="<?= $on ? 'on' : '' ?>"><span><?= e($lbl) ?></span></a>
    <?php endforeach; ?>
  </nav>
  <main id="main" class="app-main"><?= flash_html() ?><?= $content ?></main>
</div>
<script src="<?= e(asset('js/app.min.js')) ?>" defer></script>
</body></html>
