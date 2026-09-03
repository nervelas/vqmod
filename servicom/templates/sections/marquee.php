<?php
declare(strict_types=1);
if (!Content::blockEnabled('marquee')) { return; }
$items = array_values(array_filter(array_map('trim', explode('·', Content::b('marquee', 'body')))));
if ($items === []) { return; }
?>
<div class="marquee" aria-hidden="true">
  <div class="marquee__track">
    <?php for ($g = 0; $g < 2; $g++): ?>
      <div class="marquee__group">
        <?php foreach ($items as $it): ?>
          <span class="marquee__item"><?= icon('chispa', 16) ?><?= e($it) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endfor; ?>
  </div>
</div>
