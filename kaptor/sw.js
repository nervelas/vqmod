/* ===========================================================================
   Kaptor · Service worker
   Permite instalar Kaptor como aplicación y que abra aunque no haya conexión.

   Estrategia:
   · Los archivos que no cambian (tipografías, imágenes, CSS, JS) se sirven
     desde la caché y se actualizan en segundo plano.
   · Las páginas van siempre a la red primero, para que los datos y el tema
     estén al día; si no hay conexión se sirve la copia guardada.
   · Nada relacionado con el panel, la API o el envío de campañas se guarda:
     esos datos deben ser siempre los de verdad.
   =========================================================================== */

const VERSION = 'kaptor-v14';
const CACHE_ESTATICA = VERSION + '-estatica';
const CACHE_PAGINAS  = VERSION + '-paginas';

/* Ruta base: el service worker puede vivir en la raíz o en una subcarpeta. */
const BASE = new URL('./', self.location).pathname;

const ESENCIALES = [
  BASE,
  BASE + 'assets/css/app.css',
  BASE + 'assets/js/app.js',
  BASE + 'assets/fonts/fonts.css',
  BASE + 'assets/img/icono-192.png',
  BASE + 'assets/img/icono-512.png',
];

self.addEventListener('install', (evento) => {
  evento.waitUntil(
    caches.open(CACHE_ESTATICA)
      .then((c) => c.addAll(ESENCIALES.map((u) => new Request(u, { cache: 'reload' }))))
      .catch(() => null)          // si algo falla, la instalación no se cae
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (evento) => {
  evento.waitUntil(
    caches.keys()
      .then((claves) => Promise.all(
        claves.filter((c) => !c.startsWith(VERSION)).map((c) => caches.delete(c))
      ))
      .then(() => self.clients.claim())
  );
});

function esEstatico(url) {
  return /\.(css|js|woff2?|png|jpe?g|svg|webp|ico)$/i.test(url.pathname);
}

function noSeGuarda(url) {
  return url.pathname.includes('/admin/')
      || url.pathname.includes('/api/')
      || url.pathname.endsWith('/cron.php')
      || url.pathname.endsWith('/install.php')
      || url.searchParams.has('descargar');
}

self.addEventListener('fetch', (evento) => {
  const peticion = evento.request;
  if (peticion.method !== 'GET') { return; }

  const url = new URL(peticion.url);
  if (url.origin !== self.location.origin) { return; }
  if (noSeGuarda(url)) { return; }

  /* Archivos estáticos: primero la caché, y se refresca por detrás. */
  if (esEstatico(url)) {
    evento.respondWith(
      caches.match(peticion).then((guardada) => {
        const red = fetch(peticion).then((respuesta) => {
          if (respuesta && respuesta.ok) {
            const copia = respuesta.clone();
            caches.open(CACHE_ESTATICA).then((c) => c.put(peticion, copia));
          }
          return respuesta;
        }).catch(() => guardada);
        return guardada || red;
      })
    );
    return;
  }

  /* Páginas: primero la red; sin conexión, la última copia guardada. */
  evento.respondWith(
    fetch(peticion)
      .then((respuesta) => {
        if (respuesta && respuesta.ok && respuesta.type === 'basic') {
          const copia = respuesta.clone();
          caches.open(CACHE_PAGINAS).then((c) => c.put(peticion, copia));
        }
        return respuesta;
      })
      .catch(() => caches.match(peticion).then((g) => g || caches.match(BASE)))
  );
});
