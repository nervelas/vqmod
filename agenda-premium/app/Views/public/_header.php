<?php
/** Encabezado público. Variables: $biz, $nav (opcional: 'home'|'team'|''), $cta (bool: mostrar "Reservar") */
$nav = $nav ?? '';
?>
<header class="pub-nav" role="banner">
  <div class="pub-nav-in">
    <a class="pub-brand" href="<?= e(url('/')) ?>" aria-label="<?= e($biz['name']) ?>: ir al inicio">
      <?php if ($biz['logo'] !== '') : ?>
        <img class="pub-brand-logo" src="<?= e($biz['logo']) ?>" alt="" width="36" height="36" decoding="async">
      <?php else : ?>
        <?php partial('public/_mark'); ?>
      <?php endif; ?>
      <span class="pub-brand-name"><?= e($biz['name']) ?></span>
    </a>
    <nav class="pub-links" aria-label="Principal">
      <a href="<?= e(url('/')) ?>#eventos"<?= $nav === 'home' ? ' aria-current="page"' : '' ?>>Servicios</a>
      <a href="<?= e(url('/equipo')) ?>"<?= $nav === 'team' ? ' aria-current="page"' : '' ?>>Equipo</a>
      <a href="<?= e(url('/')) ?>#contacto">Contacto</a>
    </nav>
    <a class="btn btn-gold btn-sm pub-nav-cta" href="<?= e(url('/')) ?>#eventos">Reservar</a>
  </div>
</header>
