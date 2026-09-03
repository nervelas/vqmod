<?php
declare(strict_types=1);
if (!Content::blockEnabled('planes')) { return; }
$plans = Content::plans();
if ($plans === []) { return; }
?>
<section class="section" id="seccion-planes" aria-labelledby="tit-planes">
  <div class="wrap">
    <header class="shead shead--center" data-reveal>
      <span class="shead__index">06</span>
      <div class="shead__eyebrow"><?= e(Content::b('planes', 'eyebrow')) ?></div>
      <h2 class="shead__title" id="tit-planes"><?= e(Content::b('planes', 'title')) ?></h2>
      <p class="shead__sub"><?= e(Content::b('planes', 'subtitle')) ?></p>
    </header>

    <div class="plans" data-stagger>
      <?php foreach ($plans as $p): ?>
        <article class="card plan<?= (int) $p['featured'] === 1 ? ' plan--featured' : '' ?>">
          <?php if ((int) $p['featured'] === 1): ?><span class="plan__tag">Más solicitado</span><?php endif; ?>
          <div class="plan__icon"><?= icon((string) $p['icon'], 23) ?></div>
          <h3 class="plan__name"><?= e($p['name']) ?></h3>
          <p class="plan__tagline"><?= e($p['tagline']) ?></p>
          <div class="plan__price"><?= e($p['price_text']) ?></div>
          <ul class="plan__features">
            <?php foreach (lines((string) $p['features']) as $f): ?>
              <li><?= icon('check', 17) ?><span><?= e($f) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <a class="btn<?= (int) $p['featured'] === 1 ? '' : ' btn--ghost' ?> btn--block" data-magnetic=".16"
             href="<?= e(base(ltrim((string) ($p['btn_url'] ?: '/contacto/'), '/'))) ?>">
            <?= icon('cotizar', 18) ?><span><?= e($p['btn_text'] ?: 'Solicitar cotización') ?></span>
          </a>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if (($bt = Content::b('planes', 'btn_text')) !== ''): ?>
      <p class="center muted" style="margin-top:2rem;font-size:.92rem" data-reveal>
        <?= icon('escudo', 15) ?> <?= e($bt) ?>
      </p>
    <?php endif; ?>
  </div>
</section>
