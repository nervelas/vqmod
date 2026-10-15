<?php require_once __DIR__ . '/partials.php'; $imp = !empty($_SESSION['impersonator']);
$cur = request_path();
$nav = ['app' => ['Oportunidades', '◎'], 'app/calendario' => ['Vencimientos', '▦'], 'app/alertas' => ['Alertas', '◔'], 'app/asistente' => ['Mis preferencias', '≡'], 'app/perfil' => ['Mi empresa', '▣'], 'app/suscripcion' => ['Suscripción', '✦'], 'app/ayuda' => ['Ayuda', '?']];
$sub = $u['sub_ends'] ? days_left($u['sub_ends']) : null; ?>
<!doctype html>
<html lang="es" class="no-js" data-theme="dark">
<head><?= head_html($title ?? 'Mi panel') ?></head>
<body class="app">
<a class="skip" href="#main">Saltar al contenido</a>
<?php if ($imp): ?><div class="imp-bar">Estás viendo el panel de <strong><?= e($u['name']) ?></strong> (modo soporte). <form method="post" action="<?= e(url('admin/volver')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-gold">Volver al admin</button></form></div><?php endif; ?>
<header class="app-top">
  <a href="<?= e(url('app')) ?>"><?= brand_html() ?></a>
  <div class="app-top-r">
    <?php if ($sub !== null && $sub <= 5): ?><a class="pill <?= $sub < 0 ? 'p-urgent' : 'p-soon' ?>" href="<?= e(url('app/suscripcion')) ?>"><?= $sub < 0 ? 'Plan vencido' : 'Vence en ' . max(0, $sub) . ' d' ?></a><?php endif; ?>
    <button class="icon-btn" type="button" data-theme-toggle aria-label="Cambiar tema"><span aria-hidden="true">◐</span></button>
    <form method="post" action="<?= e(url('salir')) ?>"><?= csrf_field() ?><button class="btn btn-quiet btn-sm">Salir</button></form>
  </div>
</header>
<div class="app-shell">
  <nav class="app-nav" aria-label="Panel">
    <?php foreach ($nav as $p => [$lbl, $ic]): $on = ('/' . $p) === $cur || ($p !== 'app' && str_starts_with($cur, '/' . $p)) || ($p === 'app' && (str_starts_with($cur, '/app/oportunidad') || str_starts_with($cur, '/app/borrador') || $cur === '/app')); ?>
      <a href="<?= e(url($p)) ?>" class="<?= $on ? 'on' : '' ?>"><span class="ni" aria-hidden="true"><?= $ic ?></span><span><?= e($lbl) ?></span></a>
    <?php endforeach; ?>
  </nav>
  <main id="main" class="app-main"><?= flash_html() ?><?= $content ?></main>
</div>
<script src="<?= e(asset('js/app.min.js')) ?>" defer></script>
</body></html>
