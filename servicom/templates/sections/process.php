<?php
declare(strict_types=1);
if (!Content::blockEnabled('proceso')) { return; }
$steps = Content::steps();
if ($steps === []) { return; }
?>
<section class="section section--alt" aria-labelledby="tit-proceso">
  <div class="wrap">
    <header class="shead" data-reveal>
      <span class="shead__index">03</span>
      <div class="shead__eyebrow"><?= e(Content::b('proceso', 'eyebrow')) ?></div>
      <h2 class="shead__title" id="tit-proceso"><?= e(Content::b('proceso', 'title')) ?></h2>
      <p class="shead__sub"><?= e(Content::b('proceso', 'subtitle')) ?></p>
    </header>

    <div class="process" data-process data-stagger>
      <span class="process__line" aria-hidden="true"></span>
      <?php foreach ($steps as $i => $st): ?>
        <article class="step">
          <div class="step__mark"><?= icon((string) $st['icon'], 23) ?><b><?= (int) ($i + 1) ?></b></div>
          <h3 class="step__title"><?= e($st['title']) ?></h3>
          <p class="step__text"><?= e($st['body']) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
