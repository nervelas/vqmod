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
        <div style="display:flex;align-items:center;gap:.6rem">
            <button class="btn btn-sm" id="app-install" style="display:none">📲 Instalar app</button>
            <button class="nav-toggle" aria-label="Menú">☰</button>
            <nav class="nav">
                <?php foreach ($nav as $key => $item): ?>
                    <a href="<?= e($item[1]) ?>" class="<?= ($activeNav ?? '') === $key ? 'active' : '' ?>"><?= e($item[0]) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>
</header>

<div id="app-banner" hidden style="background:var(--c-surface);border-bottom:1px solid var(--c-border)">
    <div class="container" style="display:flex;gap:1rem;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:.6rem 0">
        <div style="display:flex;gap:.7rem;align-items:center">
            <span style="font-size:1.6rem">📲</span>
            <div>
                <strong>Descarga la app</strong>
                <div class="muted" style="font-size:.85rem">Instálala en tu teléfono y consulta la información cuando quieras. Todo lo que cambie en la web se ve en la app.</div>
            </div>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            <button class="btn btn-sm" id="app-install-btn">⬇️ Instalar app</button>
            <button class="btn btn-sm btn-accent" id="app-notify-btn" style="display:none">🔔 Activar notificaciones</button>
            <button class="btn btn-sm btn-ghost" id="app-close-btn" aria-label="Cerrar" title="Cerrar">✕</button>
        </div>
    </div>
</div>

<!-- How-to-install modal -->
<div id="app-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:200;align-items:center;justify-content:center;padding:1rem" role="dialog" aria-modal="true">
    <div class="card card-pad-lg" style="max-width:440px;width:100%">
        <div class="flex justify-between items-center" style="margin-bottom:.4rem">
            <h3 style="margin:0">📲 Instalar la app</h3>
            <button class="btn btn-sm btn-ghost" id="app-modal-close" aria-label="Cerrar">✕</button>
        </div>
        <div id="app-steps-android">
            <p class="muted" style="margin:.2rem 0 .6rem">En tu teléfono Android (Chrome):</p>
            <ol style="margin:0;padding-left:1.2rem;line-height:1.9">
                <li>Abre el menú <strong>⋮</strong> (arriba a la derecha).</li>
                <li>Toca <strong>“Instalar aplicación”</strong> o <strong>“Agregar a pantalla de inicio”</strong>.</li>
                <li>Confirma. El ícono quedará en tu pantalla de inicio.</li>
            </ol>
        </div>
        <div id="app-steps-ios">
            <p class="muted" style="margin:.2rem 0 .6rem">En iPhone / iPad (Safari):</p>
            <ol style="margin:0;padding-left:1.2rem;line-height:1.9">
                <li>Toca el botón <strong>Compartir</strong> (cuadro con la flecha ↑).</li>
                <li>Elige <strong>“Añadir a pantalla de inicio”</strong>.</li>
                <li>Toca <strong>Añadir</strong>. Abre la app desde el ícono.</li>
            </ol>
        </div>
        <p class="help mt-2">Al abrir desde el ícono, verás siempre la información actualizada del sitio.</p>
    </div>
</div>

<script>
(function () {
    var base = window.FL_BASE || './';
    if ('serviceWorker' in navigator) { navigator.serviceWorker.register(base + 'sw.js').catch(function () {}); }

    var banner = document.getElementById('app-banner');
    var headerBtn = document.getElementById('app-install');
    var installBtn = document.getElementById('app-install-btn');
    var notifyBtn = document.getElementById('app-notify-btn');
    var closeBtn = document.getElementById('app-close-btn');
    var modal = document.getElementById('app-modal');
    var modalClose = document.getElementById('app-modal-close');
    var stepsAndroid = document.getElementById('app-steps-android');
    var stepsIOS = document.getElementById('app-steps-ios');

    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
    var deferred = null;

    // The install option is always available to every visitor (until installed).
    if (!standalone && headerBtn) { headerBtn.style.display = 'inline-flex'; }
    // Show the banner once per session so every new visitor sees it.
    if (banner && !standalone && sessionStorage.getItem('fl_app_banner') !== 'off') { banner.hidden = false; }

    window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferred = e; });
    window.addEventListener('appinstalled', function () {
        if (headerBtn) headerBtn.style.display = 'none';
        if (banner) banner.hidden = true;
    });

    function openModal() {
        if (stepsAndroid) stepsAndroid.style.display = isIOS ? 'none' : 'block';
        if (stepsIOS) stepsIOS.style.display = isIOS ? 'block' : 'none';
        modal.style.display = 'flex';
    }
    function closeModal() { modal.style.display = 'none'; }

    function doInstall() {
        if (deferred) {
            deferred.prompt();
            deferred.userChoice.finally(function () { deferred = null; });
        } else {
            openModal(); // iOS, or Android before the prompt is ready: show clear steps
        }
    }
    if (headerBtn) headerBtn.addEventListener('click', doInstall);
    if (installBtn) installBtn.addEventListener('click', doInstall);
    if (modalClose) modalClose.addEventListener('click', closeModal);
    if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

    // Notifications opt-in (only if enabled by the admin)
    function b64ToUint8(b) { var p = '='.repeat((4 - b.length % 4) % 4); var s = (b + p).replace(/-/g, '+').replace(/_/g, '/'); var raw = atob(s); var a = new Uint8Array(raw.length); for (var i = 0; i < raw.length; i++) { a[i] = raw.charCodeAt(i); } return a; }
    if (window.FL_PUSH && 'PushManager' in window && 'serviceWorker' in navigator && window.FL_VAPID && notifyBtn) { notifyBtn.style.display = 'inline-flex'; }
    if (notifyBtn) notifyBtn.addEventListener('click', function () {
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

    if (closeBtn) closeBtn.addEventListener('click', function () { banner.hidden = true; sessionStorage.setItem('fl_app_banner', 'off'); });
})();
</script>

<main>
