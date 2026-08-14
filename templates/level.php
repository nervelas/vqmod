<?php
/** Educational level page (preprimaria, primaria, nivel-medio). */
if (!defined('BASE_PATH')) { exit; }
$map = Content::sectionMap($sections);
$intro = $map['intro'] ?? null;
?>
<section class="page-hero page-hero--level">
  <div class="container">
    <h1><?= e($page['h1'] ?: $page['title']) ?></h1>
    <?php if (!empty($page['intro'])): ?><p class="page-hero__intro"><?= e($page['intro']) ?></p><?php endif; ?>
  </div>
</section>

<?php if ($intro): ?>
<section class="section text-block">
  <div class="container text-block__grid <?= empty($intro['image']) ? 'text-block__grid--single' : '' ?>">
    <div class="text-block__body">
      <h2><?= e($intro['title']) ?></h2>
      <p><?= nl2br(e($intro['body'])) ?></p>
      <a class="btn btn--primary" href="<?= e(base_url('admisiones')) ?>">Solicitar admisión</a>
    </div>
    <?php if (!empty($intro['image'])): ?>
    <div class="text-block__media"><img src="<?= e(asset_url($intro['image'])) ?>" alt="<?= e($intro['title']) ?>" loading="lazy"></div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<section class="section levels-cross">
  <div class="container">
    <div class="section-head"><h2>Otros niveles</h2></div>
    <div class="levels__grid">
      <?php
      foreach (['preprimaria'=>'Preprimaria','primaria'=>'Primaria','nivel-medio'=>'Nivel Medio'] as $slug=>$name):
        if ($slug === $page['slug']) continue;
        $lp = Content::page($slug); if(!$lp) continue; ?>
        <article class="level-card">
          <div class="level-card__body">
            <h3><?= e($lp['title']) ?></h3>
            <p><?= e($lp['intro']) ?></p>
            <a class="btn btn--outline btn--sm" href="<?= e(base_url($slug)) ?>">Ver más</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
