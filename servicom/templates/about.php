<?php
declare(strict_types=1);
/** @var array $page */
$headTitle  = (string) $page['title'];
$headSub    = (string) $page['subtitle'];
$headCrumbs = [['name' => $headTitle]];
require __DIR__ . '/layout/pagehead.php';
?>
<section class="section">
  <div class="wrap">
    <div class="about">
      <div class="about__media" data-reveal="left">
        <div class="about__frame">
          <img src="<?= e(asset_url($page['image'] ?: 'assets/img/nosotros.svg')) ?>"
               alt="<?= e(Media::altFor((string) $page['image'], 'Servicom, diseño de páginas web en Guatemala')) ?>"
               width="900" height="620" loading="lazy" decoding="async" data-parallax=".05">
        </div>
        <div class="about__badge"><b>16+</b><span>años diseñando páginas web en Guatemala</span></div>
      </div>
      <div class="prose" data-reveal="right"><?= paragraphs((string) $page['body']) ?></div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/sections/stats.php'; ?>
<?php require __DIR__ . '/sections/process.php'; ?>
<?php require __DIR__ . '/sections/testimonials.php'; ?>
<?php require __DIR__ . '/sections/cta.php'; ?>
