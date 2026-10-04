<?php
/** Paginación. Variables: $page, $pages, $base (ruta), $query (filtros actuales) */
if ($pages <= 1) {
    return;
}
$link = static fn (int $n): string => url($base, array_merge($query, ['pagina' => $n]));
$from = max(1, $page - 2);
$to = min($pages, $page + 2);
?>
<nav class="pagination" aria-label="Paginación">
  <?php if ($page > 1) : ?><a class="btn btn-ghost btn-sm" href="<?= e($link($page - 1)) ?>" rel="prev"><?= icon('chevron-left') ?>Anterior</a><?php endif; ?>
  <?php if ($from > 1) : ?><a class="btn btn-ghost btn-sm" href="<?= e($link(1)) ?>">1</a><?php if ($from > 2) : ?><span class="muted" aria-hidden="true">…</span><?php endif; ?><?php endif; ?>
  <?php for ($i = $from; $i <= $to; $i++) : ?>
    <a class="btn btn-sm <?= $i === $page ? 'btn-gold' : 'btn-ghost' ?>" href="<?= e($link($i)) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($to < $pages) : ?><?php if ($to < $pages - 1) : ?><span class="muted" aria-hidden="true">…</span><?php endif; ?><a class="btn btn-ghost btn-sm" href="<?= e($link($pages)) ?>"><?= $pages ?></a><?php endif; ?>
  <?php if ($page < $pages) : ?><a class="btn btn-ghost btn-sm" href="<?= e($link($page + 1)) ?>" rel="next">Siguiente<?= icon('chevron-right') ?></a><?php endif; ?>
</nav>
