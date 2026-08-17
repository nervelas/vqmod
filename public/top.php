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
$__ligaUrl = ($__lg && $__lg['visibility'] === 'public')
    ? base_url('liga.php?slug=' . urlencode($__lg['slug']))
    : base_url('index.php');
$nav = [
    'inicio'    => ['Inicio', base_url('index.php')],
    'liga'      => ['La Liga', $__ligaUrl],
    'noticias'  => ['Noticias', $__ligaUrl . '#noticias'],
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
                    <a href="<?= e($item[1]) ?>" class="<?= ($activeNav ?? '') === $key ? 'active' : '' ?>"><?= e($item[0]) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>
</header>

<script>
(function () {
    var base = window.FL_BASE || './';
    if (!('serviceWorker' in navigator)) { return; }

    // Register the service worker (required so Chrome treats this as an app).
    navigator.serviceWorker.register(base + 'sw.js').catch(function () {});

    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    if (standalone) { subscribePush(); return; } // already installed as an app

    var deferred = null, promptShown = false, armed = false;

    // Show Chrome's NATIVE install dialog (installs a real app / WebAPK).
    // prompt() must run inside a user gesture, so we fire it on the visitor's
    // very first interaction — it feels automatic and never logs a warning.
    function firePrompt() {
        if (!deferred || promptShown) { return; }
        promptShown = true;
        deferred.prompt();
        deferred.userChoice.then(function () { deferred = null; });
    }
    function armAutoPrompt() {
        if (armed) { return; }
        armed = true;
        var evs = ['pointerdown', 'touchend', 'click', 'keydown'];
        var handler = function () {
            evs.forEach(function (ev) { window.removeEventListener(ev, handler, true); });
            firePrompt();
        };
        evs.forEach(function (ev) { window.addEventListener(ev, handler, { capture: true, passive: true }); });
    }

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();       // suppress Chrome's mini-infobar…
        deferred = e;
        promptShown = false;
        armAutoPrompt();          // …and show the full native dialog automatically
    });

    window.addEventListener('appinstalled', function () {
        deferred = null;
        subscribePush();          // installed: turn on result notifications
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
