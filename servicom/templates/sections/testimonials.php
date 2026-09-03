<?php
declare(strict_types=1);
if (!Content::blockEnabled('testimonios')) { return; }
$items = Content::testimonials(6);
if ($items === []) { return; }
?>
<section class="section section--alt" aria-labelledby="tit-testimonios">
  <div class="wrap">
    <header class="shead shead--center" data-reveal>
      <span class="shead__index">05</span>
      <div class="shead__eyebrow"><?= e(Content::b('testimonios', 'eyebrow')) ?></div>
      <h2 class="shead__title" id="tit-testimonios"><?= e(Content::b('testimonios', 'title')) ?></h2>
    </header>
    <div class="quotes" data-stagger>
      <?php foreach ($items as $t): ?>
        <figure class="card quote">
          <span class="quote__mark"><?= icon('comilla', 34) ?></span>
          <div class="quote__stars" aria-label="<?= (int) $t['rating'] ?> de 5 estrellas">
            <?php for ($i = 0; $i < max(1, min(5, (int) $t['rating'])); $i++) { echo icon('estrella', 16); } ?>
          </div>
          <blockquote class="quote__body"><?= e($t['body']) ?></blockquote>
          <figcaption class="quote__who">
            <?php if (($av = (string) $t['avatar']) !== ''): ?>
              <img class="quote__avatar" src="<?= e(asset_url($av)) ?>" alt="<?= e($t['name']) ?>" width="46" height="46" loading="lazy">
            <?php else: ?>
              <span class="quote__avatar quote__avatar--ph"><?= e(mb_strtoupper(mb_substr((string) $t['name'], 0, 1))) ?></span>
            <?php endif; ?>
            <span>
              <span class="quote__name"><?= e($t['name']) ?></span>
              <?php if (($r = (string) $t['role']) !== ''): ?><span class="quote__role" style="display:block"><?= e($r) ?></span><?php endif; ?>
            </span>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
