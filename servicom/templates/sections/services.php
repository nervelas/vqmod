<?php
declare(strict_types=1);
if (!Content::blockEnabled('servicios')) { return; }
$services = Content::services(null, true);
if ($services === []) { return; }
?>
<section class="section" id="seccion-servicios" aria-labelledby="tit-servicios">
  <div class="wrap">
    <header class="shead" data-reveal>
      <span class="shead__index">01</span>
      <div class="shead__eyebrow"><?= e(Content::b('servicios', 'eyebrow', 'Lo que hacemos')) ?></div>
      <h2 class="shead__title" id="tit-servicios"><?= e(Content::b('servicios', 'title')) ?></h2>
      <p class="shead__sub"><?= e(Content::b('servicios', 'subtitle')) ?></p>
    </header>

    <?php $cols = count($services) % 3 === 0 ? 3 : (count($services) % 2 === 0 ? 2 : 0); ?>
    <div class="svc-grid<?= $cols ? " svc-grid--$cols" : "" ?>" data-stagger>
      <?php foreach ($services as $i => $s):
          $feats = array_slice(lines((string) $s['features']), 0, 4); ?>
        <article class="card svc">
          <span class="svc__num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <div class="svc__icon"><?= icon((string) $s['icon'], 27) ?></div>
          <h3 class="svc__title"><?= e($s['title']) ?></h3>
          <p class="svc__text"><?= e($s['excerpt']) ?></p>
          <?php if ($feats !== []): ?>
            <ul class="svc__list">
              <?php foreach ($feats as $f): ?>
                <li><?= icon('check', 16) ?><span><?= e($f) ?></span></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <div class="svc__foot">
            <?php if (($p = (string) $s['price_text']) !== ''): ?><span class="svc__price"><?= e($p) ?></span><?php endif; ?>
            <a class="link-arrow" href="<?= e(base('servicios/' . $s['slug'] . '/')) ?>" data-cursor="Ver">
              <span>Ver servicio</span><?= icon('flecha', 17) ?>
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if (($bt = Content::b('servicios', 'btn_text')) !== ''): ?>
      <div class="center" style="margin-top:clamp(2rem,4vw,3rem)" data-reveal>
        <a class="btn btn--outline btn--lg" data-magnetic=".2" href="<?= e(base(ltrim(Content::b('servicios', 'btn_url', '/servicios/'), '/'))) ?>">
          <?= icon('servicios', 19) ?><span><?= e($bt) ?></span>
        </a>
      </div>
    <?php endif; ?>
  </div>
</section>
