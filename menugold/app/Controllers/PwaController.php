<?php
namespace MenuGold\Controllers;

use MenuGold\Core\Controller;
use MenuGold\Core\Image;
use MenuGold\Core\Response;
use MenuGold\Core\Url;
use MenuGold\Models\Landing;
use MenuGold\Models\Restaurant;

/** Manifiesto dinámico, service worker y página sin conexión. */
class PwaController extends Controller
{
    public function manifest(array $params = array())
    {
        $r = !empty($params['slug']) ? Restaurant::findBySlug($params['slug']) : null;

        if ($r) {
            $name  = $r['name'];
            $short = mb_substr($r['name'], 0, 12);
            // start_url debe quedar DENTRO del scope: ambos con barra final.
            $scope = Url::to('/r/' . $r['slug']) . '/';
            $start = $scope;
            $theme = $r['primary_color'];
            $icons = $this->iconsFor($r);
            $desc  = $r['tagline'] !== '' ? $r['tagline'] : 'Menú digital de ' . $r['name'];
        } else {
            $name  = Landing::v('brand_name');
            $short = 'MenúGold';
            $start = Url::to('/');
            $scope = Url::to('/');
            $theme = '#D8B26E';
            $icons = $this->defaultIcons();
            $desc  = Landing::v('seo_description');
        }

        $manifest = array(
            'name'             => $name,
            'short_name'       => $short,
            'description'      => $desc,
            'id'               => $scope === '' ? '/' : $scope,
            'start_url'        => $start === '' ? '/' : $start,
            'scope'            => $scope === '' ? '/' : $scope,
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => '#0C0B09',
            'theme_color'      => $theme,
            'lang'             => $r ? $r['lang_default'] : 'es',
            'dir'              => 'ltr',
            'categories'       => array('food', 'shopping', 'business'),
            'icons'            => $icons,
        );

        return Response::make(
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            200,
            array('Content-Type' => 'application/manifest+json; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600')
        );
    }

    private function iconsFor(array $r)
    {
        $dir = MG_ROOT . '/uploads/' . (int)$r['id'] . '/icons';
        if (!is_dir($dir) || !is_file($dir . '/icon-512.png')) {
            return $this->defaultIcons();
        }
        $icons = array();
        foreach (array(72, 96, 128, 144, 152, 192, 384, 512) as $s) {
            if (!is_file($dir . '/icon-' . $s . '.png')) { continue; }
            $icons[] = array(
                'src'     => Url::to('/uploads/' . (int)$r['id'] . '/icons/icon-' . $s . '.png'),
                'sizes'   => $s . 'x' . $s,
                'type'    => 'image/png',
                'purpose' => 'any maskable',
            );
        }
        return $icons ? $icons : $this->defaultIcons();
    }

    private function defaultIcons()
    {
        $icons = array();
        foreach (array(72, 96, 128, 144, 152, 192, 384, 512) as $s) {
            if (!is_file(MG_ROOT . '/assets/icons/icon-' . $s . '.png')) { continue; }
            $icons[] = array(
                'src'     => Url::to('/assets/icons/icon-' . $s . '.png'),
                'sizes'   => $s . 'x' . $s,
                'type'    => 'image/png',
                'purpose' => 'any maskable',
            );
        }
        return $icons;
    }

    /** Service worker generado: precache mínimo + red primero para el HTML. */
    public function serviceWorker()
    {
        $base = Url::basePath();
        // Los estáticos se sirven desde la caché primero, así que la versión tiene
        // que moverse en cuanto cambie CUALQUIERA de ellos: si solo mirara core.css,
        // una actualización de menu.js se quedaría congelada en el teléfono.
        $archivos = array(
            'assets/css/core.css', 'assets/css/fonts.css', 'assets/css/menu.css',
            'assets/js/motion.js', 'assets/js/menu.js',
        );
        $sello = '';
        foreach ($archivos as $f) { $sello .= $f . ':' . (int)@filemtime(MG_ROOT . '/' . $f) . '|'; }
        $version = MG_VERSION . '-' . substr(md5($sello), 0, 8);
        $precache = array(
            $base . '/assets/css/core.css',
            $base . '/assets/css/fonts.css',
            $base . '/assets/css/menu.css',
            $base . '/assets/js/motion.js',
            $base . '/assets/js/menu.js',
            $base . '/sin-conexion',
        );

        $js = "/* MenúGold · service worker generado */\n"
            . "const VERSION = " . json_encode($version) . ";\n"
            . "const CACHE = 'menugold-' + VERSION;\n"
            . "const OFFLINE = " . json_encode($base . '/sin-conexion') . ";\n"
            . "const PRECACHE = " . json_encode($precache) . ";\n"
            . <<<'JS'

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE)
      .then((cache) => cache.addAll(PRECACHE).catch(() => null))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') { return; }

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) { return; }
  // El panel y las llamadas de datos nunca se cachean.
  if (/\/(panel|super|api|install)(\/|$)/.test(url.pathname)) { return; }

  // Navegación: red primero, caché como respaldo, y página sin conexión al final.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
          return res;
        })
        .catch(() => caches.match(req).then((hit) => hit || caches.match(OFFLINE)))
    );
    return;
  }

  // Estáticos: caché primero.
  if (/\.(css|js|woff2|png|jpg|jpeg|webp|svg|ico)$/.test(url.pathname)) {
    event.respondWith(
      caches.match(req).then((hit) => hit || fetch(req).then((res) => {
        if (res && res.status === 200 && res.type === 'basic') {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
        }
        return res;
      }).catch(() => hit))
    );
  }
});
JS;

        return Response::make($js, 200, array(
            'Content-Type'          => 'application/javascript; charset=UTF-8',
            'Cache-Control'         => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => Url::basePath() === '' ? '/' : Url::basePath() . '/',
        ));
    }

    public function offline()
    {
        return $this->view('errors/offline');
    }
}
