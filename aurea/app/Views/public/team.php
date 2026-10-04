<div class="wrap">
  <div class="page-head"><span class="eyebrow"><?= e(__('Equipo')) ?></span><h1><?= e(__('Nuestro equipo de %s', mb_strtolower(term('professionals')))) ?></h1><span class="rule" aria-hidden="true"></span></div>
  <?php if (!$profs): ?><div class="empty"><h3><?= e(__('Aún no hay perfiles publicados')) ?></h3></div><?php endif; ?>
  <div class="team-grid" style="margin-bottom:90px">
    <?php foreach ($profs as $p): ?>
      <a class="person reveal" href="<?= e(url('/profesional/' . $p['slug'])) ?>">
        <div class="ph"><?php if ($p['photo']): ?><img src="<?= e(url('uploads/' . rawurlencode($p['photo']))) ?>" alt="<?= e($p['name']) ?>" loading="lazy" width="400" height="500"><?php else: ?><span class="init" aria-hidden="true"><?= e(name_initial($p['name'])) ?></span><?php endif; ?></div>
        <div class="tx"><h3><?= e($p['name']) ?></h3><span class="t"><?= e($p['title']) ?></span></div>
      </a>
    <?php endforeach; ?>
  </div>
</div>
