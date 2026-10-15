<?php require_once __DIR__ . '/partials.php'; $u0 = user(); ?>
<!doctype html>
<html lang="es" class="no-js" data-theme="dark">
<head><?= head_html($title ?? S('site_name'), $desc ?? '', !empty($landing)) ?></head>
<body class="<?= !empty($landing) ? 'is-landing' : '' ?>">
<a class="skip" href="#main">Saltar al contenido</a>
<header class="site-header">
  <div class="container nav">
    <a href="<?= e(url('')) ?>" aria-label="Inicio"><?= brand_html() ?></a>
    <nav class="nav-links" id="nav" aria-label="Principal">
      <a href="<?= e(url('')) ?>#como">Cómo funciona</a><a href="<?= e(url('')) ?>#sectores">Sectores</a><a href="<?= e(url('')) ?>#planes">Planes</a><a href="<?= e(url('')) ?>#preguntas">Preguntas</a><button type="button" class="menu-theme" data-theme-toggle>◐ Cambiar tema claro/oscuro</button>
    </nav>
    <div class="nav-cta">
      <button class="icon-btn" type="button" data-theme-toggle aria-label="Cambiar entre modo oscuro y claro"><span aria-hidden="true">◐</span></button>
      <?php if ($u0): ?><a class="btn btn-ghost btn-sm" href="<?= e(url($u0['role'] === 'admin' ? 'admin' : 'app')) ?>">Mi panel</a>
      <?php else: ?><a class="btn btn-quiet btn-sm hide-sm" href="<?= e(url('ingresar')) ?>">Ingresar</a><a class="btn btn-gold btn-sm" href="<?= e(url('registro')) ?>">Prueba gratis</a><?php endif; ?>
      <button class="icon-btn menu-btn" type="button" data-menu aria-controls="nav" aria-expanded="false" aria-label="Menú"><span aria-hidden="true">☰</span></button>
    </div>
  </div>
</header>
<main id="main"><?php if ($f = flash_html()): ?><div class="container flash-wrap"><?= $f ?></div><?php endif; ?><?= $content ?></main>
<footer class="site-footer">
  <div class="container foot">
    <div><?= brand_html() ?><p class="muted"><?= e(S('footer_text')) ?></p></div>
    <div><strong>Legal</strong><a href="<?= e(url('legal/terminos')) ?>">Términos</a><a href="<?= e(url('legal/privacidad')) ?>">Privacidad</a><a href="<?= e(url('legal/aviso')) ?>">Aviso importante</a></div>
    <div><strong>Contacto</strong><?php if (S('contact_email')): ?><a href="mailto:<?= e(S('contact_email')) ?>"><?= e(S('contact_email')) ?></a><?php endif; ?><?php if (S('contact_whatsapp')): ?><a href="https://wa.me/<?= e(wa_phone(S('contact_whatsapp'))) ?>" rel="noopener">WhatsApp</a><?php endif; ?><?php if (S('contact_phone')): ?><span><?= e(S('contact_phone')) ?></span><?php endif; ?></div>
  </div>
  <div class="container foot-bottom"><small>© <?= date('Y') ?> <?= e(S('site_name')) ?> · Guatemala</small></div>
</footer>
<script src="<?= e(asset('js/app.min.js')) ?>" defer></script>
</body></html>
