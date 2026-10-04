/* Agenda Premium · service worker mínimo de la carcasa del panel.
 * Guarda estilos, scripts, fuentes e iconos (stale-while-revalidate). Nunca toca HTML, /_/ ni /api/ ni nada autenticado. */
'use strict';
var CACHE = 'ap-shell-v1';
var SCOPE = new URL(self.registration.scope).pathname.replace(/\/$/, '');
var ASSET = /\.(?:css|js|woff2|svg|png|webp|ico)$/i;

self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (keys) {
    return Promise.all(keys.filter(function (k) { return k.indexOf('ap-shell-') === 0 && k !== CACHE; }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});

self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.method !== 'GET') { return; }
  var url = new URL(req.url);
  if (url.origin !== self.location.origin) { return; }
  var path = url.pathname.slice(SCOPE.length);
  if (path.indexOf('/assets/') !== 0 || !ASSET.test(path)) { return; }
  if (path.indexOf('/_/') === 0 || path.indexOf('/api/') === 0) { return; }
  e.respondWith(caches.open(CACHE).then(function (cache) {
    return cache.match(req).then(function (hit) {
      var net = fetch(req).then(function (res) {
        if (res && res.status === 200 && res.type === 'basic') { cache.put(req, res.clone()); }
        return res;
      }).catch(function () { return hit; });
      return hit || net;
    });
  }));
});
