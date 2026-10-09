<?php /** Layout del panel. Vars: $content, $user, $flash, $alertCount, $nonce */
$cur = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$items = ['/admin' => 'Resumen', '/admin/pedidos' => 'Pedidos', '/admin/demos' => 'Demos', '/admin/correos' => 'Correos y dominios', '/admin/renovaciones' => 'Renovaciones', '/admin/ia' => 'Consumo de IA', '/admin/ajustes' => 'Ajustes', '/admin/bitacora' => 'Bitácora', '/admin/diagnostico' => 'Diagnóstico', '/admin/cuenta' => 'Mi cuenta'];
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0d0d10">
<title>Panel · Servicom</title><link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="<?= e(asset('admin/admin.css')) ?>"></head>
<body>
<header class="top"><a class="brand" href="/admin">SERVICOM · Panel</a>
<?php if ($alertCount > 0): ?><a href="/admin" class="badge" aria-label="<?= (int) $alertCount ?> alertas"><?= (int) $alertCount ?></a><?php endif; ?>
<button class="menu" type="button" aria-label="Menú" aria-expanded="false">☰</button></header>
<nav class="side" aria-label="Principal">
<?php foreach ($items as $href => $label): ?><a href="<?= e($href) ?>"<?= $cur === $href ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
<form method="post" action="/admin/logout"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><a href="#" onclick="this.closest('form').submit();return false">Cerrar sesión</a></form>
</nav>
<main>
<?php if (!empty($flash)): ?><div class="flash <?= e($flash[0]) ?>" role="status"><?= e($flash[1]) ?></div><?php endif; ?>
<?= $content ?>
</main>
<script src="<?= e(asset('admin/admin.js')) ?>" nonce="<?= e($nonce ?? '') ?>"></script>
</body></html>
