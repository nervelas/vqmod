/* ============================================================
   EduPortal · Service Worker
   Precaché del shell + estrategia network-first para datos.
   ============================================================ */
const VERSION = 'eduportal-v1';
const CACHE_SHELL = VERSION + '-shell';
const CACHE_DATOS = VERSION + '-datos';

const ALCANCE = new URL(self.registration.scope).pathname;
const ruta = (r) => ALCANCE.replace(/\/$/, '') + '/' + String(r).replace(/^\//, '');

const SHELL = [
  ruta('offline'),
  ruta('assets/css/app.css'),
  ruta('assets/css/paginas.css'),
  ruta('assets/js/app.js'),
  ruta('assets/fonts/inter-latin-400-normal.woff2'),
  ruta('assets/fonts/inter-latin-600-normal.woff2'),
  ruta('assets/fonts/playfair-display-latin-600-normal.woff2'),
  ruta('assets/icons/icon-192.png'),
  ruta('assets/icons/icon-512.png')
];

self.addEventListener('install', (evento) => {
  evento.waitUntil(
    caches.open(CACHE_SHELL)
      .then((c) => Promise.allSettled(SHELL.map((u) => c.add(u))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (evento) => {
  evento.waitUntil(
    caches.keys()
      .then((claves) => Promise.all(
        claves.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (evento) => {
  const peticion = evento.request;
  if (peticion.method !== 'GET') return;

  const destino = new URL(peticion.url);
  if (destino.origin !== self.location.origin) return;

  // Nunca almacenar en caché documentos privados sensibles ni descargas.
  if (/\/(recibo|boleta|boletas|carne|carnes|archivo|config\/respaldo)\//.test(destino.pathname)) {
    return;
  }

  // Navegación: red primero, con página offline elegante de respaldo.
  if (peticion.mode === 'navigate') {
    evento.respondWith(
      fetch(peticion)
        .then((r) => {
          const copia = r.clone();
          caches.open(CACHE_DATOS).then((c) => c.put(peticion, copia)).catch(() => {});
          return r;
        })
        .catch(() => caches.match(peticion).then((c) => c || caches.match(ruta('offline'))))
    );
    return;
  }

  // Recursos estáticos: caché primero.
  if (/\.(css|js|woff2|png|jpg|jpeg|svg|webp|ico)$/.test(destino.pathname)) {
    evento.respondWith(
      caches.match(peticion).then((c) => c || fetch(peticion).then((r) => {
        const copia = r.clone();
        if (r.ok) caches.open(CACHE_SHELL).then((cache) => cache.put(peticion, copia)).catch(() => {});
        return r;
      }))
    );
    return;
  }

  // Datos: red primero con respaldo en caché.
  evento.respondWith(
    fetch(peticion)
      .then((r) => {
        const copia = r.clone();
        if (r.ok) caches.open(CACHE_DATOS).then((c) => c.put(peticion, copia)).catch(() => {});
        return r;
      })
      .catch(() => caches.match(peticion))
  );
});

self.addEventListener('push', (evento) => {
  let datos = { titulo: 'EduPortal', cuerpo: 'Tiene una novedad en el portal.', url: ruta('portal') };
  try {
    if (evento.data) Object.assign(datos, evento.data.json());
  } catch (e) {
    if (evento.data) datos.cuerpo = evento.data.text();
  }
  evento.waitUntil(
    self.registration.showNotification(datos.titulo, {
      body: datos.cuerpo,
      icon: ruta('assets/icons/icon-192.png'),
      badge: ruta('assets/icons/icon-96.png'),
      data: { url: datos.url },
      lang: 'es'
    })
  );
});

self.addEventListener('notificationclick', (evento) => {
  evento.notification.close();
  const destino = (evento.notification.data && evento.notification.data.url) || ruta('portal');
  evento.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((lista) => {
      for (const cliente of lista) {
        if (cliente.url.includes(destino) && 'focus' in cliente) return cliente.focus();
      }
      return self.clients.openWindow(destino);
    })
  );
});
