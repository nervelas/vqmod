<?php require_once __DIR__ . '/partials.php'; $u0 = user();
$links = [['#como', 'Cómo funciona'], ['#producto', 'El producto'], ['#sectores', 'Sectores'], ['#planes', 'Planes'], ['#preguntas', 'Preguntas']]; ?>
<!doctype html>
<html lang="es" class="no-js" data-theme="dark">
<head><?= head_html($title ?? S('site_name'), $desc ?? '', !empty($landing)) ?></head>
<body class="<?= !empty($landing) ? 'is-landing' : '' ?>">
<a class="skip" href="#main">Saltar al contenido</a>
<?php if (empty($bare)): ?>
<div class="progress" data-progress></div>
<header class="site-header" data-header>
  <div class="container nav">
    <a href="<?= e(url('')) ?>" aria-label="Inicio"><?= brand_html() ?></a>
    <nav class="nav-links" aria-label="Principal"><?php foreach ($links as [$h, $l]): ?><a href="<?= e(url('')) ?><?= $h ?>"><?= $l ?></a><?php endforeach; ?></nav>
    <div class="nav-cta">
      <button class="icon-btn hide-sm" type="button" data-theme-toggle aria-label="Cambiar entre modo oscuro y claro"><?= icon('moon') ?></button>
      <?php if ($u0): ?><a class="btn btn-gold btn-sm" href="<?= e(url($u0['role'] === 'admin' ? 'admin' : 'app')) ?>">Mi panel</a>
      <?php else: ?><a class="btn btn-quiet btn-sm hide-sm" href="<?= e(url('ingresar')) ?>">Ingresar</a><a class="btn btn-gold btn-sm" href="<?= e(url('registro')) ?>">Prueba gratis</a><?php endif; ?>
      <button class="icon-btn menu-btn" type="button" data-sheet-open="menu" aria-label="Abrir menú"><?= icon('menu') ?></button>
    </div>
  </div>
</header>
<div class="sheet" id="menu" aria-hidden="true">
  <div class="sheet-bg" data-sheet-close></div>
  <div class="sheet-panel" role="dialog" aria-label="Menú">
    <div class="sheet-head"><?= brand_html() ?><button class="icon-btn" type="button" data-sheet-close aria-label="Cerrar"><?= icon('x') ?></button></div>
    <?php foreach ($links as [$h, $l]): ?><a class="lnk" href="<?= e(url('')) ?><?= $h ?>" data-sheet-close><?= $l ?></a><?php endforeach; ?>
    <div class="sep"></div>
    <?php if (!$u0): ?><a class="lnk" href="<?= e(url('ingresar')) ?>"><?= icon('user') ?>Ingresar</a><a class="btn btn-gold btn-block" href="<?= e(url('registro')) ?>">Probar gratis</a><?php endif; ?>
    <button class="lnk" type="button" data-theme-toggle><?= icon('moon') ?>Cambiar tema claro / oscuro</button>
  </div>
</div>
<?php endif; ?>
<main id="main"><?php if (empty($bare) && ($f = flash_html())): ?><div class="container flash-wrap"><?= $f ?></div><?php endif; ?><?= $content ?></main>
<?php if (empty($bare)): ?>
<footer class="site-footer">
  <div class="container foot">
    <div><?= brand_html() ?><p class="muted" style="margin-top:14px;max-width:34ch"><?= e(S('footer_text')) ?></p></div>
    <div><strong>Producto</strong><a href="<?= e(url('')) ?>#como">Cómo funciona</a><a href="<?= e(url('')) ?>#planes">Planes</a><a href="<?= e(url('registro')) ?>">Prueba gratis</a><a href="<?= e(url('ingresar')) ?>">Ingresar</a></div>
    <div><strong>Legal y contacto</strong><a href="<?= e(url('legal/terminos')) ?>">Términos</a><a href="<?= e(url('legal/privacidad')) ?>">Privacidad</a><a href="<?= e(url('legal/aviso')) ?>">Aviso importante</a><?php if (S('contact_email')): ?><a href="mailto:<?= e(S('contact_email')) ?>"><?= e(S('contact_email')) ?></a><?php endif; ?><?php if (S('contact_whatsapp')): ?><a href="https://wa.me/<?= e(wa_phone(S('contact_whatsapp'))) ?>" rel="noopener">WhatsApp</a><?php endif; ?></div>
  </div>
  <div class="container foot-bottom"><small>© <?= date('Y') ?> <?= e(S('site_name')) ?> · Hecho en Guatemala</small><small>Informa y asiste; no garantiza adjudicaciones.</small></div>
</footer>
<?php endif; ?>
<script src="<?= e(asset('js/app.min.js')) ?>" defer></script>
</body></html>
