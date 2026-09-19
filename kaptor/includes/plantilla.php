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
 * Imprime la cabecera completa de una página pública.
 *
 * @param array{título?:string,descripción?:string,activo?:string,clase?:string} $opciones
 */
function cr_cabecera(array $opciones = []): void
{
    $nombre = Ajustes::obtener('sitio_nombre', 'Kaptor');
    $titulo = $opciones['titulo'] ?? $nombre;
    if ($titulo !== $nombre) { $titulo .= ' · ' . $nombre; }
    $descripcion = $opciones['descripcion'] ?? Ajustes::obtener('sitio_descripcion');
    $activo = $opciones['activo'] ?? '';
    $logo   = cr_logo_url();
    $tema   = Ajustes::obtener('tema_por_defecto', 'oscuro') === 'claro' ? 'claro' : 'oscuro';
    ?><!DOCTYPE html>
<html lang="es" data-tema="<?= e($tema) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
<link rel="preload" href="<?= e(cr_url('assets/fonts/inter-var-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(cr_url('assets/css/app.css')) ?>?v=<?= e(CR_VERSION) ?>">
<link rel="stylesheet" href="<?= e(cr_url('assets/css/lujo.css')) ?>?v=<?= e(CR_VERSION) ?>">
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
    --cr-neon-claro:<?= e(Ajustes::obtener('color_neon_claro', '#3B7F55')) ?>;
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

    <nav aria-label="Navegación principal">
      <a href="<?= e(cr_url('index.php')) ?>" class="enlace-extraer <?= $activo === 'inicio' ? 'activo' : '' ?>">Extraer</a>
      <a href="<?= e(cr_url('depurar.php')) ?>" class="<?= $activo === 'depurar' ? 'activo' : '' ?>">Extraer de un texto</a>
      <?php if (Auth::autenticado()): ?>
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
    <span><?= e(Ajustes::obtener('pie_texto')) ?></span>
    <span>
      <a href="<?= e(cr_url('index.php')) ?>">Inicio</a> ·
      <a href="<?= e(cr_url('depurar.php')) ?>">Extraer de un texto</a> ·
      <a href="<?= e(cr_url('login.php')) ?>">Acceder</a>
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
    <button type="button" class="btn btn-peq" id="instalar-si">Instalar</button>
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
    temaPorDefecto: <?= ejs(Ajustes::obtener('tema_por_defecto', 'oscuro')) ?>,
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
