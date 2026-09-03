<?php
declare(strict_types=1);
/** @var string $headTitle @var string $headSub @var array $headCrumbs */
?>
<header class="phead">
  <div class="wrap">
    <nav class="crumbs" aria-label="Ruta de navegación">
      <a href="<?= e(base('')) ?>"><?= icon('inicio', 14) ?> Inicio</a>
      <?php foreach (($headCrumbs ?? []) as $c): ?>
        <span>/</span>
        <?php if (!empty($c['url'])): ?>
          <a href="<?= e(base(ltrim((string) $c['url'], '/'))) ?>"><?= e($c['name']) ?></a>
        <?php else: ?>
          <span style="opacity:1;color:var(--text)"><?= e($c['name']) ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <h1 class="phead__title"><span data-split><?= e($headTitle ?? '') ?></span></h1>
    <?php if (($headSub ?? '') !== ''): ?>
      <p class="phead__sub"><?= e($headSub) ?></p>
    <?php endif; ?>
  </div>
</header>
