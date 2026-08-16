<?php
/**
 * Public layout header. Expects: $theme (array), $pageTitle, $metaDesc,
 * optional $activeNav, $extraHead.
 */
if (!defined('FL_APP')) { exit; }
$siteName = Settings::get('site_name', 'Ligas de Fútbol');
$logo = Settings::get('logo');
$fav  = Settings::get('favicon');
$vapidPublic = (string)Settings::get('vapid_public', '');
$pushEnabled = Settings::bool('push_enabled', false) && $vapidPublic !== '';
$nav = [
    'inicio'    => ['Inicio', base_url('index.php')],
    'ligas'     => ['Ligas', base_url('index.php#ligas')],
    'noticias'  => ['Noticias', base_url('index.php#noticias')],
];
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? $siteName) ?></title>
<meta name="description" content="<?= e($metaDesc ?? Settings::get('seo_description', '')) ?>">
<meta property="og:title" content="<?= e($pageTitle ?? $siteName) ?>">
<meta property="og:description" content="<?= e($metaDesc ?? Settings::get('seo_description', '')) ?>">
<meta property="og:type" content="website">
<?php if ($fav): ?><link rel="icon" href="<?= e(base_url($fav)) ?>"><?php else: ?>
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="46" fill="#111"/><text x="50" y="70" font-size="56" text-anchor="middle">⚽</text></svg>') ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
<link rel="manifest" href="<?= e(base_url('manifest.webmanifest')) ?>">
<meta name="theme-color" content="<?= e($theme['color_bg']) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e($siteName) ?>">
<link rel="apple-touch-icon" href="<?= e(base_url('assets/img/icon-192.png')) ?>">
<meta name="csrf-token" content="<?= e(Security::csrfToken()) ?>">
<style><?= Theme::styleBlock($theme) ?></style>
<script>
window.FL_BASE = <?= json_encode(base_url('')) ?>;
window.FL_CSRF = <?= json_encode(Security::csrfToken()) ?>;
window.FL_PUSH = <?= $pushEnabled ? 'true' : 'false' ?>;
window.FL_VAPID = <?= json_encode($pushEnabled ? $vapidPublic : '') ?>;
</script>
<?= $extraHead ?? '' ?>
</head>
<body>
<header class="site-header">
    <div class="container">
        <a class="brand" href="<?= e(base_url('index.php')) ?>">
            <?php if ($logo): ?><img src="<?= e(base_url($logo)) ?>" alt="" style="height:38px;width:auto;border-radius:8px">
            <?php else: ?><span class="brand-mark">⚽</span><?php endif; ?>
            <span><?= e($siteName) ?></span>
        </a>
        <button class="nav-toggle" aria-label="Menú">☰</button>
        <nav class="nav">
            <?php foreach ($nav as $key => $item): ?>
                <a href="<?= e($item[1]) ?>" class="<?= ($activeNav ?? '') === $key ? 'active' : '' ?>"><?= e($item[0]) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>

<div id="app-banner" hidden style="background:var(--c-surface);border-bottom:1px solid var(--c-border)">
    <div class="container" style="display:flex;gap:1rem;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:.6rem 0">
        <div style="display:flex;gap:.7rem;align-items:center">
            <span style="font-size:1.6rem">📲</span>
            <div>
                <strong>Descarga la app</strong>
                <div class="muted" style="font-size:.85rem">Instálala en tu teléfono (Android o iPhone) y recibe los resultados por notificación.</div>
            </div>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            <button class="btn btn-sm" id="app-install-btn" style="display:none">⬇️ Instalar app</button>
            <button class="btn btn-sm btn-ghost" id="app-ios-btn" style="display:none">🍎 Instalar en iPhone</button>
            <button class="btn btn-sm btn-accent" id="app-notify-btn" style="display:none">🔔 Activar notificaciones</button>
            <button class="btn btn-sm btn-ghost" id="app-close-btn" aria-label="Cerrar" title="Cerrar">✕</button>
        </div>
    </div>
    <div class="container" id="app-ios-help" hidden style="padding-bottom:.8rem">
        <div class="card" style="font-size:.88rem">
            <strong>Instalar en iPhone / iPad:</strong>
            <ol style="margin:.4rem 0 0;padding-left:1.2rem">
                <li>Abre esta página en <strong>Safari</strong>.</li>
                <li>Toca el botón <strong>Compartir</strong> (el cuadro con la flecha ↑).</li>
                <li>Elige <strong>“Añadir a pantalla de inicio”</strong>.</li>
                <li>Abre la app desde el ícono para activar las notificaciones.</li>
            </ol>
        </div>
    </div>
</div>
<script>
(function () {
    var base = window.FL_BASE || './';
    if ('serviceWorker' in navigator) { navigator.serviceWorker.register(base + 'sw.js').catch(function () {}); }

    var banner = document.getElementById('app-banner');
    var installBtn = document.getElementById('app-install-btn');
    var iosBtn = document.getElementById('app-ios-btn');
    var iosHelp = document.getElementById('app-ios-help');
    var notifyBtn = document.getElementById('app-notify-btn');
    var closeBtn = document.getElementById('app-close-btn');
    if (!banner) { return; }
    var dismissed = localStorage.getItem('fl_app_banner') === 'off';
    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
    function show() { if (!dismissed && !standalone) { banner.hidden = false; } }

    var deferred = null;
    window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferred = e; installBtn.style.display = 'inline-flex'; show(); });
    installBtn.addEventListener('click', function () { if (deferred) { deferred.prompt(); deferred.userChoice.finally(function () { deferred = null; installBtn.style.display = 'none'; }); } });

    if (isIOS && !standalone) { iosBtn.style.display = 'inline-flex'; }
    iosBtn.addEventListener('click', function () { iosHelp.hidden = !iosHelp.hidden; });

    function b64ToUint8(b) { var p = '='.repeat((4 - b.length % 4) % 4); var s = (b + p).replace(/-/g, '+').replace(/_/g, '/'); var raw = atob(s); var a = new Uint8Array(raw.length); for (var i = 0; i < raw.length; i++) { a[i] = raw.charCodeAt(i); } return a; }
    if (window.FL_PUSH && 'PushManager' in window && 'serviceWorker' in navigator && window.FL_VAPID) { notifyBtn.style.display = 'inline-flex'; }
    notifyBtn.addEventListener('click', function () {
        if (!('Notification' in window)) { return; }
        Notification.requestPermission().then(function (perm) {
            if (perm !== 'granted') { alert('Activa el permiso de notificaciones para recibir los resultados.'); return; }
            navigator.serviceWorker.ready.then(function (reg) {
                return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToUint8(window.FL_VAPID) });
            }).then(function (sub) {
                var j = sub.toJSON();
                return fetch(base + 'push-subscribe.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.FL_CSRF }, body: JSON.stringify({ endpoint: j.endpoint, keys: j.keys }) });
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (d && d.ok) { notifyBtn.textContent = '✅ Notificaciones activadas'; notifyBtn.disabled = true; }
                else { alert('No se pudo activar las notificaciones.'); }
            }).catch(function () { alert('No se pudo activar las notificaciones.'); });
        });
    });

    closeBtn.addEventListener('click', function () { banner.hidden = true; localStorage.setItem('fl_app_banner', 'off'); });
    show();
})();
</script>

<main>
