<?php if ($pg['pages'] > 1):
  $qs = $_GET; unset($qs['p']);
  $link = static fn(int $p) => '?' . http_build_query($qs + ['p' => $p]); ?>
<nav class="pager" aria-label="<?= e(__('Paginación')) ?>">
  <?php if ($pg['page'] > 1): ?><a href="<?= e($link($pg['page'] - 1)) ?>">‹</a><?php endif; ?>
  <?php for ($i = max(1, $pg['page'] - 3); $i <= min($pg['pages'], $pg['page'] + 3); $i++): ?><?= $i === $pg['page'] ? '<span class="cur" aria-current="page">' . $i . '</span>' : '<a href="' . e($link($i)) . '">' . $i . '</a>' ?><?php endfor; ?>
  <?php if ($pg['page'] < $pg['pages']): ?><a href="<?= e($link($pg['page'] + 1)) ?>">›</a><?php endif; ?>
</nav>
<?php endif; ?>
