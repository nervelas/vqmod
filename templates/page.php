<?php
/** Generic content page (Nosotros, etc.). */
if (!defined('BASE_PATH')) { exit; }
?>
<section class="page-hero">
  <div class="container">
    <h1><?= e($page['h1'] ?: $page['title']) ?></h1>
    <?php if (!empty($page['intro'])): ?><p class="page-hero__intro"><?= e($page['intro']) ?></p><?php endif; ?>
  </div>
</section>

<?php
$features = [];
foreach ($sections as $s) {
    if ($s['type'] === 'feature') { $features[] = $s; continue; }
    if ($s['type'] === 'text'): ?>
      <section class="section text-block">
        <div class="container text-block__grid <?= empty($s['image']) ? 'text-block__grid--single' : '' ?>">
          <div class="text-block__body">
            <h2><?= e($s['title']) ?></h2>
            <?php if (!empty($s['subtitle'])): ?><p class="eyebrow"><?= e($s['subtitle']) ?></p><?php endif; ?>
            <p><?= nl2br(e($s['body'])) ?></p>
            <?php if (!empty($s['button_text']) && !empty($s['button_url'])): ?>
              <a class="btn btn--primary" href="<?= e(Content::url($s['button_url'])) ?>" <?= $s['button_target']==='_blank'?'target="_blank" rel="noopener"':'' ?>><?= e($s['button_text']) ?></a>
            <?php endif; ?>
          </div>
          <?php if (!empty($s['image'])): ?>
            <div class="text-block__media"><img src="<?= e(asset_url($s['image'])) ?>" alt="<?= e($s['title']) ?>" loading="lazy"></div>
          <?php endif; ?>
        </div>
      </section>
    <?php endif;
}
?>

<?php if ($features): ?>
<section class="section features">
  <div class="container features__grid">
    <?php foreach ($features as $f): ?>
      <article class="feature-card">
        <span class="feature-card__icon"><?= platform_icon($f['icon'] ?: 'star') ?></span>
        <h3><?= e($f['title']) ?></h3>
        <p><?= e($f['body']) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
