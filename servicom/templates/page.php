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
    <?php if (($img = (string) $page['image']) !== ''): ?>
      <figure class="article__hero" data-reveal="clip">
        <img src="<?= e(asset_url($img)) ?>" alt="<?= e($page['title']) ?>" width="900" height="620" loading="lazy" decoding="async">
      </figure>
    <?php endif; ?>
    <div class="prose" data-reveal><?= paragraphs((string) $page['body']) ?></div>
  </div>
</section>
