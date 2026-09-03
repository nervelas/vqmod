<?php if (!empty($flash)): ?>
  <div class="stack-sm" role="status" aria-live="polite">
  <?php foreach ($flash as $f): ?>
    <div class="alert alert--<?= e($f['type'] === 'ok' ? 'ok' : ($f['type'] === 'error' ? 'error' : 'warn')) ?>">
      <span aria-hidden="true"><?= $f['type'] === 'ok' ? '✓' : ($f['type'] === 'error' ? '!' : '△') ?></span>
      <span><?= e($f['message']) ?></span>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
