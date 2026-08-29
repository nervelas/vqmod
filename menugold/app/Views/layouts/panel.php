<?php
/**
 * Layout del panel del restaurante.
 * @var array|null $usuario, $restaurante
 */
use MenuGold\Core\App;
use MenuGold\Core\Auth;
use MenuGold\Core\Security;
use MenuGold\Core\Setting;
use MenuGold\Core\View;

$u = $usuario ?? Auth::user() ?? [];
$r = $restaurante ?? App::restaurant() ?? [];
$modo = (string)($u['tema_panel'] ?? 'auto');
$uri  = App::uri();
$marca = (string)Setting::plat('nombre_plataforma', 'MenúGold');
$pendientes = $pendientes ?? ['pedidos' => 0, 'llamadas' => 0];

$activo = static function (string $ruta) use ($uri): string {
    $ruta = '/' . trim($ruta, '/');
    if ($ruta === '/panel') return $uri === '/panel' ? 'page' : 'false';
    return strncmp($uri, $ruta, strlen($ruta)) === 0 ? 'page' : 'false';
};

$menu = [
    ['grupo' => 'Operación', 'items' => [
        ['panel',          'panel',    'Escritorio',  'dueno,admin,mesero'],
        ['panel/cocina',   'chef',     'Cocina',      'dueno,admin,cocina,mesero', $pendientes['pedidos']],
        ['panel/mesero',   'table',    'Mesas y caja','dueno,admin,mesero',        $pendientes['llamadas']],
        ['panel/pedidos',  'receipt',  'Pedidos',     'dueno,admin,mesero'],
    ]],
    ['grupo' => 'Mi menú', 'items' => [
        ['panel/productos',     'utensils', 'Platillos',      'dueno,admin'],
        ['panel/categorias',    'layers',   'Categorías',     'dueno,admin'],
        ['panel/modificadores', 'grid',     'Modificadores',  'dueno,admin'],
        ['panel/promociones',   'percent',  'Promociones',    'dueno,admin'],
        ['panel/importar',      'excel',    'Importar Excel', 'dueno,admin'],
    ]],
    ['grupo' => 'Mi negocio', 'items' => [
        ['panel/mesas',    'qr',       'Mesas y QR',     'dueno,admin'],
        ['panel/clientes', 'users',    'Clientes',       'dueno,admin,mesero'],
        ['panel/cupones',  'ticket',   'Cupones',        'dueno,admin'],
        ['panel/reportes', 'chart',    'Reportes',       'dueno,admin'],
    ]],
    ['grupo' => 'Ajustes', 'items' => [
        ['panel/configuracion', 'settings', 'Configuración', 'dueno,admin'],
        ['panel/usuarios',      'user',     'Usuarios',      'dueno,admin'],
        ['panel/auditoria',     'history',  'Auditoría',     'dueno,admin'],
        ['panel/respaldo',      'database', 'Respaldos',     'dueno'],
    ]],
];

$movil = [
    ['panel',          'panel',   'Inicio'],
    ['panel/cocina',   'chef',    'Cocina', $pendientes['pedidos']],
    ['panel/mesero',   'table',   'Mesas',  $pendientes['llamadas']],
    ['panel/productos','utensils','Menú'],
    ['panel/reportes', 'chart',   'Reportes'],
];
$rol = (string)($u['rol'] ?? '');
$puede = static function (string $roles) use ($rol): bool {
    return $rol === 'superadmin' || in_array($rol, array_map('trim', explode(',', $roles)), true);
};
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(View::section('titulo', 'Panel')) ?> · <?= e((string)($r['nombre'] ?? $marca)) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#141414">
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= e(mb_substr($marca, 0, 12)) ?>">
<link rel="apple-touch-icon" href="<?= e(url('icono/180')) ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?= e(url('icono/192')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Inter:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
<link rel="stylesheet" href="<?= e(asset('css/temas.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/panel.css')) ?>">
<?= View::section('estilos') ?>
</head>
<body class="panel" data-modo="<?= e($modo) ?>">
<a class="saltar-al-contenido" href="#principal">Saltar al contenido</a>
<div class="velo-lateral" id="veloLateral" aria-hidden="true"></div>

<div class="app">
  <!-- ============ Barra lateral ============ -->
  <aside class="lateral" id="lateral" aria-label="Menú de navegación">
    <div class="lateral__marca">
      <div class="lateral__logo">
        <?php if (!empty($r['logo'])): ?>
          <img src="<?= e(uploaded((string)$r['logo'])) ?>" alt="">
        <?php else: ?><?= e(mb_strtoupper(mb_substr((string)($r['nombre'] ?? $marca), 0, 1))) ?><?php endif; ?>
      </div>
      <div class="crece truncar">
        <div class="lateral__nombre truncar"><?= e((string)($r['nombre'] ?? $marca)) ?></div>
        <div class="lateral__plan"><?= e(\MenuGold\Models\User::etiquetaRol($rol)) ?></div>
      </div>
    </div>

    <nav class="lateral__nav">
      <?php foreach ($menu as $g): ?>
        <?php $visibles = array_filter($g['items'], static fn($i) => $puede($i[3])); ?>
        <?php if (!$visibles) continue; ?>
        <div class="lateral__grupo"><?= e($g['grupo']) ?></div>
        <?php foreach ($visibles as $i): ?>
          <a class="nav-item" href="<?= e(url($i[0])) ?>" aria-current="<?= $activo($i[0]) ?>">
            <?= icon($i[1]) ?><span><?= e($i[2]) ?></span>
            <?php if (!empty($i[4])): ?><span class="nav-item__globo"><?= (int)$i[4] ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>

    <div class="lateral__pie">
      <?php if (!empty($r['slug'])): ?>
        <a class="nav-item" href="<?= e(url('r/' . $r['slug'])) ?>" target="_blank" rel="noopener">
          <?= icon('external') ?><span>Ver mi menú</span>
        </a>
      <?php endif; ?>
      <?php if (Auth::isSuper()): ?>
        <a class="nav-item" href="<?= e(url('super')) ?>"><?= icon('crown') ?><span>Plataforma</span></a>
      <?php endif; ?>
      <a class="nav-item" href="<?= e(url('panel/perfil')) ?>"><?= icon('user') ?><span>Mi perfil</span></a>
      <a class="nav-item" href="<?= e(url('salir')) ?>"><?= icon('logout') ?><span>Cerrar sesión</span></a>
    </div>
  </aside>

  <!-- ============ Contenido ============ -->
  <div class="principal">
    <header class="cabecera">
      <button class="bt bt--icono bt--suave" type="button" id="abrirMenu" aria-label="Abrir menú" aria-expanded="false"
              style="display:none"><?= icon('menu') ?></button>
      <button class="bt bt--icono bt--suave" type="button" id="colapsarMenu" aria-label="Colapsar menú"><?= icon('menu') ?></button>
      <div class="crece truncar">
        <h1 class="cabecera__titulo truncar"><?= e(View::section('titulo', 'Escritorio')) ?></h1>
        <?php if (View::has('subtitulo')): ?>
          <div class="cabecera__sub truncar"><?= View::section('subtitulo') ?></div>
        <?php endif; ?>
      </div>
      <?= View::section('acciones') ?>
      <button class="bt bt--icono bt--suave" type="button" id="cambiarTema" aria-label="Cambiar entre modo claro y oscuro">
        <?= icon('moon') ?>
      </button>
    </header>

    <main class="contenido" id="principal">
      <?php foreach (($flashes ?? []) as $f): ?>
        <?php
          $mapa = ['exito' => ['exito', 'check-circle'], 'error' => ['error', 'alert'],
                   'aviso' => ['aviso', 'info'], 'info' => ['info', 'info']];
          [$cls, $ic] = $mapa[$f['tipo']] ?? ['info', 'info'];
        ?>
        <div class="aviso aviso--<?= e($cls) ?>" role="status"><?= icon($ic) ?><span><?= e($f['texto']) ?></span></div>
      <?php endforeach; ?>

      <?= View::section('contenido') ?>
    </main>
  </div>
</div>

<!-- ============ Navegación móvil ============ -->
<nav class="nav-movil" aria-label="Navegación principal">
  <div class="nav-movil__lista">
    <?php foreach ($movil as $i): ?>
      <a href="<?= e(url($i[0])) ?>" aria-current="<?= $activo($i[0]) ?>">
        <?= icon($i[1]) ?><span><?= e($i[2]) ?></span>
        <?php if (!empty($i[3])): ?><span class="nav-movil__globo"><?= (int)$i[3] ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</nav>

<div class="tostadas-p" id="tostadasP" role="region" aria-live="polite" aria-label="Avisos"></div>

<!-- Modal de confirmación reutilizable -->
<div class="modal-p" id="modalConfirmar" role="dialog" aria-modal="true" aria-labelledby="confTitulo">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja" style="width:min(430px,calc(100vw - 28px))">
    <div class="modal-p__cab">
      <h2 class="modal-p__titulo" id="confTitulo">Confirmar</h2>
      <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
    </div>
    <div class="modal-p__cuerpo"><p id="confTexto" style="margin:0"></p></div>
    <div class="modal-p__pie">
      <button class="bt bt--linea" type="button" data-cerrar-modal>Cancelar</button>
      <button class="bt bt--peligro" type="button" id="confOk">Sí, continuar</button>
    </div>
  </div>
</div>

<script nonce="<?= e(Security::nonce()) ?>">
window.MGP = {
  base: <?= json_encode(App::baseUrl()) ?>,
  token: <?= json_encode(csrf_token()) ?>,
  simbolo: <?= json_encode((string)($r['simbolo'] ?? 'Q')) ?>,
  rol: <?= json_encode($rol) ?>,
  restaurante: <?= json_encode((string)($r['nombre'] ?? ''), JSON_UNESCAPED_UNICODE) ?>
};
</script>
<!-- Sin defer: los scripts de cada vista usan window.MGPanel al cargarse -->
<script src="<?= e(guion('panel')) ?>" nonce="<?= e(Security::nonce()) ?>"></script>
<?= View::section('scripts') ?>
<script nonce="<?= e(Security::nonce()) ?>">
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register(<?= json_encode(url('sw.js')) ?>, { scope: <?= json_encode(App::basePath() === '' ? '/' : App::basePath() . '/') ?> })
      .catch(function () {});
  });
}
</script>
</body>
</html>
