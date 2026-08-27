<?php
/** @var int $total @var int $pagina @var int $porPagina */
$totalPaginas = max(1, (int)ceil(($total ?? 0) / max(1, $porPagina ?? 25)));
if ($totalPaginas <= 1) {
    return;
}
$pagina = max(1, min($totalPaginas, (int)($pagina ?? 1)));
$query = $_GET;
$enlace = static function (int $p) use ($query): string {
    $query['p'] = $p;
    return '?' . http_build_query($query);
};
$desde = max(1, $pagina - 2);
$hasta = min($totalPaginas, $pagina + 2);
?>
<nav class="paginacion" aria-label="Paginación">
  <?php if ($pagina > 1): ?>
    <a href="<?= e($enlace($pagina - 1)) ?>" rel="prev">Anterior</a>
  <?php endif; ?>
  <?php if ($desde > 1): ?>
    <a href="<?= e($enlace(1)) ?>">1</a><?php if ($desde > 2): ?><span>…</span><?php endif; ?>
  <?php endif; ?>
  <?php for ($i = $desde; $i <= $hasta; $i++): ?>
    <?php if ($i === $pagina): ?>
      <span class="actual" aria-current="page"><?= $i ?></span>
    <?php else: ?>
      <a href="<?= e($enlace($i)) ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($hasta < $totalPaginas): ?>
    <?php if ($hasta < $totalPaginas - 1): ?><span>…</span><?php endif; ?>
    <a href="<?= e($enlace($totalPaginas)) ?>"><?= $totalPaginas ?></a>
  <?php endif; ?>
  <?php if ($pagina < $totalPaginas): ?>
    <a href="<?= e($enlace($pagina + 1)) ?>" rel="next">Siguiente</a>
  <?php endif; ?>
</nav>
<p class="sm txt-3 cen mt-2"><?= number_format((float)$total) ?> registros en total</p>
