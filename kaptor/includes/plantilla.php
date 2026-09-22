<?php
/**
 * Kaptor - Plantilla pública (cabecera y pie).
 *
 * Todas las páginas públicas la usan para compartir la misma dirección de arte
 * y para que los colores, el logo y los textos del panel se apliquen en todas.
 */
declare(strict_types=1);

/** Devuelve la URL del logo configurado o cadena vacia si se usa el SVG propio. */
function cr_logo_url(): string
{
    $logo = trim(Ajustes::obtener('logo'));
    if ($logo === '') { return ''; }
    return preg_match('~^https?://~i', $logo) ? $logo : cr_url($logo);
}

/**
 * Pinta un grupo del menú con sus entradas dentro.
 *
 * Es un <details> y no un menú de JavaScript a propósito: así se abre y se
 * cierra sin una línea de código, funciona con el teclado de fábrica, y si el
 * JavaScript falla o tarda, el menú sigue sirviendo. El comportamiento de
 * escritorio (abrir al pasar el ratón, cerrar al salir) lo añade el CSS y un
 * puñado de líneas en app.js; si no llegan, no se pierde nada.
 *
 * @param array<int,array{0:string,1:string,2:string,3:string}> $entradas
 *        [clave activa, archivo, título, descripción]
 */
function cr_menu_grupo(string $titulo, string $activo, array $entradas): void
{
    $claves = array_column($entradas, 0);
    $aqui   = in_array($activo, $claves, true);
    ?>
    <details class="menu-grupo<?= $aqui ? ' activo' : '' ?>">
      <summary>
        <span><?= e($titulo) ?></span>
        <svg class="menu-flecha" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="m6 9 6 6 6-6"/>
        </svg>
      </summary>
      <div class="menu-desplegable">
        <?php foreach ($entradas as [$clave, $archivo, $nombre, $pista]): ?>
          <a href="<?= e(cr_url($archivo)) ?>" class="<?= $activo === $clave ? 'activo' : '' ?>">
            <span class="menu-nombre"><?= e($nombre) ?></span>
            <span class="menu-pista"><?= e($pista) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </details>
    <?php
}

/**
 * Imprime la cabecera completa de una página pública.
 *
 * @param array{título?:string,descripción?:string,activo?:string,clase?:string,css?:string[]} $opciones
 */
function cr_cabecera(array $opciones = []): void
{
    $nombre = Ajustes::obtener('sitio_nombre', 'Kaptor');
    $titulo = $opciones['titulo'] ?? $nombre;
    if ($titulo !== $nombre) { $titulo .= ' · ' . $nombre; }
    $descripcion = $opciones['descripcion'] ?? Ajustes::obtener('sitio_descripcion');
    $activo = $opciones['activo'] ?? '';
    $logo   = cr_logo_url();
    // El tema de la casa es el claro: papel blanco. El oscuro sigue ahi
    // para quien lo prefiera, redisenado con el mismo criterio.
    $tema   = Ajustes::obtener('tema_por_defecto', 'claro') === 'oscuro' ? 'oscuro' : 'claro';
    ?><!DOCTYPE html>
<html lang="es" data-tema="<?= e($tema) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

<?php /* El tema se decide AQUI, antes de pintar nada, y en TODAS las paginas
         (tambien en las que no cargan app.js). Si no, al pasar de una pagina
         con JS a otra sin el, la pantalla cambiaba de oscuro a claro sola. */ ?>
<script>
(function(){
  var CLAVE='kaptor-tema', raiz=document.documentElement;

  function poner(t){
    raiz.setAttribute('data-tema',t);
    try{ localStorage.setItem(CLAVE,t); }catch(e){}
    var b=document.querySelector('.tema');
    if(b){ b.setAttribute('aria-label', t==='oscuro'?'Cambiar a modo claro':'Cambiar a modo oscuro'); }
  }

  // El tema se decide AQUI, antes de pintar, para que la pagina no
  // parpadee de oscuro a claro al entrar.
  var g=null;
  try{ g=localStorage.getItem(CLAVE); }catch(e){}
  if(g!=='claro'&&g!=='oscuro'){ g=<?= ejs($tema) ?>; }
  raiz.setAttribute('data-tema',g);

  // Y el boton se conecta AQUI TAMBIEN, no en app.js: hay cinco paginas
  // (auditor, SEO, virus, mis extracciones y correcciones) que no cargan
  // app.js, y en ellas el boton de la luna no hacia absolutamente nada.
  function conectar(){
    var b=document.querySelector('.tema');
    if(!b || b.dataset.listo) { return; }
    b.dataset.listo='1';
    poner(raiz.getAttribute('data-tema')==='oscuro'?'oscuro':'claro');
    b.addEventListener('click', function(){
      poner(raiz.getAttribute('data-tema')==='oscuro'?'claro':'oscuro');
    });
  }
  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded', conectar);
  } else { conectar(); }
})();
</script>
<title><?= e($titulo) ?></title>
<meta name="description" content="<?= e($descripcion) ?>">
<meta name="theme-color" content="<?= e(Ajustes::obtener('color_fondo', '#07080A')) ?>">
<link rel="canonical" href="<?= e(cr_url(ltrim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/'))) ?>">
<link rel="icon" href="<?= e(cr_url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="alternate icon" href="<?= e(cr_url('assets/img/favicon.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(cr_url('assets/img/apple-touch-icon.png')) ?>">

<!-- Aplicación instalable (PWA): manifiesto e indicaciones para iOS. -->
<link rel="manifest" href="<?= e(cr_url('manifest.php')) ?>">
<meta name="application-name" content="<?= e(Ajustes::obtener('sitio_nombre', 'Kaptor')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e(Ajustes::obtener('sitio_nombre', 'Kaptor')) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($titulo) ?>">
<meta property="og:description" content="<?= e($descripcion) ?>">
<meta property="og:image" content="<?= e(cr_url('assets/img/og.jpg')) ?>">
<meta property="og:locale" content="es_ES">
<meta name="twitter:card" content="summary_large_image">
<link rel="preload" href="<?= e(cr_url('assets/fonts/manrope-400-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(cr_url('assets/fonts/cormorant-garamond-300-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(cr_url('assets/css/app.css')) ?>?v=<?= e(CR_VERSION) ?>">
<link rel="stylesheet" href="<?= e(cr_url('assets/css/lujo.css')) ?>?v=<?= e(CR_VERSION) ?>">
<?php // Hojas propias de una página, si la página las pide. Van al final para
      // que puedan ajustar lo anterior sin pelearse por la especificidad.
foreach ((array) ($opciones['css'] ?? []) as $hoja): ?>
<link rel="stylesheet" href="<?= e(cr_url('assets/css/' . $hoja)) ?>?v=<?= e(CR_VERSION) ?>">
<?php endforeach; ?>
<style>
  /* Paleta configurada desde el panel de administración */
  :root{
    --cr-fondo:<?= e(Ajustes::obtener('color_fondo', '#07080A')) ?>;
    --cr-fondo2:<?= e(Ajustes::obtener('color_fondo2', '#161008')) ?>;
    --cr-oro:<?= e(Ajustes::obtener('color_oro', '#D8B36A')) ?>;
    --cr-oro2:<?= e(Ajustes::obtener('color_oro2', '#F3D89A')) ?>;
    --cr-neon:<?= e(Ajustes::obtener('color_neon', '#6EF3A5')) ?>;
    --cr-fondo-claro:<?= e(Ajustes::obtener('color_fondo_claro', '#FCFAF4')) ?>;
    --cr-fondo2-claro:<?= e(Ajustes::obtener('color_fondo2_claro', '#F3EAD8')) ?>;
    --cr-texto-claro:<?= e(Ajustes::obtener('color_texto_claro', '#171512')) ?>;
    --cr-oro-claro:<?= e(Ajustes::obtener('color_oro_claro', '#7E682F')) ?>;
    --cr-oro2-claro:<?= e(Ajustes::obtener('color_oro2_claro', '#8D7A40')) ?>;
    --cr-neon-claro:<?= e(Ajustes::obtener('color_neon_claro', '#0F7B43')) ?>;
    --cr-acento-claro:<?= e(Ajustes::obtener('color_acento_claro', '#FF4800')) ?>;
    --cr-acento:<?= e(Ajustes::obtener('color_acento', '#FF6A33')) ?>;
    --cr-texto:<?= e(Ajustes::obtener('color_texto', '#EDEAE3')) ?>;
  }
</style>
</head>
<body class="<?= e($opciones['clase'] ?? '') ?>">

<!-- Aurora de fondo: halos en los colores del tema. Solo decorativa. -->
<div class="aurora" aria-hidden="true"></div>

<header class="barra">
  <div class="contenedor barra-int">
    <a class="marca" href="<?= e(cr_url('index.php')) ?>">
      <?php if ($logo !== ''): ?>
        <img src="<?= e($logo) ?>" alt="<?= e($nombre) ?>" width="36" height="36">
      <?php else: ?>
        <?= cr_logo_svg(36) ?>
      <?php endif; ?>
      <span class="marca-txt"><?= cr_logotipo($nombre) ?></span>
    </a>

    <!-- Botón del menú: en el teléfono cinco enlaces no caben en una línea.
         Solo se ve por debajo de 900 px; con el teclado funciona igual. -->
    <button type="button" class="menu-movil" id="menu-movil"
            aria-label="Abrir el menú" aria-expanded="false" aria-controls="menu-principal">
      <span class="menu-barras" aria-hidden="true"><i></i><i></i><i></i></span>
    </button>

    <nav id="menu-principal" aria-label="Navegación principal">
      <?php if (Auth::autenticado()): ?>
        <!-- Kaptor es privado: sin sesión no se enseñan destinos que solo
             llevarían de vuelta al acceso.

             Las herramientas van agrupadas en dos desplegables. Sueltas eran
             siete enlaces y no cabían; agrupadas son tres, y además dicen de
             un vistazo que Kaptor hace dos cosas: sacar datos y analizarlos. -->
        <?php
        // Todo lo que hace Kaptor, agrupado por para qué sirve. Nada de esto
        // vive escondido en el panel: si la herramienta existe, se ve.
        cr_menu_grupo('Extraer', $activo, [
            ['inicio',   'index.php',    'Extractor de una web',  'Correos y WhatsApp de una web, una lista o una búsqueda'],
            ['depurar',  'depurar.php',  'Extractor de correos',  'Saca los correos de un texto pegado'],
            ['whatsapp', 'whatsapp.php', 'Extractor de WhatsApp', 'Saca los números de un texto pegado'],
            ['dominios', 'dominios.php', 'Extractor de dominios', 'Saca las páginas web de un listado'],
        ]);
        cr_menu_grupo('Analizar', $activo, [
            ['auditor', 'auditor.php', 'Auditor de sitios web', 'Las siete áreas del sitio, con informe de marca'],
            ['seo',     'seo.php',     'Análisis SEO',          'Recorre el sitio entero y dice qué falta para el 100 %'],
            ['malware', 'malware.php', 'Análisis de virus',     'Código malicioso, listas negras y spam escondido'],
        ]);
        if (Auth::esAdmin()) {
            cr_menu_grupo('Campañas', $activo, [
                ['campanas',   'admin/campanas.php',   'Campañas de correo',  'Crear, programar y seguir los envíos'],
                ['listas',     'admin/listas.php',     'Listas de contactos', 'A quién se le escribe'],
                ['plantillas', 'admin/plantillas.php', 'Plantillas',          'Los mensajes que se envían'],
                ['remitentes', 'admin/remitentes.php', 'Remitentes',          'Los buzones desde los que sale el correo'],
                ['supresion',  'admin/supresion.php',  'Bajas y supresión',   'Quién no vuelve a recibir nada'],
            ]);
        }
        ?>
        <a href="<?= e(cr_url('mis-extracciones.php')) ?>" class="<?= $activo === 'mias' ? 'activo' : '' ?>">Mis extracciones</a>
        <?php if (Auth::esAdmin()): ?>
          <a href="<?= e(cr_url('admin/index.php')) ?>">Panel</a>
        <?php endif; ?>
        <a href="<?= e(cr_url('logout.php')) ?>">Salir</a>
      <?php else: ?>
        <a href="<?= e(cr_url('login.php')) ?>" class="<?= $activo === 'login' ? 'activo' : '' ?>">Entrar</a>
      <?php endif; ?>
    </nav>

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

<main>
<?php
    foreach (cr_flash_pendientes() as $flash) {
        $clase = $flash['tipo'] === 'error' ? 'aviso-error' : ($flash['tipo'] === 'exito' ? 'aviso-exito' : 'aviso-info');
        echo '<div class="contenedor" style="padding-top:20px"><div class="aviso ' . $clase . '"><span>' . e($flash['mensaje']) . '</span></div></div>';
    }
}

/** Imprime el pie de página y carga el JavaScript. */
function cr_pie(bool $conJs = true): void
{
    ?>
</main>

<footer class="pie">
  <div class="contenedor pie-int">
    <span><?= e(Ajustes::obtener('pie_texto')) ?>
      <span class="pie-version" title="Versión instalada de Kaptor">v<?= e(CR_VERSION) ?></span>
    </span>
    <span>
      <?php if (Auth::autenticado()): ?>
        <a href="<?= e(cr_url('index.php')) ?>">Inicio</a> ·
        <a href="<?= e(cr_url('depurar.php')) ?>">Extraer correos</a> ·
        <a href="<?= e(cr_url('dominios.php')) ?>">Extraer dominios</a> ·
        <a href="<?= e(cr_url('logout.php')) ?>">Salir</a>
      <?php else: ?>
        <a href="<?= e(cr_url('login.php')) ?>">Acceder</a>
      <?php endif; ?>
    </span>
  </div>
</footer>

<div class="brindis" id="brindis" role="status" aria-live="polite">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="m5 12.5 4.5 4.5L19 7.5"/>
  </svg>
  <span></span>
</div>

<!-- Aviso para instalar la aplicación. Aparece solo cuando el navegador
     admite la instalación y todavía no está instalada. -->
<div class="instalar-app" id="instalar-app" hidden>
  <img src="<?= e(cr_url('assets/img/icono-192.png')) ?>" alt="" width="46" height="46">
  <div class="instalar-txt">
    <b>Instalar <?= e(Ajustes::obtener('sitio_nombre', 'Kaptor')) ?></b>
    <span id="instalar-pista">Añádelo a tu pantalla de inicio y ábrelo como una aplicación.</span>
  </div>
  <div class="instalar-botones">
    <button type="button" class="btn btn-peq" id="instalar-si" hidden>Instalar</button>
    <button type="button" class="btn btn-fantasma btn-peq" id="instalar-no" aria-label="Ahora no">Ahora no</button>
  </div>
</div>

<?php if ($conJs): ?>
<script>
  window.CR = {
    csrf: <?= ejs(Seguridad::tokenCsrf()) ?>,
    apiEscaneo: <?= ejs(cr_url('api/escaneo.php')) ?>,
    apiExportar: <?= ejs(cr_url('api/exportar.php')) ?>,
    urlLogin: <?= ejs(cr_url('login.php')) ?>,
    temaPorDefecto: <?= ejs(Ajustes::obtener('tema_por_defecto', 'claro')) ?>,
    sw: <?= ejs(cr_url('sw.js')) ?>,
    base: <?= ejs(rtrim(cr_url_base(), '/') . '/') ?>
  };
</script>
<script src="<?= e(cr_url('assets/js/app.js')) ?>?v=<?= e(CR_VERSION) ?>" defer></script>
<?php endif; ?>
</body>
</html>
<?php
}
