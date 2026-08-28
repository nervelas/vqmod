<?php
use MenuGold\Core\App;
use MenuGold\Core\Auth;
use MenuGold\Core\DB;
use MenuGold\Core\Security;
use MenuGold\Core\Setting;
use MenuGold\Core\View;

$u = $usuario ?? Auth::user() ?? [];
$marca = (string)Setting::plat('nombre_plataforma', 'MenúGold');
$uri = App::uri();
$sinLeer = 0;
try { $sinLeer = DB::int('SELECT COUNT(*) FROM contact_messages WHERE leido = 0'); } catch (\Throwable $e) {}

$nav = [
    ['super',              'panel',    'Escritorio'],
    ['super/restaurantes', 'store',    'Restaurantes'],
    ['super/planes',       'crown',    'Planes'],
    ['super/mensajes',     'mail',     'Mensajes', $sinLeer],
    ['super/respaldos',    'database', 'Respaldos'],
    ['super/ajustes',      'settings', 'Ajustes'],
];
$activo = static function (string $ruta) use ($uri): string {
    $ruta = '/' . trim($ruta, '/');
    if ($ruta === '/super') return $uri === '/super' ? 'page' : 'false';
    return strncmp($uri, $ruta, strlen($ruta)) === 0 ? 'page' : 'false';
};
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(View::section('titulo', 'Plataforma')) ?> · <?= e($marca) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#141414">
<link rel="icon" type="image/png" sizes="192x192" href="<?= e(url('icono/192')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Inter:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
<link rel="stylesheet" href="<?= e(asset('css/temas.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/panel.css')) ?>">
</head>
<body class="panel" data-modo="<?= e((string)($u['tema_panel'] ?? 'auto')) ?>">
<a class="saltar-al-contenido" href="#principal">Saltar al contenido</a>
<div class="velo-lateral" id="veloLateral"></div>

<div class="app">
  <aside class="lateral" id="lateral" aria-label="Navegación de plataforma">
    <div class="lateral__marca">
      <div class="lateral__logo" style="background:#D4AF37"><?= icon('crown') ?></div>
      <div class="crece truncar">
        <div class="lateral__nombre truncar"><?= e($marca) ?></div>
        <div class="lateral__plan">Plataforma</div>
      </div>
    </div>
    <nav class="lateral__nav">
      <div class="lateral__grupo">Administración</div>
      <?php foreach ($nav as $i): ?>
        <a class="nav-item" href="<?= e(url($i[0])) ?>" aria-current="<?= $activo($i[0]) ?>">
          <?= icon($i[1]) ?><span><?= e($i[2]) ?></span>
          <?php if (!empty($i[3])): ?><span class="nav-item__globo"><?= (int)$i[3] ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="lateral__pie">
      <a class="nav-item" href="<?= e(url('')) ?>" target="_blank" rel="noopener"><?= icon('globe') ?><span>Ver el sitio</span></a>
      <a class="nav-item" href="<?= e(url('salir')) ?>"><?= icon('logout') ?><span>Cerrar sesión</span></a>
    </div>
  </aside>

  <div class="principal">
    <header class="cabecera">
      <button class="bt bt--icono bt--suave" type="button" id="colapsarMenu" aria-label="Menú"><?= icon('menu') ?></button>
      <div class="crece truncar">
        <h1 class="cabecera__titulo truncar"><?= e(View::section('titulo', 'Escritorio')) ?></h1>
        <?php if (View::has('subtitulo')): ?><div class="cabecera__sub truncar"><?= View::section('subtitulo') ?></div><?php endif; ?>
      </div>
      <?= View::section('acciones') ?>
      <button class="bt bt--icono bt--suave" type="button" id="cambiarTema" aria-label="Cambiar tema"><?= icon('moon') ?></button>
    </header>

    <main class="contenido" id="principal">
      <?php foreach (($flashes ?? []) as $f): ?>
        <?php $mapa = ['exito' => ['exito','check-circle'], 'error' => ['error','alert'], 'aviso' => ['aviso','info'], 'info' => ['info','info']];
              [$cls, $ic] = $mapa[$f['tipo']] ?? ['info','info']; ?>
        <div class="aviso aviso--<?= e($cls) ?>" role="status"><?= icon($ic) ?><span><?= e($f['texto']) ?></span></div>
      <?php endforeach; ?>
      <?= View::section('contenido') ?>
    </main>
  </div>
</div>

<div class="tostadas-p" id="tostadasP" role="region" aria-live="polite"></div>

<div class="modal-p" id="modalConfirmar" role="dialog" aria-modal="true">
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
window.MGP = { base: <?= json_encode(App::baseUrl()) ?>, token: <?= json_encode(csrf_token()) ?>,
               simbolo: 'Q', rol: 'superadmin' };
</script>
<!-- Sin defer: los scripts de cada vista usan window.MGPanel al cargarse -->
<script src="<?= e(asset('js/panel.js')) ?>" nonce="<?= e(Security::nonce()) ?>"></script>
<?= View::section('scripts') ?>
</body>
</html>
