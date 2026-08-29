/* CotizaPro B2B — service worker: precache, caché de estáticos y página offline. */
var BASE = '__BASE__';
var VERSION = 'cotizapro-__VERSION__';
var SHELL = VERSION + '-shell';
var ASSETS = VERSION + '-assets';
var OFFLINE = BASE + '/offline';

var PRECACHE = [
  OFFLINE,
  BASE + '/assets/css/app.css',
  BASE + '/assets/css/panel.css',
  BASE + '/assets/js/app.js',
  BASE + '/assets/js/panel.js',
  BASE + '/assets/fonts/Inter-400.woff2',
  BASE + '/assets/fonts/Inter-600.woff2',
  BASE + '/assets/fonts/SpaceGrotesk-700.woff2'
];

self.addEventListener('install', function (ev) {
  ev.waitUntil(
    caches.open(SHELL).then(function (c) {
      return Promise.all(PRECACHE.map(function (u) {
        return c.add(new Request(u, { cache: 'reload' })).catch(function () { return null; });
      }));
    }).then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (ev) {
  ev.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (k) {
        if (k.indexOf('cotizapro-') === 0 && k.indexOf(VERSION) !== 0) { return caches.delete(k); }
        return null;
      }));
    }).then(function () { return self.clients.claim(); })
  );
});

function isAsset(url) {
  return /\/assets\/|\/media\/|\.(css|js|woff2|png|jpg|jpeg|webp|svg|ico)$/i.test(url.pathname);
}

self.addEventListener('fetch', function (ev) {
  var req = ev.request;
  if (req.method !== 'GET') { return; }
  var url;
  try { url = new URL(req.url); } catch (e) { return; }
  if (url.origin !== self.location.origin) { return; }
  // Nunca se cachea el panel, el superadmin ni las descargas.
  if (/\/(panel|super|install|cron|c\/)/.test(url.pathname) && !isAsset(url)) {
    ev.respondWith(fetch(req).catch(function () { return caches.match(OFFLINE); }));
    return;
  }
  if (isAsset(url)) {
    ev.respondWith(
      caches.open(ASSETS).then(function (cache) {
        return cache.match(req).then(function (hit) {
          var net = fetch(req).then(function (res) {
            if (res && res.status === 200 && res.type === 'basic') { cache.put(req, res.clone()); }
            return res;
          }).catch(function () { return hit; });
          return hit || net;
        });
      })
    );
    return;
  }
  // Documentos: red primero, caché como respaldo, offline como último recurso.
  ev.respondWith(
    fetch(req).then(function (res) {
      if (res && res.status === 200 && res.type === 'basic') {
        var copy = res.clone();
        caches.open(SHELL).then(function (c) { c.put(req, copy); });
      }
      return res;
    }).catch(function () {
      return caches.match(req).then(function (hit) { return hit || caches.match(OFFLINE); });
    })
  );
});
