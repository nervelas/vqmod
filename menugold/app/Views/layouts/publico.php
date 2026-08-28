<?php
use MenuGold\Core\App;
use MenuGold\Core\Security;
use MenuGold\Core\Setting;
use MenuGold\Core\View;

$marca   = (string)Setting::plat('nombre_plataforma', 'MenúGold');
$eslogan = (string)Setting::plat('eslogan', 'Menús QR con pedidos para restaurantes');
$desc    = (string)Setting::plat('descripcion', $eslogan);
$logo    = (string)Setting::plat('landing_logo', '');
$og      = (string)Setting::plat('landing_imagen', '');
$nonce   = Security::nonce();
?><!doctype html>
<html lang="es" data-tema="negro-oro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($marca) ?> · <?= e($eslogan) ?></title>
<meta name="description" content="<?= e(mb_substr($desc, 0, 180)) ?>">
<meta name="theme-color" content="#141414">
<link rel="canonical" href="<?= e(App::url('')) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($marca) ?> · <?= e($eslogan) ?>">
<meta property="og:description" content="<?= e(mb_substr($desc, 0, 180)) ?>">
<meta property="og:url" content="<?= e(App::url('')) ?>">
<?php if ($og): ?><meta property="og:image" content="<?= e(uploaded($og)) ?>"><?php endif; ?>
<meta name="twitter:card" content="<?= $og ? 'summary_large_image' : 'summary' ?>">

<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= e(mb_substr($marca, 0, 12)) ?>">
<link rel="apple-touch-icon" href="<?= e(url('icono/180')) ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?= e(url('icono/192')) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Inter:wght@400;600&display=swap"></noscript>

<link rel="stylesheet" href="<?= e(asset('css/temas.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/menu.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/publico.css')) ?>">

<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'SoftwareApplication',
    'name'     => $marca,
    'applicationCategory' => 'BusinessApplication',
    'operatingSystem' => 'Web',
    'description' => $desc,
    'url' => App::url(''),
    'offers' => ['@type' => 'AggregateOffer', 'priceCurrency' => 'GTQ'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</head>
<body class="publico">
<a class="saltar-al-contenido" href="#contenido">Saltar al contenido</a>

<header class="cab-pub">
  <div class="cab-pub__int">
    <a class="cab-pub__marca" href="<?= e(url('')) ?>">
      <?php if ($logo): ?><img src="<?= e(uploaded($logo)) ?>" alt=""><?php endif; ?>
      <?= e($marca) ?>
    </a>
    <nav class="cab-pub__nav">
      <a href="#beneficios">Beneficios</a>
      <a href="#como">Cómo funciona</a>
      <a href="#planes">Planes</a>
      <a href="#contacto">Contacto</a>
    </nav>
    <div class="cab-pub__acciones">
      <a class="btn btn--linea" href="<?= e(url('ingresar')) ?>" style="min-height:42px;padding:10px 18px;font-size:14px">
        Ingresar
      </a>
      <a class="btn btn--oro" href="#contacto" style="min-height:42px;padding:10px 18px;font-size:14px">
        <?= e((string)Setting::plat('cta_texto', 'Quiero mi menú')) ?>
      </a>
    </div>
  </div>
</header>

<?= View::section('contenido') ?>

<div class="tostadas" id="tostadas" role="region" aria-live="polite"></div>

<script nonce="<?= e($nonce) ?>">
(function () {
  // Aparición suave al hacer scroll
  var obs = ('IntersectionObserver' in window)
    ? new IntersectionObserver(function (e) {
        e.forEach(function (x) { if (x.isIntersecting) { x.target.classList.add('visible'); obs.unobserve(x.target); } });
      }, { threshold: .12 })
    : null;
  document.querySelectorAll('.revelar').forEach(function (el) {
    if (obs) obs.observe(el); else el.classList.add('visible');
  });

  // Desplazamiento suave
  document.addEventListener('click', function (ev) {
    var a = ev.target.closest('a[href^="#"]');
    if (!a) return;
    var d = document.querySelector(a.getAttribute('href'));
    if (!d) return;
    ev.preventDefault();
    window.scrollTo({ top: d.getBoundingClientRect().top + window.pageYOffset - 76, behavior: 'smooth' });
  });
})();
</script>
<?= View::section('scripts') ?>
</body>
</html>
