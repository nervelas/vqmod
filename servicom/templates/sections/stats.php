<?php
declare(strict_types=1);
if (!Content::blockEnabled('stats')) { return; }
$stats = Content::stats();
if ($stats === []) { return; }
?>
<section class="section section--tight section--alt" aria-labelledby="tit-stats">
  <div class="wrap">
    <header class="shead shead--center" data-reveal>
      <div class="shead__eyebrow"><?= e(Content::b('stats', 'eyebrow', 'En números')) ?></div>
      <h2 class="shead__title" id="tit-stats"><?= e(Content::b('stats', 'title')) ?></h2>
    </header>
    <div class="grid g-4 stats-grid" data-stagger>
      <?php foreach ($stats as $st): ?>
        <div class="stat">
          <div class="stat__icon"><?= icon((string) $st['icon'], 20) ?></div>
          <div class="stat__num">
            <?php if (($pf = (string) $st['prefix']) !== ''): ?><span><?= e($pf) ?></span><?php endif; ?>
            <span data-count="<?= e($st['value']) ?>"><?= e($st['value']) ?></span>
            <?php if (($sf = (string) $st['suffix']) !== ''): ?><sup><?= e($sf) ?></sup><?php endif; ?>
          </div>
          <p class="stat__label"><?= e($st['label']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
