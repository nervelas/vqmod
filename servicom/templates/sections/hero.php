<?php
declare(strict_types=1);
$slides = Content::slides();
if ($slides === []) { return; }
$phone  = Settings::get('phone');
$waLink = whatsapp_link(Settings::get('whatsapp', $phone), Settings::get('whatsapp_message'));
$link   = static function (string $url) use ($waLink, $phone): string {
    if ($url === 'whatsapp') { return $waLink; }
    if ($url === 'tel') { return 'tel:+' . digits($phone); }
    return preg_match('#^(https?://|mailto:|tel:)#i', $url) ? $url : base(ltrim($url, '/'));
};
$interval = max(2500, Settings::int('slider_interval', 6500));
?>
<section class="hero" data-slider data-autoplay="<?= Settings::bool('slider_autoplay', true) ? '1' : '0' ?>" data-interval="<?= $interval ?>" aria-label="Presentación principal" aria-roledescription="carrusel">
  <div class="hero__media">
    <?php foreach ($slides as $i => $s): ?>
      <div class="hero__slide<?= $i === 0 ? ' is-active' : '' ?>" role="group" aria-roledescription="diapositiva" aria-label="<?= (int) ($i + 1) ?> de <?= count($slides) ?>" aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>">
        <?php if (($img = (string) $s['image']) !== ''): ?>
          <img class="hero__img" src="<?= e(asset_url($img)) ?>"
               alt="<?= e($s['image_alt'] ?: $s['title']) ?>"
               width="1600" height="940"
               <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy" decoding="async"' ?>>
        <?php endif; ?>
        <div class="hero__scrim"></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="wrap wrap-wide hero__in">
    <div class="hero__stage">
      <?php foreach ($slides as $i => $s): ?>
        <div class="hero__panel<?= $i === 0 ? ' is-active' : '' ?>" aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>">
          <div class="hero__content" data-align="<?= e($s['align'] ?: 'left') ?>">
            <?php if (($eyebrow = (string) $s['eyebrow']) !== ''): ?>
              <span class="eyebrow"><?= e($eyebrow) ?></span>
            <?php endif; ?>

            <?php $Tag = $i === 0 ? 'h1' : 'p'; ?>
            <<?= $Tag ?> class="hero__title">
              <span data-split><?= e($s['title']) ?></span>
              <?php if (($hl = (string) $s['highlight']) !== ''): ?>
                <span class="hl"><span data-split style="--d:180ms"><?= e($hl) ?></span></span>
              <?php endif; ?>
            </<?= $Tag ?>>

            <?php if (($sub = (string) $s['subtitle']) !== ''): ?>
              <p class="hero__text"><?= e($sub) ?></p>
            <?php endif; ?>

            <div class="hero__actions">
              <?php if (($b1 = (string) $s['btn1_text']) !== ''): ?>
                <a class="btn btn--lg" data-magnetic=".25" data-cursor="Ir" href="<?= e($link((string) $s['btn1_url'])) ?>"
                   <?= $s['btn1_url'] === 'whatsapp' ? 'target="_blank" rel="noopener"' : '' ?>>
                  <?= icon((string) ($s['btn1_icon'] ?: 'flecha'), 19) ?><span><?= e($b1) ?></span>
                </a>
              <?php endif; ?>
              <?php if (($b2 = (string) $s['btn2_text']) !== ''): ?>
                <a class="btn btn--ghost btn--lg" data-magnetic=".2" href="<?= e($link((string) $s['btn2_url'])) ?>"
                   <?= $s['btn2_url'] === 'whatsapp' ? 'target="_blank" rel="noopener"' : '' ?>>
                  <?= icon((string) ($s['btn2_icon'] ?: 'servicios'), 19) ?><span><?= e($b2) ?></span>
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="hero__bar">
      <div class="hero__count"><b data-slider-current>01</b> / <?= str_pad((string) count($slides), 2, '0', STR_PAD_LEFT) ?></div>
      <div class="hero__dots" role="tablist" aria-label="Diapositivas">
        <?php foreach ($slides as $i => $s): ?>
          <button class="hero__dot<?= $i === 0 ? ' is-active' : '' ?>" type="button" role="tab"
                  style="--dur:<?= $interval ?>ms" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                  aria-label="Ir a la diapositiva <?= (int) ($i + 1) ?>"><i></i></button>
        <?php endforeach; ?>
      </div>
      <a class="hero__scroll" href="#seccion-servicios"><i></i><span>Descubrir</span></a>
      <div class="hero__nav">
        <button class="btn btn--icon" type="button" data-slider-prev aria-label="Diapositiva anterior" style="transform:scaleX(-1)"><?= icon('flecha', 19) ?></button>
        <button class="btn btn--icon" type="button" data-slider-next aria-label="Diapositiva siguiente"><?= icon('flecha', 19) ?></button>
      </div>
    </div>
  </div>
</section>
