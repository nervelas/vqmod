/*!
 * ResidencialPro — Service Worker
 * Precarga del armazón, caché de recursos y modo sin conexión para la garita.
 */
const VERSION = 'rpro-v6';
const CACHE_ESTATICO = VERSION + '-estatico';
const CACHE_DATOS    = VERSION + '-datos';

const RAIZ = new URL(self.registration.scope).pathname.replace(/\/$/, '');
const ruta = (p) => RAIZ + p;

const PRECARGA = [
  ruta('/'),
  ruta('/sin-conexion'),
  ruta('/assets/css/app.css'),
  ruta('/assets/css/fuentes-locales.css'),
  ruta('/assets/fonts/archivo-variable-latin.woff2'),
  ruta('/assets/fonts/fraunces-variable-latin.woff2'),
  ruta('/assets/fonts/plexmono-400-latin.woff2'),
  ruta('/assets/js/app.js'),
  ruta('/assets/js/garita.js'),
  ruta('/assets/vendor/grafica.js'),
  // El lector de QR debe estar disponible aunque la garita se quede sin red.
  ruta('/assets/vendor/jsqr.js'),
  ruta('/assets/img/icono-192.png'),
  ruta('/assets/img/icono-512.png'),
  ruta('/manifest.json'),
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE_ESTATICO)
      .then((c) => Promise.allSettled(PRECARGA.map((u) => c.add(new Request(u, { cache: 'reload' })))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((llaves) => Promise.all(llaves.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Recursos estáticos: primero la caché.
  if (/\.(css|js|png|jpg|jpeg|webp|svg|ico|woff2?)$/i.test(url.pathname)) {
    e.respondWith(
      caches.match(req).then((hit) => hit || fetch(req).then((r) => {
        const copia = r.clone();
        caches.open(CACHE_ESTATICO).then((c) => c.put(req, copia));
        return r;
      }).catch(() => hit))
    );
    return;
  }

  // Documentos y datos: primero la red, con reserva en caché.
  e.respondWith(
    fetch(req)
      .then((r) => {
        if (r.ok && req.mode === 'navigate') {
          const copia = r.clone();
          caches.open(CACHE_DATOS).then((c) => c.put(req, copia));
        }
        return r;
      })
      .catch(() => caches.match(req).then((hit) => hit || caches.match(ruta('/sin-conexion'))))
  );
});

/* Notificaciones push */
self.addEventListener('push', (e) => {
  let d = { titulo: 'ResidencialPro', cuerpo: '', url: ruta('/portal') };
  try { if (e.data) d = Object.assign(d, e.data.json()); } catch (x) {
    try { d.cuerpo = e.data ? e.data.text() : ''; } catch (y) {}
  }
  e.waitUntil(self.registration.showNotification(d.titulo, {
    body: d.cuerpo,
    icon: d.icono || ruta('/assets/img/icono-192.png'),
    badge: ruta('/assets/img/icono-96.png'),
    vibrate: [90, 50, 90],
    tag: d.tag || 'rpro',
    data: { url: d.url || ruta('/portal') },
  }));
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const destino = (e.notification.data && e.notification.data.url) || ruta('/portal');
  e.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((lista) => {
      for (const c of lista) {
        if (c.url.includes(RAIZ) && 'focus' in c) { c.navigate(destino); return c.focus(); }
      }
      return self.clients.openWindow(destino);
    })
  );
});

/* Sincronización en segundo plano de la garita */
self.addEventListener('sync', (e) => {
  if (e.tag === 'rp-garita') {
    e.waitUntil(self.clients.matchAll().then((l) => l.forEach((c) => c.postMessage({ tipo: 'sincronizar' }))));
  }
});
