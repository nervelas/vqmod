<?php
/** Sitio público de la empresa. */
$base = rtrim(url('/'), '/');
$path = \App\Core\Request::path();
$is = static fn (string $suffix): bool => str_ends_with($path, $suffix);
$jsConfig = ['cartUrl' => $base . '/carrito'];
$cartCount = (int) ($cartCount ?? 0);
?>
<!doctype html>
<html lang="es">
<head><?= \App\Core\View::partial('partials/head', get_defined_vars()) ?></head>
<body>
<div class="sweep" aria-hidden="true"></div>
<a class="skip" href="#contenido">Saltar al contenido</a>

<header class="topbar">
  <div class="wrap topbar__in">
    <a class="brand" href="<?= e(url("/")) ?>">
      <?php if (!empty($company['logo'])): ?>
        <img src="<?= e(upload($company['logo'])) ?>" alt="<?= e($company['name']) ?>" width="140" height="34">
      <?php else: ?>
        <span class="brand__mark" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($company['name'], 0, 2))) ?></span>
        <span><?= e($company['name']) ?></span>
      <?php endif; ?>
    </a>
    <button class="navtoggle" type="button" aria-expanded="false" aria-controls="mainnav" aria-label="Abrir menú"><span></span></button>
    <nav class="nav" id="mainnav" aria-label="Principal">
      <a href="<?= e($base . '/catalogo') ?>"<?= str_contains($path, '/catalogo') || str_contains($path, '/categoria/') ? ' aria-current="page"' : '' ?>>Catálogo</a>
      <a href="<?= e($base . '/nosotros') ?>"<?= $is('/nosotros') ? ' aria-current="page"' : '' ?>>Quiénes somos</a>
      <a href="<?= e($base . '/contacto') ?>"<?= $is('/contacto') ? ' aria-current="page"' : '' ?>>Contacto</a>
      <a class="btn btn--accent btn--sm" href="<?= e($base . '/cotizacion') ?>">
        Mi cotización <span class="cartfab__n" data-cart-count><?= e($cartCount) ?></span>
      </a>
    </nav>
  </div>
</header>

<main id="contenido"><?= $content ?></main>

<footer class="footer">
  <div class="wrap">
    <div class="footer__grid">
      <div>
        <a class="brand" href="<?= e(url("/")) ?>">
          <span class="brand__mark" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($company['name'], 0, 2))) ?></span>
          <span><?= e($company['name']) ?></span>
        </a>
        <p style="margin-top:16px;max-width:38ch;font-size:.875rem;color:rgba(255,255,255,.6)">
          <?= e(str_limit((string) ($company['tagline'] ?: $company['about']), 150)) ?>
        </p>
      </div>
      <div>
        <h2>Catálogo</h2>
        <ul>
          <li><a href="<?= e($base . '/catalogo') ?>">Ver todo</a></li>
          <li><a href="<?= e($base . '/catalogo?orden=cotizados') ?>">Más cotizados</a></li>
          <li><a href="<?= e($base . '/cotizacion') ?>">Mi solicitud</a></li>
        </ul>
      </div>
      <div>
        <h2>Empresa</h2>
        <ul>
          <li><a href="<?= e($base . '/nosotros') ?>">Quiénes somos</a></li>
          <li><a href="<?= e($base . '/contacto') ?>">Contacto</a></li>
          <li><a href="<?= e(url('/entrar')) ?>">Acceso del equipo</a></li>
        </ul>
      </div>
      <div>
        <h2>Contacto</h2>
        <ul>
          <?php if ($company['phone']): ?><li><a href="tel:<?= e(preg_replace('/\D/', '', (string) $company['phone'])) ?>"><?= e($company['phone']) ?></a></li><?php endif; ?>
          <?php if ($company['email']): ?><li><a href="mailto:<?= e($company['email']) ?>"><?= e($company['email']) ?></a></li><?php endif; ?>
          <?php if ($company['address']): ?><li><?= e($company['address']) ?><?= $company['city'] ? ', ' . e($company['city']) : '' ?></li><?php endif; ?>
          <?php if ($company['whatsapp']): ?>
            <li><a href="https://wa.me/<?= e($company['whatsapp']) ?>" rel="noopener" target="_blank">WhatsApp</a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
    <div class="footer__bottom">
      <span>© <?= date('Y') ?> <?= e($company['legal_name'] ?: $company['name']) ?><?= $company['nit'] ? ' · NIT ' . e($company['nit']) : '' ?></span>
      <span>Cotizador en línea por <?= e($appName ?? 'CotizaPro B2B') ?></span>
    </div>
  </div>
</footer>

<a class="cartfab<?= $cartCount > 0 ? ' is-on' : '' ?>" href="<?= e($base . '/cotizacion') ?>">
  <span>Solicitud de cotización</span>
  <span class="cartfab__n" data-cart-count><?= e($cartCount) ?></span>
</a>

<?= \App\Core\View::partial('partials/scripts', get_defined_vars()) ?>
</body>
</html>
