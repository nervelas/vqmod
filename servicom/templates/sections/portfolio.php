<?php
declare(strict_types=1);
if (!Content::blockEnabled('portafolio')) { return; }
$projects = Content::projects($limitProjects ?? 6);
if ($projects === []) { return; }
?>
<section class="section" id="seccion-portafolio" aria-labelledby="tit-portafolio">
  <div class="wrap wrap-wide">
    <header class="shead" data-reveal>
      <span class="shead__index">04</span>
      <div class="shead__eyebrow"><?= e(Content::b('portafolio', 'eyebrow')) ?></div>
      <h2 class="shead__title" id="tit-portafolio"><?= e(Content::b('portafolio', 'title')) ?></h2>
      <p class="shead__sub"><?= e(Content::b('portafolio', 'subtitle')) ?></p>
    </header>

    <div class="folio" data-stagger>
      <?php foreach ($projects as $p):
          $href = trim((string) $p['url']);
          $tag  = $href !== '' ? 'a' : 'div'; ?>
        <<?= $tag ?> class="folio__item"<?= $href !== '' ? ' href="' . e($href) . '" target="_blank" rel="noopener" data-cursor="Abrir"' : '' ?>>
          <div class="folio__img">
            <?php if (($img = (string) $p['image']) !== ''): ?>
              <img src="<?= e(asset_url($img)) ?>" alt="<?= e($p['image_alt'] ?: $p['title']) ?>" width="900" height="620" loading="lazy" decoding="async">
            <?php endif; ?>
            <span class="folio__veil"></span>
          </div>
          <div class="folio__body">
            <?php if (($cat = (string) $p['category']) !== ''): ?>
              <span class="folio__cat"><?= icon('chispa', 14) ?><?= e($cat) ?></span>
            <?php endif; ?>
            <h3 class="folio__title"><?= e($p['title']) ?></h3>
            <p class="folio__text"><?= e($p['description']) ?></p>
          </div>
        </<?= $tag ?>>
      <?php endforeach; ?>
    </div>

    <?php if (($bt = Content::b('portafolio', 'btn_text')) !== '' && ($showBtn ?? true)): ?>
      <div class="center" style="margin-top:clamp(2rem,4vw,3rem)" data-reveal>
        <a class="btn btn--outline btn--lg" data-magnetic=".2" href="<?= e(base(ltrim(Content::b('portafolio', 'btn_url', '/portafolio/'), '/'))) ?>">
          <?= icon('portafolio', 19) ?><span><?= e($bt) ?></span></a>
      </div>
    <?php endif; ?>
  </div>
</section>
