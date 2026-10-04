<?php /** @var array $reviews */ ?>
<ul class="pub-reviews">
  <?php foreach ($reviews as $r) : ?>
  <li class="rev-card">
    <?php partial('public/_stars', ['rating' => $r['rating']]); ?>
    <?php if (trim((string) $r['comment']) !== '') : ?><blockquote class="rev-quote serif">“<?= e($r['comment']) ?>”</blockquote><?php endif; ?>
    <p class="rev-by"><?= e($r['_name']) ?></p>
  </li>
  <?php endforeach; ?>
</ul>
