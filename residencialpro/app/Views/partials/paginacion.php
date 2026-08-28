<?php
/** @var int $pagina @var int $total @var int $porPagina */
$paginas = (int) max(1, ceil($total / max(1, $porPagina)));
if ($paginas <= 1) { return; }
$q = $_GET;
$enlace = static function (int $p) use ($q): string {
    $q['p'] = $p;
    return '?' . http_build_query($q);
};
$desde = max(1, $pagina - 2);
$hasta = min($paginas, $pagina + 2);
?>
<nav class="fila-entre mt-3" aria-label="Paginación">
  <span class="texto-3" style="font-size:.85rem">
    <?= number_format(min($total, ($pagina - 1) * $porPagina + 1)) ?>–<?= number_format(min($total, $pagina * $porPagina)) ?>
    de <?= number_format($total) ?> registros
  </span>
  <div class="btn-grupo">
    <?php if ($pagina > 1): ?>
      <a href="<?= e($enlace($pagina - 1)) ?>" aria-label="Página anterior"><?= ico('chevronI', 15) ?></a>
    <?php endif; ?>
    <?php if ($desde > 1): ?><a href="<?= e($enlace(1)) ?>">1</a><?php if ($desde > 2): ?><span style="padding:8px 4px;color:var(--texto-3)">…</span><?php endif; endif; ?>
    <?php for ($i = $desde; $i <= $hasta; $i++): ?>
      <a href="<?= e($enlace($i)) ?>" class="<?= $i === $pagina ? 'is-activo' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($hasta < $paginas): ?><?php if ($hasta < $paginas - 1): ?><span style="padding:8px 4px;color:var(--texto-3)">…</span><?php endif; ?><a href="<?= e($enlace($paginas)) ?>"><?= $paginas ?></a><?php endif; ?>
    <?php if ($pagina < $paginas): ?>
      <a href="<?= e($enlace($pagina + 1)) ?>" aria-label="Página siguiente"><?= ico('chevronD', 15) ?></a>
    <?php endif; ?>
  </div>
</nav>
