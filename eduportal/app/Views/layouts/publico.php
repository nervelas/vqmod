<?php
use App\Controllers\Configuracion;
use App\Core\Settings;

$temaClave = (string)Settings::get('tema', 'default');
if (!isset(Configuracion::TEMAS[$temaClave])) {
    $temaClave = 'default';
}
$logo = (string)Settings::get('colegio_logo', '');
$nombre = (string)Settings::get('colegio_nombre', 'EduPortal');
$og = (string)Settings::get('seo_og', '');
?>
<!doctype html>
<html lang="es-GT" data-tema="<?= e($temaClave) ?>" data-oscuro="0"
      data-base="<?= e(base_path_url()) ?>" data-csrf="<?= e(csrf_token()) ?>">
<head>
<?= App\Core\View::partial('partials/head', ['titulo' => $titulo ?? '']) ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($nombre) ?>">
<meta property="og:title" content="<?= e($titulo ?? $nombre) ?>">
<meta property="og:description" content="<?= e(Settings::get('seo_description', '')) ?>">
<?php if ($og !== ''): ?><meta property="og:image" content="<?= e(url_absoluta(ltrim(archivo_url($og), '/'))) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<link rel="canonical" href="<?= e(url_absoluta(ltrim(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/', '/'))) ?>">
</head>
<body>
<a class="saltar" href="#contenido">Ir al contenido principal</a>
<header class="sitio-barra">
  <div class="sitio-barra__int">
    <a href="<?= e(url('/')) ?>" class="sitio-barra__marca">
      <?php if ($logo !== ''): ?><img src="<?= e(archivo_url($logo)) ?>" alt=""><?php else: ?><?= icono('escuela', 28) ?><?php endif; ?>
      <span><?= e($nombre) ?></span>
    </a>
    <button type="button" class="btn btn--linea btn--icono sitio-nav__movil" data-nav-publico
            aria-label="Mostrar el menú" aria-expanded="false"><?= icono('menu') ?></button>
    <nav class="sitio-nav" id="nav-publico" aria-label="Navegación del sitio">
      <a href="<?= e(url('/#niveles')) ?>">Niveles</a>
      <a href="<?= e(url('/#nosotros')) ?>">Nosotros</a>
      <a href="<?= e(url('calendario')) ?>">Calendario</a>
      <a href="<?= e(url('/#contacto')) ?>">Contacto</a>
      <?php if (Settings::bool('sitio_inscripcion', true)): ?>
        <a href="<?= e(url('inscripcion')) ?>" class="btn btn--oro btn--sm">Inscripción en línea</a>
      <?php endif; ?>
      <a href="<?= e(url('ingresar')) ?>" class="btn btn--sm">Portal</a>
    </nav>
  </div>
</header>
<main id="contenido"><?= $contenido ?? '' ?></main>
<footer class="sitio-pie">
  <div class="sitio-pie__int">
    <div>
      <h4><?= e($nombre) ?></h4>
      <p class="sm"><?= e(Settings::get('colegio_lema', '')) ?></p>
    </div>
    <div>
      <h4>Contacto</h4>
      <p class="sm">
        <?php if (Settings::get('colegio_direccion', '') !== ''): ?><?= e(Settings::get('colegio_direccion')) ?><br><?php endif; ?>
        <?php if (Settings::get('colegio_telefono', '') !== ''): ?><?= e(Settings::get('colegio_telefono')) ?><br><?php endif; ?>
        <?php if (Settings::get('colegio_email', '') !== ''): ?>
          <a href="mailto:<?= e(Settings::get('colegio_email')) ?>"><?= e(Settings::get('colegio_email')) ?></a>
        <?php endif; ?>
      </p>
    </div>
    <div>
      <h4>Accesos</h4>
      <p class="sm">
        <a href="<?= e(url('ingresar')) ?>">Portal de padres</a><br>
        <a href="<?= e(url('calendario')) ?>">Calendario escolar</a><br>
        <?php if (Settings::bool('sitio_inscripcion', true)): ?>
          <a href="<?= e(url('inscripcion')) ?>">Pre-inscripción</a>
        <?php endif; ?>
      </p>
    </div>
  </div>
  <div class="sitio-pie__legal">
    <span>&copy; <?= date('Y') ?> <?= e($nombre) ?>. NIT <?= e(Settings::get('colegio_nit', 'C/F')) ?>.</span>
    <span>Guatemala</span>
  </div>
</footer>
<?= App\Core\View::partial('partials/flash') ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<script src="<?= e(asset('js/sitio.js')) ?>" defer></script>
</body>
</html>
