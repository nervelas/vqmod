/* Service worker: installability + push notifications for the PWA. */
const CACHE = 'fl-shell-v1';

self.addEventListener('install', function (e) {
    self.skipWaiting();
});

self.addEventListener('activate', function (e) {
    e.waitUntil(self.clients.claim());
});

/* Network-first for navigations so website changes always show in the app. */
self.addEventListener('fetch', function (e) {
    const req = e.request;
    if (req.method !== 'GET') return;
    if (req.mode === 'navigate') {
        e.respondWith(
            fetch(req).then(function (res) {
                const copy = res.clone();
                caches.open(CACHE).then(function (c) { c.put(req, copy); });
                return res;
            }).catch(function () { return caches.match(req); })
        );
    }
});

/* Incoming push: show the notification. */
self.addEventListener('push', function (e) {
    let data = { title: 'Liga de Fútbol', body: '', url: './' };
    try { if (e.data) { data = Object.assign(data, e.data.json()); } }
    catch (_) { if (e.data) { data.body = e.data.text(); } }
    e.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            data: { url: data.url || './' },
            icon: 'assets/img/icon-192.png',
            badge: 'assets/img/icon-192.png',
            vibrate: [80, 40, 80]
        })
    );
});

/* Tap on a notification: focus/open the relevant page. */
self.addEventListener('notificationclick', function (e) {
    e.notification.close();
    const url = (e.notification.data && e.notification.data.url) || './';
    e.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (cl) {
            for (const c of cl) { if ('focus' in c) { try { c.navigate(url); } catch (_) {} return c.focus(); } }
            if (clients.openWindow) { return clients.openWindow(url); }
        })
    );
});
