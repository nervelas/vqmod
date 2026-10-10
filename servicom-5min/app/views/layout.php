<?php
/**
 * Layout público "Maison Dorée".
 * Variables: $content (HTML ya renderizado de la vista), $title, $description, $nonce (opcional),
 *            $cfg (array), $page ('home'|'wizard'|'status'|'pay'|'error'|'gone').
 * Las vistas cargan sus propios CSS/JS específicos (portal/*.php) para no depender de variables.
 */
if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
$title       = isset($title) && $title !== '' ? (string)$title : 'Tu web en 5 minutos';
$description = isset($description) && $description !== '' ? (string)$description : 'Crea tu sitio web profesional en minutos: dominio .com, correos corporativos, hosting y diseño premium. Llena tus datos o sube tu presentación y nosotros armamos tu web.';
$page        = isset($page) ? (string)$page : 'home';
$nonceAttr   = !empty($nonce) ? ' nonce="' . e($nonce) . '"' : '';
$csrf        = function_exists('csrf_token') ? csrf_token() : '';
$ver         = '1';
?><!doctype html>
<html lang="es" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="csrf" content="<?= e($csrf) ?>">
<meta name="theme-color" content="#08080a">
<meta name="color-scheme" content="dark">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:type" content="website">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="preload" href="/assets/fonts/cormorant-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/manrope-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
</head>
<body class="pg-<?= e($page) ?>">
<a class="skip" href="#main">Saltar al contenido</a>
<?= $content ?? '' ?>
<script src="<?= e(asset('js/portal.js')) ?>" defer<?= $nonceAttr ?>></script>
</body>
</html>
