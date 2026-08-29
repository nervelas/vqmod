/* =====================================================================
   MenúGold · Service Worker
   Precarga el esqueleto, guarda las fotos del menú y usa red primero
   para los pedidos. Página elegante sin conexión.
   ===================================================================== */
'use strict';

var VERSION = '{{VERSION}}';
var BASE = '{{BASE}}';
var CACHE_SHELL = 'mg-shell-' + VERSION;
var CACHE_FOTOS = 'mg-fotos-v1';
var CACHE_PAGS  = 'mg-pags-v1';
var OFFLINE = '{{OFFLINE}}';

var PRECARGA = [
  OFFLINE,
  '{{CSS}}',
  '{{JS}}',
  BASE + '/assets/css/base.css',
  BASE + '/assets/css/temas.css'
];

var MAX_FOTOS = 140;

self.addEventListener('install', function (ev) {
  ev.waitUntil(
    caches.open(CACHE_SHELL).then(function (c) {
      return Promise.all(PRECARGA.map(function (u) {
        return c.add(new Request(u, { cache: 'reload' })).catch(function () {});
      }));
    }).then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (ev) {
  ev.waitUntil(
    caches.keys().then(function (llaves) {
      return Promise.all(llaves.map(function (k) {
        if (k !== CACHE_SHELL && k !== CACHE_FOTOS && k !== CACHE_PAGS) return caches.delete(k);
        return null;
      }));
    }).then(function () { return self.clients.claim(); })
  );
});

function esFoto(req) {
  return req.destination === 'image' || /\.(png|jpe?g|webp|gif|svg)(\?|$)/i.test(req.url);
}
function esEstatico(req) {
  return req.destination === 'style' || req.destination === 'script' || req.destination === 'font'
      || /\.(css|js|woff2?)(\?|$)/i.test(req.url);
}
function esApi(url) {
  return url.pathname.indexOf('/api/') >= 0 || url.pathname.indexOf('/panel/') >= 0
      || url.pathname.indexOf('/super/') >= 0 || url.pathname.indexOf('/install') >= 0;
}

function recortarCache(nombre, max) {
  caches.open(nombre).then(function (c) {
    c.keys().then(function (ks) {
      if (ks.length <= max) return;
      for (var i = 0; i < ks.length - max; i++) c.delete(ks[i]);
    });
  });
}

self.addEventListener('fetch', function (ev) {
  var req = ev.request;
  if (req.method !== 'GET') return;

  var url;
  try { url = new URL(req.url); } catch (e) { return; }
  if (url.origin !== self.location.origin) return;
  if (esApi(url)) return;                      // pedidos y panel: siempre a la red
  if (url.pathname.indexOf('/sw.js') >= 0) return;

  // --- Fotos del menú: caché primero, se refrescan en segundo plano ---
  if (esFoto(req)) {
    ev.respondWith(
      caches.open(CACHE_FOTOS).then(function (c) {
        return c.match(req).then(function (hit) {
          var red = fetch(req).then(function (res) {
            if (res && res.status === 200) {
              c.put(req, res.clone());
              recortarCache(CACHE_FOTOS, MAX_FOTOS);
            }
            return res;
          }).catch(function () { return hit; });
          return hit || red;
        });
      })
    );
    return;
  }

  // --- CSS, JS y fuentes: caché primero ---
  if (esEstatico(req)) {
    ev.respondWith(
      caches.match(req).then(function (hit) {
        return hit || fetch(req).then(function (res) {
          if (res && res.status === 200) {
            var copia = res.clone();
            caches.open(CACHE_SHELL).then(function (c) { c.put(req, copia); });
          }
          return res;
        });
      }).catch(function () { return caches.match(OFFLINE); })
    );
    return;
  }

  // --- Páginas: red primero, con respaldo de caché ---
  if (req.mode === 'navigate' || (req.headers.get('accept') || '').indexOf('text/html') >= 0) {
    ev.respondWith(
      fetch(req).then(function (res) {
        if (res && res.status === 200) {
          var copia = res.clone();
          caches.open(CACHE_PAGS).then(function (c) {
            c.put(req, copia);
            recortarCache(CACHE_PAGS, 40);
          });
        }
        return res;
      }).catch(function () {
        return caches.match(req).then(function (hit) {
          return hit || caches.match(OFFLINE);
        });
      })
    );
  }
});

self.addEventListener('message', function (ev) {
  if (ev.data === 'saltar-espera') self.skipWaiting();
});
