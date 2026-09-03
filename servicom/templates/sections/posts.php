<?php
declare(strict_types=1);
if (!Content::blockEnabled('blog')) { return; }
$posts = Content::posts(3);
if ($posts === []) { return; }
?>
<section class="section" id="seccion-blog" aria-labelledby="tit-blog">
  <div class="wrap">
    <header class="shead" data-reveal>
      <span class="shead__index">08</span>
      <div class="shead__eyebrow"><?= e(Content::b('blog', 'eyebrow')) ?></div>
      <h2 class="shead__title" id="tit-blog"><?= e(Content::b('blog', 'title')) ?></h2>
      <p class="shead__sub"><?= e(Content::b('blog', 'subtitle')) ?></p>
    </header>

    <div class="posts" data-stagger>
      <?php foreach ($posts as $p): ?>
        <article class="card post">
          <a class="post__img" href="<?= e(base('actualidad-web/' . $p['slug'] . '/')) ?>" data-cursor="Leer" aria-label="<?= e($p['title']) ?>">
            <?php if (($img = (string) $p['image']) !== ''): ?>
              <img src="<?= e(asset_url($img)) ?>" alt="<?= e($p['image_alt'] ?: $p['title']) ?>" width="900" height="620" loading="lazy" decoding="async">
            <?php endif; ?>
          </a>
          <div class="post__body">
            <span class="post__date"><?= e(fecha_larga((string) $p['published_at'])) ?></span>
            <h3 class="post__title"><a href="<?= e(base('actualidad-web/' . $p['slug'] . '/')) ?>"><?= e($p['title']) ?></a></h3>
            <p class="post__excerpt"><?= e(excerpt((string) ($p['excerpt'] ?: $p['body']), 150)) ?></p>
            <a class="link-arrow" href="<?= e(base('actualidad-web/' . $p['slug'] . '/')) ?>"><span>Leer artículo</span><?= icon('flecha', 17) ?></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if (($bt = Content::b('blog', 'btn_text')) !== ''): ?>
      <div class="center" style="margin-top:clamp(2rem,4vw,3rem)" data-reveal>
        <a class="btn btn--outline btn--lg" data-magnetic=".2" href="<?= e(base('actualidad-web/')) ?>">
          <?= icon('blog', 19) ?><span><?= e($bt) ?></span></a>
      </div>
    <?php endif; ?>
  </div>
</section>
