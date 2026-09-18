<?php
/**
 * Kaptor - Estructura del panel de administración (cabecera y pie).
 */
declare(strict_types=1);

/** Enlaces de la barra lateral: [archivo, texto, icono SVG]. */
function admin_menu(): array
{
    return [
        'Principal' => [
            ['index.php',     'Resumen',    '<path d="M4 13h6V4H4zM14 20h6v-9h-6zM4 20h6v-4H4zM14 8h6V4h-6z"/>'],
            ['historial.php', 'Historial',  '<path d="M3 12a9 9 0 1 0 9-9"/><path d="M3 4v5h5"/><path d="M12 8v4l3 2"/>'],
        ],
        'Campañas' => [
            ['campanas.php',  'Campañas',   '<path d="M3 5h18v14H3z"/><path d="m3.5 6 8 5.4a1.5 1.5 0 0 0 1.8 0L21 6"/><path d="m16 15 5 4M8 15l-5 4" opacity=".5"/>'],
            ['listas.php',    'Contactos',  '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.2 2.7-5.4 6-5.4s6 2.2 6 5.4"/><path d="M17 8h5M17 12h5M17 16h3"/>'],
            ['plantillas.php','Plantillas', '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>'],
            ['remitentes.php','Buzones',    '<path d="M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z"/><path d="m3.4 6.8 8 5.4a1.2 1.2 0 0 0 1.3 0l8-5.4"/>'],
            ['supresion.php', 'Supresión',  '<circle cx="12" cy="12" r="9"/><path d="m8 8 8 8"/>'],
        ],
        'Gestión' => [
            ['usuarios.php',  'Usuarios',   '<circle cx="9" cy="8" r="3.4"/><path d="M3 20c0-3.4 2.7-5.6 6-5.6s6 2.2 6 5.6"/><path d="M17 11a3 3 0 1 0-1.8-5.4M21 20c0-2.6-1.6-4.4-4-5"/>'],
            ['ajustes.php',   'Ajustes',    '<circle cx="12" cy="12" r="3.2"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 9 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 9a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z"/>'],
        ],
        'Sitio' => [
            ['../index.php',  'Ver el sitio', '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6M10 14 21 3"/>'],
        ],
    ];
}

/**
 * Imprime la cabecera del panel.
 *
 * @param array{titulo?:string,activo?:string} $opciones
 */
function admin_cabecera(array $opciones = []): void
{
    $titulo  = $opciones['titulo'] ?? 'Panel';
    $activo  = $opciones['activo'] ?? '';
    $usuario = Auth::usuario();
    $nombre  = Ajustes::obtener('sitio_nombre', 'Kaptor');
    $tema    = Ajustes::obtener('tema_por_defecto', 'oscuro') === 'claro' ? 'claro' : 'oscuro';
    ?><!DOCTYPE html>
<html lang="es" data-tema="<?= e($tema) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> · <?= e($nombre) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e(cr_url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(cr_url('admin/assets/admin.css')) ?>?v=<?= e(CR_VERSION) ?>">
<style>
  :root{
    --cr-fondo:<?= e(Ajustes::obtener('color_fondo', '#07080A')) ?>;
    --cr-oro:<?= e(Ajustes::obtener('color_oro', '#D8B36A')) ?>;
    --cr-neon:<?= e(Ajustes::obtener('color_neon', '#6EF3A5')) ?>;
    --cr-texto:<?= e(Ajustes::obtener('color_texto', '#EDEAE3')) ?>;
  }
</style>
</head>
<body class="panel-cuerpo">

<div class="velo" id="velo"></div>

<aside class="lateral" id="lateral">
  <a class="marca" href="<?= e(cr_url('admin/index.php')) ?>">
    <img src="<?= e(cr_url('assets/img/logo.svg')) ?>" alt="" width="34" height="34" aria-hidden="true">
    <span class="marca-txt"><?= e($nombre) ?></span>
  </a>

  <?php foreach (admin_menu() as $grupo => $enlaces): ?>
    <span class="lateral-grupo"><?= e($grupo) ?></span>
    <?php foreach ($enlaces as [$archivo, $texto, $icono]): ?>
      <a href="<?= e($archivo) ?>" class="<?= $activo === $archivo ? 'activo' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icono ?></svg>
        <?= e($texto) ?>
      </a>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <a class="salir" href="<?= e(cr_url('logout.php')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>
    </svg>
    Cerrar sesión
  </a>
</aside>

<div class="panel-contenido">
  <header class="panel-barra">
    <button type="button" class="menu-btn" id="menu-btn" aria-label="Abrir el menú">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
        <path d="M4 7h16M4 12h16M4 17h16"/>
      </svg>
    </button>
    <h1><?= e($titulo) ?></h1>
    <div class="acciones">
      <span class="chip chip-gris"><?= e((string) ($usuario['usuario'] ?? '')) ?></span>
      <button type="button" class="tema" aria-label="Cambiar el tema de color">
        <svg class="icono-sol" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
          <circle cx="12" cy="12" r="4.2"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
        </svg>
        <svg class="icono-luna" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>
        </svg>
      </button>
    </div>
  </header>

  <div class="panel-interior">
<?php
    foreach (cr_flash_pendientes() as $flash) {
        $clase = $flash['tipo'] === 'error' ? 'aviso-error' : ($flash['tipo'] === 'exito' ? 'aviso-exito' : 'aviso-info');
        echo '<div class="aviso ' . $clase . '"><span>' . e($flash['mensaje']) . '</span></div>';
    }
}

/** Cierra la estructura del panel y carga los scripts. */
function admin_pie(array $scripts = []): void
{
    ?>
  </div>
</div>

<div class="brindis" id="brindis" role="status" aria-live="polite">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="m5 12.5 4.5 4.5L19 7.5"/>
  </svg>
  <span></span>
</div>

<script>
/* Menú lateral en móvil + tema claro/oscuro con memoria */
(function () {
  var lateral = document.getElementById('lateral');
  var velo    = document.getElementById('velo');
  var btn     = document.getElementById('menu-btn');

  function alternar(abrir) {
    lateral.classList.toggle('abierto', abrir);
    velo.classList.toggle('visible', abrir);
  }
  if (btn)  { btn.addEventListener('click', function () { alternar(!lateral.classList.contains('abierto')); }); }
  if (velo) { velo.addEventListener('click', function () { alternar(false); }); }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { alternar(false); } });

  var CLAVE = 'kaptor-tema';
  function aplicar(t) {
    document.documentElement.setAttribute('data-tema', t);
    try { localStorage.setItem(CLAVE, t); } catch (e) {}
  }
  var guardado = null;
  try { guardado = localStorage.getItem(CLAVE); } catch (e) {}
  if (guardado) { aplicar(guardado); }
  var bt = document.querySelector('.tema');
  if (bt) {
    bt.addEventListener('click', function () {
      aplicar(document.documentElement.getAttribute('data-tema') === 'oscuro' ? 'claro' : 'oscuro');
    });
  }

  /* Pestañas de la página de ajustes */
  var pestanas = document.querySelectorAll('.pestanas button');
  if (pestanas.length) {
    Array.prototype.forEach.call(pestanas, function (b) {
      b.addEventListener('click', function () {
        Array.prototype.forEach.call(pestanas, function (x) { x.classList.remove('activa'); });
        Array.prototype.forEach.call(document.querySelectorAll('.hoja'), function (h) { h.classList.remove('activa'); });
        b.classList.add('activa');
        var destino = document.getElementById(b.dataset.hoja);
        if (destino) { destino.classList.add('activa'); }
        try { localStorage.setItem('kaptor-hoja', b.dataset.hoja); } catch (e) {}
      });
    });
    var recordada = null;
    try { recordada = localStorage.getItem('kaptor-hoja'); } catch (e) {}
    if (recordada && document.getElementById(recordada)) {
      var b = document.querySelector('.pestanas button[data-hoja="' + recordada + '"]');
      if (b) { b.click(); }
    }
  }

  /* Los selectores de color y su campo de texto se mantienen sincronizados */
  Array.prototype.forEach.call(document.querySelectorAll('.color-fila'), function (fila) {
    var color = fila.querySelector('input[type=color]');
    var texto = fila.querySelector('input[type=text]');
    if (!color || !texto) { return; }
    color.addEventListener('input', function () { texto.value = color.value.toUpperCase(); });
    texto.addEventListener('input', function () {
      if (/^#[0-9a-fA-F]{6}$/.test(texto.value)) { color.value = texto.value; }
    });
  });

  /* Confirmación antes de borrar */
  Array.prototype.forEach.call(document.querySelectorAll('[data-confirmar]'), function (el) {
    el.addEventListener('click', function (e) {
      if (!window.confirm(el.getAttribute('data-confirmar'))) { e.preventDefault(); }
    });
  });
})();
</script>
<?php foreach ($scripts as $src): ?>
<script src="<?= e($src) ?>?v=<?= e(CR_VERSION) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}
