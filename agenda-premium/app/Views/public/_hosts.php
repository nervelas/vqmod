<?php /** @var array $hosts */ ?>
<ul class="pub-hosts">
  <?php foreach ($hosts as $h) : ?>
  <li class="host-card">
    <a href="<?= e(url('/h/' . $h['slug'])) ?>" class="host-link">
      <span class="host-photo">
        <?php if (!empty($h['_photo'])) : ?><img src="<?= e($h['_photo']) ?>" alt="" width="96" height="96" loading="lazy" decoding="async"><?php else : ?><span class="host-initial serif" aria-hidden="true"><?= e($h['_initial']) ?></span><?php endif; ?>
      </span>
      <span class="host-name serif"><?= e($h['name']) ?></span>
      <?php if (!empty($h['title'])) : ?><span class="host-title muted"><?= e($h['title']) ?></span><?php endif; ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>
