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
$__lg = the_league();
// Professional inline SVG icons for the public menu.
$__ico = [
    'liga'     => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V5z"/></svg>',
    'torneos'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 4h12v3a6 6 0 0 1-12 0z"/><path d="M6 5H3v1a3 3 0 0 0 3 3M18 5h3v1a3 3 0 0 1-3 3"/><path d="M9 13.5V17h6v-3.5M8 21h8M12 17v4"/></svg>',
    'noticias' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h13v14H6a2 2 0 0 1-2-2z"/><path d="M17 8h3v9a2 2 0 0 1-2 2M8 9h6M8 13h6"/></svg>',
];
$nav = [
    'liga'     => ['La Liga',  base_url('index.php'),    $__ico['liga']],
    'torneos'  => ['Torneos',  base_url('torneos.php'),  $__ico['torneos']],
    'noticias' => ['Noticias', base_url('noticias.php'), $__ico['noticias']],
];
?><!DOCTYPE html>
<html lang="es">
<head>
<script>document.documentElement.className+=' js';</script>
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
<link rel="manifest" href="<?= e(base_url('manifest.php')) ?>">
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
        <div style="display:flex;align-items:center;gap:.6rem">
            <button class="nav-toggle" aria-label="Menú">☰</button>
            <nav class="nav">
                <?php foreach ($nav as $key => $item): ?>
                    <a href="<?= e($item[1]) ?>" class="nav-link <?= ($activeNav ?? '') === $key ? 'active' : '' ?>"><span class="nav-ico"><?= $item[2] ?? '' ?></span><span><?= e($item[0]) ?></span></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>
</header>

<!-- PWA install invitation (shown only when the browser allows installation) -->
<div id="pwa-install" class="pwa-invite" hidden role="dialog" aria-label="Instalar aplicación">
    <div class="pwa-invite-in">
        <span class="pwa-ico">📲</span>
        <div class="pwa-text">
            <strong>Instalar aplicación</strong>
            <span id="pwa-sub">Añádela a tu teléfono para acceder más rápido y recibir resultados.</span>
        </div>
        <div class="pwa-actions">
            <button type="button" id="pwa-btn" class="btn btn-sm">Instalar</button>
            <button type="button" id="pwa-close" class="pwa-close" aria-label="Cerrar">✕</button>
        </div>
    </div>
</div>

<script>
(function () {
    var base = window.FL_BASE || './';
    if (!('serviceWorker' in navigator)) { return; }

    navigator.serviceWorker.register(base + 'sw.js').catch(function () {});

    var box = document.getElementById('pwa-install');
    var btn = document.getElementById('pwa-btn');
    var sub = document.getElementById('pwa-sub');
    var closeBtn = document.getElementById('pwa-close');

    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var ua = navigator.userAgent || '';
    var isIOS = /iphone|ipad|ipod/i.test(ua) && !window.MSStream;
    var deferred = null;

    if (standalone) { subscribePush(); return; }        // already installed → nothing to show
    if (sessionStorage.getItem('pwa_hide') === '1') { /* dismissed this session */ }

    function show() {
        if (box && sessionStorage.getItem('pwa_hide') !== '1') { box.hidden = false; }
    }
    function hide() { if (box) box.hidden = true; }
    if (closeBtn) closeBtn.addEventListener('click', function () { sessionStorage.setItem('pwa_hide', '1'); hide(); });

    // ---- Android / Chromium: capture the install event and show the button ----
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferred = e;
        if (btn) btn.style.display = '';
        show();
    });
    if (btn) btn.addEventListener('click', function () {
        if (!deferred) { return; }
        deferred.prompt();
        deferred.userChoice.then(function (c) {
            deferred = null;
            if (c && c.outcome === 'accepted') { hide(); }
        });
    });

    // ---- iOS / iPadOS Safari: no beforeinstallprompt → show manual steps ------
    if (isIOS) {
        if (sub) sub.innerHTML = 'Toca <strong>Compartir</strong> (el icono ⬆️) y luego <strong>“Añadir a pantalla de inicio”</strong>.';
        if (btn) btn.style.display = 'none';
        // give the page a moment so it isn't jarring on entry
        setTimeout(show, 1200);
    }

    window.addEventListener('appinstalled', function () {
        deferred = null; hide(); subscribePush();
    });

    // ---- Push notifications (only if the admin enabled them) ----------------
    function b64ToUint8(b) { var p = '='.repeat((4 - b.length % 4) % 4); var s = (b + p).replace(/-/g, '+').replace(/_/g, '/'); var raw = atob(s); var a = new Uint8Array(raw.length); for (var i = 0; i < raw.length; i++) { a[i] = raw.charCodeAt(i); } return a; }
    function subscribePush() {
        if (!(window.FL_PUSH && 'PushManager' in window && window.FL_VAPID && 'Notification' in window)) { return; }
        if (Notification.permission === 'denied') { return; }
        Notification.requestPermission().then(function (perm) {
            if (perm !== 'granted') { return; }
            navigator.serviceWorker.ready.then(function (reg) {
                return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToUint8(window.FL_VAPID) });
            }).then(function (sub) {
                var j = sub.toJSON();
                return fetch(base + 'push-subscribe.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.FL_CSRF }, body: JSON.stringify({ endpoint: j.endpoint, keys: j.keys }) });
            }).catch(function () {});
        }).catch(function () {});
    }
})();
</script>

<main>
